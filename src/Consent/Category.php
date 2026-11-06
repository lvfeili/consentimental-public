<?php

declare(strict_types=1);

namespace Consentimental\Consent;

if (!defined('ABSPATH')) {
	exit;
}

final class Category
{
	public function __construct(
		public readonly string $slug,
		public string $label,
		public string $description,
		public readonly bool $required,
		public bool $default_enabled,
		public int $order,
	) {
	}

	/**
	 * @return array{slug: string, label: string, description: string, required: bool, default_enabled: bool, order: int}
	 */
	public function to_array(): array
	{
		return [
			'slug' => $this->slug,
			'label' => $this->label,
			'description' => $this->description,
			'required' => $this->required,
			'default_enabled' => $this->default_enabled,
			'order' => $this->order,
		];
	}

	/**
	 * @param array{slug: string, label: string, description?: string, required?: bool, default_enabled?: bool, order?: int} $data
	 */
	public static function from_array(array $data): self
	{
		$required = (bool) ($data['required'] ?? false);

		return new self(
			slug: (string) $data['slug'],
			label: (string) ($data['label'] ?? $data['slug']),
			description: (string) ($data['description'] ?? ''),
			required: $required,
			default_enabled: $required || (bool) ($data['default_enabled'] ?? false),
			order: (int) ($data['order'] ?? 0),
		);
	}
}
