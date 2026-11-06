<?php

declare(strict_types=1);

namespace Consentimental\Frontend;

use Consentimental\Consent\CategoryRepository;
use Consentimental\Consent\ConsentManager;
use Consentimental\Consent\DisclosureRecorder;
use Consentimental\Consent\Settings;
use Consentimental\Integrations\MultilingualStrings;
use Consentimental\Services\ServiceRegistry;

if (!defined('ABSPATH')) {
	exit;
}

final class ConsentBootstrap
{
	public function __construct(
		private readonly CategoryRepository $categories,
		private readonly Settings $settings,
		private readonly ServiceRegistry $services,
		private readonly MultilingualStrings $multilingual,
		private readonly ?BannerContent $content = null,
		private readonly ?DisclosureRecorder $disclosures = null,
		private readonly bool $preview = false,
	) {
	}

	public function register(): void
	{
		add_action('wp_head', $this->print_bootstrap(...), 1);
	}

	private function print_bootstrap(): void
	{
		$multilingual = $this->multilingual;

		$config = [
			'cookieName' => ConsentManager::COOKIE_NAME,
			'restUrl' => esc_url_raw(rest_url('consentimental/v1/consent')),
			'configVersion' => $this->settings->config_version(),
			'legalFramework' => (string) $this->settings->get('legal_framework'),
			'explicitServiceConsent' => $this->settings->enforces_framework(),
			'californiaEnabled' => (bool) $this->settings->get('california_enabled'),
			'privacyUrl' => esc_url_raw(rest_url('consentimental/v1/privacy-choice')),
			'expiryDays' => (int) $this->settings->get('consent_expiry_days'),
			'requireDecisionBeforeInteraction' => (bool) $this->settings->get('require_decision_before_interaction'),
			'reloadOnWithdrawal' => (bool) $this->settings->get('reload_on_withdrawal'),
			'categories' => array_map(
				static fn ($category) => [
					'slug' => $category->slug,
					'label' => $multilingual->category($category)->label,
					'required' => $category->required,
					'defaultEnabled' => $category->default_enabled,
				],
				$this->categories->all(),
			),
			'services' => array_combine(
				array_map(static fn ($service) => $service->id, $this->active_services()),
				array_map(
					static fn ($service) => [
						'category' => $service->category,
						'saleSharing' => $service->sale_sharing,
						'name' => $multilingual->service($service)->name,
						'provider' => $service->provider,
						'cookies' => array_column($service->cookies, 'name'),
						'storageKeys' => $service->storage_keys,
						'privacyPolicyUrl' => $service->privacy_policy_url,
					],
					$this->active_services(),
				),
			),
		];

		if ($this->preview) {
			$config['preview'] = true;
		}

		if (null !== $this->content && null !== $this->disclosures) {
			$config['disclosure'] = $this->disclosures->for_render($this->content->resolve());
		}

		$engine = file_get_contents(\Consentimental\CMP_PLUGIN_DIR . 'assets/js/consent.js');

		echo "<script id=\"cmp-bootstrap\">\n";
		echo 'var cmpConfig = ' . wp_json_encode($config) . ";\n";
		echo $engine ?: '';
		echo "</script>\n";
	}

	/**
	 * @return \Consentimental\Services\Service[]
	 */
	private function active_services(): array
	{
		return array_values(array_filter(
			$this->services->all(),
			static fn ($service) => $service->active,
		));
	}
}
