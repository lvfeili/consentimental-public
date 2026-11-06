<?php

add_action('cmp_register_services', function ($registry) {
	$registry->register([
		'id' => 'test-tracker',
		'category' => 'analytics',
		'name' => 'Test Tracker',
		'provider' => 'Test Provider',
		'active' => true,
	]);
	$registry->register([
		'id' => 'test-embed',
		'category' => 'marketing',
		'name' => 'Test Embed',
		'provider' => 'Test Provider',
		'domains' => ['test-embed-domain.test'],
		'active' => true,
	]);
});

add_action('wp_enqueue_scripts', function () {
	wp_register_script('cmp-test-external', home_url('/wp-content/mu-plugins/test-external.js'), [], null, true);
	wp_enqueue_script('cmp-test-external');
	cmp_register_script('cmp-test-external', 'test-tracker');

	wp_register_script('cmp-test-inline-carrier', '', [], null, true);
	wp_enqueue_script('cmp-test-inline-carrier');
	wp_add_inline_script('cmp-test-inline-carrier', 'window.cmpTestInlineRan = true;');
	cmp_register_script('cmp-test-inline-carrier', 'marketing');
});

add_action('wp_footer', function () {
	echo '<script src="https://test-embed-domain.test/raw.js"></script>' . "\n";
	echo '<iframe src="https://test-embed-domain.test/embed" width="300" height="150" id="cmp-test-raw-iframe"></iframe>' . "\n";
});
