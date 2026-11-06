<?php

declare(strict_types=1);

namespace Consentimental\Services;

if (!defined('ABSPATH')) {
	exit;
}

use InvalidArgumentException;

final class ServiceRegistry
{
	/**
	 * @var array<string, Service>
	 */
	private array $built_in = [];

	public function __construct(private readonly CustomServiceRepository $custom_services)
	{
	}

	/**
	 * @param array<string, mixed> $definition
	 */
	public function register(array $definition): Service
	{
		if (empty($definition['id']) || !is_string($definition['id'])) {
			throw new InvalidArgumentException('Service definition requires a string "id".');
		}

		if (empty($definition['category']) || !is_string($definition['category'])) {
			throw new InvalidArgumentException('Service definition requires a string "category".');
		}

		$service = Service::from_array($definition, built_in: true);
		$this->built_in[$service->id] = $service;

		return $service;
	}

	/**
	 * @return Service[] built-in services, then custom services, both id-unique
	 */
	public function all(): array
	{
		$custom = [];

		foreach ($this->custom_services->all() as $service) {
			if (!isset($this->built_in[$service->id])) {
				$custom[$service->id] = $service;
			}
		}

		return array_values($this->built_in + $custom);
	}

	public function get(string $id): ?Service
	{
		foreach ($this->all() as $service) {
			if ($service->id === $id) {
				return $service;
			}
		}

		return null;
	}

	public function exists(string $id): bool
	{
		return null !== $this->get($id);
	}

	/**
	 * @return Service[] services belonging to a given category slug
	 */
	public function by_category(string $category): array
	{
		return array_values(array_filter(
			$this->all(),
			static fn (Service $service): bool => $service->category === $category,
		));
	}

	/**
	 * @param array{category?: string, purpose?: string, privacy_policy_url?: string, domains?: string[], script_patterns?: string[], iframe_patterns?: string[], active?: bool} $override
	 */
	public function apply_override(string $id, array $override): bool
	{
		$existing = $this->built_in[$id] ?? null;

		if (null === $existing) {
			return false;
		}

		$this->register(array_merge($existing->to_array(), $override, ['id' => $id]));

		return true;
	}
}
