<?php

declare(strict_types=1);

namespace Consentimental\Consent;

use Closure;
use Consentimental\Frontend\BannerContent;
use Consentimental\Integrations\MultilingualStrings;
use Consentimental\Services\Service;

if (!defined('ABSPATH')) {
	exit;
}

final class DisclosureRecorder
{
	private ?string $pending_source = null;

	/**
	 * @param Closure(): BannerContent $fresh_content builds banner content from a
	 *        freshly loaded live registry — the request-scoped one still holds the
	 *        pre-publish built-in overrides when a publish happens mid-request.
	 */
	public function __construct(
		private readonly DisclosureSnapshotRepository $snapshots,
		private readonly Settings $settings,
		private readonly MultilingualStrings $multilingual,
		private readonly Closure $fresh_content,
	) {
	}

	public function register(): void
	{
		add_action('cmp_config_published', fn () => $this->schedule(DisclosureSnapshotRepository::SOURCE_PUBLISH));
		add_action('cmp_settings_changed', fn () => $this->schedule(DisclosureSnapshotRepository::SOURCE_SETTINGS));
	}

	private function schedule(string $source): void
	{
		if (null === $this->pending_source) {
			add_action('shutdown', fn () => $this->capture_site_default((string) $this->pending_source));
		}

		if (DisclosureSnapshotRepository::SOURCE_PUBLISH === $source || null === $this->pending_source) {
			$this->pending_source = $source;
		}
	}

	public function capture_site_default(string $source): string
	{
		$switched = function_exists('switch_to_locale') && switch_to_locale(get_locale());

		try {
			$code = (string) apply_filters('wpml_default_language', '');

			if ('' === $code && function_exists('pll_default_language')) {
				$code = (string) pll_default_language('slug');
			}

			$language = ['locale' => (string) get_locale(), 'code' => sanitize_key($code), 'direction' => is_rtl() ? 'rtl' : 'ltr'];

			return $this->store(($this->fresh_content)()->resolve(translate: false), $language, $source);
		} finally {
			if ($switched) {
				restore_previous_locale();
			}
		}
	}

	/**
	 * @param array<string, mixed> $content a BannerContent::resolve() result
	 * @return array{id: string, language: array{locale: string, code: string, direction: string}}
	 */
	public function for_render(array $content): array
	{
		$language = $this->multilingual->current_language();

		return ['id' => $this->store($content, $language, DisclosureSnapshotRepository::SOURCE_RENDER), 'language' => $language];
	}

	/**
	 * @param array<string, mixed> $content
	 * @param array{locale: string, code: string, direction: string} $language
	 */
	private function store(array $content, array $language, string $source): string
	{
		$version = $this->settings->config_version();

		$services = [];

		foreach ($content['services_by_category'] as $category_services) {
			foreach ($category_services as $service) {
				/**
				 * @var Service $service
				 */
				$services[] = $service->to_array();
			}
		}

		$snapshot = DisclosureSnapshot::build(
			[
				'title' => $content['title'],
				'text' => $content['text'],
				'customize_title' => $content['customize_title'],
				'customize_text' => $content['customize_text'],
				'labels' => $content['labels'],
				'policy_links' => $content['policy_links'],
				'categories' => array_map(static fn (Category $category): array => $category->to_array(), $content['categories']),
				'services' => $services,
			],
			$version,
			$language,
			[
				'consent_expiry_days' => (int) $this->settings->get('consent_expiry_days'),
				'consent_mode_type' => (string) $this->settings->get('consent_mode_type'),
				'legal_framework' => (string) $this->settings->get('legal_framework'),
				'california_enabled' => (bool) $this->settings->get('california_enabled'),
			],
		);

		return $this->snapshots->ensure($snapshot, $source, $this->settings->published_at($version));
	}
}
