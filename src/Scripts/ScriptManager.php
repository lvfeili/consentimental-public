<?php

declare(strict_types=1);

namespace Consentimental\Scripts;

if (!defined('ABSPATH')) {
	exit;
}

final class ScriptManager
{
	public function __construct(private readonly CustomScriptRepository $scripts)
	{
	}

	public function register(): void
	{
		add_action('wp_head', fn () => $this->output(CustomScript::PLACEMENT_HEAD), 50);
		add_action('wp_body_open', fn () => $this->output(CustomScript::PLACEMENT_BODY_START), 10);
		add_action('wp_footer', fn () => $this->output(CustomScript::PLACEMENT_FOOTER), 50);
	}

	private function output(string $placement): void
	{
		foreach ($this->scripts->all() as $script) {
			if ($script->placement !== $placement) {
				continue;
			}

			if (!self::condition_matches($script, $this->current_request_uri(), $this->current_post_type())) {
				continue;
			}

			$proxy_base_url = $script->proxy ? ScriptProxy::base_url($script->id) : null;

			echo self::render_tag($script, $proxy_base_url);
		}
	}

	private function current_request_uri(): string
	{
		return is_string($_SERVER['REQUEST_URI'] ?? null) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
	}

	private function current_post_type(): ?string
	{
		$post_type = get_post_type();

		return false !== $post_type ? $post_type : null;
	}

	public static function condition_matches(CustomScript $script, string $request_uri, ?string $post_type): bool
	{
		return match ($script->condition_type) {
			CustomScript::CONDITION_WHOLE_SITE => true,
			CustomScript::CONDITION_URL => rtrim($request_uri, '/') === rtrim($script->condition_value, '/'),
			CustomScript::CONDITION_URL_PATTERN => '' !== $script->condition_value
				&& fnmatch($script->condition_value, $request_uri),
			CustomScript::CONDITION_POST_TYPE => null !== $post_type && $post_type === $script->condition_value,
			default => false,
		};
	}

	public static function render_tag(CustomScript $script, ?string $proxy_base_url = null): string
	{
		$attributes = ' type="text/plain" data-cmp-category="' . esc_attr($script->category) . '"';

		if ('' !== $script->service) {
			$attributes .= ' data-cmp-service="' . esc_attr($script->service) . '"';
		}

		if (CustomScript::TYPE_URL === $script->type) {
			$src = $script->url;
			$extra = self::parse_extra_attributes($script->attributes);

			if ($script->proxy && null !== $proxy_base_url) {
				$src = self::proxied_src($proxy_base_url, $script->url);

				if (!array_key_exists('data-host-url', $extra)) {
					$extra['data-host-url'] = $proxy_base_url;
				}
			}

			foreach ($extra as $name => $value) {
				$attributes .= ' ' . esc_attr($name) . '="' . esc_attr($value) . '"';
			}

			return '<script' . $attributes . ' src="' . esc_url($src) . '"></script>' . "\n";
		}

		$code = str_ireplace('</script', '<\/script', $script->code);

		return '<script' . $attributes . '>' . $code . '</script>' . "\n";
	}

	/**
	 * @return array<string, string>
	 */
	public static function parse_extra_attributes(string $raw): array
	{
		$attributes = [];

		foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
			$line = trim($line);

			if ('' === $line) {
				continue;
			}

			if (!preg_match('/^([a-zA-Z][a-zA-Z0-9_-]*)\s*=\s*"([^"]*)"$/', $line, $m)
				&& !preg_match('/^([a-zA-Z][a-zA-Z0-9_-]*)\s*=\s*\'([^\']*)\'$/', $line, $m)
				&& !preg_match('/^([a-zA-Z][a-zA-Z0-9_-]*)\s*=\s*(\S+)$/', $line, $m)
			) {
				continue;
			}

			$name = strtolower($m[1]);

			if ('type' === $name || 'src' === $name || 0 === strpos($name, 'data-cmp-')) {
				continue;
			}

			$attributes[$name] = $m[2];
		}

		return $attributes;
	}

	public static function proxied_src(string $proxy_base_url, string $upstream_url): string
	{
		$path = (string) parse_url($upstream_url, PHP_URL_PATH);
		$query = (string) parse_url($upstream_url, PHP_URL_QUERY);

		$src = rtrim($proxy_base_url, '/') . '/' . ltrim($path, '/');

		return '' !== $query ? $src . '?' . $query : $src;
	}
}
