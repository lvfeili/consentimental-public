<?php

declare(strict_types=1);

namespace Consentimental\Api;

use Consentimental\Consent\CategoryRepository;
use Consentimental\Consent\CaliforniaPolicy;
use Consentimental\Consent\ConsentManager;
use Consentimental\Consent\ConsentState;
use Consentimental\Consent\DisclosureSnapshot;
use Consentimental\Consent\DisclosureSnapshotRepository;
use Consentimental\Consent\Settings;
use Consentimental\Services\ServiceRegistry;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
	exit;
}

final class ConsentController
{
	private const NAMESPACE = 'consentimental/v1';

	public function __construct(
		private readonly CategoryRepository $categories,
		private readonly ServiceRegistry $services,
		private readonly Settings $settings,
		private readonly ConsentManager $manager,
		private readonly ?DisclosureSnapshotRepository $snapshots = null,
	) {
	}

	public function register(): void
	{
		add_action('rest_api_init', $this->register_routes(...));
	}

	private function register_routes(): void
	{
		register_rest_route(self::NAMESPACE, '/privacy-choice', [
			['methods' => 'GET', 'callback' => $this->handle_privacy_read(...), 'permission_callback' => '__return_true'],
			['methods' => 'POST', 'callback' => $this->handle_privacy_opt_out(...), 'permission_callback' => '__return_true'],
		]);
		register_rest_route(self::NAMESPACE, '/consent', [
			'methods' => 'POST',
			'callback' => $this->handle_update(...),
			'permission_callback' => '__return_true',
			'args' => [
				'consent_id' => ['type' => 'string', 'required' => false],
				'categories' => ['type' => 'object', 'required' => true],
				'services' => ['type' => 'object', 'required' => false],
				'config_version' => ['type' => 'integer', 'required' => false],
				'previous_state' => ['type' => ['object', 'null'], 'required' => false],
				'disclosure_id' => ['type' => ['string', 'null'], 'required' => false],
			],
		]);

		register_rest_route(self::NAMESPACE, '/consent', [
			'methods' => 'GET',
			'callback' => $this->handle_read(...),
			'permission_callback' => '__return_true',
		]);
	}

	public function handle_privacy_read(): WP_REST_Response
	{
		$response = new WP_REST_Response([
			'enabled' => (bool) $this->settings->get('california_enabled'),
			'gpc' => CaliforniaPolicy::gpc(),
			'opted_out' => CaliforniaPolicy::opted_out(),
		]);
		$response->header('Cache-Control', 'private, no-store, max-age=0');
		$response->header('Vary', 'Sec-GPC, Cookie');
		return $response;
	}

	public function handle_privacy_opt_out(WP_REST_Request $request): WP_REST_Response|WP_Error
	{
		if (!$this->settings->get('california_enabled')) {
			return new WP_Error('cmp_california_disabled', __('California controls are disabled.', 'consentimental'), ['status' => 409]);
		}
		if ($this->is_rate_limited()) {
			return new WP_Error('cmp_rate_limited', __('Too many requests. Please try again shortly.', 'consentimental'), ['status' => 429]);
		}
		if (true !== $request->get_param('opted_out')) {
			return new WP_Error('cmp_opt_out_only', __('This endpoint only accepts an opt-out.', 'consentimental'), ['status' => 400]);
		}
		$_COOKIE[CaliforniaPolicy::COOKIE_NAME] = '1';
		do_action('cmp_sale_sharing_opt_out', [
			'source' => CaliforniaPolicy::gpc() || 'gpc' === $request->get_param('source') ? 'gpc' : ('stored' === $request->get_param('source') ? 'stored' : 'manual'),
			'consent_id' => $this->manager->get_state()?->consent_id,
			'timestamp' => time(),
		]);
		return $this->handle_privacy_read();
	}

	public function handle_read(): WP_REST_Response
	{
		$state = $this->manager->get_state();

		return new WP_REST_Response([
			'state' => $state?->to_array(),
			'needs_consent' => $this->manager->needs_consent(),
			'config_version' => $this->settings->config_version(),
		]);
	}

