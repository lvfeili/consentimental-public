<?php


wp_set_current_user(1);

$active = static fn (string $file): bool => in_array($file, (array) get_option('active_plugins', []), true);

if ($active('woocommerce/woocommerce.php')) {
	WC_Install::create_pages();
	update_option('woocommerce_coming_soon', 'no');
	$product = new WC_Product_Simple();
	$product->set_name('E2E Product');
	$product->set_regular_price('9.00');
	$product->set_status('publish');
	$product->save();
	WP_CLI::log('WooCommerce: pages and a product created.');
}

if ($active('elementor/elementor.php')) {
	$id = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Elementor Page', 'post_name' => 'elementor-page', 'post_author' => 1]);
	update_post_meta($id, '_elementor_edit_mode', 'builder');
	update_post_meta($id, '_elementor_template_type', 'wp-page');
	update_post_meta($id, '_elementor_data', wp_slash(wp_json_encode([[
		'id' => 'a1b2c3d', 'elType' => 'container', 'settings' => [], 'elements' => [[
			'id' => 'e4f5a6b', 'elType' => 'widget', 'widgetType' => 'html', 'settings' => [
				'html' => '<p id="cmp-e2e-elementor">Built with Elementor</p><iframe id="cmp-e2e-elementor-video" title="Elementor video" src="https://video.cmp-e2e.test/embed/elementor"></iframe>',
			], 'elements' => [],
		]],
	]])));
	WP_CLI::log("Elementor: page {$id} built with an HTML widget containing an embed.");
}

if ($active('autoptimize/autoptimize.php')) {
	foreach (['autoptimize_js' => 'on', 'autoptimize_js_aggregate' => 'on', 'autoptimize_js_include_inline' => '', 'autoptimize_css' => 'on', 'autoptimize_html' => 'on'] as $name => $value) {
		update_option($name, $value);
	}
	WP_CLI::log('Autoptimize: JS aggregation and HTML/CSS optimisation on.');
}

if ($active('wp-super-cache/wp-cache.php')) {
	global $wp_cache_config_file;
	if (function_exists('wpsc_init')) {
		wpsc_init();
	}
	if (function_exists('wp_cache_enable')) {
		wp_cache_enable();
	}
	if (function_exists('wp_cache_setting')) {
		wp_cache_setting('wp_cache_mod_rewrite', 0);
		wp_cache_setting('cache_compression', 0);
		wp_cache_setting('wp_cache_not_logged_in', 1);
	}
	WP_CLI::log('WP Super Cache: enabled (PHP caching) — WP_CACHE ' . (defined('WP_CACHE') && WP_CACHE ? 'set' : 'pending until next request') . '.');
}

if ($active('polylang/polylang.php') && function_exists('PLL')) {
	$model = PLL()->model;
	foreach ([['English', 'en', 'en_US', 0], ['Deutsch', 'de', 'de_DE', 1]] as [$name, $slug, $locale, $group]) {
		if (!$model->get_language($slug)) {
			$result = $model->add_language(['name' => $name, 'slug' => $slug, 'locale' => $locale, 'rtl' => 0, 'term_group' => $group]);
			if (is_wp_error($result)) {
				WP_CLI::warning('Polylang: ' . $result->get_error_message());
			}
		}
	}
	$options = get_option('polylang');
	$options['force_lang'] = 1;
	$options['hide_default'] = 1;
	$options['rewrite'] = 1;
	update_option('polylang', $options);
	$model->set_language_in_mass('post', array_map('intval', get_posts(['post_type' => 'any', 'numberposts' => -1, 'fields' => 'ids'])), 'en');

	Consentimental\consentimental()->settings()->save(['banner_title' => 'Your privacy choices']);
	$german = $model->get_language('de');
	$mo = new PLL_MO();
	$mo->import_from_db($german);
	$mo->add_entry($mo->make_entry('Your privacy choices', 'Ihre Datenschutz-Einstellungen'));
	$mo->export_to_db($german);
	flush_rewrite_rules();
	WP_CLI::log('Polylang: en + de, German banner title translated.');
}
