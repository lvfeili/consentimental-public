<?php

declare(strict_types=1);

namespace Consentimental\Frontend;

if (!defined('ABSPATH')) {
	exit;
}

final class FloatingButtonIcon
{
	private const PRESETS = ['cookie', 'fingerprint', 'gear'];

	public static function render(string $icon, string $emoji, string $image_url): string
	{
		if ('image' === $icon && '' !== $image_url) {
			return '<img src="' . esc_url($image_url) . '" width="24" height="24" alt="" />';
		}

		if ('emoji' === $icon && '' !== trim($emoji)) {
			return '<span aria-hidden="true" style="font-size:var(--cmp-floating-emoji-size,20px)!important;line-height:1!important;transform:none!important">' . esc_html($emoji) . '</span>';
		}

		$svg = self::svg(in_array($icon, self::PRESETS, true) ? $icon : 'cookie');

		return '' !== $svg ? $svg : '<span aria-hidden="true" style="font-size:var(--cmp-floating-emoji-size,20px)!important;line-height:1!important;transform:none!important">🍪</span>';
	}

	private static function svg(string $preset): string
	{
		static $cache = [];

		if (array_key_exists($preset, $cache)) {
			return $cache[$preset];
		}

		$path = \Consentimental\CMP_PLUGIN_DIR . 'assets/icons/' . $preset . '.svg';
		$contents = is_file($path) ? file_get_contents($path) : false;

		return $cache[$preset] = false !== $contents
			? preg_replace('/^<svg\s/', '<svg style="width:var(--cmp-floating-svg-size,22px)!important;height:var(--cmp-floating-svg-size,22px)!important;min-width:var(--cmp-floating-svg-size,22px)!important;min-height:var(--cmp-floating-svg-size,22px)!important;max-width:var(--cmp-floating-svg-size,22px)!important;max-height:var(--cmp-floating-svg-size,22px)!important;flex:none!important;transform:none!important" ', trim($contents), 1)
			: '';
	}
}
