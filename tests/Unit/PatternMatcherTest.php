<?php

declare(strict_types=1);

namespace Consentimental\Tests;

use Consentimental\Blocking\PatternMatcher;
use PHPUnit\Framework\TestCase;

final class PatternMatcherTest extends TestCase
{
    public function test_plain_pattern_matches_as_substring(): void
    {
        self::assertTrue(PatternMatcher::matches('https://www.google-analytics.com/analytics.js', ['google-analytics.com']));
        self::assertFalse(PatternMatcher::matches('https://example.com/app.js', ['google-analytics.com']));
    }

    public function test_matching_is_case_insensitive(): void
    {
        self::assertTrue(PatternMatcher::matches('HTTPS://WWW.GOOGLE-ANALYTICS.COM/x.js', ['google-analytics.com']));
    }

    public function test_wildcard_pattern(): void
    {
        self::assertTrue(PatternMatcher::matches('_ga_ABC123', ['_ga*']));
        self::assertFalse(PatternMatcher::matches('other_cookie', ['_ga*']));
    }

    public function test_empty_pattern_list_never_matches(): void
    {
        self::assertFalse(PatternMatcher::matches('https://google-analytics.com/x.js', []));
    }

    public function test_empty_pattern_string_is_ignored(): void
    {
        self::assertFalse(PatternMatcher::matches('anything', ['']));
    }

    public function test_matches_against_a_haystack_longer_than_fnmatch_would_allow(): void
    {
        $longContent = str_repeat('console.log("noise");', 1000) . 'matomo.js' . str_repeat('x', 5000);

        self::assertTrue(PatternMatcher::matches($longContent, ['matomo.js']));
        self::assertGreaterThan(4096, strlen($longContent));
    }

    public function test_newlines_in_haystack_are_matched_by_wildcard(): void
    {
        self::assertTrue(PatternMatcher::matches("line one\nfbq('init')\nline three", ['fbq(']));
    }
}
