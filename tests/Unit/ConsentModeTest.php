<?php

declare(strict_types=1);

namespace Consentimental\Tests;

use Consentimental\Consent\Settings;
use Consentimental\Integrations\ConsentMode;
use PHPUnit\Framework\TestCase;

final class ConsentModeTest extends TestCase
{
    private Settings $settings;
    private ConsentMode $consent_mode;

    protected function setUp(): void
    {
        $GLOBALS['cmp_test_options'] = [];
        $GLOBALS['cmp_test_hooks'] = [];

        $this->settings = new Settings();
        $this->settings->seed_defaults();

        $this->consent_mode = new ConsentMode($this->settings);
    }

    public function test_defaults_to_basic(): void
    {
        self::assertSame('basic', $this->consent_mode->type());
        self::assertTrue($this->consent_mode->is_strict_gtm_blocking());
    }

    public function test_advanced_setting_disables_strict_gtm_blocking(): void
    {
        $this->settings->save(['consent_mode_type' => 'advanced']);

        self::assertSame('advanced', $this->consent_mode->type());
        self::assertFalse($this->consent_mode->is_strict_gtm_blocking());
    }

    public function test_invalid_stored_value_falls_back_to_basic(): void
    {
        $this->settings->save(['consent_mode_type' => 'not-a-real-mode']);

        self::assertSame('basic', $this->consent_mode->type());
    }

    public function test_category_map_covers_functional_analytics_and_marketing(): void
    {
        $map = $this->consent_mode->category_map();

        self::assertSame(['functionality_storage', 'personalization_storage'], $map['functional']);
        self::assertSame(['analytics_storage'], $map['analytics']);
        self::assertSame(['ad_storage', 'ad_user_data', 'ad_personalization'], $map['marketing']);
    }

    public function test_category_map_is_filterable(): void
    {
        add_filter('cmp_consent_mode_category_map', static fn (array $map): array => ['analytics' => ['custom_storage']]);

        self::assertSame(['analytics' => ['custom_storage']], $this->consent_mode->category_map());
    }

    public function test_can_be_disabled_via_filter(): void
    {
        self::assertTrue($this->consent_mode->is_enabled());

        add_filter('cmp_consent_mode_enabled', static fn (): bool => false);

        self::assertFalse($this->consent_mode->is_enabled());
    }
}
