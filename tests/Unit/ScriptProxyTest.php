<?php

declare(strict_types=1);

namespace Consentimental\Tests;

use Consentimental\Scripts\CustomScript;
use Consentimental\Scripts\ScriptProxy;
use PHPUnit\Framework\TestCase;

final class ScriptProxyTest extends TestCase
{
    private function script(array $overrides = []): CustomScript
    {
        return CustomScript::from_array(array_merge([
            'id' => 'x',
            'category' => 'analytics',
            'type' => CustomScript::TYPE_URL,
            'url' => 'https://www.googletagmanager.com/gtag/js',
            'proxy' => true,
            'proxy_paths' => "GET /g/collect\nPOST /g/collect",
        ], $overrides));
    }

    public function test_script_path_extracts_the_url_path(): void
    {
        self::assertSame('gtag/js', ScriptProxy::script_path($this->script()));
    }

    public function test_resolve_origin_uses_the_script_own_origin_for_the_script_path(): void
    {
        $script = $this->script(['proxy_api_origin' => 'https://www.google-analytics.com']);

        self::assertSame('https://www.googletagmanager.com', ScriptProxy::resolve_origin($script, 'gtag/js'));
    }

    public function test_resolve_origin_uses_the_api_origin_for_any_other_path(): void
    {
        $script = $this->script(['proxy_api_origin' => 'https://www.google-analytics.com']);

        self::assertSame('https://www.google-analytics.com', ScriptProxy::resolve_origin($script, 'g/collect'));
    }

    public function test_resolve_origin_falls_back_to_the_script_origin_when_no_api_origin_is_set(): void
    {
        $script = $this->script(['url' => 'https://umami.example.test/script.js']);

        self::assertSame('https://umami.example.test', ScriptProxy::resolve_origin($script, 'api/send'));
    }

    public function test_upstream_origin_extracts_scheme_and_host(): void
    {
        self::assertSame('https://umami.example.test', ScriptProxy::upstream_origin('https://umami.example.test/script.js'));
    }

    public function test_upstream_origin_includes_a_non_default_port(): void
    {
        self::assertSame('https://umami.example.test:8443', ScriptProxy::upstream_origin('https://umami.example.test:8443/script.js'));
    }

    public function test_upstream_origin_rejects_a_url_without_a_host(): void
    {
        self::assertNull(ScriptProxy::upstream_origin('not-a-url'));
    }

    public function test_upstream_url_joins_origin_and_path(): void
    {
        self::assertSame('https://umami.example.test/api/send', ScriptProxy::upstream_url('https://umami.example.test', '/api/send'));
        self::assertSame('https://umami.example.test/api/send', ScriptProxy::upstream_url('https://umami.example.test/', 'api/send'));
    }

    public function test_upstream_url_appends_query_string(): void
    {
        self::assertSame(
            'https://umami.example.test/api/send?a=1&b=2',
            ScriptProxy::upstream_url('https://umami.example.test', 'api/send', ['a' => '1', 'b' => '2']),
        );
    }

    public function test_allowed_content_types_are_accepted(): void
    {
        self::assertTrue(ScriptProxy::is_allowed_content_type('application/javascript'));
        self::assertTrue(ScriptProxy::is_allowed_content_type('text/javascript; charset=utf-8'));
        self::assertTrue(ScriptProxy::is_allowed_content_type('image/gif'));
        self::assertTrue(ScriptProxy::is_allowed_content_type('application/json'));
    }

    public function test_html_and_svg_content_types_are_rejected(): void
    {
        self::assertFalse(ScriptProxy::is_allowed_content_type('text/html'));
        self::assertFalse(ScriptProxy::is_allowed_content_type('text/html; charset=utf-8'));
        self::assertFalse(ScriptProxy::is_allowed_content_type('image/svg+xml'));
    }
    public function test_proxy_paths_and_methods_are_explicit(): void
    {
        $script = $this->script();
        self::assertTrue(ScriptProxy::allows_request($script, 'gtag/js', 'GET'));
        self::assertTrue(ScriptProxy::allows_request($script, 'g/collect', 'POST'));
        self::assertFalse(ScriptProxy::allows_request($script, 'gtag/js', 'POST'));
        foreach (['admin', 'redirect', '../gtag/js', '%2e%2e/gtag/js', 'gtag/js?x=1'] as $path) {
            self::assertFalse(ScriptProxy::allows_request($script, $path, 'GET'));
        }
    }

    public function test_static_query_must_match_configuration(): void
    {
        $script = $this->script(['url' => 'https://www.googletagmanager.com/gtag/js?id=G-TEST']);
        self::assertTrue(ScriptProxy::matches_static_query($script, ['id' => 'G-TEST']));
        self::assertFalse(ScriptProxy::matches_static_query($script, ['id' => 'G-TEST', 'random' => 'cache-flood']));
        self::assertFalse(ScriptProxy::matches_static_query($script, []));
        self::assertFalse(ScriptProxy::is_allowed_content_type('text/plain-malicious'));
    }

}
