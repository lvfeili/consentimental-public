<?php

declare(strict_types=1);

namespace Consentimental\Frontend;

use Consentimental\Consent\CategoryRepository;
use Consentimental\Consent\Settings;
use Consentimental\Integrations\MultilingualStrings;
use Consentimental\Services\ServiceRegistry;

if (!defined('ABSPATH')) {
	exit;
}

final class ServicesShortcode
{
	public function __construct(
		private readonly ServiceRegistry $services,
		private readonly CategoryRepository $categories,
		private readonly MultilingualStrings $multilingual,
		private readonly Settings $settings,
	) {
	}

	public function register(): void
	{
		add_shortcode('cmp_services', $this->render(...));

		add_action('wp_enqueue_scripts', $this->register_style(...));
	}

	public function render(): string
	{
		$services = array_map(
			$this->multilingual->service(...),
			array_values(array_filter(
				$this->services->all(),
				static fn ($service) => $service->active,
			)),
		);

		$consent_log_retention_days = (int) $this->settings->get('consent_log_retention_days');
		$consent_log_enabled = (bool) $this->settings->get('consent_log_enabled');
		$category_labels = [];
		foreach ($this->categories->all() as $category) {
			$category_labels[$category->slug] = $this->multilingual->category($category)->label;
		}

		ob_start();
		require \Consentimental\CMP_PLUGIN_DIR . 'templates/frontend/services-table.php';

		return (string) ob_get_clean();
	}

	private function register_style(): void
	{
		$post = get_post();

		if (
			!$post
			|| (!has_shortcode($post->post_content, 'cmp_services') && !has_block('consentimental/services', $post))
		) {
			return;
		}

		wp_enqueue_style(
			'cmp-services-table',
			\Consentimental\CMP_PLUGIN_URL . 'assets/css/services-table.css',
			[],
			\Consentimental\CMP_VERSION,
		);
	}
}
