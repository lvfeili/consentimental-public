<?php

declare(strict_types=1);

namespace Consentimental\Tests;

use Consentimental\Consent\Settings;
use Consentimental\Frontend\BannerStyle;
use PHPUnit\Framework\TestCase;

final class BannerStyleTest extends TestCase
{
    public function test_no_overrides_produces_no_css(): void
    {
        self::assertSame('', BannerStyle::css_variables(Settings::defaults()));
    }

    public function test_a_single_color_override_is_emitted(): void
    {
        $css = BannerStyle::css_variables(array_merge(Settings::defaults(), ['banner_color_bg' => '#123456']));

        self::assertStringContainsString('--cmp-color-bg: #123456;', $css);
        self::assertStringNotContainsString('--cmp-color-text', $css);
    }

    public function test_radius_and_font_size_are_emitted_in_pixels(): void
    {
        $css = BannerStyle::css_variables(array_merge(Settings::defaults(), [
            'banner_radius' => '12',
            'banner_font_size' => '18',
        ]));

        self::assertStringContainsString('--cmp-radius: 12px;', $css);
        self::assertStringContainsString('--cmp-font-size: 18px;', $css);
    }

    public function test_button_shape_pill_sets_button_radius(): void
    {
        $css = BannerStyle::css_variables(array_merge(Settings::defaults(), ['button_shape' => 'pill']));

        self::assertStringContainsString('--cmp-btn-radius: 999px;', $css);
    }

    public function test_button_shape_square_sets_zero_radius(): void
    {
        $css = BannerStyle::css_variables(array_merge(Settings::defaults(), ['button_shape' => 'square']));

        self::assertStringContainsString('--cmp-btn-radius: 0;', $css);
    }

    public function test_button_shape_rounded_emits_no_override(): void
    {
        $css = BannerStyle::css_variables(array_merge(Settings::defaults(), ['button_shape' => 'rounded']));

        self::assertStringNotContainsString('--cmp-btn-radius', $css);
    }

    public function test_font_family_is_sanitized(): void
    {
        $css = BannerStyle::css_variables(array_merge(Settings::defaults(), [
            'banner_font_family' => 'Georgia, serif</style><script>alert(1)</script>',
        ]));

        self::assertStringNotContainsString('<script>', $css);
        self::assertStringContainsString('Georgia, serif', $css);
    }

    public function test_invalid_position_falls_back_to_bottom_bar(): void
    {
        self::assertSame('bottom_bar', BannerStyle::position(['banner_position' => 'nonsense']));
    }

    public function test_valid_position_is_kept(): void
    {
        self::assertSame('top_bar', BannerStyle::position(['banner_position' => 'top_bar']));
    }

    public function test_logo_width_is_emitted_in_pixels(): void
    {
        $css = BannerStyle::css_variables(array_merge(Settings::defaults(), ['banner_logo_width' => '220']));

        self::assertStringContainsString('--cmp-logo-width: 220px;', $css);
    }

    public function test_floating_button_positions_are_independent_by_viewport(): void
    {
        $css = BannerStyle::floating_button_css(array_merge(Settings::defaults(), [
            'floating_button_desktop_side' => 'left',
            'floating_button_desktop_edge' => '32',
            'floating_button_desktop_bottom' => '24',
            'floating_button_mobile_side' => 'right',
            'floating_button_mobile_edge' => '8',
            'floating_button_mobile_bottom' => '64',
        ]));

        self::assertStringContainsString('#cmp-floating-button { left: 32px; right: auto; bottom: 24px; }', $css);
        self::assertStringContainsString('@media (max-width: 600px) { #cmp-floating-button { right: 8px; left: auto; bottom: 64px; } }', $css);
    }

    public function test_default_floating_button_position_needs_no_override(): void
    {
        self::assertSame('', BannerStyle::floating_button_css(Settings::defaults()));
    }

    public function test_floating_button_position_rejects_invalid_values(): void
    {
        $css = BannerStyle::floating_button_css([
            'floating_button_desktop_side' => 'center',
            'floating_button_desktop_edge' => '12px; color:red',
            'floating_button_desktop_bottom' => '-10',
        ]);

        self::assertStringContainsString('right: 12px; left: auto; bottom: 0px;', $css);
        self::assertStringNotContainsString('color:red', $css);
    }

    public function test_no_custom_css_is_empty(): void
    {
        self::assertSame('', BannerStyle::custom_css(Settings::defaults()));
    }

    public function test_custom_css_is_passed_through(): void
    {
        $css = BannerStyle::custom_css(array_merge(Settings::defaults(), [
            'custom_css' => '.cmp-banner__panel { border: 2px solid red; }',
        ]));

        self::assertSame('.cmp-banner__panel { border: 2px solid red; }', $css);
    }

    public function test_custom_css_cannot_break_out_of_the_style_tag(): void
    {
        $css = BannerStyle::custom_css(array_merge(Settings::defaults(), [
            'custom_css' => 'a { color: red; } </style><script>alert(1)</script>',
        ]));

        self::assertStringNotContainsString('</style>', $css);
    }
}
