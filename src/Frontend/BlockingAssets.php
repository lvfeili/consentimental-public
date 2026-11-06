<?php

declare(strict_types=1);

namespace Consentimental\Frontend;

if (!defined('ABSPATH')) {
	exit;
}

final class BlockingAssets
{
	public function register(): void
	{
		add_action('wp_enqueue_scripts', $this->enqueue(...));
	}

	private function enqueue(): void
	{
		wp_enqueue_style(
			'cmp-blocking',
			\Consentimental\CMP_PLUGIN_URL . 'assets/css/blocking.css',
			[],
			\Consentimental\CMP_VERSION,
		);

		wp_enqueue_script(
			'cmp-blocking',
			\Consentimental\CMP_PLUGIN_URL . 'assets/js/blocking.js',
			[],
			\Consentimental\CMP_VERSION,
			true,
		);

		wp_localize_script('cmp-blocking', 'cmpBlockingL10n', [
			/* translators: 1: content provider name, 2: consent category label */
			'contentByProviderRequiresConsent' => __('This content is provided by %1$s and requires %2$s consent.', 'consentimental'),
			/* translators: %1$s: consent category label */
			'contentRequiresConsent' => __('This content requires %1$s consent.', 'consentimental'),
			'contentRequiresConsentGeneric' => __('This content requires consent.', 'consentimental'),
			'privacyBlocked' => __('This service is unavailable because of your privacy choice or a pending privacy review. Open privacy settings for details.', 'consentimental'),
			'multipleProviders' => __('This content uses multiple services. Review and allow them in privacy settings to load it.', 'consentimental'),
			/* translators: %1$s: service name */
			'enableService' => __('Allow %1$s and load content', 'consentimental'),
			/* translators: %1$s: provider name */
			'connectionNotice' => __('Allowing this service connects your browser to %1$s, sharing your IP address and potentially other data. Your choice applies to this service across this site until it expires or you withdraw it in privacy settings.', 'consentimental'),
			'openPrivacySettings' => __('Open privacy settings', 'consentimental'),
			/* translators: %1$s: provider name, e.g. "Google" */
			'providerPrivacyPolicy' => __('%1$s privacy policy', 'consentimental'),
		]);

		wp_enqueue_script(
			'cmp-cleanup',
			\Consentimental\CMP_PLUGIN_URL . 'assets/js/cleanup.js',
			[],
			\Consentimental\CMP_VERSION,
			true,
		);
	}
}
