<?php

declare(strict_types=1);

namespace Consentimental\Frontend;

if (!defined('ABSPATH')) {
	exit;
}

final class ServicesBlock
{
	public function __construct(private readonly ServicesShortcode $services_shortcode)
	{
	}

	public function register(): void
	{
		$this->register_block();
	}

	private function register_block(): void
	{
		wp_register_script(
			'cmp-services-block',
			\Consentimental\CMP_PLUGIN_URL . 'assets/js/blocks/services-block.js',
			['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-i18n'],
			\Consentimental\CMP_VERSION,
			true,
		);

		register_block_type('consentimental/services', [
			'editor_script' => 'cmp-services-block',
			'render_callback' => fn () => $this->services_shortcode->render(),
		]);
	}
}
