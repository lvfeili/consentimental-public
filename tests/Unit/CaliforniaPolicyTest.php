<?php

declare(strict_types=1);

namespace Consentimental\Tests;

use Consentimental\Consent\CaliforniaPolicy;
use Consentimental\Consent\CategoryRepository;
use Consentimental\Consent\ConsentManager;
use Consentimental\Consent\ConsentState;
use Consentimental\Consent\Settings;
use Consentimental\Services\CustomServiceRepository;
use Consentimental\Services\Service;
use Consentimental\Services\ServiceRegistry;
use PHPUnit\Framework\TestCase;

final class CaliforniaPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['cmp_test_options'] = [];
        $GLOBALS['cmp_test_hooks'] = [];
        $_COOKIE = [];
        unset($_SERVER['HTTP_SEC_GPC']);
    }

    protected function tearDown(): void
    {
        $_COOKIE = [];
        unset($_SERVER['HTTP_SEC_GPC']);
    }

    public function test_classification_round_trips_and_invalid_values_fail_closed(): void
    {
        foreach (['involved', 'not_involved', 'unreviewed', 'invalid'] as $value) {
            $service = Service::from_array(['id' => 'test', 'category' => 'analytics', 'sale_sharing' => $value]);
            self::assertSame(CaliforniaPolicy::classification($value), Service::from_array($service->to_array())->sale_sharing);
        }
    }

    public function test_california_is_additive_and_requires_basic_mode(): void
    {
        $settings = new Settings();
        $settings->save(['consent_mode_type' => 'advanced', 'reload_on_withdrawal' => false]);
        $version = $settings->config_version();
        $settings->save(['california_enabled' => true]);
        self::assertSame($version + 1, $settings->config_version());
        self::assertSame('custom', $settings->get('legal_framework'));
        self::assertSame('basic', $settings->get('consent_mode_type'));
        self::assertTrue($settings->get('reload_on_withdrawal'));
        $settings->save(['consent_mode_type' => 'advanced', 'reload_on_withdrawal' => false]);
        self::assertSame($version + 1, $settings->config_version());
        $settings->save(['legal_framework' => 'eu_germany']);
        self::assertTrue($settings->get('california_enabled'));
        self::assertTrue($settings->enforces_framework());
        $_COOKIE[CaliforniaPolicy::COOKIE_NAME] = '1';
        $settings->save(['california_enabled' => false]);
        self::assertTrue(CaliforniaPolicy::opted_out());
    }

    public function test_gpc_and_remembered_opt_out_override_grants_even_in_required_category(): void
    {
        $settings = new Settings();
        $settings->save(['california_enabled' => true]);
        $services = new ServiceRegistry(new CustomServiceRepository());
        foreach (['ads' => 'involved', 'safe' => 'not_involved', 'unknown' => 'unreviewed'] as $id => $classification) {
            $services->register(['id' => $id, 'category' => 'necessary', 'active' => true, 'sale_sharing' => $classification]);
        }
        $manager = new ConsentManager(new CategoryRepository(), $services, $settings);
        $_COOKIE[ConsentManager::COOKIE_NAME] = json_encode((new ConsentState('test', time(), time() + 3600, $settings->config_version(), ['necessary' => true], ['ads' => true, 'safe' => true, 'unknown' => true]))->to_array());
        self::assertTrue($manager->has_service_consent('ads'));
        self::assertFalse($manager->has_service_consent('unknown'));
        $_SERVER['HTTP_SEC_GPC'] = '1';
        self::assertFalse($manager->has_service_consent('ads'));
        self::assertTrue($manager->has_service_consent('safe'));
        self::assertFalse($manager->has_consent('necessary'));
        unset($_SERVER['HTTP_SEC_GPC']);
        $_COOKIE[CaliforniaPolicy::COOKIE_NAME] = '1';
        $settings->bump_config_version();
        self::assertFalse($manager->has_service_consent('ads'));
        self::assertTrue($manager->has_service_consent('safe'));
    }
}
