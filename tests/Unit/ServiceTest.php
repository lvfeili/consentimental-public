<?php

declare(strict_types=1);

namespace Consentimental\Tests;

use Consentimental\Services\Service;
use PHPUnit\Framework\TestCase;

final class ServiceTest extends TestCase
{
    public function test_a_built_in_defaults_inactive(): void
    {
        $service = Service::from_array(['id' => 'x', 'category' => 'analytics'], built_in: true);

        self::assertFalse($service->active);
    }

    public function test_a_custom_service_defaults_active(): void
    {
        $service = Service::from_array(['id' => 'x', 'category' => 'analytics'], built_in: false);

        self::assertTrue($service->active);
    }

    public function test_an_explicit_active_value_overrides_the_built_in_default(): void
    {
        $service = Service::from_array(['id' => 'x', 'category' => 'analytics', 'active' => true], built_in: true);

        self::assertTrue($service->active);
    }

    public function test_round_trips_through_array(): void
    {
        $service = Service::from_array(['id' => 'x', 'category' => 'analytics', 'active' => false], built_in: false);

        self::assertFalse(Service::from_array($service->to_array())->active);
    }
}
