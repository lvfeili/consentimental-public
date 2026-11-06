<?php

declare(strict_types=1);

namespace Consentimental\Blocking;

use Consentimental\Services\Service;
use Consentimental\Services\ServiceRegistry;

if (!defined('ABSPATH')) {
	exit;
}

final class DomRewriter
{
	public function __construct(private readonly ServiceRegistry $services)
	{
	}

	public function has_matchable_services(): bool
	{
		return [] !== $this->matchable_services();
	}

	public function rewrite(string $html): string
	{
		$services = $this->matchable_services();

		if ([] === $services) {
			return $html;
		}

		$html = $this->rewrite_scripts($html, $services);
		$html = $this->remove_resource_hints($html, $services);
		$html = $this->rewrite_embed($html, $services, 'iframe');
		$html = $this->rewrite_embed($html, $services, 'img');
		$html = $this->rewrite_embed($html, $services, 'source');

		return $html;
	}

	private function remove_resource_hints(string $html, array $services): string
	{
		return preg_replace_callback(
			'~<link\b((?:[^>\"\']|\"[^\"]*\"|\'[^\']*\')*)>~i',
			function (array $match) use ($services): string {
				$rel = preg_split('/\s+/', strtolower($this->extract_attribute($match[1], 'rel') ?? ''));
				if (!array_intersect($rel, ['preconnect', 'dns-prefetch', 'preload', 'prefetch', 'modulepreload', 'prerender'])) {
					return $match[0];
				}
				$url = ($this->extract_attribute($match[1], 'href') ?? '') . "\n"
					. ($this->extract_attribute($match[1], 'imagesrcset') ?? '');
				if (array_intersect($rel, ['preconnect', 'dns-prefetch'])) {
					$host = parse_url('https://' . ltrim(preg_replace('~^https?://~i', '', trim($url)), '/'), PHP_URL_HOST);
					foreach ($services as $service) {
						foreach ([...$service->domains, ...$service->iframe_patterns, ...$service->script_patterns] as $pattern) {
							$provider_host = parse_url('https://' . ltrim(preg_replace('~^https?://~i', '', $pattern), '/'), PHP_URL_HOST);
							if (is_string($host) && is_string($provider_host) && str_contains($provider_host, '.')
								&& (strtolower($host) === strtolower($provider_host) || str_ends_with(strtolower($host), '.' . strtolower($provider_host)))) {
								return '';
							}
						}
					}
				}
				return null !== $this->match_embed_src($services, $url, true) ? '' : $match[0];
			},
			$html,
		) ?? $html;
	}

	/**
	 * @return Service[]
	 */
	private function matchable_services(): array
	{
		return array_values(array_filter(
			$this->services->all(),
			static fn (Service $service): bool => [] !== $service->domains
				|| [] !== $service->script_patterns
				|| [] !== $service->iframe_patterns,
		));
	}

	/**
	 * @param Service[] $services
	 */
	private function rewrite_scripts(string $html, array $services): string
	{
		$result = preg_replace_callback(
			'~<script\b((?:[^>\"\']|\"[^\"]*\"|\'[^\']*\')*)>(.*?)</script>~is',
			function (array $m) use ($services): string {
				[$full, $attrs, $content] = $m;

				if (null !== $this->extract_attribute($attrs, 'data-cmp-category') || $this->is_own_bootstrap_script($attrs)) {
					return $full;
				}

				$src = $this->extract_attribute($attrs, 'src');
				$service = $this->match_script($services, $src, $content);

				if (null === $service) {
					return $full;
				}

				$original_type = $this->extract_attribute($attrs, 'type');

				$new_attrs = $this->strip_attribute($attrs, 'type')
					. ' type="text/plain" data-cmp-category="' . esc_attr($service->category) . '"'
					. ' data-cmp-service="' . esc_attr($service->id) . '"'
					. (null !== $original_type && '' !== $original_type ? ' data-cmp-original-type="' . esc_attr($original_type) . '"' : '');

				return '<script' . $new_attrs . '>' . $content . '</script>';
			},
			$html,
		);

		return null === $result ? $html : $result;
	}

