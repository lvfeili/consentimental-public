<?php

declare(strict_types=1);

namespace Consentimental\Tests;

use Consentimental\Consent\DraftManager;
use Consentimental\Consent\Settings;
use Consentimental\Services\CustomServiceRepository;
use Consentimental\Services\Service;
use Consentimental\Services\ServiceActivator;
use Consentimental\Services\ServiceOverrideRepository;
use Consentimental\Services\ServiceRegistry;
use PHPUnit\Framework\TestCase;

final class ServiceActivatorTest extends TestCase
{
    private ServiceRegistry $registry;
    private ServiceOverrideRepository $overrides;
    private CustomServiceRepository $custom_services;
    private Settings $settings;
    private DraftManager $draft;
    private ServiceActivator $activator;

    protected function setUp(): void
    {
        $GLOBALS['cmp_test_options'] = [];

        $this->custom_services = new CustomServiceRepository();
        $this->registry = new ServiceRegistry($this->custom_services);
        $this->registry->register(['id' => 'google-analytics', 'category' => 'analytics']);

        $this->overrides = new ServiceOverrideRepository();
        $this->settings = new Settings();
        $this->settings->save([]); // seeds the option so config_version() has a baseline
        $this->draft = new DraftManager($this->settings);
        $this->activator = new ServiceActivator($this->registry, $this->overrides, $this->custom_services, $this->draft);
    }

    public function test_activating_a_built_in_stores_an_override_and_marks_the_draft_meaningful(): void
    {
        self::assertFalse($this->registry->get('google-analytics')->active);
        self::assertFalse($this->draft->is_meaningful());

        $changed = $this->activator->set_active('google-analytics', true);

        self::assertTrue($changed);
        self::assertTrue($this->draft->is_meaningful());

        $before = $this->settings->config_version();
        $result = $this->draft->publish();
        self::assertTrue($result['version_bumped']);
        self::assertSame($before + 1, $this->settings->config_version());

        $this->registry->apply_override('google-analytics', $this->overrides->get('google-analytics'));
        self::assertTrue($this->registry->get('google-analytics')->active);
    }

    public function test_activating_a_built_in_preserves_its_other_override_fields(): void
    {
        $this->overrides->save('google-analytics', [
            'category' => 'marketing',
            'domains' => ['self-hosted.example.test'],
            'script_patterns' => [],
            'iframe_patterns' => [],
        ]);
        $this->registry->apply_override('google-analytics', $this->overrides->get('google-analytics'));

        $this->activator->set_active('google-analytics', true);

        $override = $this->overrides->get('google-analytics');
        self::assertSame('marketing', $override['category']);
        self::assertSame(['self-hosted.example.test'], $override['domains']);
        self::assertTrue($override['active']);
    }

    public function test_activating_a_custom_service_updates_it_directly(): void
    {
        $this->custom_services->save_one(Service::from_array(['id' => 'my-service', 'category' => 'marketing', 'active' => false]));

        $changed = $this->activator->set_active('my-service', true);

        self::assertTrue($changed);
        self::assertTrue($this->registry->get('my-service')->active);
    }

    public function test_setting_the_same_state_does_nothing_and_does_not_bump_the_version(): void
    {
        $before = $this->settings->config_version();

        $changed = $this->activator->set_active('google-analytics', false);

        self::assertFalse($changed);
        self::assertSame($before, $this->settings->config_version());
    }

    public function test_an_unknown_service_id_does_nothing(): void
    {
        self::assertFalse($this->activator->set_active('nonexistent', true));
    }
}
