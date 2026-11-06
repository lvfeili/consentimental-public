<?php

declare(strict_types=1);

namespace Consentimental\Frontend;

use Consentimental\Consent\Category;
use Consentimental\Consent\CategoryRepository;
use Consentimental\Consent\Settings;
use Consentimental\Integrations\MultilingualStrings;
use Consentimental\Services\Service;
use Consentimental\Services\ServiceRegistry;

if (!defined('ABSPATH')) {
	exit;
}

final class BannerContent
{
	/**
	 * @var array<string, mixed>|null translated result, memoized per request
	 */
	private ?array $resolved = null;

	public function __construct(
		private readonly CategoryRepository $categories,
		private readonly Settings $settings,
		private readonly ServiceRegistry $services,
		private readonly MultilingualStrings $multilingual,
	) {
	}

	/**
	 * @param bool $translate run admin-entered text through WPML/Polylang. Off for
	 *                        admin-side captures, where the "current language" is
	 *                        the editor's, not a visitor's.
	 * @return array{
	 *     title: string,
	 *     text: string,
	 *     customize_title: string,
	 *     customize_text: string,
	 *     labels: array<string, string>,
	 *     policy_links: list<array{label: string, url: string}>,
	 *     categories: Category[],
	 *     services_by_category: array<string, Service[]>,
	 * }
	 */
	public function resolve(bool $translate = true): array
	{
		if ($translate && null !== $this->resolved) {
			return $this->resolved;
		}

		$text = fn (string $name, string $value, bool $multiline = false): string => $translate
			? $this->multilingual->text($name, $value, $multiline)
			: $value;

		$title = $text('banner_title', (string) $this->settings->get('banner_title'));
		$body = $text('banner_text', (string) $this->settings->get('banner_text'), true);
		$customize_text = $text('customize_text', (string) $this->settings->get('customize_text'), true);

		$categories = $translate
			? array_map($this->multilingual->category(...), $this->categories->all())
			: $this->categories->all();

		$services_by_category = [];

		foreach ($this->services->all() as $service) {
			if ($service->active) {
				$services_by_category[$service->category][] = $translate ? $this->multilingual->service($service) : $service;
			}
		}

		$resolved = [
			'title' => '' !== $title ? $title : __('We value your privacy', 'consentimental'),
			'text' => '' !== $body ? $body : __('We use cookies and similar technologies for necessary site functionality and, with your consent, for functional, analytics and marketing purposes. You can change your choice at any time.', 'consentimental'),
			'customize_title' => __('Privacy settings', 'consentimental'),
			'customize_text' => '' !== $customize_text ? $customize_text : __('Choose which categories of cookies and similar technologies you allow, or select individual services below each category for more specific control. Necessary technologies cannot be switched off as they are required for the site to work.', 'consentimental'),
			'labels' => [
				'reject_all' => __('Reject all', 'consentimental'),
				'customize' => __('Customize', 'consentimental'),
				'accept_all' => __('Accept all', 'consentimental'),
				'save' => __('Save settings', 'consentimental'),
				'always_active' => __('Always active', 'consentimental'),
			],
			'policy_links' => $this->policy_links($translate),
			'categories' => $categories,
			'services_by_category' => $services_by_category,
		];

		if ($this->settings->get('california_enabled')) {
			$resolved['labels'] += [
				'sale_opt_out' => __('Do Not Sell or Share My Personal Information', 'consentimental'),
				'sale_description' => __('You can opt out of sale or sharing for cross-context behavioral advertising. This choice is separate from cookie consent. Accepting cookies will not undo it.', 'consentimental'),
				'sale_confirmed' => __('Sale/sharing opt-out is active for this browser.', 'consentimental'),
				'sale_gpc' => __('Global Privacy Control detected. Sale/sharing opt-out is active for this browser.', 'consentimental'),
				'sale_waiting' => __('Checking privacy signals. Affected services stay blocked until the check completes.', 'consentimental'),
				'sale_error' => __('The server could not confirm your privacy choice. Affected services remain blocked in this browser. Retry or use the privacy rights request page for help.', 'consentimental'),
				'sale_blocked' => __('Some services are unavailable because of your privacy choice or a pending service review.', 'consentimental'),
				'sale_retry' => __('Retry privacy request', 'consentimental'),
			];
		}

		if ($translate) {
			$this->resolved = $resolved;
		}

		return $resolved;
	}

	/**
	 * @return list<array{label: string, url: string}>
	 */
	public function policy_links(bool $translate = true): array
	{
		$links = [];
		$privacy_id = self::privacy_policy_page_id($this->settings);
		$candidates = [
			[$privacy_id, __('Privacy policy', 'consentimental')],
			[(int) $this->settings->get('cookie_policy_page_id'), __('Cookie policy', 'consentimental')],
		];
		if ($this->settings->get('california_enabled')) {
			$candidates[] = [(int) $this->settings->get('privacy_rights_page_id'), __('Privacy rights requests', 'consentimental')];
		}

		foreach ($candidates as [$page_id, $label]) {
			$page_id = $translate ? $this->multilingual->page_id($page_id) : $page_id;

			if ($page_id <= 0 || 'publish' !== get_post_status($page_id)) {
				continue;
			}

			$url = get_permalink($page_id);

			if (is_string($url) && '' !== $url && !in_array($url, array_column($links, 'url'), true)) {
				$links[] = ['label' => $label, 'url' => $url];
			}
		}

		return $links;
	}

	public static function has_published_policy(Settings $settings): bool
	{
		foreach ([self::privacy_policy_page_id($settings), (int) $settings->get('cookie_policy_page_id')] as $page_id) {
			if ($page_id > 0 && 'publish' === get_post_status($page_id)) {
				return true;
			}
		}

		return false;
	}

	public static function privacy_policy_page_id(Settings $settings): int
	{
		$chosen = (int) $settings->get('privacy_policy_page_id');

		return $chosen > 0 ? $chosen : (int) get_option('wp_page_for_privacy_policy', 0);
	}
}
