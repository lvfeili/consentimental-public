<?php

declare(strict_types=1);

namespace Consentimental\Frontend;

use Consentimental\Consent\Settings;

if (!defined('ABSPATH')) {
	exit;
}

final class Banner
{
	public function __construct(
		private readonly Settings $settings,
		private readonly BannerContent $content,
		private readonly bool $preview = false,
	) {
	}

	public function register(): void
	{
		add_action('wp_enqueue_scripts', $this->enqueue_assets(...));
		add_action('wp_footer', $this->render(...));
	}

	private function enqueue_assets(): void
	{
		wp_enqueue_style(
			'cmp-banner',
			\Consentimental\CMP_PLUGIN_URL . 'assets/css/banner.css',
			[],
			\Consentimental\CMP_VERSION,
		);

		wp_enqueue_script(
			'cmp-banner',
			\Consentimental\CMP_PLUGIN_URL . 'assets/js/banner.js',
			[],
			\Consentimental\CMP_VERSION,
			true,
		);
		if ($this->settings->get('california_enabled')) {
			wp_enqueue_script('cmp-california', \Consentimental\CMP_PLUGIN_URL . 'assets/js/california.js', [], \Consentimental\CMP_VERSION, true);
		}
	}

	private function render(): void
	{
		$content = $this->content->resolve();
		$categories = $content['categories'];
		$services_by_category = $content['services_by_category'];
		$floating_button_enabled = (bool) $this->settings->get('floating_button_enabled');
		$banner_logo_url = (string) $this->settings->get('banner_logo_url');
		$floating_button_icon = (string) $this->settings->get('floating_button_icon');
		$floating_button_icon_emoji = (string) $this->settings->get('floating_button_icon_emoji');
		$floating_button_icon_image_url = (string) $this->settings->get('floating_button_icon_image_url');
		$settings_all = $this->settings->all();
		$banner_style_css = BannerStyle::css_variables($settings_all);
		$floating_button_css = BannerStyle::floating_button_css($settings_all);
		$banner_position = BannerStyle::position($settings_all);
		$banner_custom_css = BannerStyle::custom_css($settings_all);
		$preview = $this->preview;

		require \Consentimental\CMP_PLUGIN_DIR . 'templates/frontend/banner.php';
	}
}
