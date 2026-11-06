<?php

declare(strict_types=1);

namespace Consentimental\Tests;

use Consentimental\Services\CustomServiceRepository;
use Consentimental\Services\ServiceRegistry;
use PHPUnit\Framework\TestCase;

final class ServiceRegistryOverrideTest extends TestCase
{
    private ServiceRegistry $registry;

    protected function setUp(): void
    {
        $GLOBALS['cmp_test_options'] = [];
        $this->registry = new ServiceRegistry(new CustomServiceRepository());
        $this->registry->register([
            'id' => 'matomo',
            'category' => 'analytics',
            'name' => 'Matomo',
            'script_patterns' => ['matomo.js', 'piwik.js'],
        ]);
    }

    public function test_override_replaces_domains_on_a_built_in_service(): void
    {
        $applied = $this->registry->apply_override('matomo', [
            'domains' => ['analytics.mysite.test'],
            'script_patterns' => [],
            'iframe_patterns' => [],
        ]);

        self::assertTrue($applied);

        $matomo = $this->registry->get('matomo');
        self::assertSame(['analytics.mysite.test'], $matomo->domains);
        self::assertSame([], $matomo->script_patterns);
    }

    public function test_override_preserves_fields_it_does_not_touch(): void
    {
        $this->registry->apply_override('matomo', ['domains' => ['a.test'], 'script_patterns' => [], 'iframe_patterns' => []]);

        $matomo = $this->registry->get('matomo');
        self::assertSame('Matomo', $matomo->name);
        self::assertSame('analytics', $matomo->category);
        self::assertTrue($matomo->built_in);
    }

    public function test_override_can_reassign_category(): void
    {
        $this->registry->apply_override('matomo', ['category' => 'functional']);

        $matomo = $this->registry->get('matomo');
        self::assertSame('functional', $matomo->category);
        self::assertSame([], $this->registry->by_category('analytics'));
        self::assertCount(1, $this->registry->by_category('functional'));
    }

    public function test_override_on_unknown_service_returns_false_and_does_nothing(): void
    {
        $applied = $this->registry->apply_override('does-not-exist', ['domains' => ['a.test'], 'script_patterns' => [], 'iframe_patterns' => []]);

        self::assertFalse($applied);
        self::assertNull($this->registry->get('does-not-exist'));
    }

    public function test_override_cannot_target_a_custom_service(): void
    {
        $customRegistry = new ServiceRegistry($repo = new CustomServiceRepository());
        $repo->save([\Consentimental\Services\Service::from_array(['id' => 'my-custom', 'category' => 'marketing'])]);

        $applied = $customRegistry->apply_override('my-custom', ['domains' => ['a.test'], 'script_patterns' => [], 'iframe_patterns' => []]);

        self::assertFalse($applied);
    }
}
