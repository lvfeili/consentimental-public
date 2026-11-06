<?php

declare(strict_types=1);

namespace Consentimental\Frontend;

if (!defined('ABSPATH')) {
	exit;
}

final class Shortcodes
{
	public function register(): void
	{
		add_shortcode('cmp_privacy_settings', [self::class, 'render_privacy_settings']);
	}

	/**
	 * @param array<string, mixed>|string $atts
	 */
	public static function render_privacy_settings($atts): string
	{
		$atts = shortcode_atts(['text' => __('Privacy settings', 'consentimental')], $atts, 'cmp_privacy_settings');

		return \cmp_privacy_settings_link(['text' => (string) $atts['text']]);
	}
}
