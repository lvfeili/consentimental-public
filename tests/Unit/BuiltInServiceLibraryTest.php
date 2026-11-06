<?php

declare(strict_types=1);

namespace Consentimental\Tests;

use Consentimental\Services\BuiltInServiceLibrary;
use Consentimental\Services\CustomServiceRepository;
use Consentimental\Services\ServiceRegistry;
use PHPUnit\Framework\TestCase;

final class BuiltInServiceLibraryTest extends TestCase
{
    private ServiceRegistry $registry;

    protected function setUp(): void
    {
        $GLOBALS['cmp_test_options'] = [];

        $this->registry = new ServiceRegistry(new CustomServiceRepository());
        (new BuiltInServiceLibrary())->register($this->registry);
    }

    public function test_registers_the_eleven_required_services(): void
    {
        $expected = [
            'meta-pixel',
            'microsoft-clarity',
            'youtube',
            'vimeo',
            'google-maps',
            'recaptcha',
            'cloudflare-turnstile',
            'hotjar',
            'linkedin-insight',
            'tiktok-pixel',
            'matomo',
        ];

        foreach ($expected as $id) {
            self::assertTrue($this->registry->exists($id), "Expected built-in service \"{$id}\" to be registered.");
        }
    }

    public function test_every_service_has_required_documentation_fields(): void
    {
        foreach ($this->registry->all() as $service) {
            self::assertNotSame('', $service->name, "{$service->id} is missing a name");
            self::assertNotSame('', $service->provider, "{$service->id} is missing a provider");
            self::assertNotSame('', $service->purpose, "{$service->id} is missing a purpose");
            self::assertContains($service->category, ['necessary', 'functional', 'analytics', 'marketing'], "{$service->id} has an unexpected category");
        }
    }

    public function test_youtube_is_matched_by_iframe_pattern(): void
    {
        $youtube = $this->registry->get('youtube');

        self::assertNotNull($youtube);
        self::assertContains('youtube.com/embed', $youtube->iframe_patterns);
    }

    public function test_matomo_has_no_domain_since_it_is_self_hosted(): void
    {
        $matomo = $this->registry->get('matomo');

        self::assertNotNull($matomo);
        self::assertSame([], $matomo->domains);
        self::assertNotEmpty($matomo->script_patterns);
    }

    public function test_wildcard_cookie_names_are_preserved(): void
    {
        $hotjar = $this->registry->get('hotjar');

        self::assertNotNull($hotjar);
        $names = array_column($hotjar->cookies, 'name');
        self::assertContains('_hjSession*', $names);
    }
}
