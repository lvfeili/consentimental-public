<?php

declare(strict_types=1);

namespace Consentimental\Diagnostics;

use Consentimental\Blocking\PatternMatcher;
use Consentimental\Services\Service;
use Consentimental\Services\ServiceRegistry;

if (!defined('ABSPATH')) {
	exit;
}

final class SiteScanner
{
	private const MAX_PAGES = 40;
	private const REQUEST_TIMEOUT = 5;

	public function __construct(private readonly ServiceRegistry $services)
	{
	}

	/**
	 * @return array{
	 *     pages_scanned: int,
	 *     matched: array<string, array{service: Service, pages: string[]}>,
	 *     unknown_domains: array<string, string[]>,
	 * }
	 */
	public function scan(): array
	{
		$urls = $this->collect_urls();
		$services = $this->services->all();
		$own_host = (string) parse_url(home_url(), PHP_URL_HOST);

		$matched = [];
		$unknown_domains = [];

		foreach ($urls as $url) {
			$html = $this->fetch($url);

			if (null === $html) {
				continue;
			}

			foreach ($services as $service) {
				$patterns = array_merge($service->domains, $service->script_patterns, $service->iframe_patterns);

				if ([] === $patterns || !PatternMatcher::matches($html, $patterns)) {
					continue;
				}

				$matched[$service->id]['service'] = $service;
				$matched[$service->id]['pages'][] = $url;
			}

			foreach (self::external_hosts($html, $own_host) as $host) {
				if (self::matches_any_service($host, $services)) {
					continue;
				}

				$unknown_domains[$host][] = $url;
			}
		}

		foreach ($matched as &$entry) {
			$entry['pages'] = array_slice(array_values(array_unique($entry['pages'])), 0, 5);
		}

		unset($entry);

		foreach ($unknown_domains as &$pages) {
			$pages = array_slice(array_values(array_unique($pages)), 0, 5);
		}

		unset($pages);

		ksort($unknown_domains);

		return [
			'pages_scanned' => count($urls),
			'matched' => $matched,
			'unknown_domains' => $unknown_domains,
		];
	}

	/**
	 * @return string[]
	 */
	private function collect_urls(): array
	{
		$urls = [home_url('/')];

		foreach (get_post_types(['public' => true], 'names') as $post_type) {
			$ids = get_posts([
				'post_type' => $post_type,
				'post_status' => 'publish',
				'posts_per_page' => self::MAX_PAGES,
				'fields' => 'ids',
				'orderby' => 'modified',
				'order' => 'DESC',
			]);

			foreach ($ids as $id) {
				$permalink = get_permalink($id);

				if (is_string($permalink)) {
					$urls[] = $permalink;
				}
			}
		}

		return array_slice(array_values(array_unique($urls)), 0, self::MAX_PAGES);
	}

	private function fetch(string $url): ?string
	{
		$response = wp_remote_get($url, ['timeout' => self::REQUEST_TIMEOUT]);

		if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
			return null;
		}

		$body = wp_remote_retrieve_body($response);

		return '' !== $body ? $body : null;
	}

	/**
	 * @return string[]
	 */
	public static function external_hosts(string $html, string $own_host): array
	{
		if (0 === preg_match_all('/<(?:script|iframe|img)\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1/is', $html, $matches)) {
			return [];
		}

		$hosts = [];

		foreach ($matches[2] as $src) {
			$url = str_starts_with($src, '//') ? 'https:' . $src : $src;
			$host = parse_url($url, PHP_URL_HOST);

			if (!is_string($host) || '' === $host || '' !== $own_host && $host === $own_host) {
				continue;
			}

			$hosts[$host] = true;
		}

		return array_keys($hosts);
	}

	/**
	 * @param Service[] $services
	 */
	public static function matches_any_service(string $host, array $services): bool
	{
		foreach ($services as $service) {
			$patterns = array_merge($service->domains, $service->script_patterns, $service->iframe_patterns);

			if ([] !== $patterns && PatternMatcher::matches($host, $patterns)) {
				return true;
			}
		}

		return false;
	}
}
