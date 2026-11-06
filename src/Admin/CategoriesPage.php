<?php

declare(strict_types=1);

namespace Consentimental\Admin;

use Consentimental\Consent\Category;
use Consentimental\Consent\CategoryRepository;
use Consentimental\Consent\DraftManager;

if (!defined('ABSPATH')) {
	exit;
}

final class CategoriesPage
{
	public const PAGE_SLUG = 'consentimental-categories';

	private const PROTECTED_SLUGS = ['necessary', 'functional', 'analytics', 'marketing'];

	public function __construct(
		private readonly CategoryRepository $categories,
		private readonly DraftManager $draft,
	) {
	}

	public function register(): void
	{
		add_action('admin_menu', $this->add_menu(...));
		add_action('admin_init', $this->maybe_handle_form(...));
	}

	private function add_menu(): void
	{
		add_submenu_page(
			parent_slug: AdminMenu::PAGE_SLUG,
			page_title: __('Categories', 'consentimental'),
			menu_title: __('Categories', 'consentimental'),
			capability: 'manage_options',
			menu_slug: self::PAGE_SLUG,
			callback: $this->render(...),
		);
	}

	private function maybe_handle_form(): void
	{
		if (!current_user_can('manage_options') || !isset($_POST['cmp_categories_nonce'])) {
			return;
		}

		check_admin_referer('cmp_categories_save', 'cmp_categories_nonce');

		if (isset($_POST['cmp_delete_category'])) {
			$slug = sanitize_key((string) wp_unslash($_POST['slug']));

			if (in_array($slug, self::PROTECTED_SLUGS, true)) {
				$this->redirect(['updated' => 'saved']);
			}

			$this->delete_category($slug);
			$this->redirect(['updated' => 'deleted', 'version_bumped' => '1']);
		}

		if (isset($_POST['cmp_save_categories'])) {
			$this->save_categories();
			$this->redirect(['updated' => 'saved']);
		}

		if (isset($_POST['cmp_add_category'])) {
			$this->add_category();
			$this->redirect(['updated' => 'saved', 'version_bumped' => '1']);
		}
	}

	private function delete_category(string $slug): void
	{
		if (in_array($slug, self::PROTECTED_SLUGS, true)) {
			return;
		}

		$remaining = array_values(array_filter(
			$this->categories->all(),
			static fn (Category $category): bool => $category->slug !== $slug,
		));

		$this->categories->save($remaining);

		$this->draft->mark_meaningful();
	}

	private function save_categories(): void
	{
		$slugs = (array) ($_POST['slug'] ?? []);
		$labels = (array) ($_POST['label'] ?? []);
		$descriptions = (array) ($_POST['description'] ?? []);
		$orders = (array) ($_POST['order'] ?? []);
		$requireds = (array) ($_POST['required'] ?? []);

		$existing = $this->categories->all();
		$next = [];

		foreach ($slugs as $i => $raw_slug) {
			$slug = sanitize_key((string) wp_unslash($raw_slug));
			$original = null;

			foreach ($existing as $category) {
				if ($category->slug === $slug) {
					$original = $category;

					break;
				}
			}

			if (null === $original) {
				continue;
			}

			$required = 'necessary' === $slug
				? true
				: isset($requireds[$i]);

			$next[] = new Category(
				slug: $slug,
				label: sanitize_text_field((string) wp_unslash($labels[$i] ?? $original->label)),
				description: sanitize_textarea_field((string) wp_unslash($descriptions[$i] ?? $original->description)),
				required: $required,
				default_enabled: $required || $original->default_enabled,
				order: (int) ($orders[$i] ?? $original->order),
			);
		}

		if ([] === $next) {
			return;
		}

		$this->categories->save($next);
	}

	private function add_category(): void
	{
		$slug = sanitize_key((string) wp_unslash($_POST['new_slug'] ?? ''));

		if ('' === $slug || null !== $this->categories->find($slug)) {
			return;
		}

		$existing = $this->categories->all();
		$max_order = 0;

		foreach ($existing as $category) {
			$max_order = max($max_order, $category->order);
		}

		$existing[] = new Category(
			slug: $slug,
			label: sanitize_text_field((string) wp_unslash($_POST['new_label'] ?? $slug)),
			description: sanitize_textarea_field((string) wp_unslash($_POST['new_description'] ?? '')),
			required: false,
			default_enabled: false,
			order: $max_order + 1,
		);

		$this->categories->save($existing);

		$this->draft->mark_meaningful();
	}

	/**
	 * @param array<string, string> $args
	 */
	private function redirect(array $args): void
	{
		wp_safe_redirect(add_query_arg(array_merge(['page' => self::PAGE_SLUG], $args), admin_url('admin.php')));

		exit;
	}

	private function render(): void
	{
		if (!current_user_can('manage_options')) {
			return;
		}

		$categories = $this->categories->all();
		$protected_slugs = self::PROTECTED_SLUGS;

		require \Consentimental\CMP_PLUGIN_DIR . 'templates/admin/categories.php';
	}
}
