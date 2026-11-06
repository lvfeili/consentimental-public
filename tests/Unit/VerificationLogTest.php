<?php

declare(strict_types=1);

namespace Consentimental\Tests;

use Consentimental\Diagnostics\Finding;
use Consentimental\Diagnostics\VerificationLog;
use PHPUnit\Framework\TestCase;

final class VerificationLogTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['cmp_test_options'] = [];
    }

    public function test_passed_only_when_every_step_is_confirmed(): void
    {
        $log = new VerificationLog();
        $all = array_keys(VerificationLog::steps()[VerificationLog::TYPE_BROWSER]);

        self::assertSame(Finding::PASSED, $log->record('browser', false, $all, '', 3, 1, 'admin')['result']);
        self::assertSame('partial', $log->record('browser', false, array_slice($all, 1), '', 3, 1, 'admin')['result']);
        self::assertSame(Finding::FAILED, $log->record('browser', true, $all, 'x', 3, 1, 'admin')['result']);
    }

    public function test_unknown_step_keys_are_ignored(): void
    {
        $entry = (new VerificationLog())->record('vendor', false, ['received', 'invented'], '', 3, 1, 'admin');

        self::assertSame(['received'], $entry['confirmed']);
        self::assertSame('partial', $entry['result']);
    }

    public function test_latest_is_per_type_and_history_is_bounded(): void
    {
        $log = new VerificationLog();

        for ($i = 0; $i < 25; ++$i) {
            $log->record('browser', false, [], (string) $i, 3, 1, 'admin');
        }

        $log->record('vendor', false, [], 'v', 3, 1, 'admin');

        self::assertCount(20, $log->all());
        self::assertSame('24', $log->latest('browser')['notes']);
        self::assertSame('v', $log->latest('vendor')['notes']);
    }
}