	/**
	 * @param Service[] $services
	 */
	private function rewrite_embed(string $html, array $services, string $tag): string
	{
		$result = preg_replace_callback(
			'~<' . $tag . '\b((?:[^>\"\']|\"[^\"]*\"|\'[^\']*\')*)>~i',
			function (array $m) use ($services, $tag): string {
				[$full, $attrs] = $m;

				if (null !== $this->extract_attribute($attrs, 'data-cmp-category') || null !== $this->extract_attribute($attrs, 'data-cmp-src')) {
					return $full;
				}

				$src = $this->extract_attribute($attrs, 'src');
				$srcset = in_array($tag, ['img', 'source'], true) ? $this->extract_attribute($attrs, 'srcset') : null;
				$srcdoc = 'iframe' === $tag ? $this->extract_attribute($attrs, 'srcdoc') : null;
				$sources = ($src ?? '') . "\n" . ($srcset ?? '') . "\n" . ($srcdoc ?? '');
				$matched = array_values(array_filter($services, fn (Service $candidate): bool => null !== $this->match_embed_src([$candidate], $sources, 'iframe' === $tag)));
				$service = $matched[0] ?? null;
				if (null === $service) {
					return $full;
				}
				$stripped = $this->strip_attribute($attrs, 'src');
				if (null !== $srcdoc) {
					$stripped = $this->strip_attribute($stripped, 'srcdoc');
				}

				if (null !== $srcset) {
					$stripped = $this->strip_attribute($stripped, 'srcset');
				}

				$self_closing = '';

				if (1 === preg_match('#/\s*$#', $stripped)) {
					$stripped = (string) preg_replace('#/\s*$#', '', $stripped);
					$self_closing = ' /';
				}

				$new_attrs = rtrim($stripped)
					. (count($matched) > 1 ? ' data-cmp-services="' . esc_attr((string) json_encode(array_column($matched, 'id'))) . '"' : '')
					. (null !== $srcdoc ? ' data-cmp-srcdoc="' . esc_attr($srcdoc) . '"' : '')
					. (null !== $src ? ' data-cmp-src="' . esc_attr($src) . '"' : '')
					. (null !== $srcset ? ' data-cmp-srcset="' . esc_attr($srcset) . '"' : '')
					. ' data-cmp-category="' . esc_attr($service->category) . '"'
					. ' data-cmp-service="' . esc_attr($service->id) . '"'
					. $self_closing;

				return '<' . $tag . ' ' . ltrim($new_attrs) . '>';
			},
			$html,
		);

		return null === $result ? $html : $result;
	}

	/**
	 * @param Service[] $services
	 */
	private function match_script(array $services, ?string $src, string $content): ?Service
	{
		foreach ($services as $service) {
			if (null !== $src && PatternMatcher::matches($src, [...$service->domains, ...$service->script_patterns])) {
				return $service;
			}

			if (null === $src && PatternMatcher::matches($content, [...$service->domains, ...$service->script_patterns])) {
				return $service;
			}
		}

		return null;
	}

	/**
	 * @param Service[] $services
	 */
	private function match_embed_src(array $services, string $src, bool $include_iframe_patterns): ?Service
	{
		foreach ($services as $service) {
			$patterns = $include_iframe_patterns
				? [...$service->domains, ...$service->iframe_patterns]
				: $service->domains;

			if (PatternMatcher::matches($src, $patterns)) {
				return $service;
			}
		}

		return null;
	}

	private function is_own_bootstrap_script(string $attrs): bool
	{
		return in_array($this->extract_attribute($attrs, 'id'), ['cmp-bootstrap', 'cmp-consent-mode'], true);
	}

	/**
	 * @return array<int, array<int, array{0: string, 1: int}>>
	 */
	private function attributes(string $attrs): array
	{
		preg_match_all('~\s+([^\s=/>]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+)))?~', $attrs, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL);
		return $matches;
	}

	private function extract_attribute(string $attrs, string $name): ?string
	{
		foreach ($this->attributes($attrs) as $attribute) {
			if (strtolower($attribute[1][0]) !== $name) { continue; }
			return html_entity_decode($attribute[2][0] ?? $attribute[3][0] ?? $attribute[4][0] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
		}
		return null;
	}

	private function strip_attribute(string $attrs, string $name): string
	{
		foreach (array_reverse($this->attributes($attrs)) as $attribute) {
			if (strtolower($attribute[1][0]) === $name) {
				$attrs = substr_replace($attrs, '', $attribute[0][1], strlen($attribute[0][0]));
			}
		}
		return $attrs;
	}
}
