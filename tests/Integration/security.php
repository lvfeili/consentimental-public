<?php
declare(strict_types=1);
require getenv('CMP_WP_LOAD') ?: '/var/www/html/wp-load.php';

use Consentimental\Api\ConsentController;
use Consentimental\Consent\CategoryRepository;
use Consentimental\Consent\ConsentLogRepository;
use Consentimental\Consent\ConsentManager;
use Consentimental\Consent\Settings;
use Consentimental\Scripts\CustomScript;
use Consentimental\Scripts\CustomScriptRepository;
use Consentimental\Scripts\ScriptProxy;
use Consentimental\Services\CustomServiceRepository;
use Consentimental\Services\ServiceRegistry;

if (wp_using_ext_object_cache()) {
    throw new RuntimeException('Run on an isolated development site without a persistent object cache.');
}
$log = new ConsentLogRepository();
foreach ([$wpdb->options, $log->table_name()] as $table) {
    $status = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $table));
    if (!$status || 'InnoDB' !== $status->Engine) {
        throw new RuntimeException('Rollback tests require existing InnoDB tables.');
    }
}
$checks = 0;
function check(bool $condition, string $message): void {
    global $checks;
    if (!$condition) { throw new RuntimeException($message); }
    ++$checks;
}
$wpdb->query('START TRANSACTION');
try {
    $settings = new Settings();
    $categories = new CategoryRepository();
    $services = new ServiceRegistry(new CustomServiceRepository());
    $manager = new ConsentManager($categories, $services, $settings);
    $controller = new ConsentController($categories, $services, $settings, $manager);
    $id = wp_generate_uuid4();
    $_COOKIE[ConsentManager::COOKIE_NAME] = wp_json_encode([
        'consent_id' => $id, 'expiry' => time() + 3600, 'config_version' => $settings->config_version(),
        'categories' => ['analytics' => true],
    ]);
    $request = new WP_REST_Request('POST');
    $request->set_body_params(['previous_state' => null, 'categories' => ['analytics' => true], 'consent_id' => $id]);
    $resolve = new ReflectionMethod($controller, 'resolve_previous');
    $resolve->setAccessible(true);
    check(null === $resolve->invoke($controller, $request), 'Explicit null must not fall back to the new cookie');
    check(null !== $resolve->invoke($controller, new WP_REST_Request('POST')), 'Legacy omitted previous state still reads cookie');
    remove_all_actions('cmp_consent_changed');
    $previous = 'unset';
    add_action('cmp_consent_changed', function ($current, $old) use (&$previous): void { $previous = $old; }, 10, 2);
    $response = $controller->handle_update($request);
    check($response instanceof WP_REST_Response && null === $previous, 'First decision must fire hooks with null previous state');
    check(!isset($response->get_headers()['Set-Cookie']), 'Server must not overwrite the client cookie');

    $log->record($id, 'updated', ['analytics' => true], [], 1, null, null, null);
    $log->record($id, 'updated', ['analytics' => true], [], 1, null, null, null);
    check(1 === $log->query($id)['total'], 'Immediate retries should be deduplicated');
    $wpdb->update($log->table_name(), ['recorded_at' => gmdate('Y-m-d H:i:s', time() - 60)], ['consent_id' => $id]);
    $log->record($id, 'updated', ['analytics' => true], [], 1, null, null, null);
    check(2 === $log->query($id)['total'], 'Later identical decision must retain its timestamp');

    remove_all_actions('cmp_settings_changed');
    $invalidations = 0;
    add_action('cmp_settings_changed', function () use (&$invalidations): void { ++$invalidations; });
    $settings->save(['consent_mode_type' => 'basic' === $settings->get('consent_mode_type') ? 'advanced' : 'basic']);
    $settings->bump_config_version();
    check(2 === $invalidations, 'Both settings changes and version bumps must invalidate caches');

    $script_id = 'audit-' . strtolower(wp_generate_password(8, false));
    $repo = new CustomScriptRepository('cmp_integration_scripts');
    $repo->save(CustomScript::from_array([
        'id' => $script_id, 'type' => 'url', 'category' => 'analytics', 'proxy' => true,
        'url' => 'https://www.googletagmanager.com/gtag/js?id=G-TEST',
        'proxy_paths' => 'POST /g/collect',
    ]));
    $proxy = new ScriptProxy($repo);
    $calls = [];
    $body = 'window.testLibrary = true;';
    $status = 200;
    add_filter('pre_http_request', function ($preempt, $args, $url) use (&$calls, &$body, &$status) {
        $calls[] = ['url' => $url, 'args' => $args];
        return ['response' => ['code' => $status, 'message' => 'Test'], 'headers' => ['content-type' => 'application/javascript'], 'body' => 'HEAD' === $args['method'] ? '' : $body];
    }, 10, 3);
    $request_for = function (string $method, string $path = 'gtag/js', array $query = ['id' => 'G-TEST']) use ($script_id): WP_REST_Request {
        $r = new WP_REST_Request($method);
        $r->set_url_params(['id' => $script_id, 'path' => $path]);
        $r->set_query_params($query);
        return $r;
    };
    $proxy->handle($request_for('HEAD'));
    $response = $proxy->handle($request_for('GET'));
    check($response instanceof WP_REST_Response && $response->get_data()['cmp_proxy_body'] === $body, 'HEAD must not poison GET');
    check(2 === count($calls) && str_contains($calls[1]['url'], 'id=G-TEST'), 'Preserve the configured vendor query');
    check(true === $calls[1]['args']['reject_unsafe_urls'], 'Use safe HTTP destination validation');
    check(0 === $calls[1]['args']['redirection'] && 2 * MB_IN_BYTES + 1 === $calls[1]['args']['limit_response_size'], 'Bound download size and disallow redirect escape');
    check('nosniff' === $response->get_headers()['X-Content-Type-Options'], 'Disable content sniffing');
    $proxy->handle($request_for('GET'));
    check(2 === count($calls), 'Static GET uses cache');
    $response = $proxy->handle($request_for('GET', 'gtag/js', ['id' => 'G-TEST', 'random' => '1']));
    check(is_wp_error($response) && 'cmp_proxy_bad_query' === $response->get_error_code(), 'Reject unbounded static cache variants');
    $response = $proxy->handle($request_for('POST', 'admin', []));
    check(is_wp_error($response) && 'cmp_proxy_bad_path' === $response->get_error_code(), 'Reject arbitrary upstream endpoints');
    $body = str_repeat('x', 2 * MB_IN_BYTES + 1);
    $response = $proxy->handle($request_for('POST', 'g/collect', []));
    check(is_wp_error($response) && 'cmp_proxy_response_too_large' === $response->get_error_code(), 'Reject oversized upstream responses');
    $body = ''; $status = 302;
    $response = $proxy->handle($request_for('POST', 'g/collect', []));
    check(is_wp_error($response) && 'cmp_proxy_redirect' === $response->get_error_code(), 'Reject redirects');
    $request = $request_for('POST', 'g/collect', []);
    $request->set_body(str_repeat('x', 64 * 1024 + 1));
    $response = $proxy->handle($request);
    check(is_wp_error($response) && 'cmp_proxy_too_large' === $response->get_error_code(), 'Bound incoming payloads');
    set_transient('cmp_proxy_rate_' . md5($script_id), ['window' => (int) floor(time() / 60), 'count' => 120], 60);
    $response = $proxy->handle($request_for('POST', 'g/collect', []));
    check(is_wp_error($response) && 'cmp_proxy_rate_limited' === $response->get_error_code(), 'Bound upstream request rate');
    $health = new \Consentimental\Diagnostics\ProxyHealth($repo, $proxy);
    $health->register();
    $script = $repo->get($script_id);
    $mail = [];
    $mail_ok = true;
    add_filter('pre_wp_mail', function ($preempt, $attributes) use (&$mail, &$mail_ok) {
        $mail[] = $attributes;
        return $mail_ok; // Never contact a mail transport in these tests.
    }, 10, 2);
    update_option('admin_email', 'integration@example.test');
    update_option(\Consentimental\Diagnostics\ProxyHealth::EMAIL_OPTION, false);
    delete_option('cmp_proxy_health_last_email');
    delete_transient('cmp_proxy_rate_' . md5($script_id));
    check([] === $health->state($script), 'Unknown endpoints must not be reported as healthy');
    $status = 503; $body = 'unavailable';
    for ($i = 0; $i < 3; ++$i) { $proxy->handle($request_for('GET'), true); }
    $key = 'GET /gtag/js';
    check('failing' === $health->state($script)['endpoints'][$key]['status'], 'Repeated transport failures appear in diagnostics');
    update_option(\Consentimental\Diagnostics\ProxyHealth::EMAIL_OPTION, true);
    $health->notify();
    check([] === $mail, 'Initial setup failures must not send regression mail');
    $status = 200; $body = 'window.healthy=true;';
    $proxy->handle($request_for('GET'), true);
    check('healthy' === $health->state($script)['endpoints'][$key]['status'], 'Successful fresh forwarding establishes baseline');
    $status = 503;
    for ($i = 0; $i < 3; ++$i) { $proxy->handle($request_for('GET'), true); }
    $proxy->handle($request_for('GET')); // Cached 200 must not clear fresh failures.
    check('failing' === $health->state($script)['endpoints'][$key]['status'], 'Cached success cannot mask an upstream incident');
    $admins = get_users(['role' => 'administrator', 'number' => 1]);
    check(!empty($admins), 'Admin rendering tests need an administrator');
    $old_user = get_current_user_id();
    wp_set_current_user($admins[0]->ID);
    try {
        $health_page = new \Consentimental\Admin\ProxyHealthPage($health);
        $notice = new ReflectionMethod($health_page, 'notice'); $notice->setAccessible(true);
        ob_start(); $notice->invoke($health_page); $notice_html = ob_get_clean();
        check(str_contains($notice_html, 'notice-error') && str_contains($notice_html, 'consentimental-proxy-health'), 'Confirmed failures show an actionable admin error notice');
        $render = new ReflectionMethod($health_page, 'render'); $render->setAccessible(true);
        ob_start(); $render->invoke($health_page); $page_html = ob_get_clean();
        check(str_contains($page_html, 'HTTP 503') && str_contains($page_html, 'GET /gtag/js'), 'Diagnostics render the failing endpoint and HTTP result');
    } finally { wp_set_current_user($old_user); }

    update_option(\Consentimental\Diagnostics\ProxyHealth::EMAIL_OPTION, false);
    $health->notify();
    check([] === $mail, 'Mail stays disabled without opt-in');
    update_option(\Consentimental\Diagnostics\ProxyHealth::EMAIL_OPTION, true);
    $health->notify(); $health->notify();
    check(1 === count($mail) && 'integration@example.test' === $mail[0]['to'], 'Send one incident digest to the administration address');
    check(!str_contains($mail[0]['message'], 'G-TEST'), 'No request query data belongs in mail');
    $status = 200; $proxy->handle($request_for('GET'), true);
    $status = 503;
    for ($i = 0; $i < 3; ++$i) { $proxy->handle($request_for('GET'), true); }
    $health->notify();
    check(1 === count($mail), 'A flapping endpoint respects the daily cooldown');
    update_option('cmp_proxy_health_last_email', time() - DAY_IN_SECONDS - 1);
    $mail_ok = false;
    $health->notify();
    check('failed' === get_option('cmp_proxy_health_mail_status'), 'Expose mail transport failures');
    check(empty($health->state($script)['endpoints'][$key]['alerted']), 'Failed mail leaves the incident retryable');
    $before = $health->state($script);
    $proxy->handle($request_for('POST', 'not-allowed', []));
    check($before === $health->state($script), 'Rejected arbitrary paths cannot poison diagnostics');
    $status = 200;
    $proxy->handle($request_for('POST', 'g/collect', []));
    $collection_key = 'POST /g/collect';
    check('healthy' === $health->state($script)['endpoints'][$collection_key]['status'], 'Real collection transport can establish a baseline');
    $status = 400;
    $proxy->handle($request_for('POST', 'g/collect', []));
    check('healthy' === $health->state($script)['endpoints'][$collection_key]['status'], 'Malformed anonymous events do not manufacture incidents');
    $calls = []; $status = 200;
    $health->run();
    check(1 === count($calls) && 'GET' === $calls[0]['args']['method'], 'Scheduled checks fetch only script assets, never synthetic events');
    $script->proxy_paths = 'POST /different'; $repo->save($script);
    check([] === $health->state($script), 'Changed configuration resets the health baseline');
    $repo->delete($script_id); $health->run();
    check(!isset(get_option(\Consentimental\Diagnostics\ProxyHealth::OPTION, [])[$script_id]), 'Deleted scripts are pruned from health history');
    echo "Passed {$checks} WordPress integration checks.\n";
} finally {
    $wpdb->query('ROLLBACK');
}
