<?php

declare(strict_types=1);

namespace Consentimental\Services;

if (!defined('ABSPATH')) {
	exit;
}

final class ServiceOverrideRepository
{
	public const OPTION_NAME = 'cmp_service_overrides';

	public function __construct(
		private readonly ?string $option_name = null,
		private readonly ?string $fallback_option_name = null,
	) {
	}

	/**
	 * @return array<string, array{category?: string, domains: string[], script_patterns: string[], iframe_patterns: string[], active?: bool}>
	 */
	public function all(): array
	{
		$stored = get_option($this->option_name ?? self::OPTION_NAME, null);

		if (null === $stored && null !== $this->fallback_option_name) {
			$stored = get_option($this->fallback_option_name, null);
		}

		return is_array($stored) ? $stored : [];
	}

	/**
	 * @return array{category?: string, domains: string[], script_patterns: string[], iframe_patterns: string[], active?: bool}|null
	 */
	public function get(string $service_id): ?array
	{
		return $this->all()[$service_id] ?? null;
	}

	/**
	 * @param array{category?: string, domains: string[], script_patterns: string[], iframe_patterns: string[], active?: bool} $override
	 */
	public function save(string $service_id, array $override): void
	{
		$all = $this->all();
		$all[$service_id] = $override;
		update_option($this->option_name ?? self::OPTION_NAME, $all);
	}

	public function delete(string $service_id): void
	{
		$all = $this->all();
		unset($all[$service_id]);
		update_option($this->option_name ?? self::OPTION_NAME, $all);
	}
}
