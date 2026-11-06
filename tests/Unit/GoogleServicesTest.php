<?php

declare(strict_types=1);

namespace Consentimental\Tests;

use Consentimental\Consent\Settings;
use Consentimental\Integrations\ConsentMode;
use Consentimental\Integrations\GoogleServices;
use Consentimental\Services\CustomServiceRepository;
use Consentimental\Services\ServiceRegistry;
use PHPUnit\Framework\TestCase;

final class GoogleServicesTest extends TestCase
{
    private Settings $settings;
    private ServiceRegistry $registry;

    protected function setUp(): void
    {
        $GLOBALS['cmp_test_options'] = [];
        $GLOBALS['cmp_test_hooks'] = [];

        $this->settings = new Settings();
        $this->settings->seed_defaults();
        $this->registry = new ServiceRegistry(new CustomServiceRepository());
    }

    public function test_registers_gtm_ga4_and_ads(): void
    {
        (new GoogleServices(new ConsentMode($this->settings)))->register($this->registry);

        self::assertNotNull($this->registry->get('google-tag-manager'));
        self::assertNotNull($this->registry->get('google-analytics'));
        self::assertNotNull($this->registry->get('google-ads'));
        self::assertSame('marketing', $this->registry->get('google-tag-manager')->category);
        self::assertSame('analytics', $this->registry->get('google-analytics')->category);
        self::assertSame('marketing', $this->registry->get('google-ads')->category);
    }

    public function test_gtm_is_blockable_in_basic_mode(): void
    {
        (new GoogleServices(new ConsentMode($this->settings)))->register($this->registry);

        self::assertNotEmpty($this->registry->get('google-tag-manager')->domains);
    }

    public function test_gtm_is_not_blockable_in_advanced_mode(): void
    {
        $this->settings->save(['consent_mode_type' => 'advanced']);

        (new GoogleServices(new ConsentMode($this->settings)))->register($this->registry);

        self::assertSame([], $this->registry->get('google-tag-manager')->domains);
        self::assertSame([], $this->registry->get('google-tag-manager')->script_patterns);
        self::assertSame([], $this->registry->get('google-tag-manager')->iframe_patterns);
    }

    public function test_gtm_covers_the_inline_loader_and_noscript_fallback_in_basic_mode(): void
    {
        (new GoogleServices(new ConsentMode($this->settings)))->register($this->registry);

        $gtm = $this->registry->get('google-tag-manager');

        self::assertContains('googletagmanager.com/gtm.js', $gtm->script_patterns);
        self::assertContains('googletagmanager.com/ns.html', $gtm->iframe_patterns);
    }

    public function test_ga4_and_ads_gtag_urls_are_distinguished_by_id_prefix(): void
    {
        (new GoogleServices(new ConsentMode($this->settings)))->register($this->registry);

        $ga4 = $this->registry->get('google-analytics');
        $ads = $this->registry->get('google-ads');

        self::assertContains('googletagmanager.com/gtag/js?id=G-', $ga4->domains);
        self::assertContains('googletagmanager.com/gtag/js?id=AW-', $ads->domains);
    }
}
