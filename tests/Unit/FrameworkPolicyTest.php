<?php

declare(strict_types=1);

namespace Consentimental\Tests;

use Consentimental\Consent\CategoryRepository;
use Consentimental\Consent\ConsentManager;
use Consentimental\Consent\ConsentState;
use Consentimental\Consent\Settings;
use Consentimental\Services\CustomServiceRepository;
use Consentimental\Services\ServiceRegistry;
use PHPUnit\Framework\TestCase;

final class FrameworkPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['cmp_test_options'] = [];
        $GLOBALS['cmp_test_hooks'] = [];
        $_COOKIE = [];
    }

    public function test_profile_enforces_runtime_rules_and_renews_consent(): void
    {
        $settings = new Settings();
        $settings->save(['consent_mode_type' => 'advanced', 'floating_button_enabled' => false]);
        $version = $settings->config_version();
        $settings->save(['legal_framework' => 'eu_germany']);
        self::assertSame($version + 1, $settings->config_version());
        self::assertSame('basic', $settings->get('consent_mode_type'));
        self::assertTrue($settings->get('floating_button_enabled'));
        self::assertFalse($settings->get('require_decision_before_interaction'));
        self::assertTrue($settings->get('reload_on_withdrawal'));
        $settings->save(['reload_on_withdrawal' => false, 'consent_mode_type' => 'advanced']);
        self::assertTrue($settings->get('reload_on_withdrawal'));
        self::assertSame('basic', $settings->get('consent_mode_type'));
        $settings->save(['legal_framework' => 'eu_germany']);
        self::assertSame($version + 1, $settings->config_version());
        $settings->save(['legal_framework' => 'custom']);
        self::assertSame($version + 2, $settings->config_version());
        self::assertSame('advanced', $settings->get('consent_mode_type'));
        self::assertFalse($settings->get('floating_button_enabled'));
    }

    public function test_profile_overrides_stored_required_and_preselected_categories(): void
    {
        update_option(CategoryRepository::OPTION_NAME, [
            ['slug' => 'necessary', 'required' => true],
            ['slug' => 'analytics', 'required' => true, 'default_enabled' => true],
        ]);
        (new Settings())->save(['legal_framework' => 'eu_germany']);
        $categories = new CategoryRepository();
        self::assertTrue($categories->find('necessary')->required);
        self::assertFalse($categories->find('analytics')->required);
        self::assertFalse($categories->find('analytics')->default_enabled);
    }

    public function test_optional_services_need_explicit_current_active_grants(): void
    {
        $settings = new Settings();
        $settings->save(['legal_framework' => 'eu_germany']);
        $registry = new ServiceRegistry(new CustomServiceRepository());
        $registry->register(['id' => 'youtube', 'category' => 'marketing', 'active' => true]);
        $registry->register(['id' => 'unconfirmed', 'category' => 'marketing']);
        $manager = new ConsentManager(new CategoryRepository(), $registry, $settings);
        $state = new ConsentState('test', time(), time() + 3600, $settings->config_version(), ['marketing' => true]);
        $_COOKIE[ConsentManager::COOKIE_NAME] = json_encode($state->to_array());
        self::assertFalse($manager->has_service_consent('youtube'));
        $data = $state->to_array();
        $data['services'] = ['youtube' => true, 'unconfirmed' => true];
        $_COOKIE[ConsentManager::COOKIE_NAME] = json_encode($data);
        self::assertTrue($manager->has_service_consent('youtube'));
        self::assertFalse($manager->has_service_consent('unconfirmed'));
        $settings->bump_config_version();
        self::assertFalse($manager->has_service_consent('youtube'));
    }
}
