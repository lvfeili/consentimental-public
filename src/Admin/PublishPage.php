<?php

declare(strict_types=1);

namespace Consentimental\Admin;

use Consentimental\Consent\CategoryRepository;
use Consentimental\Consent\DraftManager;
use Consentimental\Scripts\CustomScriptRepository;
use Consentimental\Services\CustomServiceRepository;

if (!defined('ABSPATH')) {
	exit;
}

final class PublishPage
{
	public const PAGE_SLUG = 'consentimental-publish';

	public function __construct(
		private readonly DraftManager $draft,
		private readonly CategoryRepository $categories,
		private readonly CategoryRepository $categories_draft,
		private readonly CustomServiceRepository $custom_services,
		private readonly CustomServiceRepository $custom_services_draft,
		private readonly CustomScriptRepository $custom_scripts,
		private readonly CustomScriptRepository $custom_scripts_draft,
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
			page_title: __('Review & Publish', 'consentimental'),
			menu_title: __('Review & Publish', 'consentimental'),
			capability: 'manage_options',
			menu_slug: self::PAGE_SLUG,
			callback: $this->render(...),
		);
	}

	private function maybe_handle_form(): void
	{
		if (!current_user_can('manage_options') || !isset($_POST['cmp_publish_nonce'])) {
			return;
		}

		check_admin_referer('cmp_publish', 'cmp_publish_nonce');

		if (isset($_POST['cmp_publish'])) {
			$result = $this->draft->publish();
			$this->redirect(['published' => '1', 'version_bumped' => $result['version_bumped'] ? '1' : '0']);
		}

		if (isset($_POST['cmp_discard'])) {
			$this->draft->discard();
			$this->redirect(['discarded' => '1']);
		}
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

		$pending = $this->draft->pending_sections();
		$meaningful = $this->draft->is_meaningful();
		$counts = [
			'categories' => [count($this->categories->all()), count($this->categories_draft->all())],
			'custom_services' => [count($this->custom_services->all()), count($this->custom_services_draft->all())],
			'custom_scripts' => [count($this->custom_scripts->all()), count($this->custom_scripts_draft->all())],
		];

		require \Consentimental\CMP_PLUGIN_DIR . 'templates/admin/publish.php';
	}
}
