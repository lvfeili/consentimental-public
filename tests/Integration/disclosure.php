<?php
declare(strict_types=1);
require __DIR__ . '/harness.php';

use Consentimental\Api\ConsentController;
use Consentimental\Consent\CategoryRepository;
use Consentimental\Consent\ConsentLogRepository;
use Consentimental\Consent\ConsentManager;
use Consentimental\Consent\ConsentRecorder;
use Consentimental\Consent\DisclosureRecorder;
use Consentimental\Consent\DisclosureSnapshot;
use Consentimental\Consent\DisclosureSnapshotRepository;
use Consentimental\Consent\Settings;
use Consentimental\Frontend\BannerContent;
use Consentimental\Integrations\MultilingualStrings;
use Consentimental\Services\CustomServiceRepository;
use Consentimental\Services\ServiceRegistry;

$log = new ConsentLogRepository();
$snapshots = new DisclosureSnapshotRepository();
cmp_require_innodb([$wpdb->options, $log->table_name(), $snapshots->table_name()]);

$wpdb->query('START TRANSACTION');
try {
    $settings = new Settings();
    $categories = new CategoryRepository();
    $services = new ServiceRegistry(new CustomServiceRepository());
    $services->register(['id' => 'it-analytics', 'name' => 'IT Analytics', 'category' => 'analytics', 'purpose' => 'Counting', 'active' => true]);
    $multilingual = new MultilingualStrings();
    $content = static fn (): BannerContent => new BannerContent($categories, $settings, $services, $multilingual);
    $recorder = new DisclosureRecorder($snapshots, $settings, $multilingual, $content);

    $version = $settings->bump_config_version();
    check(abs(time() - (int) $settings->published_at($version)) < 5, 'Publishing records the publication time');
    check(null === $settings->published_at(-1), 'An unknown version has no invented publication time');

    $settings->save(['banner_text' => 'Integration wording A']);
    $hash_a = $recorder->capture_site_default(DisclosureSnapshotRepository::SOURCE_PUBLISH);
    $row_a = $snapshots->get($hash_a);
    check(null !== $row_a && $row_a['intact'], 'Publish stores an intact snapshot');
    check($version === $row_a['config_version'], 'Snapshot carries the configuration version');
    check(null !== $row_a['config_published_at'], 'Snapshot carries the publication time when known');
    check('Integration wording A' === $row_a['content']['banner']['text'], 'Snapshot stores the banner wording');
    check('Accept all' === $row_a['content']['banner']['labels']['accept_all'] || '' !== $row_a['content']['banner']['labels']['accept_all'], 'Snapshot stores choice labels');
    check(in_array('it-analytics', array_column($row_a['content']['services'], 'id'), true), 'Snapshot lists active services with their purpose');
    check('Counting' === array_column($row_a['content']['services'], 'purpose', 'id')['it-analytics'], 'Snapshot stores service purposes');
    check(get_locale() === $row_a['content']['language']['locale'], 'Admin-side capture records the site locale');

    $count = static fn (): int => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$snapshots->table_name()}");
    $before = $count();
    check($hash_a === $recorder->capture_site_default(DisclosureSnapshotRepository::SOURCE_SETTINGS), 'Identical disclosure reuses the snapshot');
    delete_option('cmp_disclosure_known');
    check($hash_a === $recorder->capture_site_default(DisclosureSnapshotRepository::SOURCE_SETTINGS), 'Deduplication does not depend on the in-memory cache');
    check($before === $count(), 'No duplicate rows for identical content');

    $settings->save(['banner_text' => 'Integration wording B']);
    $hash_b = $recorder->capture_site_default(DisclosureSnapshotRepository::SOURCE_SETTINGS);
    check($hash_a !== $hash_b, 'Changed wording creates a new snapshot');
    check('Integration wording A' === $snapshots->get($hash_a)['content']['banner']['text'], 'Older snapshot keeps its original wording');

    $wpdb->update($snapshots->table_name(), ['content' => str_replace('wording A', 'wording X', DisclosureSnapshot::canonical_json($row_a['content']))], ['snapshot_hash' => $hash_a]);
    check(false === $snapshots->get($hash_a)['intact'], 'Modified snapshot content is flagged');

    $render = $recorder->for_render($content()->resolve());
    check(DisclosureSnapshot::is_hash($render['id']) && null !== $snapshots->get($render['id']), 'Render-time disclosure is stored');
    check($hash_b === $render['id'], 'Identical rendered disclosure reuses the published snapshot');
    $settings->save(['banner_title' => 'Rendered-only title']);
    $fresh = new BannerContent($categories, $settings, $services, $multilingual);
    $render = $recorder->for_render($fresh->resolve());
    check('render' === $snapshots->get($render['id'])['source'], 'A disclosure first seen at render is labelled as shown to visitors');

    $manager = new ConsentManager($categories, $services, $settings);
    $controller = new ConsentController($categories, $services, $settings, $manager, $snapshots);
    remove_all_actions('cmp_consent_changed');
    $settings->save(['consent_log_enabled' => true]);
    (new ConsentRecorder($log, $settings))->register();
    delete_transient('cmp_rl_' . md5((string) ($_SERVER['REMOTE_ADDR'] ?? '')));

    $decide = static function (?string $disclosure, int $config_version) use ($controller, $log): array {
        $id = wp_generate_uuid4();
        $request = new WP_REST_Request('POST');
        $request->set_body_params(['consent_id' => $id, 'categories' => ['analytics' => true], 'previous_state' => null,
            'config_version' => $config_version, 'disclosure_id' => $disclosure]);
        $controller->handle_update($request);
        return $log->query($id)['items'][0] ?? [];
    };

    $row = $decide($render['id'], $version);
    check($render['id'] === $row['disclosure_hash'], 'A consent record links the snapshot the page showed');
    $expected_language = '' !== $render['language']['code'] ? $render['language']['code'] : $render['language']['locale'];
    check($expected_language === $row['language'], 'A consent record stores the language shown');

    $row = $decide(str_repeat('a', 64), $version);
    check(null === $row['disclosure_hash'] && null === $row['language'], 'An unknown snapshot id is recorded as unknown, not guessed');

    $row = $decide('<script>', $version);
    check(null === $row['disclosure_hash'], 'Malformed ids are rejected');

    $old = $settings->bump_config_version();
    $row = $decide($render['id'], $old);
    check(null === $row['disclosure_hash'], 'A snapshot from another configuration version is not linked');

    $row = $decide(null, $old);
    check(null === $row['disclosure_hash'], 'Older clients without a disclosure id still record consent');
    check(!empty($row['consent_id']), 'Recording still works without a disclosure id');

    $controller->register();
    do_action('rest_api_init', rest_get_server());
    $rest = new WP_REST_Request('POST', '/consentimental/v1/consent');
    $rest->set_header('Content-Type', 'application/json');
    $first_id = wp_generate_uuid4();
    $rest->set_body((string) wp_json_encode(['consent_id' => $first_id, 'categories' => ['analytics' => true], 'services' => new stdClass(),
        'config_version' => $old, 'previous_state' => null, 'disclosure_id' => null]));
    $response = rest_do_request($rest);
    check(200 === $response->get_status(), 'A first decision (previous_state: null) is accepted by the REST schema, got ' . $response->get_status());
    check('granted' === ($log->query($first_id)['items'][0]['action'] ?? ''), 'A first decision is logged as granted');

    $wpdb->query("UPDATE {$snapshots->table_name()} SET captured_at = '2000-01-01 00:00:00'");
    $snapshots->purge_unreferenced(30, $log->table_name());
    check(null !== $snapshots->get($render['id']), 'A snapshot referenced by a consent record is retained');
    $remaining = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$snapshots->table_name()} WHERE snapshot_hash IN ('{$hash_a}','{$hash_b}')");
    check($remaining <= 1, 'Old unreferenced snapshots are purged except the newest per language');

    $exported = iterator_to_array($log->each(), false);
    check(count($exported) >= 5 && array_key_exists('disclosure_hash', $exported[0]), 'Export iterates all records with disclosure columns');

    echo "Passed {$checks} disclosure integration checks.\n";
} finally {
    $wpdb->query('ROLLBACK');
}
