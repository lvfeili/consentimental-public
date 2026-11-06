<?php

declare(strict_types=1);

namespace Consentimental\Tests;

use Consentimental\Services\CookieDatabaseLookup;
use PHPUnit\Framework\TestCase;

final class CookieDatabaseLookupTest extends TestCase
{
    private string $data_file;

    protected function setUp(): void
    {
        $this->data_file = tempnam(sys_get_temp_dir(), 'cmp-cookie-db');

        file_put_contents($this->data_file, json_encode([
            'example.com' => [
                ['name' => '_ex_id', 'purpose' => 'Tracks the visitor.', 'duration' => '1 year'],
            ],
        ]));
    }

    protected function tearDown(): void
    {
        unlink($this->data_file);
    }

    public function test_it_finds_cookies_for_an_exact_domain(): void
    {
        $lookup = new CookieDatabaseLookup($this->data_file);

        self::assertSame(
            [['name' => '_ex_id', 'purpose' => 'Tracks the visitor.', 'duration' => '1 year']],
            $lookup->find_by_domain('example.com'),
        );
    }

    public function test_it_returns_nothing_for_an_unknown_domain(): void
    {
        $lookup = new CookieDatabaseLookup($this->data_file);

        self::assertSame([], $lookup->find_by_domain('unknown.example'));
    }

    public function test_it_is_case_and_whitespace_insensitive(): void
    {
        $lookup = new CookieDatabaseLookup($this->data_file);

        self::assertNotSame([], $lookup->find_by_domain(' EXAMPLE.com '));
    }

    public function test_a_missing_data_file_yields_no_matches_instead_of_erroring(): void
    {
        $lookup = new CookieDatabaseLookup('/nonexistent/open-cookie-database.json');

        self::assertSame([], $lookup->find_by_domain('example.com'));
    }
}
