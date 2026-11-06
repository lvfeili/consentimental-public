<?php

declare(strict_types=1);

namespace Consentimental\Tests;

use Consentimental\Diagnostics\SiteScanner;
use Consentimental\Services\Service;
use PHPUnit\Framework\TestCase;

final class SiteScannerTest extends TestCase
{
    public function test_finds_hosts_from_script_iframe_and_img_src(): void
    {
        $html = '<html><head><script src="https://tracker.example.test/a.js"></script></head>'
            . '<body><iframe src="https://embed.example.test/v"></iframe>'
            . '<img src="https://pixel.example.test/p.gif"></body></html>';

        $hosts = SiteScanner::external_hosts($html, 'mysite.test');

        self::assertSame(
            ['tracker.example.test', 'embed.example.test', 'pixel.example.test'],
            $hosts,
        );
    }

    public function test_excludes_the_site_own_host(): void
    {
        $html = '<script src="https://mysite.test/wp-content/plugins/x.js"></script>';

        self::assertSame([], SiteScanner::external_hosts($html, 'mysite.test'));
    }

    public function test_ignores_relative_and_protocol_relative_srcs_correctly(): void
    {
        $html = '<img src="/local/image.png"><script src="//cdn.example.test/lib.js"></script>';

        self::assertSame(['cdn.example.test'], SiteScanner::external_hosts($html, 'mysite.test'));
    }

    public function test_ignores_links_and_stylesheets_not_script_iframe_or_img(): void
    {
        $html = '<link rel="stylesheet" href="https://fonts.example.test/font.css">'
            . '<a href="https://other.example.test/page">link</a>';

        self::assertSame([], SiteScanner::external_hosts($html, 'mysite.test'));
    }

    public function test_deduplicates_repeated_hosts(): void
    {
        $html = '<script src="https://tracker.example.test/a.js"></script>'
            . '<script src="https://tracker.example.test/b.js"></script>';

        self::assertSame(['tracker.example.test'], SiteScanner::external_hosts($html, 'mysite.test'));
    }

    public function test_matches_any_service_true_when_a_domain_pattern_matches(): void
    {
        $services = [
            Service::from_array(['id' => 'ga', 'category' => 'analytics', 'domains' => ['google-analytics.com']]),
        ];

        self::assertTrue(SiteScanner::matches_any_service('www.google-analytics.com', $services));
    }

    public function test_matches_any_service_false_when_nothing_matches(): void
    {
        $services = [
            Service::from_array(['id' => 'ga', 'category' => 'analytics', 'domains' => ['google-analytics.com']]),
        ];

        self::assertFalse(SiteScanner::matches_any_service('unrelated.example.test', $services));
    }

    public function test_matches_any_service_false_for_a_service_with_no_patterns(): void
    {
        $services = [Service::from_array(['id' => 'x', 'category' => 'analytics'])];

        self::assertFalse(SiteScanner::matches_any_service('anything.test', $services));
    }
}
