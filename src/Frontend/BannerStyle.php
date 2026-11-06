<?php

declare(strict_types=1);

namespace Consentimental\Frontend;

if (!defined('ABSPATH')) {
	exit;
}

final class BannerStyle
{
	public const POSITIONS = ['bottom_bar', 'top_bar', 'centered_modal'];
	public const BUTTON_SHAPES = ['rounded', 'pill', 'square'];

	private const COLOR_VARS = [
		'banner_color_bg' => '--cmp-color-bg',
		'banner_color_text' => '--cmp-color-text',
		'banner_color_muted' => '--cmp-color-muted',
		'banner_color_border' => '--cmp-color-border',
		'banner_color_primary' => '--cmp-color-primary',
		'banner_color_primary_text' => '--cmp-color-primary-text',
		'banner_color_focus' => '--cmp-color-focus',
	];

	private const BUTTON_RADIUS = [
		'rounded' => null, // Falls through to --cmp-radius, whatever that is.
		'pill' => '999px',
		'square' => '0',
	];

	/**
	 * @param array<string, mixed> $settings the full Settings::all() array
	 */
	public static function css_variables(array $settings): string
	{
		$declarations = [];

		foreach (self::COLOR_VARS as $setting_key => $css_var) {
			$value = (string) ($settings[$setting_key] ?? '');

			if ('' !== $value) {
				$declarations[] = $css_var . ': ' . self::sanitize_color($value) . ';';
			}
		}

		$radius = (string) ($settings['banner_radius'] ?? '');

		if ('' !== $radius) {
			$declarations[] = '--cmp-radius: ' . self::sanitize_px($radius) . ';';
		}

		$font_family = trim((string) ($settings['banner_font_family'] ?? ''));

		if ('' !== $font_family) {
			$declarations[] = '--cmp-font-family: ' . self::sanitize_font_family($font_family) . ';';
		}

		$font_size = (string) ($settings['banner_font_size'] ?? '');

		if ('' !== $font_size) {
			$declarations[] = '--cmp-font-size: ' . self::sanitize_px($font_size) . ';';
		}

		$shape = (string) ($settings['button_shape'] ?? 'rounded');
		$button_radius = self::BUTTON_RADIUS[$shape] ?? null;

		if (null !== $button_radius) {
			$declarations[] = '--cmp-btn-radius: ' . $button_radius . ';';
		}

		$logo_width = (string) ($settings['banner_logo_width'] ?? '');

		if ('' !== $logo_width) {
			$declarations[] = '--cmp-logo-width: ' . self::sanitize_px($logo_width) . ';';
		}

		return [] === $declarations ? '' : ":root {\n\t" . implode("\n\t", $declarations) . "\n}";
	}

	public static function custom_css(array $settings): string
	{
		$css = trim((string) ($settings['custom_css'] ?? ''));

		return '' === $css ? '' : str_ireplace('</style', '<\\/style', $css);
	}

	public static function position(array $settings): string
	{
		$position = (string) ($settings['banner_position'] ?? 'bottom_bar');

		return in_array($position, self::POSITIONS, true) ? $position : 'bottom_bar';
	}

	public static function floating_button_css(array $settings): string
	{
		$rules = [];
		$is_default = true;
		foreach (['desktop', 'mobile'] as $device) {
			$side = (string) ($settings['floating_button_' . $device . '_side'] ?? 'right');
			$side = in_array($side, ['left', 'right'], true) ? $side : 'right';
			$opposite = 'left' === $side ? 'right' : 'left';
			$edge = self::sanitize_px((string) ($settings['floating_button_' . $device . '_edge'] ?? '16'));
			$bottom = self::sanitize_px((string) ($settings['floating_button_' . $device . '_bottom'] ?? '16'));
			$is_default = $is_default && 'right' === $side && '16px' === $edge && '16px' === $bottom;
			$rule = '#cmp-floating-button { ' . $side . ': ' . $edge . '; ' . $opposite . ': auto; bottom: ' . $bottom . '; }';
			$rules[] = 'mobile' === $device ? '@media (max-width: 600px) { ' . $rule . ' }' : $rule;
		}

		return $is_default ? '' : implode("\n", $rules);
	}

	private static function sanitize_color(string $value): string
	{
		return (string) preg_replace('/[^#a-zA-Z0-9(),.%\s-]/', '', $value);
	}

	private static function sanitize_px(string $value): string
	{
		return max(0, (int) $value) . 'px';
	}

	private static function sanitize_font_family(string $value): string
	{
		return (string) preg_replace('/[^a-zA-Z0-9\s,\'"-]/', '', $value);
	}
}
