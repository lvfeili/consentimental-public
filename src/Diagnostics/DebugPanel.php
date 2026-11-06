<?php

declare(strict_types=1);

namespace Consentimental\Diagnostics;

if (!defined('ABSPATH')) {
	exit;
}

final class DebugPanel
{
	public function register(): void
	{
		add_action('wp_enqueue_scripts', $this->enqueue(...));
		add_action('wp_footer', $this->render(...));
	}

	private function should_display(): bool
	{
		return (bool) apply_filters('cmp_diagnostics_enabled', current_user_can('manage_options'));
	}

	private function enqueue(): void
	{
		if (!$this->should_display()) {
			return;
		}

		wp_enqueue_style(
			'cmp-diagnostics',
			\Consentimental\CMP_PLUGIN_URL . 'assets/css/diagnostics.css',
			[],
			\Consentimental\CMP_VERSION,
		);

		wp_enqueue_script(
			'cmp-diagnostics',
			\Consentimental\CMP_PLUGIN_URL . 'assets/js/diagnostics.js',
			[],
			\Consentimental\CMP_VERSION,
			true,
		);
	}

	private function render(): void
	{
		if (!$this->should_display()) {
			return;
		}

		require \Consentimental\CMP_PLUGIN_DIR . 'templates/frontend/debug-panel.php';
	}
}
