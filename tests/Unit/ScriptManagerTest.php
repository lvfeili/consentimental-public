<?php

declare(strict_types=1);

namespace Consentimental\Tests;

use Consentimental\Scripts\CustomScript;
use Consentimental\Scripts\ScriptManager;
use PHPUnit\Framework\TestCase;

final class ScriptManagerTest extends TestCase
{
    private function script(array $overrides = []): CustomScript
    {
        return CustomScript::from_array(array_merge([
            'id' => 'x',
            'category' => 'marketing',
        ], $overrides));
    }

    public function test_whole_site_always_matches(): void
    {
        $script = $this->script(['condition_type' => CustomScript::CONDITION_WHOLE_SITE]);

        self::assertTrue(ScriptManager::condition_matches($script, '/anything', null));
        self::assertTrue(ScriptManager::condition_matches($script, '/', 'page'));
    }

    public function test_url_condition_matches_exact_path_ignoring_trailing_slash(): void
    {
        $script = $this->script(['condition_type' => CustomScript::CONDITION_URL, 'condition_value' => '/contact/']);

        self::assertTrue(ScriptManager::condition_matches($script, '/contact', null));
        self::assertTrue(ScriptManager::condition_matches($script, '/contact/', null));
        self::assertFalse(ScriptManager::condition_matches($script, '/about', null));
    }

    public function test_url_pattern_condition_uses_wildcards(): void
    {
        $script = $this->script(['condition_type' => CustomScript::CONDITION_URL_PATTERN, 'condition_value' => '/blog/*']);

        self::assertTrue(ScriptManager::condition_matches($script, '/blog/hello-world', null));
        self::assertFalse(ScriptManager::condition_matches($script, '/shop/item', null));
    }

    public function test_post_type_condition(): void
    {
        $script = $this->script(['condition_type' => CustomScript::CONDITION_POST_TYPE, 'condition_value' => 'product']);

        self::assertTrue(ScriptManager::condition_matches($script, '/whatever', 'product'));
        self::assertFalse(ScriptManager::condition_matches($script, '/whatever', 'post'));
        self::assertFalse(ScriptManager::condition_matches($script, '/whatever', null));
    }

    public function test_render_url_script_is_neutralized_with_category_and_service(): void
    {
        $script = $this->script([
            'type' => CustomScript::TYPE_URL,
            'url' => 'https://example.test/a.js',
            'service' => 'my-service',
        ]);

        $html = ScriptManager::render_tag($script);

        self::assertStringContainsString('type="text/plain"', $html);
        self::assertStringContainsString('data-cmp-category="marketing"', $html);
        self::assertStringContainsString('data-cmp-service="my-service"', $html);
        self::assertStringContainsString('src="https://example.test/a.js"', $html);
    }

    public function test_render_url_script_includes_extra_attributes(): void
    {
        $script = $this->script([
            'type' => CustomScript::TYPE_URL,
            'url' => 'https://umami.example.test/script.js',
            'attributes' => "defer=\"true\"\ndata-website-id=\"630c3f94-db3d-42af-8d5d-b9b2a7bd4966\"",
        ]);

        $html = ScriptManager::render_tag($script);

        self::assertStringContainsString('defer="true"', $html);
        self::assertStringContainsString('data-website-id="630c3f94-db3d-42af-8d5d-b9b2a7bd4966"', $html);
    }

    public function test_render_proxied_script_rewrites_src_and_adds_host_url(): void
    {
        $script = $this->script([
            'type' => CustomScript::TYPE_URL,
            'url' => 'https://umami.example.test/script.js?x=1',
            'proxy' => true,
        ]);

        $html = ScriptManager::render_tag($script, 'https://mysite.test/wp-json/consentimental/v1/proxy/x');

        self::assertStringContainsString('src="https://mysite.test/wp-json/consentimental/v1/proxy/x/script.js?x=1"', $html);
        self::assertStringContainsString('data-host-url="https://mysite.test/wp-json/consentimental/v1/proxy/x"', $html);
    }

    public function test_render_proxied_script_without_a_base_url_falls_back_to_upstream(): void
    {
        $script = $this->script(['type' => CustomScript::TYPE_URL, 'url' => 'https://umami.example.test/script.js', 'proxy' => true]);

        $html = ScriptManager::render_tag($script);

        self::assertStringContainsString('src="https://umami.example.test/script.js"', $html);
        self::assertStringNotContainsString('data-host-url', $html);
    }

    public function test_render_proxied_script_respects_an_explicit_host_url_override(): void
    {
        $script = $this->script([
            'type' => CustomScript::TYPE_URL,
            'url' => 'https://umami.example.test/script.js',
            'attributes' => 'data-host-url="https://custom.example.test"',
            'proxy' => true,
        ]);

        $html = ScriptManager::render_tag($script, 'https://mysite.test/proxy/x');

        self::assertStringContainsString('data-host-url="https://custom.example.test"', $html);
    }

    public function test_proxied_src_mirrors_upstream_path_and_query(): void
    {
        self::assertSame(
            'https://mysite.test/proxy/x/script.js?x=1',
            ScriptManager::proxied_src('https://mysite.test/proxy/x', 'https://umami.example.test/script.js?x=1'),
        );
    }

    public function test_extra_attributes_cannot_override_reserved_names(): void
    {
        $attributes = ScriptManager::parse_extra_attributes("type=\"module\"\nsrc=\"https://evil.test/x.js\"\ndata-cmp-category=\"necessary\"\nok=\"1\"");

        self::assertSame(['ok' => '1'], $attributes);
    }

    public function test_render_inline_script_omits_service_attribute_when_none_set(): void
    {
        $script = $this->script(['type' => CustomScript::TYPE_INLINE, 'code' => 'console.log(1);']);

        $html = ScriptManager::render_tag($script);

        self::assertStringContainsString('console.log(1);', $html);
        self::assertStringNotContainsString('data-cmp-service', $html);
    }

    public function test_render_inline_script_escapes_a_literal_closing_script_tag(): void
    {
        $script = $this->script(['type' => CustomScript::TYPE_INLINE, 'code' => 'var x = "</script><script>alert(1)</script>";']);

        $html = ScriptManager::render_tag($script);

        self::assertStringNotContainsString('</script><script>alert', $html);
    }
}
