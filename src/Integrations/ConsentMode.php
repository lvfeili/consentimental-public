<?php

declare(strict_types=1);

namespace Consentimental\Integrations;

use Consentimental\Consent\Settings;

if (!defined('ABSPATH')) {
	exit;
}

final class ConsentMode
{
	private const DEFAULT_CATEGORY_MAP = [
		'functional' => ['functionality_storage', 'personalization_storage'],
		'analytics' => ['analytics_storage'],
		'marketing' => ['ad_storage', 'ad_user_data', 'ad_personalization'],
	];

	public function __construct(private readonly Settings $settings)
	{
	}

	public function is_enabled(): bool
	{
		return (bool) apply_filters('cmp_consent_mode_enabled', true);
	}

	public function type(): string
	{
		$type = apply_filters('cmp_consent_mode_type', (string) $this->settings->get('consent_mode_type'));

		return 'advanced' === $type ? 'advanced' : 'basic';
	}

	public function is_strict_gtm_blocking(): bool
	{
		return 'basic' === $this->type();
	}

	/**
	 * @return array<string, string[]> consent category slug => gtag consent parameter names
	 */
	public function category_map(): array
	{
		return apply_filters('cmp_consent_mode_category_map', self::DEFAULT_CATEGORY_MAP);
	}

	public function register(): void
	{
		if (!$this->is_enabled()) {
			return;
		}

		add_action('wp_head', $this->print_bootstrap(...), 2);
	}

	private function print_bootstrap(): void
	{
		$config = [
			'categoryMap' => $this->category_map(),
			'waitForUpdate' => 'advanced' === $this->type() ? 500 : 0,
		];

		$engine = file_get_contents(\Consentimental\CMP_PLUGIN_DIR . 'assets/js/consent-mode.js');

		echo "<script id=\"cmp-consent-mode\">\n";
		echo 'var cmpConsentModeConfig = ' . wp_json_encode($config) . ";\n";
		echo $engine ?: '';
		echo "</script>\n";
	}
}
