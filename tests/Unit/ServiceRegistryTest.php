<?php

declare(strict_types=1);

namespace Consentimental\Tests;

use Consentimental\Services\CustomServiceRepository;
use Consentimental\Services\Service;
use Consentimental\Services\ServiceRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ServiceRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['cmp_test_options'] = [];
    }

    public function test_register_adds_a_built_in_service(): void
    {
        $registry = new ServiceRegistry(new CustomServiceRepository());

        $registry->register([
            'id' => 'google-analytics',
            'name' => 'Google Analytics',
            'category' => 'analytics',
        ]);

        self::assertTrue($registry->exists('google-analytics'));
        self::assertSame('analytics', $registry->get('google-analytics')->category);
    }

    public function test_register_requires_id_and_category(): void
    {
        $registry = new ServiceRegistry(new CustomServiceRepository());

        $this->expectException(InvalidArgumentException::class);

        $registry->register(['name' => 'Missing id and category']);
    }

    public function test_custom_services_are_included_alongside_built_ins(): void
    {
        $repository = new CustomServiceRepository();
        $repository->save([
            new Service(id: 'my-custom-script', name: 'Custom Script', provider: 'Acme', purpose: 'Tracking', category: 'marketing'),
        ]);

        $registry = new ServiceRegistry($repository);
        $registry->register(['id' => 'google-analytics', 'category' => 'analytics']);

        $ids = array_map(static fn (Service $service): string => $service->id, $registry->all());

        self::assertContains('google-analytics', $ids);
        self::assertContains('my-custom-script', $ids);
    }

    public function test_custom_service_cannot_shadow_a_built_in_id(): void
    {
        $repository = new CustomServiceRepository();
        $repository->save([
            new Service(id: 'google-analytics', name: 'Fake GA', provider: 'Acme', purpose: 'x', category: 'marketing'),
        ]);

        $registry = new ServiceRegistry($repository);
        $registry->register(['id' => 'google-analytics', 'category' => 'analytics']);

        self::assertSame('analytics', $registry->get('google-analytics')->category);
        self::assertCount(1, $registry->all());
    }

    public function test_by_category_filters_services(): void
    {
        $registry = new ServiceRegistry(new CustomServiceRepository());
        $registry->register(['id' => 'google-analytics', 'category' => 'analytics']);
        $registry->register(['id' => 'meta-pixel', 'category' => 'marketing']);

        $analytics = $registry->by_category('analytics');

        self::assertCount(1, $analytics);
        self::assertSame('google-analytics', $analytics[0]->id);
    }
}
