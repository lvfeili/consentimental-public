<?php

declare(strict_types=1);

namespace Consentimental\Services;

if (!defined('ABSPATH')) {
	exit;
}

final class CustomServiceRepository
{
	public const OPTION_NAME = 'cmp_services';

	public function __construct(
		private readonly ?string $option_name = null,
		private readonly ?string $fallback_option_name = null,
	) {
	}

	public function seed_defaults(): void
	{
		if (false === get_option(self::OPTION_NAME, false)) {
			add_option(self::OPTION_NAME, []);
		}
	}

	/**
	 * @return Service[]
	 */
	public function all(): array
	{
		$stored = get_option($this->option_name ?? self::OPTION_NAME, null);

		if (null === $stored && null !== $this->fallback_option_name) {
			$stored = get_option($this->fallback_option_name, null);
		}

		if (!is_array($stored)) {
			return [];
		}

		return array_map(
			static fn (array $data): Service => Service::from_array($data, built_in: false),
			$stored,
		);
	}

	/**
	 * @param Service[] $services
	 */
	public function save(array $services): void
	{
		$data = array_map(static fn (Service $service): array => $service->to_array(), $services);

		update_option($this->option_name ?? self::OPTION_NAME, $data);
	}

	public function find(string $id): ?Service
	{
		foreach ($this->all() as $service) {
			if ($service->id === $id) {
				return $service;
			}
		}

		return null;
	}

	public function save_one(Service $service): void
	{
		$services = $this->all();
		$found = false;

		foreach ($services as $i => $existing) {
			if ($existing->id === $service->id) {
				$services[$i] = $service;
				$found = true;

				break;
			}
		}

		if (!$found) {
			$services[] = $service;
		}

		$this->save($services);
	}

	public function delete(string $id): void
	{
		$this->save(array_values(array_filter(
			$this->all(),
			static fn (Service $service): bool => $service->id !== $id,
		)));
	}
}
