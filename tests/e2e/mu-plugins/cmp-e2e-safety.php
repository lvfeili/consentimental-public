<?php

declare(strict_types=1);

add_filter('pre_wp_mail', static function ($short_circuit, array $attributes) {
	$sent = get_option('cmp_e2e_mail', []);
	$sent[] = ['to' => $attributes['to'], 'subject' => $attributes['subject']];
	update_option('cmp_e2e_mail', $sent, false);

	return true;
}, 10, 2);

add_filter('pre_http_request', static function ($preempt, array $args, string $url) {
	$host = (string) parse_url($url, PHP_URL_HOST);

	if (defined('WP_CLI') && WP_CLI && preg_match('/(^|\.)wordpress\.org$/', $host)) {
		return $preempt;
	}

	if (!in_array($host, ['localhost', '127.0.0.1'], true)) {
		return new WP_Error('cmp_e2e_blocked', 'External HTTP is blocked in the e2e environment: ' . $host);
	}

	$port = (int) parse_url($url, PHP_URL_PORT);

	if ($port > 0 && 80 !== $port) {
		$args['headers'] = array_merge((array) ($args['headers'] ?? []), ['Host' => $host . ':' . $port]);

		return wp_remote_request(preg_replace('~^(https?://[^/:]+):\d+~', '$1', $url), $args);
	}

	return $preempt;
}, 10, 3);

add_filter('pre_site_transient_update_core', '__return_null');
add_filter('pre_site_transient_update_plugins', '__return_null');
add_filter('pre_site_transient_update_themes', '__return_null');
add_action('init', static function (): void {
	foreach (['_maybe_update_core', '_maybe_update_plugins', '_maybe_update_themes'] as $callback) {
		remove_action('admin_init', $callback);
	}

	remove_action('wp_version_check', 'wp_version_check');
	remove_action('wp_update_plugins', 'wp_update_plugins');
	remove_action('wp_update_themes', 'wp_update_themes');
	remove_action('admin_init', 'wp_check_browser_version');
}, 1);
add_filter('pre_http_request', static fn ($preempt, array $args, string $url) => str_contains($url, 'api.wordpress.org') && !(defined('WP_CLI') && WP_CLI) ? new WP_Error('cmp_e2e_blocked', 'blocked') : $preempt, 5, 3);

if (isset($_GET['cmp_e2e_rtl'])) {
	add_action('init', static function (): void {
		$GLOBALS['wp_locale']->text_direction = 'rtl';
	}, 0);
}

add_filter('cmp_consent_rate_limit', static fn (): int => 10000);