	public function handle_update(WP_REST_Request $request): WP_REST_Response|WP_Error
	{
		if ($this->is_rate_limited()) {
			return new WP_Error('cmp_rate_limited', __('Too many requests. Please try again shortly.', 'consentimental'), ['status' => 429]);
		}

		$categories_input = $request->get_param('categories');

		if (!is_array($categories_input)) {
			return new WP_Error('cmp_invalid_categories', __('categories must be an object of category => bool.', 'consentimental'), ['status' => 400]);
		}

		$previous = $this->resolve_previous($request);

		$sanitized_categories = $this->sanitize_categories($categories_input);
		$sanitized_services = $this->sanitize_services((array) $request->get_param('services'));
		foreach ($this->services->all() as $service) {
			if ($service->active && CaliforniaPolicy::blocks($service, $this->settings)) {
				$sanitized_services[$service->id] = false;
				$sanitized_categories[$service->category] = false;
			}
		}

		$consent_id = sanitize_text_field((string) $request->get_param('consent_id'));

		if ('' === $consent_id || !$this->is_valid_consent_id($consent_id)) {
			$consent_id = (null !== $previous && $this->is_valid_consent_id($previous->consent_id))
				? $previous->consent_id
				: wp_generate_uuid4();
		}

		$now = time();
		$expiry_days = max(1, (int) $this->settings->get('consent_expiry_days'));

		$state = new ConsentState(
			consent_id: $consent_id,
			timestamp: $now,
			expiry: $now + ($expiry_days * DAY_IN_SECONDS),
			config_version: $this->resolve_config_version($request),
			categories: $sanitized_categories,
			services: $sanitized_services,
		);

		$this->fire_hooks($previous, $state, $this->resolve_disclosure($request, $state->config_version));

		return new WP_REST_Response(['state' => $state->to_array()]);
	}

	private function resolve_previous(WP_REST_Request $request): ?ConsentState
	{
		$previous_input = $request->get_param('previous_state');

		if ($request->has_param('previous_state') && null === $previous_input) {
			return null;
		}

		if (is_array($previous_input)) {
			$previous = ConsentState::from_array($previous_input);

			if (null !== $previous) {
				return $previous;
			}
		}

		return $this->manager->get_state();
	}

	private function resolve_config_version(WP_REST_Request $request): int
	{
		$current = $this->settings->config_version();
		$requested = (int) $request->get_param('config_version');

		return $requested > 0 && $requested <= $current ? $requested : $current;
	}

	/**
	 * @return array{hash: string, language: string}|null
	 */
	private function resolve_disclosure(WP_REST_Request $request, int $config_version): ?array
	{
		$hash = strtolower((string) $request->get_param('disclosure_id'));

		if (null === $this->snapshots || !DisclosureSnapshot::is_hash($hash)) {
			return null;
		}

		$snapshot = $this->snapshots->get($hash);

		if (null === $snapshot || $snapshot['config_version'] !== $config_version) {
			return null;
		}

		$language = '' !== (string) $snapshot['language_code'] ? (string) $snapshot['language_code'] : (string) $snapshot['locale'];

		return ['hash' => $hash, 'language' => $language];
	}

	private function is_valid_consent_id(string $id): bool
	{
		return 1 === preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)
			|| 1 === preg_match('/^cmp-[0-9a-z]{1,20}-[0-9a-z]{1,20}$/i', $id);
	}

	private function is_rate_limited(): bool
	{
		$ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP);

		if (false === $ip) {
			return false;
		}

		$key = 'cmp_rl_' . md5($ip);
		$count = (int) get_transient($key);

		$limit = max(1, (int) apply_filters('cmp_consent_rate_limit', 20));

		if ($count >= $limit) {
			return true;
		}

		set_transient($key, $count + 1, MINUTE_IN_SECONDS);

		return false;
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, bool>
	 */
	private function sanitize_categories(array $input): array
	{
		$sanitized = [];

		foreach ($this->categories->all() as $category) {
			$sanitized[$category->slug] = $category->required
				? true
				: (bool) ($input[$category->slug] ?? false);
		}

		return $sanitized;
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, bool>
	 */
	private function sanitize_services(array $input): array
	{
		$sanitized = [];

		foreach ($input as $service_id => $value) {
			$service = is_string($service_id) ? $this->services->get($service_id) : null;

			if (null !== $service && $service->active) {
				$sanitized[$service_id] = (bool) $value;
			}
		}

		return $sanitized;
	}

	/**
	 * @param array{hash: string, language: string}|null $disclosure
	 */
	private function fire_hooks(?ConsentState $previous, ConsentState $current, ?array $disclosure = null): void
	{
		do_action('cmp_consent_changed', $current, $previous, ['disclosure' => $disclosure]);

		foreach ($current->categories as $slug => $enabled) {
			$was_enabled = $previous?->category($slug) ?? false;

			if ($enabled && !$was_enabled) {
				do_action('cmp_consent_granted', $slug, $current);
			}

			if (!$enabled && $was_enabled) {
				do_action('cmp_consent_withdrawn', $slug, $current);
			}
		}
	}
}
