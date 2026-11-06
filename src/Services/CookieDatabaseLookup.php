<?php

declare(strict_types=1);

namespace Consentimental\Services;

if (!defined('ABSPATH')) {
	exit;
}

final class CookieDatabaseLookup
{
	/**
	 * @var array<string, array<int, array{name: string, purpose: string, duration: string}>>|null
	 */
	private ?array $by_domain = null;

	public function __construct(private readonly string $data_file)
	{
	}

	/**
	 * @return array<int, array{name: string, purpose: string, duration: string}>
	 */
	public function find_by_domain(string $domain): array
	{
		$domain = strtolower(trim($domain, ". \t\n\r\0\x0B"));

		if ('' === $domain) {
			return [];
		}

		return $this->load()[$domain] ?? [];
	}

	/**
	 * @return array<string, array<int, array{name: string, purpose: string, duration: string}>>
	 */
	public function all(): array
	{
		return $this->load();
	}

	/**
	 * @return array<string, array<int, array{name: string, purpose: string, duration: string}>>
	 */
	private function load(): array
	{
		if (null !== $this->by_domain) {
			return $this->by_domain;
		}

		if (!is_readable($this->data_file)) {
			return $this->by_domain = [];
		}

		$decoded = json_decode((string) file_get_contents($this->data_file), true);

		return $this->by_domain = is_array($decoded) ? $decoded : [];
	}
}
