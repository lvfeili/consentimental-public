<?php

declare(strict_types=1);

namespace Consentimental\Tests;

use Consentimental\Blocking\DomRewriter;
use Consentimental\Services\CustomServiceRepository;
use Consentimental\Services\ServiceRegistry;
use PHPUnit\Framework\TestCase;

final class DomRewriterTest extends TestCase
{
    private ServiceRegistry $services;
    private DomRewriter $rewriter;

    protected function setUp(): void
    {
        $GLOBALS['cmp_test_options'] = [];

        $this->services = new ServiceRegistry(new CustomServiceRepository());
        $this->rewriter = new DomRewriter($this->services);
    }

    public function test_resource_hints_cannot_contact_embed_providers(): void
    {
        $this->services->register(['id' => 'youtube', 'category' => 'marketing', 'iframe_patterns' => ['youtube-nocookie.com/embed']]);
        foreach (['preconnect', 'dns-prefetch', 'PRECONNECT dns-prefetch'] as $rel) {
            self::assertSame('', $this->rewriter->rewrite('<link rel="' . $rel . '" href="//www.youtube-nocookie.com">'));
        }
        self::assertSame('', $this->rewriter->rewrite('<link rel="prefetch" href="https://www.youtube-nocookie.com/embed/test">'));
        $local = '<link rel="preload" href="/app.js" as="script">';
        self::assertSame($local, $this->rewriter->rewrite($local));
    }

    public function test_srcdoc_is_inert_and_all_matched_providers_are_required(): void
    {
        $this->services->register(['id' => 'youtube', 'category' => 'marketing', 'domains' => ['youtube.com']]);
        $this->services->register(['id' => 'vimeo', 'category' => 'marketing', 'domains' => ['vimeo.com']]);
        $html = '<iframe src="https://youtube.com/embed/one" srcdoc="&lt;img src=&quot;https://vimeo.com/image&quot;&gt;"></iframe>';
        $result = $this->rewriter->rewrite($html);
        self::assertStringNotContainsString(' srcdoc=', $result);
        self::assertStringNotContainsString(' src="', $result);
        self::assertStringContainsString('data-cmp-srcdoc=', $result);
        self::assertStringContainsString('data-cmp-services="[&quot;youtube&quot;,&quot;vimeo&quot;]"', $result);
        self::assertSame($result, $this->rewriter->rewrite($result));
    }

    public function test_no_matchable_services_leaves_html_untouched(): void
    {
        $html = '<script src="https://googletagmanager.com/gtm.js"></script>';

        self::assertFalse($this->rewriter->has_matchable_services());
        self::assertSame($html, $this->rewriter->rewrite($html));
    }

    public function test_external_script_matching_a_domain_is_neutralized(): void
    {
        $this->services->register([
            'id' => 'gtm',
            'category' => 'marketing',
            'domains' => ['googletagmanager.com'],
        ]);

        $html = '<script src="https://www.googletagmanager.com/gtm.js?id=X"></script>';
        $result = $this->rewriter->rewrite($html);

        self::assertStringContainsString('type="text/plain"', $result);
        self::assertStringContainsString('data-cmp-category="marketing"', $result);
        self::assertStringContainsString('data-cmp-service="gtm"', $result);
        self::assertStringContainsString('src="https://www.googletagmanager.com/gtm.js?id=X"', $result);
    }

    public function test_non_matching_script_is_left_untouched(): void
    {
        $this->services->register(['id' => 'gtm', 'category' => 'marketing', 'domains' => ['googletagmanager.com']]);

        $html = '<script src="https://example.com/site.js"></script>';

        self::assertSame($html, $this->rewriter->rewrite($html));
    }

    public function test_inline_script_matching_a_content_pattern_is_neutralized(): void
    {
        $this->services->register([
            'id' => 'fbq',
            'category' => 'marketing',
            'script_patterns' => ['fbq('],
        ]);

        $html = '<script>fbq(\'init\', \'12345\');</script>';
        $result = $this->rewriter->rewrite($html);

        self::assertStringContainsString('type="text/plain"', $result);
        self::assertStringContainsString('data-cmp-service="fbq"', $result);
        self::assertStringContainsString("fbq('init', '12345');", $result);
    }

    public function test_iframe_matching_a_domain_has_src_renamed(): void
    {
        $this->services->register([
            'id' => 'youtube',
            'category' => 'marketing',
            'domains' => ['youtube.com'],
        ]);

        $html = '<iframe src="https://www.youtube.com/embed/xyz" width="560" height="315"></iframe>';
        $result = $this->rewriter->rewrite($html);

        self::assertStringNotContainsString(' src="https://www.youtube.com', $result);
        self::assertStringContainsString('data-cmp-src="https://www.youtube.com/embed/xyz"', $result);
        self::assertStringContainsString('data-cmp-category="marketing"', $result);
        self::assertStringContainsString('width="560"', $result);
    }

