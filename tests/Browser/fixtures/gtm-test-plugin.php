<?php

add_action('wp_footer', function () {
	echo '<script src="https://www.googletagmanager.com/gtm.js?id=GTM-PWTEST"></script>' . "\n";
});

add_action('cmp_register_services', function ($registry) {
	$service = $registry->get('google-tag-manager');

	if (null !== $service) {
		$registry->apply_override('google-tag-manager', array_merge($service->to_array(), ['active' => true]));
	}
}, 20);
