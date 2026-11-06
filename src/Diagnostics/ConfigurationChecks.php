<?php

declare(strict_types=1);

namespace Consentimental\Diagnostics;

use Consentimental\Consent\Category;
use Consentimental\Scripts\CustomScript;
use Consentimental\Scripts\ProxyRoutes;
use Consentimental\Services\Service;

if (!defined('ABSPATH')) {
	exit;
}

final class ConfigurationChecks
{
	/**
	 * @param Category[] $categories
	 * @param Service[] $services every registered service (active or not)
	 * @param CustomScript[] $scripts
	 * @return Finding[]
	 */
	public static function run(array $categories, array $services, array $scripts, bool $has_policy_link, bool $has_pending_draft, int $config_version, int $now, string $services_url = '', string $scripts_url = '', string $banner_url = '', string $publish_url = ''): array
	{
		$category_slugs = array_map(static fn (Category $category): string => $category->slug, $categories);
		$required_slugs = array_map(static fn (Category $category): string => $category->slug, array_filter($categories, static fn (Category $category): bool => $category->required));
		$active = array_filter($services, static fn (Service $service): bool => $service->active);
		$by_id = [];

		foreach ($services as $service) {
			$by_id[$service->id] = $service;
		}

		$scope = sprintf(
			/* translators: 1: configuration version, 2: active services, 3: scripts */
			__('Published configuration v%1$d: %2$d active services, %3$d scripts', 'consentimental'),
			$config_version,
			count($active),
			count($scripts),
		);

		$findings = [];

		$orphans = array_filter($active, static fn (Service $service): bool => !in_array($service->category, $category_slugs, true));
		$findings[] = [] === $orphans
			? new Finding('service-categories', Finding::AREA_CONFIGURATION, Finding::PASSED, __('Every active service is assigned to an existing category', 'consentimental'), $scope, $now)
			: new Finding(
				'service-categories',
				Finding::AREA_CONFIGURATION,
				Finding::FAILED,
				__('Active services are assigned to a category that does not exist', 'consentimental'),
				$scope,
				$now,
				problem: __('These services point at a deleted or renamed category.', 'consentimental'),
				impact: __('They are not listed in the banner, so visitors cannot see or allow them. They stay blocked.', 'consentimental'),
				action: __('Edit each service and choose an existing category, then publish.', 'consentimental'),
				action_url: $services_url,
				items: self::names($orphans),
				item_urls: self::edit_urls($orphans, $services_url),
			);

		$broken = [];
		$unassigned = [];
		$necessary = [];

		foreach ($scripts as $script) {
			$label = '' !== $script->name ? $script->name : $script->id;

			if (!in_array($script->category, $category_slugs, true)) {
				$broken[] = $label;
			} elseif ('' !== $script->service && (!isset($by_id[$script->service]) || !$by_id[$script->service]->active)) {
				$broken[] = $label;
			} elseif (in_array($script->category, $required_slugs, true)) {
				$necessary[] = $label;
			} elseif ('' === $script->service) {
				$unassigned[] = $label;
			}
		}

		$findings[] = [] === $broken
			? new Finding('script-release', Finding::AREA_CONFIGURATION, Finding::PASSED, __('Every script can be released by a consent choice', 'consentimental'), $scope, $now)
			: new Finding(
				'script-release',
				Finding::AREA_CONFIGURATION,
				Finding::FAILED,
				__('Scripts can never run', 'consentimental'),
				$scope,
				$now,
				problem: __('These scripts are assigned to a category that does not exist, or to a service that is inactive or missing.', 'consentimental'),
				impact: __('No visitor choice can release them, so the integration silently does nothing.', 'consentimental'),
				action: __('Edit each script, pick an existing category and an active service, then publish.', 'consentimental'),
				action_url: $scripts_url,
				items: $broken,
			);

		if ([] !== $unassigned) {
			$findings[] = new Finding(
				'script-service',
				Finding::AREA_CONFIGURATION,
				Finding::WARNING,
				__('Scripts are not assigned to a service', 'consentimental'),
				$scope,
				$now,
				problem: __('These scripts are gated by category only.', 'consentimental'),
				impact: __('The banner does not name the vendor behind them, and visitors can only allow them by allowing the whole category.', 'consentimental'),
				action: __('Assign each script to the service it belongs to (create a custom service if needed), then publish.', 'consentimental'),
				action_url: $scripts_url,
				items: $unassigned,
			);
		}

		if ([] !== $necessary) {
			$findings[] = new Finding(
				'script-necessary',
				Finding::AREA_CONFIGURATION,
				Finding::WARNING,
				__('Scripts run without consent', 'consentimental'),
				$scope,
				$now,
				problem: __('These scripts are in an always-active category, so they load for every visitor before any choice.', 'consentimental'),
				impact: __('That is only appropriate for technology that is strictly necessary for the site to work.', 'consentimental'),
				action: __('Confirm each one is strictly necessary, or move it to an optional category.', 'consentimental'),
				action_url: $scripts_url,
				items: $necessary,
			);
		}

		$incomplete = array_filter($active, static fn (Service $service): bool => '' === trim($service->purpose) || '' === trim($service->privacy_policy_url));
		$findings[] = [] === $incomplete
			? new Finding('service-disclosure', Finding::AREA_CONFIGURATION, Finding::PASSED, __('Every active service has a purpose and a privacy policy link', 'consentimental'), $scope, $now)
			: new Finding(
				'service-disclosure',
				Finding::AREA_CONFIGURATION,
				Finding::WARNING,
				__('Active services are missing a purpose or privacy policy link', 'consentimental'),
				$scope,
				$now,
				problem: __('The banner shows these services without a purpose description or without a link to the provider’s privacy policy.', 'consentimental'),
				impact: __('Visitors get less information than they need to make a specific, informed choice.', 'consentimental'),
				action: __('Edit each service below, fill in its purpose and privacy policy URL, then publish. This check looks at the published configuration, so it clears after publishing.', 'consentimental'),
				action_url: $services_url,
				items: self::names($incomplete),
				item_urls: self::edit_urls($incomplete, $services_url),
			);

		$scripted = array_map(static fn (CustomScript $script): string => $script->service, $scripts);
		$undetectable = array_filter($active, static fn (Service $service): bool => !$service->built_in
			&& [] === array_filter(array_merge($service->domains, $service->script_patterns, $service->iframe_patterns))
			&& !in_array($service->id, $scripted, true));

		if ([] !== $undetectable) {
			$findings[] = new Finding(
				'service-detection',
				Finding::AREA_CONFIGURATION,
				Finding::WARNING,
				__('Custom services have no domains or patterns', 'consentimental'),
				$scope,
				$now,
				problem: __('No domain, script pattern, iframe pattern or managed script is linked to these services.', 'consentimental'),
				impact: __('The plugin cannot recognise their markup, so it cannot block it automatically. The banner still lists them.', 'consentimental'),
				action: __('Add the vendor’s domains, or load the integration through Scripts and assign it to the service.', 'consentimental'),
				action_url: $services_url,
				items: self::names($undetectable),
				item_urls: self::edit_urls($undetectable, $services_url),
			);
		}

		$proxy_problems = [];

		foreach ($scripts as $script) {
			if (!$script->proxy || CustomScript::TYPE_URL !== $script->type) {
				continue;
			}

			$label = '' !== $script->name ? $script->name : $script->id;

			if ('https' !== strtolower((string) parse_url($script->url, PHP_URL_SCHEME)) || '' === (string) parse_url($script->url, PHP_URL_HOST)) {
				/* translators: %s: script name */
				$proxy_problems[] = sprintf(__('%s: script URL must be an absolute https:// URL', 'consentimental'), $label);
			}

			if ('' !== $script->proxy_api_origin && ('https' !== strtolower((string) parse_url($script->proxy_api_origin, PHP_URL_SCHEME)) || '' === (string) parse_url($script->proxy_api_origin, PHP_URL_HOST))) {
				/* translators: %s: script name */
				$proxy_problems[] = sprintf(__('%s: runtime host must be an absolute https:// origin', 'consentimental'), $label);
			}

			try {
				ProxyRoutes::parse($script->proxy_paths);
			} catch (\InvalidArgumentException) {
				/* translators: %s: script name */
				$proxy_problems[] = sprintf(__('%s: allowed runtime endpoints cannot be parsed', 'consentimental'), $label);
			}
		}

		$proxied = array_filter($scripts, static fn (CustomScript $script): bool => $script->proxy && CustomScript::TYPE_URL === $script->type);

		if ([] === $proxied) {
			$findings[] = new Finding('proxy-configuration', Finding::AREA_CONFIGURATION, Finding::NOT_APPLICABLE, __('Proxy configuration', 'consentimental'), __('No published script uses the proxy', 'consentimental'), $now);
		} else {
			$findings[] = [] === $proxy_problems
				? new Finding('proxy-configuration', Finding::AREA_CONFIGURATION, Finding::PASSED, __('Proxy configuration is valid', 'consentimental'), $scope, $now)
				: new Finding(
					'proxy-configuration',
					Finding::AREA_CONFIGURATION,
					Finding::FAILED,
					__('Proxy configuration is invalid', 'consentimental'),
					$scope,
					$now,
					problem: __('The proxy refuses to forward requests for these settings.', 'consentimental'),
					impact: __('The affected integrations will fail to load or to send data once consent is given.', 'consentimental'),
					action: __('Correct the script settings, then publish and check Proxy diagnostics.', 'consentimental'),
					action_url: $scripts_url,
					items: $proxy_problems,
				);
		}

		$duplicates = self::duplicates($scripts);
		$findings[] = [] === $duplicates
			? new Finding('duplicate-integrations', Finding::AREA_CONFIGURATION, Finding::PASSED, __('No duplicate managed scripts found', 'consentimental'), __('Scripts managed by this plugin only; tags hard-coded in themes or other plugins are not compared', 'consentimental'), $now)
			: new Finding(
				'duplicate-integrations',
				Finding::AREA_CONFIGURATION,
				Finding::WARNING,
				__('Possible duplicate integrations', 'consentimental'),
				__('Scripts managed by this plugin only; tags hard-coded in themes or other plugins are not compared', 'consentimental'),
				$now,
				problem: __('Several scripts load the same URL or the same Google tag ID.', 'consentimental'),
				impact: __('The vendor may count page views and events twice.', 'consentimental'),
				action: __('Keep one script per integration and delete or disable the others.', 'consentimental'),
				action_url: $scripts_url,
				items: $duplicates,
			);

		$findings[] = $has_policy_link
			? new Finding('policy-link', Finding::AREA_CONFIGURATION, Finding::PASSED, __('The banner links to a privacy or cookie policy', 'consentimental'), __('Published policy pages', 'consentimental'), $now)
			: new Finding(
				'policy-link',
				Finding::AREA_CONFIGURATION,
				Finding::WARNING,
				__('The banner does not link to a privacy or cookie policy', 'consentimental'),
				__('Published policy pages', 'consentimental'),
				$now,
				problem: __('No published privacy policy page is selected, and no cookie policy page exists.', 'consentimental'),
				impact: __('Visitors cannot reach your full privacy information from the consent banner.', 'consentimental'),
				action: __('Select your privacy policy page in the banner settings.', 'consentimental'),
				action_url: $banner_url,
			);

		if ($has_pending_draft) {
			$findings[] = new Finding(
				'pending-draft',
				Finding::AREA_CONFIGURATION,
				Finding::WARNING,
				__('There are unpublished changes', 'consentimental'),
				__('Draft configuration', 'consentimental'),
				$now,
				problem: __('Categories, services or scripts were changed but not published.', 'consentimental'),
				impact: __('Visitors still see the published configuration. The checks on this page apply to the published configuration only.', 'consentimental'),
				action: __('Review and publish, or discard the changes.', 'consentimental'),
				action_url: $publish_url,
			);
		}

		return $findings;
	}

