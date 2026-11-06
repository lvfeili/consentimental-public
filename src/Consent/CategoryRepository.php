<?php

declare(strict_types=1);

namespace Consentimental\Consent;

if (!defined('ABSPATH')) {
	exit;
}

final class CategoryRepository
{
	public const OPTION_NAME = 'cmp_categories';

	public function __construct(
		private readonly ?string $option_name = null,
		private readonly ?string $fallback_option_name = null,
	) {
	}

	/**
	 * @return array<int, array{slug: string, label: string, description: string, required: bool, default_enabled: bool, order: int}>
	 */
	public static function defaults(): array
	{
		return [
			[
				'slug' => 'necessary',
				'label' => __('Necessary', 'consentimental'),
				'description' => __('Required for the website to function and cannot be disabled.', 'consentimental'),
				'required' => true,
				'default_enabled' => true,
				'order' => 0,
			],
			[
				'slug' => 'functional',
				'label' => __('Functional', 'consentimental'),
				'description' => __('Enables enhanced functionality such as embedded content and preferences.', 'consentimental'),
				'required' => false,
				'default_enabled' => false,
				'order' => 1,
			],
			[
				'slug' => 'analytics',
				'label' => __('Analytics', 'consentimental'),
				'description' => __('Helps understand how visitors use the website.', 'consentimental'),
				'required' => false,
				'default_enabled' => false,
				'order' => 2,
			],
			[
				'slug' => 'marketing',
				'label' => __('Marketing', 'consentimental'),
				'description' => __('Used to track visitors for advertising purposes.', 'consentimental'),
				'required' => false,
				'default_enabled' => false,
				'order' => 3,
			],
		];
	}

	public function seed_defaults(): void
	{
		if (false === get_option(self::OPTION_NAME, false)) {
			add_option(self::OPTION_NAME, self::defaults());
		}
	}

	/**
	 * @return Category[] ordered by Category::$order
	 */
	public function all(): array
	{
		$stored = get_option($this->option_name ?? self::OPTION_NAME, null);

		if (null === $stored && null !== $this->fallback_option_name) {
			$stored = get_option($this->fallback_option_name, null);
		}

		if (!is_array($stored) || [] === $stored) {
			$stored = self::defaults();
		}

		$enforced = (new Settings())->enforces_framework();
		$categories = array_map(
			static fn (array $data): Category => Category::from_array(FrameworkPolicy::category($data, $enforced)),
			$stored,
		);

		usort($categories, static fn (Category $a, Category $b): int => $a->order <=> $b->order);

		return $categories;
	}

	public function find(string $slug): ?Category
	{
		foreach ($this->all() as $category) {
			if ($category->slug === $slug) {
				return $category;
			}
		}

		return null;
	}

	/**
	 * @param Category[] $categories
	 */
	public function save(array $categories): void
	{
		$data = array_map(static fn (Category $category): array => $category->to_array(), $categories);

		update_option($this->option_name ?? self::OPTION_NAME, $data);
	}
}
