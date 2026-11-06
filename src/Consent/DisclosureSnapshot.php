<?php

declare(strict_types=1);

namespace Consentimental\Consent;

if (!defined('ABSPATH')) {
	exit;
}

final class DisclosureSnapshot
{
	public const SCHEMA = 1;

	/**
	 * @param array{
	 *     title: string, text: string, customize_title: string, customize_text: string,
	 *     labels: array<string, string>, policy_links: list<array{label: string, url: string}>,
	 *     categories: list<array<string, mixed>>, services: list<array<string, mixed>>,
	 * } $content
	 * @param array{locale: string, code: string, direction: string} $language
	 * @param array<string, scalar> $terms settings that change what a decision means (expiry, Consent Mode)
	 * @return array<string, mixed>
	 */
	public static function build(array $content, int $config_version, array $language, array $terms): array
	{
		$categories = array_map(static fn (array $category): array => [
			'slug' => (string) $category['slug'],
			'label' => (string) $category['label'],
			'description' => (string) ($category['description'] ?? ''),
			'required' => (bool) ($category['required'] ?? false),
		], $content['categories']);

		$services = array_map(static fn (array $service): array => [
			'id' => (string) $service['id'],
			'name' => (string) $service['name'],
			'provider' => (string) ($service['provider'] ?? ''),
			'purpose' => (string) ($service['purpose'] ?? ''),
			'category' => (string) $service['category'],
			'sale_sharing' => (string) ($service['sale_sharing'] ?? 'unreviewed'),
			'privacy_policy_url' => (string) ($service['privacy_policy_url'] ?? ''),
			'cookies' => array_values(array_map(static fn (array $cookie): array => [
				'name' => (string) ($cookie['name'] ?? ''),
				'duration' => (string) ($cookie['duration'] ?? ''),
				'purpose' => (string) ($cookie['purpose'] ?? ''),
			], (array) ($service['cookies'] ?? []))),
		], $content['services']);

		usort($services, static fn (array $a, array $b): int => strcmp($a['id'], $b['id']));

		$labels = array_map('strval', $content['labels']);
		ksort($labels);
		ksort($terms);

		return [
			'schema' => self::SCHEMA,
			'config_version' => $config_version,
			'language' => [
				'locale' => (string) $language['locale'],
				'code' => (string) $language['code'],
				'direction' => 'rtl' === $language['direction'] ? 'rtl' : 'ltr',
			],
			'banner' => [
				'title' => (string) $content['title'],
				'text' => (string) $content['text'],
				'customize_title' => (string) $content['customize_title'],
				'customize_text' => (string) $content['customize_text'],
				'labels' => $labels,
				'policy_links' => array_values(array_map(static fn (array $link): array => [
					'label' => (string) $link['label'],
					'url' => (string) $link['url'],
				], $content['policy_links'])),
			],
			'categories' => array_values($categories),
			'services' => $services,
			'terms' => $terms,
		];
	}

	public static function canonical_json(array $snapshot): string
	{
		return (string) json_encode(self::sort_keys($snapshot), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}

	public static function hash(array $snapshot): string
	{
		return hash('sha256', self::canonical_json($snapshot));
	}

	public static function is_hash(string $value): bool
	{
		return 1 === preg_match('/^[0-9a-f]{64}$/D', $value);
	}

	private static function sort_keys(mixed $value): mixed
	{
		if (!is_array($value)) {
			return $value;
		}

		if (!array_is_list($value)) {
			ksort($value);
		}

		return array_map(self::sort_keys(...), $value);
	}
}