    public function test_img_pixel_matching_a_domain_has_src_renamed(): void
    {
        $this->services->register([
            'id' => 'meta-pixel',
            'category' => 'marketing',
            'domains' => ['facebook.com/tr'],
        ]);

        $html = '<img src="https://www.facebook.com/tr?id=1" width="1" height="1" />';
        $result = $this->rewriter->rewrite($html);

        self::assertStringContainsString('data-cmp-src="https://www.facebook.com/tr?id=1"', $result);
        self::assertStringNotContainsString(' src="https://www.facebook.com/tr?id=1"', $result);
    }

    public function test_already_gated_tags_are_not_processed_twice(): void
    {
        $this->services->register(['id' => 'gtm', 'category' => 'marketing', 'domains' => ['googletagmanager.com']]);

        $html = '<script type="text/plain" data-cmp-category="marketing" src="https://googletagmanager.com/gtm.js"></script>';

        self::assertSame($html, $this->rewriter->rewrite($html));
    }

    public function test_own_plugin_assets_are_not_matched(): void
    {
        $this->services->register(['id' => 'gtm', 'category' => 'marketing', 'domains' => ['googletagmanager.com']]);

        $html = '<script src="https://example.com/wp-content/plugins/consentimental/assets/js/banner.js"></script>';

        self::assertSame($html, $this->rewriter->rewrite($html));
    }

    public function test_inline_loader_matching_a_domain_substring_is_neutralized(): void
    {
        $this->services->register([
            'id' => 'gtm',
            'category' => 'marketing',
            'domains' => ['googletagmanager.com/gtm.js'],
        ]);

        $html = "<script>(function(w,d,s,l,i){j.src='https://www.googletagmanager.com/gtm.js?id='+i;})(window,document,'script','dataLayer','GTM-XXXX');</script>";
        $result = $this->rewriter->rewrite($html);

        self::assertStringContainsString('type="text/plain"', $result);
        self::assertStringContainsString('data-cmp-service="gtm"', $result);
    }

    public function test_unquoted_src_attribute_is_still_matched_and_neutralized(): void
    {
        $this->services->register(['id' => 'tracker', 'category' => 'marketing', 'domains' => ['tracker.example']]);

        $html = '<script src=https://tracker.example/a.js></script>';
        $result = $this->rewriter->rewrite($html);

        self::assertStringContainsString('type="text/plain"', $result);
        self::assertStringContainsString('data-cmp-service="tracker"', $result);
        self::assertStringContainsString('src=https://tracker.example/a.js', $result);
    }

    public function test_img_srcset_is_stripped_alongside_src(): void
    {
        $this->services->register(['id' => 'meta-pixel', 'category' => 'marketing', 'domains' => ['facebook.com/tr']]);

        $html = '<img src="https://www.facebook.com/tr?id=1" srcset="https://www.facebook.com/tr?id=1 1x, https://www.facebook.com/tr?id=2 2x" />';
        $result = $this->rewriter->rewrite($html);

        self::assertStringNotContainsString(' srcset="https://www.facebook.com', $result);
        self::assertStringContainsString('data-cmp-srcset="https://www.facebook.com/tr?id=1 1x, https://www.facebook.com/tr?id=2 2x"', $result);
    }

    public function test_own_inline_bootstrap_script_is_never_pattern_matched(): void
    {
        $this->services->register(['id' => 'matomo', 'category' => 'analytics', 'script_patterns' => ['matomo.js']]);

        $html = '<script id="cmp-bootstrap">' . str_repeat('/* consent engine matomo.js noise */', 200) . '</script>';

        self::assertSame($html, $this->rewriter->rewrite($html));
    }
    public function test_independent_srcset_and_exact_attribute_names(): void
    {
        $this->services->register(['id' => 'tracker', 'category' => 'marketing', 'domains' => ['tracker.example']]);
        foreach ([
            '<img src="/local.png" srcset="https://tracker.example/a.png 2x">',
            '<img srcset="https://tracker.example/a.png 2x">',
            '<source srcset="https://tracker.example/a.png 2x">',
            '<script data-src="/local.js" src="https://tracker.example/a.js"></script>',
            '<script data-id="cmp-bootstrap" src="https://tracker.example/a.js"></script>',
            '<img title="data-cmp-category" src="https://tracker.example/a.png">',
            '<script title="a > b src=local" src="https://tracker.example/a.js"></script>',
        ] as $html) {
            $result = $this->rewriter->rewrite($html);
            self::assertStringContainsString('data-cmp-service="tracker"', $result);
            self::assertStringNotContainsString(' srcset=', $result);
        }
    }

}
