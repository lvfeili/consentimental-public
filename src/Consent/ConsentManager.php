<?php

declare(strict_types=1);

namespace Consentimental\Consent;

use Consentimental\Services\ServiceRegistry;

if (!defined('ABSPATH')) {
	exit;
}

final class ConsentManager
{
	public const COOKIE_NAME = 'cmp_consent';

	public function __construct(
		private readonly CategoryRepository $categories,
		private readonly ServiceRegistry $services,
		private readonly Settings $settings,
	) {
	}

	public function get_state(): ?ConsentState
	{
		$raw = $_COOKIE[self::COOKIE_NAME] ?? null;

		if (!is_string($raw) || '' === $raw) {
			return null;
		}

		$decoded = json_decode(wp_unslash($raw), true);

		if (!is_array($decoded)) {
			return null;
		}

		$state = ConsentState::from_array($decoded);

		if (null === $state || $state->is_expired()) {
			return null;
		}

		return $state;
	}

	public function has_consent(string $category_slug): bool
	{
		if ($this->settings->get('california_enabled')) {
			$services = array_filter($this->services->by_category($category_slug), static fn ($service) => $service->active);
			if ([] === $services) {
				return false;
			}
			foreach ($services as $service) {
				if (CaliforniaPolicy::blocks($service, $this->settings)) {
					return false;
				}
			}
		}
		$category = $this->categories->find($category_slug);

		if (null !== $category && $category->required) {
			return true;
		}

		$state = $this->get_state();

		if (null === $state || $state->is_stale($this->settings->config_version())) {
			return false;
		}

		return $state->category($category_slug);
	}

	public function has_service_consent(string $service_id): bool
	{
		$service = $this->services->get($service_id);

		if (null === $service || !$service->active || CaliforniaPolicy::blocks($service, $this->settings)) {
			return false;
		}

		$state = $this->get_state();

		if (
			null !== $state
			&& !$state->is_stale($this->settings->config_version())
			&& $state->has_service_override($service_id)
		) {
			return $state->service($service_id);
		}

		if ($this->settings->enforces_framework() && !($this->categories->find($service->category)?->required ?? false)) {
			return false;
		}

		return ($this->categories->find($service->category)?->required ?? false)
			|| (null !== $state && !$state->is_stale($this->settings->config_version()) && $state->category($service->category));
	}

	public function needs_consent(): bool
	{
		$state = $this->get_state();

		return null === $state || $state->is_stale($this->settings->config_version());
	}
}
