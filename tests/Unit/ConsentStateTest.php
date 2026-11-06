<?php

declare(strict_types=1);

namespace Consentimental\Tests;

use Consentimental\Consent\ConsentState;
use PHPUnit\Framework\TestCase;

final class ConsentStateTest extends TestCase
{
    public function test_from_array_requires_consent_id_and_categories(): void
    {
        self::assertNull(ConsentState::from_array(['categories' => ['necessary' => true]]));
        self::assertNull(ConsentState::from_array(['consent_id' => 'abc']));
    }

    public function test_round_trips_through_array(): void
    {
        $state = new ConsentState(
            consent_id: 'abc',
            timestamp: 1000,
            expiry: 2000,
            config_version: 3,
            categories: ['necessary' => true, 'analytics' => false],
            services: ['google-analytics' => true],
        );

        $restored = ConsentState::from_array($state->to_array());

        self::assertNotNull($restored);
        self::assertSame($state->to_array(), $restored->to_array());
    }

    public function test_is_expired(): void
    {
        $state = new ConsentState('id', 1000, 2000, 1, ['necessary' => true]);

        self::assertFalse($state->is_expired(1500));
        self::assertTrue($state->is_expired(2000));
        self::assertTrue($state->is_expired(2500));
    }

    public function test_is_stale_compares_config_version(): void
    {
        $state = new ConsentState('id', 1000, 2000, 3, ['necessary' => true]);

        self::assertFalse($state->is_stale(3));
        self::assertTrue($state->is_stale(4));
    }
}
