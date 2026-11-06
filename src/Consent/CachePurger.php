<?php

declare(strict_types=1);

namespace Consentimental\Consent;

if (!defined('ABSPATH')) {
	exit;
}

final class CachePurger
{
	public function register(): void
	{
		add_action('cmp_config_published', $this->purge(...));
		add_action('cmp_settings_changed', $this->purge(...));
	}

	private function purge(): void
	{
		if (function_exists('rocket_clean_domain')) {
			rocket_clean_domain();
		}

		if (class_exists('LiteSpeed\Purge') || defined('LSCWP_V')) {
			do_action('litespeed_purge_all');
		}

		if (function_exists('w3tc_flush_all')) {
			w3tc_flush_all();
		}

		if (function_exists('wp_cache_clear_cache')) {
			wp_cache_clear_cache();
		}
	}
}
