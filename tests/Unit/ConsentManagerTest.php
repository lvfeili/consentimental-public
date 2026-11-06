<?php

declare(strict_types=1);

namespace Consentimental\Tests;

use Consentimental\Consent\CategoryRepository;
use Consentimental\Consent\ConsentManager;
use Consentimental\Consent\ConsentState;
use Consentimental\Consent\Settings;
use Consentimental\Services\CustomServiceRepository;
use Consentimental\Services\Service;
use Consentimental\Services\ServiceRegistry;
use PHPUnit\Framework\TestCase;

final class ConsentManagerTest extends TestCase
{
    private CategoryRepository $categories;
    private ServiceRegistry $services;
    private Settings $settings;
    private ConsentManager $manager;

    protected function setUp(): void
    {
        $GLOBALS['cmp_test_options'] = [];
        $_COOKIE = [];

        $this->categories = new CategoryRepository();
        $this->categories->seed_defaults();

        $this->services = new ServiceRegistry(new CustomServiceRepository());
		$this->services->register(['id' => 'google-analytics', 'category' => 'analytics', 'active' => true]);

        $this->settings = new Settings();
        $this->settings->seed_defaults();

        $this->manager = new ConsentManager($this->categories, $this->services, $this->settings);
    }

    private function put_cookie(ConsentState $state): void
    {
        $_COOKIE[ConsentManager::COOKIE_NAME] = json_encode($state->to_array());
    }

    private function state(array $categories, array $services = [], ?int $config_version = null, ?int $expiry = null): ConsentState
    {
        return new ConsentState(
            consent_id: 'test-id',
            timestamp: time(),
            expiry: $expiry ?? (time() + 3600),
            config_version: $config_version ?? $this->settings->config_version(),
            categories: $categories,
            services: $services,
        );
    }

    public function test_necessary_category_is_always_consented(): void
    {
        self::assertTrue($this->manager->has_consent('necessary'));
    }

    public function test_no_cookie_means_no_consent_for_optional_categories(): void
    {
        self::assertFalse($this->manager->has_consent('analytics'));
        self::assertTrue($this->manager->needs_consent());
    }

    public function test_valid_cookie_grants_consent_for_selected_categories(): void
    {
        $this->put_cookie($this->state([
            'necessary' => true,
            'functional' => false,
            'analytics' => true,
            'marketing' => false,
        ]));

        self::assertTrue($this->manager->has_consent('analytics'));
        self::assertFalse($this->manager->has_consent('marketing'));
        self::assertFalse($this->manager->needs_consent());
    }

    public function test_expired_cookie_is_treated_as_no_consent(): void
    {
        $this->put_cookie($this->state(['necessary' => true, 'analytics' => true], expiry: time() - 10));

        self::assertFalse($this->manager->has_consent('analytics'));
        self::assertTrue($this->manager->needs_consent());
    }

    public function test_stale_config_version_invalidates_consent(): void
    {
        $this->put_cookie($this->state(['necessary' => true, 'analytics' => true], config_version: 999));

        self::assertFalse($this->manager->has_consent('analytics'));
        self::assertTrue($this->manager->needs_consent());
    }

    public function test_service_level_override_takes_precedence_over_category(): void
    {
        $this->put_cookie($this->state(
            ['necessary' => true, 'analytics' => false],
            ['google-analytics' => true],
        ));

        self::assertFalse($this->manager->has_consent('analytics'));
        self::assertTrue($this->manager->has_service_consent('google-analytics'));
    }

    public function test_service_without_override_falls_back_to_category(): void
    {
        $this->put_cookie($this->state(['necessary' => true, 'analytics' => true]));

        self::assertTrue($this->manager->has_service_consent('google-analytics'));
    }

    public function test_unknown_service_has_no_consent(): void
    {
        self::assertFalse($this->manager->has_service_consent('unknown-service'));
    }
}
