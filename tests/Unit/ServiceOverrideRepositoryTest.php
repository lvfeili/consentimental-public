<?php

declare(strict_types=1);

namespace Consentimental\Tests;

use Consentimental\Services\ServiceOverrideRepository;
use PHPUnit\Framework\TestCase;

final class ServiceOverrideRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['cmp_test_options'] = [];
    }

    public function test_no_override_by_default(): void
    {
        $repo = new ServiceOverrideRepository();

        self::assertNull($repo->get('matomo'));
        self::assertSame([], $repo->all());
    }

    public function test_save_and_get(): void
    {
        $repo = new ServiceOverrideRepository();
        $repo->save('matomo', ['domains' => ['analytics.example.com'], 'script_patterns' => [], 'iframe_patterns' => []]);

        self::assertSame(['analytics.example.com'], $repo->get('matomo')['domains']);
    }

    public function test_delete(): void
    {
        $repo = new ServiceOverrideRepository();
        $repo->save('matomo', ['domains' => ['a.test'], 'script_patterns' => [], 'iframe_patterns' => []]);
        $repo->delete('matomo');

        self::assertNull($repo->get('matomo'));
    }

    public function test_save_does_not_clobber_other_services(): void
    {
        $repo = new ServiceOverrideRepository();
        $repo->save('matomo', ['domains' => ['a.test'], 'script_patterns' => [], 'iframe_patterns' => []]);
        $repo->save('google-tag-manager', ['domains' => ['b.test'], 'script_patterns' => [], 'iframe_patterns' => []]);

        self::assertSame(['a.test'], $repo->get('matomo')['domains']);
        self::assertSame(['b.test'], $repo->get('google-tag-manager')['domains']);
    }
}
