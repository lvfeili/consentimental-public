<?php

declare(strict_types=1);

namespace Consentimental\Scripts;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
	exit;
}

final class ScriptProxy
{
	public const NAMESPACE = 'consentimental/v1';

	private const ALLOWED_METHODS = ['GET', 'HEAD', 'POST'];

	private const CACHE_TTL = 15 * MINUTE_IN_SECONDS;

	private const MAX_CACHEABLE_BYTES = 2 * MB_IN_BYTES;

	private const ALLOWED_CONTENT_TYPES = [
		'application/javascript',
		'text/javascript',
		'application/json',
		'text/plain',
		'image/gif',
		'image/png',
		'image/jpeg',
		'image/webp',
		'application/octet-stream',
	];

	public function __construct(private readonly CustomScriptRepository $scripts)
	{
	}

	public function register(): void
	{
		add_action('rest_api_init', $this->register_route(...));
		add_filter('rest_pre_serve_request', $this->maybe_serve_raw(...), 10, 3);
	}

	private function register_route(): void
	{
		register_rest_route(self::NAMESPACE, '/proxy/(?P<id>[a-z0-9-]+)/(?P<path>.*)', [
			'methods' => self::ALLOWED_METHODS,
			'callback' => $this->handle(...),
			'permission_callback' => '__return_true',
		]);
	}

	public static function base_url(string $script_id): string
	{
		return rest_url(self::NAMESPACE . '/proxy/' . $script_id);
	}

	private bool $attempted = false;

	public function handle(WP_REST_Request $request, bool $fresh = false): WP_REST_Response|WP_Error
	{
		$this->attempted = false;
		$result = $this->execute($request, $fresh);
		if ($this->attempted) {
			$params = $request->get_url_params();
			$status = $result instanceof WP_REST_Response ? $result->get_status() : 0;
			$ok = $status >= 200 && $status < 300;
			do_action('cmp_proxy_result', (string) $params['id'], ltrim((string) $params['path'], '/'), $request->get_method(), $ok,
				$result instanceof WP_Error ? $result->get_error_code() : 'http_' . $status, $fresh);
		}
		return $result;
	}

	private function execute(WP_REST_Request $request, bool $fresh): WP_REST_Response|WP_Error
	{
		$url_params = $request->get_url_params();
		$script = $this->scripts->get(sanitize_key((string) ($url_params['id'] ?? '')));

		if (null === $script || CustomScript::TYPE_URL !== $script->type || !$script->proxy) {
			return new WP_Error('cmp_proxy_not_found', __('Unknown or non-proxied script.', 'consentimental'), ['status' => 404]);
		}

		$request_path = ltrim((string) ($url_params['path'] ?? ''), '/');

		$method = strtoupper($request->get_method());
		if (!self::allows_request($script, $request_path, $method)) {
			return new WP_Error('cmp_proxy_bad_path', __('Proxy path or method not allowed.', 'consentimental'), ['status' => 403]);
		}

		$origin = self::resolve_origin($script, $request_path);

		if (null === $origin) {
			return new WP_Error('cmp_proxy_bad_upstream', __('This script has no valid upstream URL to proxy.', 'consentimental'), ['status' => 500]);
		}

		$query = $request->get_query_params();
		unset($query['rest_route']);

		$is_static_asset = $request_path === self::script_path($script);
		if ($is_static_asset && !self::matches_static_query($script, $query)) {
			return new WP_Error('cmp_proxy_bad_query', __('Script query must match the configured URL.', 'consentimental'), ['status' => 400]);
		}
		if (strlen((string) $request->get_body()) > 64 * 1024 || strlen(http_build_query($query)) > 8192) {
			return new WP_Error('cmp_proxy_too_large', __('Proxy request too large.', 'consentimental'), ['status' => 413]);
		}
		$upstream_url = self::upstream_url($origin, $request_path, $query);
		$cacheable = $is_static_asset && 'GET' === $method;
		$cache_key = 'cmp_proxy_' . md5($script->id);

		if ($cacheable && !$fresh) {
			$cached = get_transient($cache_key);

			if (is_array($cached) && ($cached['url'] ?? '') === $upstream_url) {
				return $this->raw_response((int) $cached['status'], (string) $cached['content_type'], (string) $cached['body']);
			}
		}

		$rate_key = 'cmp_proxy_rate_' . md5($script->id);
		$window = (int) floor(time() / 60);
		$rate = get_transient($rate_key);
		$count = is_array($rate) && ($rate['window'] ?? null) === $window ? (int) $rate['count'] : 0;
		if ($count >= 120) {
			return new WP_Error('cmp_proxy_rate_limited', __('Proxy request limit reached.', 'consentimental'), ['status' => 429]);
		}
		set_transient($rate_key, ['window' => $window, 'count' => $count + 1], 60);

		$args = [
			'method' => $method,
			'timeout' => 10,
			'redirection' => 0,
			'limit_response_size' => self::MAX_CACHEABLE_BYTES + 1,
			'headers' => $this->forwarded_headers($request),
		];

		if ('POST' === $method) {
			$args['body'] = $request->get_body();
		}

		$this->attempted = true;
		$upstream_response = wp_safe_remote_request($upstream_url, $args);

		if (is_wp_error($upstream_response)) {
			return new WP_Error('cmp_proxy_upstream_unreachable', $upstream_response->get_error_message(), ['status' => 502]);
		}

		$status = (int) wp_remote_retrieve_response_code($upstream_response);
		$content_type = wp_remote_retrieve_header($upstream_response, 'content-type');
		$content_type = is_string($content_type) && '' !== $content_type ? $content_type : 'application/octet-stream';
		$body = wp_remote_retrieve_body($upstream_response);

		if (strlen($body) > self::MAX_CACHEABLE_BYTES) {
			return new WP_Error('cmp_proxy_response_too_large', __('Upstream response too large.', 'consentimental'), ['status' => 502]);
		}

		if ($status >= 300 && $status < 400) {
			return new WP_Error('cmp_proxy_redirect', __('Configure the final upstream URL; proxy redirects are disabled.', 'consentimental'), ['status' => 502]);
		}

		if (!self::is_allowed_content_type($content_type)) {
			return new WP_Error('cmp_proxy_disallowed_content_type', __('Upstream response type not allowed.', 'consentimental'), ['status' => 502]);
		}

		if ($cacheable && $status >= 200 && $status < 300 && strlen($body) <= self::MAX_CACHEABLE_BYTES) {
			set_transient($cache_key, ['url' => $upstream_url, 'status' => $status, 'content_type' => $content_type, 'body' => $body], self::CACHE_TTL);
		}

		return $this->raw_response($status, $content_type, $body);
	}

