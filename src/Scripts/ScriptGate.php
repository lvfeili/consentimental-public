<?php

declare(strict_types=1);

namespace Consentimental\Scripts;

use Consentimental\Services\ServiceRegistry;

if (!defined('ABSPATH')) {
	exit;
}

final class ScriptGate
{
	/**
	 * @var array<string, array{category: string, service: string|null}>
	 */
	private array $registered = [];

	public function __construct(private readonly ServiceRegistry $services)
	{
	}

	public function register(string $handle, string $category_or_service): void
	{
		$service = $this->services->get($category_or_service);

		$this->registered[$handle] = $service
			? ['category' => $service->category, 'service' => $service->id]
			: ['category' => $category_or_service, 'service' => null];
	}

	public function register_hooks(): void
	{
		add_filter('wp_script_attributes', $this->neutralize(...));
		add_filter('wp_inline_script_attributes', $this->neutralize(...));
	}

	/**
	 * @param array<string, mixed> $attributes
	 * @return array<string, mixed>
	 */
	private function neutralize(array $attributes): array
	{
		$id = $attributes['id'] ?? null;

		if (!is_string($id) || '' === $id) {
			return $attributes;
		}

		$handle = $this->extract_handle($id);
		$spec = null !== $handle ? ($this->registered[$handle] ?? null) : null;

		if (null === $spec) {
			return $attributes;
		}

		$original_type = $attributes['type'] ?? null;

		if (is_string($original_type) && '' !== $original_type && 'text/plain' !== $original_type) {
			$attributes['data-cmp-original-type'] = $original_type;
		}

		$attributes['type'] = 'text/plain';
		$attributes['data-cmp-category'] = $spec['category'];

		if (null !== $spec['service']) {
			$attributes['data-cmp-service'] = $spec['service'];
		}

		return $attributes;
	}

	private function extract_handle(string $id): ?string
	{
		foreach (['-js-before', '-js-after', '-js'] as $suffix) {
			if (str_ends_with($id, $suffix)) {
				return substr($id, 0, -strlen($suffix));
			}
		}

		return null;
	}
}
