<?php

declare(strict_types=1);

namespace Consentimental\Tests;

use Consentimental\Consent\ConsentExport;
use PHPUnit\Framework\TestCase;

final class ConsentExportTest extends TestCase
{
    public function test_csv_row_matches_the_declared_columns(): void
    {
        $row = ConsentExport::csv_row([
            'id' => 7, 'consent_id' => 'abc', 'recorded_at' => '2026-01-01 00:00:00', 'action' => 'granted',
            'config_version' => 3, 'disclosure_hash' => null, 'language' => null,
            'categories' => ['analytics' => true], 'services' => [], 'ip_anonymized' => '10.0.0.0',
            'user_agent' => 'UA', 'page_url' => 'https://site.test/',
        ]);

        self::assertCount(count(ConsentExport::CSV_COLUMNS), $row);
        self::assertSame('', $row[5], 'An unknown disclosure is exported empty, never guessed');
        self::assertSame('{"analytics":true}', $row[7]);
    }

    public function test_visitor_controlled_values_cannot_become_spreadsheet_formulas(): void
    {
        foreach (['=HYPERLINK("x")', '+1', '-1', '@SUM(1)', "\tx", "\rx"] as $value) {
            self::assertSame("'" . $value, ConsentExport::spreadsheet_safe($value));
        }

        self::assertSame('Mozilla/5.0', ConsentExport::spreadsheet_safe('Mozilla/5.0'));
        self::assertSame('', ConsentExport::spreadsheet_safe(''));
    }

    public function test_json_export_explains_its_limits(): void
    {
        $meta = ConsentExport::json_meta('https://site.test/', '0.3.0', 0, '');

        self::assertSame('1970-01-01T00:00:00Z', $meta['generated_at_utc']);
        self::assertStringContainsString('client assertions', implode(' ', $meta['notes']));
        self::assertStringContainsString('not been reconstructed', implode(' ', $meta['notes']));
    }
}
