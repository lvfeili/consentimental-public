<?php

declare(strict_types=1);

namespace Consentimental\Consent;

if (!defined('ABSPATH')) {
	exit;
}

final class ConsentState
{
	public const SCHEMA_VERSION = 1;

	/**
	 * @param array<string, bool> $categories
	 * @param array<string, bool> $services explicit service-level overrides, if any
	 */
	public function __construct(
		public readonly string $consent_id,
		public readonly int $timestamp,
		public readonly int $expiry,
		public readonly int $config_version,
		public readonly array $categories,
		public readonly array $services = [],
		public readonly int $schema_version = self::SCHEMA_VERSION,
	) {
	}

	public function is_expired(?int $now = null): bool
	{
		return $this->expiry <= ($now ?? time());
	}

	public function is_stale(int $current_config_version): bool
	{
		return $this->config_version !== $current_config_version;
	}

	public function category(string $slug): bool
	{
		return (bool) ($this->categories[$slug] ?? false);
	}

	public function has_service_override(string $service_id): bool
	{
		return array_key_exists($service_id, $this->services);
	}

	public function service(string $service_id): bool
	{
		return (bool) ($this->services[$service_id] ?? false);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array
	{
		return [
			'schema_version' => $this->schema_version,
			'consent_id' => $this->consent_id,
			'timestamp' => $this->timestamp,
			'expiry' => $this->expiry,
			'config_version' => $this->config_version,
			'categories' => $this->categories,
			'services' => $this->services,
		];
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function from_array(array $data): ?self
	{
		if (!isset($data['consent_id'], $data['categories']) || !is_array($data['categories'])) {
			return null;
		}

		return new self(
			consent_id: (string) $data['consent_id'],
			timestamp: (int) ($data['timestamp'] ?? 0),
			expiry: (int) ($data['expiry'] ?? 0),
			config_version: (int) ($data['config_version'] ?? 0),
			categories: array_map('boolval', $data['categories']),
			services: is_array($data['services'] ?? null) ? array_map('boolval', $data['services']) : [],
			schema_version: (int) ($data['schema_version'] ?? self::SCHEMA_VERSION),
		);
	}
}
