<?php

declare(strict_types=1);

namespace Consentimental\Tests;

use Consentimental\Frontend\FloatingButtonIcon;
use PHPUnit\Framework\TestCase;

final class FloatingButtonIconTest extends TestCase
{
    public function test_renders_a_bundled_preset_svg(): void
    {
        $html = FloatingButtonIcon::render('gear', '', '');

        self::assertStringContainsString('<svg', $html);
    }

    public function test_preset_svgs_carry_explicit_width_and_height(): void
    {
        foreach (['cookie', 'fingerprint', 'gear'] as $preset) {
            $html = FloatingButtonIcon::render($preset, '', '');

            self::assertMatchesRegularExpression('/<svg\b[^>]*\bwidth="\d/', $html, "$preset is missing an explicit width");
            self::assertMatchesRegularExpression('/<svg\b[^>]*\bheight="\d/', $html, "$preset is missing an explicit height");
        }
    }

    public function test_unknown_preset_falls_back_to_cookie(): void
    {
        self::assertSame(FloatingButtonIcon::render('cookie', '', ''), FloatingButtonIcon::render('nonsense', '', ''));
    }

    public function test_renders_a_custom_emoji(): void
    {
        self::assertStringContainsString('font-size:var(--cmp-floating-emoji-size,20px)!important', FloatingButtonIcon::render('emoji', '🔔', ''));
    }

    public function test_emoji_type_without_a_value_falls_back_to_cookie(): void
    {
        self::assertSame(FloatingButtonIcon::render('cookie', '', ''), FloatingButtonIcon::render('emoji', '  ', ''));
    }

    public function test_fingerprint_has_an_explicit_size_in_its_markup(): void
    {
        self::assertStringContainsString('width:var(--cmp-floating-svg-size,22px)!important', FloatingButtonIcon::render('fingerprint', '', ''));
    }

    public function test_renders_a_custom_image(): void
    {
        self::assertSame(
            '<img src="https://example.test/icon.png" width="24" height="24" alt="" />',
            FloatingButtonIcon::render('image', '', 'https://example.test/icon.png'),
        );
    }

    public function test_image_type_without_a_url_falls_back_to_cookie(): void
    {
        self::assertSame(FloatingButtonIcon::render('cookie', '', ''), FloatingButtonIcon::render('image', '', ''));
    }
}