	public static function allows_request(CustomScript $script, string $path, string $method): bool
	{
		if (preg_match('~[%\\\\?#\x00-\x20]|(?:^|/)\.\.?(?:/|$)~', $path)) {
			return false;
		}
		$static = self::script_path($script);
		$paths = [$static => ['GET', 'HEAD']];
		try {
			$paths += ProxyRoutes::parse($script->proxy_paths);
		} catch (\InvalidArgumentException) {
			return false;
		}
		$paths = apply_filters('cmp_proxy_allowed_paths', $paths, $script);
		return is_array($paths) && isset($paths[$path]) && is_array($paths[$path])
			&& in_array($method, $paths[$path], true) && in_array($method, self::ALLOWED_METHODS, true);
	}

	public static function matches_static_query(CustomScript $script, array $query): bool
	{
		parse_str((string) parse_url($script->url, PHP_URL_QUERY), $expected);
		ksort($expected);
		ksort($query);
		return $expected === $query;
	}

	public static function is_allowed_content_type(string $content_type): bool
	{
		$type = strtolower(trim(explode(';', $content_type, 2)[0]));

		foreach (self::ALLOWED_CONTENT_TYPES as $allowed) {
			if ($type === $allowed) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return array<string, string>
	 */
	private function forwarded_headers(WP_REST_Request $request): array
	{
		$headers = ['accept-encoding' => 'identity'];

		foreach (['content-type', 'accept', 'user-agent'] as $name) {
			$value = $request->get_header($name);

			if (is_string($value) && '' !== $value) {
				$headers[$name] = $value;
			}
		}

		return $headers;
	}

	private function raw_response(int $status, string $content_type, string $body): WP_REST_Response
	{
		$response = new WP_REST_Response(['cmp_proxy_body' => $body], $status);
		$response->header('Content-Type', $content_type);
		$response->header('X-Content-Type-Options', 'nosniff');
		$response->header('Cache-Control', 'no-store');

		return $response;
	}

	/**
	 * @param mixed $result
	 */
	public function maybe_serve_raw(bool $served, mixed $result, WP_REST_Request $request): bool
	{
		if (!$result instanceof WP_REST_Response) {
			return $served;
		}

		$data = $result->get_data();

		if (!is_array($data) || !array_key_exists('cmp_proxy_body', $data)) {
			return $served;
		}

		status_header($result->get_status());

		foreach ($result->get_headers() as $name => $value) {
			header($name . ': ' . $value);
		}

		echo $data['cmp_proxy_body'];

		return true;
	}

	public static function script_path(CustomScript $script): string
	{
		return ltrim((string) parse_url($script->url, PHP_URL_PATH), '/');
	}

	public static function resolve_origin(CustomScript $script, string $request_path): ?string
	{
		$use_api_origin = '' !== $script->proxy_api_origin && $request_path !== self::script_path($script);

		return self::upstream_origin($use_api_origin ? $script->proxy_api_origin : $script->url);
	}

	public static function upstream_origin(string $url): ?string
	{
		$scheme = parse_url($url, PHP_URL_SCHEME);
		$host = parse_url($url, PHP_URL_HOST);

		if (!is_string($scheme) || !is_string($host) || '' === $host) {
			return null;
		}

		$port = parse_url($url, PHP_URL_PORT);

		return $scheme . '://' . $host . (is_int($port) ? ':' . $port : '');
	}

	/**
	 * @param array<string, mixed> $query
	 */
	public static function upstream_url(string $origin, string $path, array $query = []): string
	{
		$url = rtrim($origin, '/') . '/' . ltrim($path, '/');

		if ([] !== $query) {
			$url .= '?' . http_build_query($query);
		}

		return $url;
	}
}
