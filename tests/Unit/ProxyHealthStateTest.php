<?php

declare(strict_types=1);
namespace Consentimental\Tests;
use Consentimental\Diagnostics\ProxyHealthState;
use PHPUnit\Framework\TestCase;

final class ProxyHealthStateTest extends TestCase
{
    public function test_initial_failures_do_not_send_regression_alerts(): void
    {
        $state = [];
        for ($i = 1; $i <= 3; ++$i) { $state = ProxyHealthState::observe($state, false, 'http_503', $i); }
        self::assertSame('failing', $state['status']);
        self::assertFalse(ProxyHealthState::should_alert($state));
    }
    public function test_regression_requires_three_failures_and_recovery_resets_incident(): void
    {
        $state = ProxyHealthState::observe([], true, 'http_200', 100);
        for ($i = 1; $i <= 2; ++$i) {
            $state = ProxyHealthState::observe($state, false, 'http_503', 100 + $i);
            self::assertFalse(ProxyHealthState::should_alert($state));
        }
        $state = ProxyHealthState::observe($state, false, 'http_503', 103);
        self::assertTrue(ProxyHealthState::should_alert($state));
        $state['alerted'] = true;
        self::assertFalse(ProxyHealthState::should_alert($state));
        $state = ProxyHealthState::observe($state, true, 'http_200', 104);
        self::assertSame('healthy', $state['status']);
        self::assertSame(0, $state['failures']);
        self::assertFalse($state['alerted']);
    }
}