	/**
	 * @param CustomScript[] $scripts
	 * @return string[] one human-readable line per duplicate key
	 */
	public static function duplicates(array $scripts): array
	{
		$keys = [];

		foreach ($scripts as $script) {
			$label = '' !== $script->name ? $script->name : $script->id;
			$found = [];

			if (CustomScript::TYPE_URL === $script->type && '' !== $script->url) {
				$found[] = strtolower(preg_replace('~^https?://~i', '', rtrim($script->url, '/')));
			}

			if (preg_match_all('/\b(?:G|GT|GTM|AW|DC|UA)-[A-Z0-9]{4,}(?:-\d+)?\b/', $script->url . ' ' . $script->code, $matches)) {
				array_push($found, ...$matches[0]);
			}

			foreach (array_unique($found) as $key) {
				$keys[$key][] = $label;
			}
		}

		$lines = [];

		foreach ($keys as $key => $labels) {
			if (count($labels) > 1) {
				$lines[] = $key . ': ' . implode(', ', $labels);
			}
		}

		return $lines;
	}

	/**
	 * @param Service[] $services
	 * @return string[]
	 */
	private static function names(array $services): array
	{
		return array_values(array_map(static fn (Service $service): string => $service->name, $services));
	}

	/**
	 * @param Service[] $services
	 * @return string[]
	 */
	private static function edit_urls(array $services, string $services_url): array
	{
		if ('' === $services_url) {
			return [];
		}

		return array_values(array_map(
			static fn (Service $service): string => $services_url . '&action=' . ($service->built_in ? 'override' : 'edit') . '&id=' . rawurlencode($service->id),
			$services,
		));
	}
}
