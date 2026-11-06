<?php
add_action('wp_footer', function () {
	echo '<script async src="https://www.googletagmanager.com/gtag/js?id=G-DODTEST"></script>' . "\n";
	echo '<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config","G-DODTEST");</script>' . "\n";
	echo '<script src="https://www.googletagmanager.com/gtm.js?id=GTM-DODTEST"></script>' . "\n";
	echo '<script async src="https://www.googletagmanager.com/gtag/js?id=AW-DODTEST"></script>' . "\n";
	echo '<script src="https://connect.facebook.net/en_US/fbevents.js"></script>' . "\n";
	echo '<iframe id="dod-youtube" width="560" height="315" src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>' . "\n";
	echo '<iframe id="dod-maps" width="600" height="450" src="https://www.google.com/maps/embed?pb=abc123"></iframe>' . "\n";
	echo '<iframe id="dod-vimeo" width="560" height="315" src="https://player.vimeo.com/video/76979871"></iframe>' . "\n";
	echo '<script src="https://www.google.com/recaptcha/api.js"></script>' . "\n";
});

add_action('cmp_register_services', function ($registry) {
	foreach (['google-analytics', 'google-tag-manager', 'google-ads', 'meta-pixel', 'youtube', 'google-maps', 'vimeo', 'recaptcha'] as $id) {
		$service = $registry->get($id);

		if (null !== $service) {
			$registry->apply_override($id, array_merge($service->to_array(), ['active' => true]));
		}
	}
}, 20);
