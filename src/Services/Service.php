<?php

declare(strict_types=1);

namespace Consentimental\Services;

if (!defined('ABSPATH')) {
	exit;
}

final class Service
{
	/**
	 * @param string[] $domains third-party domains this service communicates with
	 * @param string[] $script_patterns known <script src> / inline-content patterns
	 * @param string[] $iframe_patterns known iframe src patterns
	 * @param array<int, array{name: string, purpose?: string, duration?: string, domain?: string}> $cookies
	 * @param string[] $storage_keys localStorage/sessionStorage keys
	 */
	public function __construct(
		public readonly string $id,
		public string $name,
		public string $provider,
		public string $purpose,
		public string $category,
		public string $privacy_policy_url = '',
		public array $domains = [],
		public array $script_patterns = [],
		public array $iframe_patterns = [],
		public array $cookies = [],
		public array $storage_keys = [],
		public readonly bool $built_in = false,
		public bool $active = true,
		public string $sale_sharing = 'unreviewed',
	) {
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array
	{
		return [
			'id' => $this->id,
			'name' => $this->name,
			'provider' => $this->provider,
			'purpose' => $this->purpose,
			'category' => $this->category,
			'privacy_policy_url' => $this->privacy_policy_url,
			'domains' => $this->domains,
			'script_patterns' => $this->script_patterns,
			'iframe_patterns' => $this->iframe_patterns,
			'cookies' => $this->cookies,
			'storage_keys' => $this->storage_keys,
			'built_in' => $this->built_in,
			'active' => $this->active,
			'sale_sharing' => $this->sale_sharing,
		];
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function from_array(array $data, bool $built_in = false): self
	{
		return new self(
			id: (string) $data['id'],
			name: (string) ($data['name'] ?? $data['id']),
			provider: (string) ($data['provider'] ?? ''),
			purpose: (string) ($data['purpose'] ?? ''),
			category: (string) ($data['category'] ?? 'necessary'),
			privacy_policy_url: (string) ($data['privacy_policy_url'] ?? ''),
			domains: (array) ($data['domains'] ?? []),
			script_patterns: (array) ($data['script_patterns'] ?? []),
			iframe_patterns: (array) ($data['iframe_patterns'] ?? []),
			cookies: (array) ($data['cookies'] ?? []),
			storage_keys: (array) ($data['storage_keys'] ?? []),
			built_in: $built_in,
			active: (bool) ($data['active'] ?? !$built_in),
			sale_sharing: \Consentimental\Consent\CaliforniaPolicy::classification($data['sale_sharing'] ?? null),
		);
	}
}
