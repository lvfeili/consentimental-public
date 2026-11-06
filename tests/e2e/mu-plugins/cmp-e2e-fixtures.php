<?php

declare(strict_types=1);

add_action('cmp_register_services', static function ($registry): void {
	$registry->register([
		'id' => 'e2e-analytics', 'name' => 'E2E Analytics', 'provider' => 'E2E Stats Ltd',
		'purpose' => 'Counts page views',
		'category' => (string) get_option('cmp_e2e_analytics_category', 'analytics'),
		'privacy_policy_url' => 'https://analytics.cmp-e2e.test/privacy',
		'domains' => ['analytics.cmp-e2e.test'],
		'cookies' => [['name' => '_e2e_a', 'duration' => '1 year', 'purpose' => 'Visitor id']],
		'storage_keys' => ['e2e_a'],
		'active' => true,
	]);
	$registry->register([
		'id' => 'e2e-ads', 'name' => 'E2E Ads', 'provider' => 'E2E Ads Inc',
		'purpose' => 'Measures ad conversions', 'category' => 'marketing',
		'privacy_policy_url' => 'https://ads.cmp-e2e.test/privacy',
		'domains' => ['ads.cmp-e2e.test'],
		'cookies' => [['name' => '_e2e_m', 'duration' => '90 days', 'purpose' => 'Click id']],
		'storage_keys' => ['e2e_m'],
		'active' => true,
	]);
	$registry->register([
		'id' => 'e2e-video', 'name' => 'E2E Video', 'provider' => 'E2E Video Co',
		'purpose' => 'Embedded videos', 'category' => 'functional',
		'privacy_policy_url' => 'https://video.cmp-e2e.test/privacy',
		'domains' => ['video.cmp-e2e.test'],
		'iframe_patterns' => ['video.cmp-e2e.test'],
		'active' => true,
	]);
	$registry->register([
		'id' => 'e2e-widget', 'name' => 'E2E Chat Widget', 'provider' => 'E2E Chat',
		'purpose' => 'Live chat', 'category' => 'functional',
		'domains' => ['widget.cmp-e2e.test'],
		'active' => false,
	]);

	if (get_option('cmp_e2e_extra_service')) {
		$registry->register([
			'id' => 'e2e-late', 'name' => 'E2E Late Addition', 'provider' => 'E2E Late',
			'purpose' => 'Added after launch', 'category' => 'analytics',
			'domains' => ['late.cmp-e2e.test'], 'active' => true,
		]);
	}
});

add_action('wp_head', static function (): void {
	if (is_admin()) {
		return;
	}

	echo '<script src="https://analytics.cmp-e2e.test/a.js"></script>' . "\n";
	echo '<script src="https://ads.cmp-e2e.test/m.js"></script>' . "\n";
	echo '<script src="https://widget.cmp-e2e.test/w.js"></script>' . "\n";

	if (get_option('cmp_e2e_extra_service')) {
		echo '<script src="https://late.cmp-e2e.test/l.js"></script>' . "\n";
	}
}, 20);

add_action('wp_footer', static function (): void {
	echo '<main id="cmp-e2e-content"><iframe id="cmp-e2e-video" title="E2E video" width="320" height="180" src="https://video.cmp-e2e.test/embed/1"></iframe></main>';
}, 5);

add_action('rest_api_init', static function (): void {
	if (!get_option('cmp_e2e_disposable')) {
		return;
	}

	register_rest_route('cmp-e2e/v1', '/php-log', [
		'methods' => 'GET',
		'permission_callback' => static fn (WP_REST_Request $request): bool => 'disposable' === $request->get_header('x-cmp-e2e'),
		'callback' => static function (): array {
			$lines = is_readable('/tmp/wp-debug.log') ? (array) file('/tmp/wp-debug.log', FILE_IGNORE_NEW_LINES) : [];

			return array_values(array_filter($lines, static fn (string $line): bool => str_contains($line, '/plugins/consentimental/')));
		},
	]);

	register_rest_route('cmp-e2e/v1', '/env', [
		'methods' => 'GET',
		'permission_callback' => static fn (WP_REST_Request $request): bool => 'disposable' === $request->get_header('x-cmp-e2e'),
		'callback' => static fn (): array => [
			'wordpress' => get_bloginfo('version'),
			'php' => PHP_VERSION,
			'plugins' => array_values(array_map(static fn (string $file): string => dirname($file), (array) get_option('active_plugins', []))),
		],
	]);

	register_rest_route('cmp-e2e/v1', '/state', [
		'methods' => 'POST',
		'permission_callback' => static fn (WP_REST_Request $request): bool => 'disposable' === $request->get_header('x-cmp-e2e'),
		'callback' => static function (WP_REST_Request $request): array {
			$allowed = ['cmp_e2e_extra_service', 'cmp_e2e_analytics_category'];

			foreach ((array) $request->get_param('set') as $name => $value) {
				if (in_array($name, $allowed, true)) {
					update_option($name, sanitize_key((string) $value));
				}
			}

			foreach ((array) $request->get_param('delete') as $name) {
				if (in_array($name, $allowed, true)) {
					delete_option($name);
				}
			}

			if ($request->get_param('banner_text') !== null) {
				Consentimental\consentimental()->settings()->save(['banner_text' => sanitize_textarea_field((string) $request->get_param('banner_text'))]);
			}

			if ($request->get_param('reset')) {
				foreach (['cmp_service_overrides', 'cmp_service_overrides_draft', 'cmp_custom_services_draft', 'cmp_categories_draft', 'cmp_custom_scripts_draft', 'cmp_draft_meaningful', 'cmp_verifications', 'cmp_setup'] as $option) {
					delete_option($option);
				}
				Consentimental\consentimental()->settings()->save(['banner_title' => '', 'banner_text' => '', 'privacy_policy_page_id' => 0]);
			}

			if ($request->get_param('banner_title') !== null) {
				Consentimental\consentimental()->settings()->save(['banner_title' => sanitize_text_field((string) $request->get_param('banner_title'))]);
			}

			if ($request->get_param('require_decision') !== null) {
				Consentimental\consentimental()->settings()->save(['require_decision_before_interaction' => (bool) $request->get_param('require_decision')]);
			}

			$version = $request->get_param('bump')
				? Consentimental\consentimental()->settings()->bump_config_version()
				: Consentimental\consentimental()->settings()->config_version();

			return ['config_version' => $version];
		},
	]);
});
