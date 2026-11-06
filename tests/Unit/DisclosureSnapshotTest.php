<?php

declare(strict_types=1);

namespace Consentimental\Tests;

use Consentimental\Consent\DisclosureSnapshot;
use PHPUnit\Framework\TestCase;

final class DisclosureSnapshotTest extends TestCase
{
    private function content(array $overrides = []): array
    {
        return array_replace_recursive([
            'title' => 'We value your privacy',
            'text' => 'We use cookies.',
            'customize_title' => 'Privacy settings',
            'customize_text' => 'Choose.',
            'labels' => ['accept_all' => 'Accept all', 'reject_all' => 'Reject all', 'save' => 'Save settings'],
            'policy_links' => [['label' => 'Privacy policy', 'url' => 'https://site.test/privacy/']],
            'categories' => [
                ['slug' => 'necessary', 'label' => 'Necessary', 'description' => 'Required.', 'required' => true, 'order' => 0],
                ['slug' => 'analytics', 'label' => 'Analytics', 'description' => 'Stats.', 'required' => false, 'order' => 1],
            ],
            'services' => [
                ['id' => 'matomo', 'name' => 'Matomo', 'provider' => 'InnoCraft', 'purpose' => 'Statistics', 'category' => 'analytics', 'privacy_policy_url' => 'https://matomo.test/privacy', 'cookies' => [['name' => '_pk_id', 'duration' => '13 months', 'purpose' => 'Visitor id']], 'domains' => ['matomo.test']],
                ['id' => 'alpha', 'name' => 'Alpha', 'category' => 'analytics'],
            ],
        ], $overrides);
    }

    private function build(array $content = [], int $version = 3, string $locale = 'de_DE', string $code = 'de'): array
    {
        return DisclosureSnapshot::build(
            [] === $content ? $this->content() : $content,
            $version,
            ['locale' => $locale, 'code' => $code, 'direction' => 'ltr'],
            ['consent_mode_type' => 'basic', 'consent_expiry_days' => 180],
        );
    }

    public function test_hash_is_a_stable_sha256(): void
    {
        $hash = DisclosureSnapshot::hash($this->build());

        self::assertTrue(DisclosureSnapshot::is_hash($hash));
        self::assertSame($hash, DisclosureSnapshot::hash($this->build()));
    }

    public function test_service_registration_order_does_not_create_a_new_disclosure(): void
    {
        $content = $this->content();
        $content['services'] = array_reverse($content['services']);

        self::assertSame(DisclosureSnapshot::hash($this->build()), DisclosureSnapshot::hash($this->build($content)));
    }

    public function test_detection_only_fields_are_not_part_of_the_disclosure(): void
    {
        $content = $this->content();
        $content['services'][0]['domains'] = ['other.test'];

        self::assertSame(DisclosureSnapshot::hash($this->build()), DisclosureSnapshot::hash($this->build($content)));
        self::assertArrayNotHasKey('domains', $this->build()['services'][0]);
    }

    /**
     * @return array<string, array{0: callable(array): array}>
     */
    public static function visitor_facing_changes(): array
    {
        return [
            'banner wording' => [static fn (array $c): array => array_replace($c, ['text' => 'We use cookies and similar.'])],
            'button label (e.g. new translation)' => [static function (array $c): array { $c['labels']['accept_all'] = 'Alle akzeptieren'; return $c; }],
            'service purpose' => [static function (array $c): array { $c['services'][0]['purpose'] = 'Marketing'; return $c; }],
            'cookie duration' => [static function (array $c): array { $c['services'][0]['cookies'][0]['duration'] = '2 years'; return $c; }],
            'policy link' => [static function (array $c): array { $c['policy_links'] = []; return $c; }],
            'category order' => [static function (array $c): array { $c['categories'] = array_reverse($c['categories']); return $c; }],
            'service removed' => [static function (array $c): array { array_pop($c['services']); return $c; }],
        ];
    }

    /**
     * @dataProvider visitor_facing_changes
     */
    public function test_any_visitor_facing_change_produces_a_new_snapshot(callable $change): void
    {
        self::assertNotSame(
            DisclosureSnapshot::hash($this->build()),
            DisclosureSnapshot::hash($this->build($change($this->content()))),
        );
    }

    public function test_language_and_version_are_part_of_the_identity(): void
    {
        $base = DisclosureSnapshot::hash($this->build());

        self::assertNotSame($base, DisclosureSnapshot::hash($this->build([], 4)));
        self::assertNotSame($base, DisclosureSnapshot::hash($this->build([], 3, 'fr_FR', 'fr')));
    }

    public function test_canonical_json_sorts_keys_but_keeps_list_order(): void
    {
        $json = DisclosureSnapshot::canonical_json(['b' => 1, 'a' => ['z' => 1, 'y' => [3, 1, 2]]]);

        self::assertSame('{"a":{"y":[3,1,2],"z":1},"b":1}', $json);
    }

    public function test_records_language_and_terms(): void
    {
        $snapshot = $this->build();

        self::assertSame(['locale' => 'de_DE', 'code' => 'de', 'direction' => 'ltr'], $snapshot['language']);
        self::assertSame(['consent_expiry_days' => 180, 'consent_mode_type' => 'basic'], $snapshot['terms']);
        self::assertSame(3, $snapshot['config_version']);
    }

    public function test_rejects_non_hash_identifiers(): void
    {
        self::assertFalse(DisclosureSnapshot::is_hash(str_repeat('g', 64)));
        self::assertFalse(DisclosureSnapshot::is_hash(str_repeat('a', 63)));
        self::assertFalse(DisclosureSnapshot::is_hash(str_repeat('A', 64)));
    }
}
