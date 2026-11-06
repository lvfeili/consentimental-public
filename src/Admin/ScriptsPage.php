<?php

declare(strict_types=1);

namespace Consentimental\Admin;

use Consentimental\Consent\CategoryRepository;
use Consentimental\Consent\DraftManager;
use Consentimental\Scripts\CustomScript;
use Consentimental\Scripts\ProxyRoutes;
use Consentimental\Scripts\CustomScriptRepository;
use Consentimental\Services\ServiceRegistry;

if (!defined('ABSPATH')) {
	exit;
}

final class ScriptsPage
{
	public const PAGE_SLUG = 'consentimental-scripts';

	public function __construct(
		private readonly CustomScriptRepository $scripts,
		private readonly CategoryRepository $categories,
		private readonly ServiceRegistry $services,
		private readonly DraftManager $draft,
	) {
	}

	public function register(): void
	{
		add_action('admin_menu', $this->add_menu(...));
		add_action('admin_init', $this->maybe_handle_form(...));
	}

	private function can_manage_scripts(): bool
	{
		return current_user_can('manage_options') && current_user_can('unfiltered_html');
	}

	private function add_menu(): void
	{
		add_submenu_page(
			parent_slug: AdminMenu::PAGE_SLUG,
			page_title: __('Scripts', 'consentimental'),
			menu_title: __('Scripts', 'consentimental'),
			capability: 'unfiltered_html',
			menu_slug: self::PAGE_SLUG,
			callback: $this->render(...),
		);
	}

	private function maybe_handle_form(): void
	{
		if (!$this->can_manage_scripts() || !isset($_POST['cmp_scripts_nonce'])) {
			return;
		}

		check_admin_referer('cmp_scripts_save', 'cmp_scripts_nonce');

		if (isset($_POST['cmp_delete_script'])) {
			$this->scripts->delete(sanitize_key((string) wp_unslash($_POST['id'])));
			$this->draft->mark_meaningful();
			$this->redirect(['updated' => 'deleted', 'version_bumped' => '1']);
		}

		if (isset($_POST['cmp_save_script'])) {
			$bumped = $this->save_script();
			$this->redirect($bumped ? ['updated' => 'saved', 'version_bumped' => '1'] : ['updated' => 'saved']);
		}
	}

	private function save_script(): bool
	{
		$id = sanitize_key((string) wp_unslash($_POST['id'] ?? ''));
		$is_new = '' === $id;
		$existing = $is_new ? null : $this->scripts->get($id);

		if ($is_new) {
			$id = 'script-' . strtolower(wp_generate_password(8, false, false));
		}

		$type = 'inline' === ($_POST['type'] ?? '') ? CustomScript::TYPE_INLINE : CustomScript::TYPE_URL;
		$new_category = sanitize_key((string) wp_unslash($_POST['category'] ?? 'necessary'));
		$new_url = esc_url_raw((string) wp_unslash($_POST['url'] ?? ''));
		$new_code = CustomScript::TYPE_INLINE === $type ? (string) wp_unslash($_POST['code'] ?? '') : '';
		$new_attributes = CustomScript::TYPE_URL === $type ? (string) wp_unslash($_POST['attributes'] ?? '') : '';
		$new_proxy = CustomScript::TYPE_URL === $type && isset($_POST['proxy']);
		$new_proxy_api_origin = $new_proxy ? esc_url_raw((string) wp_unslash($_POST['proxy_api_origin'] ?? '')) : '';
		$new_proxy_paths = $new_proxy ? trim((string) wp_unslash($_POST['proxy_paths'] ?? '')) : '';
		try { ProxyRoutes::parse($new_proxy_paths); }
		catch (\InvalidArgumentException $error) { wp_die(esc_html($error->getMessage()), '', ['response' => 400]); }


		$bumped = $is_new || (null !== $existing && (
			$existing->category !== $new_category
			|| $existing->url !== $new_url
			|| $existing->code !== $new_code
			|| $existing->attributes !== $new_attributes
			|| $existing->proxy !== $new_proxy
			|| $existing->proxy_api_origin !== $new_proxy_api_origin
			|| $existing->proxy_paths !== $new_proxy_paths
		));

		if ($bumped) {
			$this->draft->mark_meaningful();
		}

		$this->scripts->save(CustomScript::from_array([
			'id' => $id,
			'name' => sanitize_text_field((string) wp_unslash($_POST['name'] ?? '')),
			'type' => $type,
			'url' => $new_url,
			'code' => $new_code,
			'attributes' => $new_attributes,
			'proxy' => $new_proxy,
			'proxy_api_origin' => $new_proxy_api_origin,
			'proxy_paths' => $new_proxy_paths,
			'placement' => sanitize_key((string) wp_unslash($_POST['placement'] ?? '')),
			'category' => $new_category,
			'service' => sanitize_key((string) wp_unslash($_POST['service'] ?? '')),
			'condition_type' => sanitize_key((string) wp_unslash($_POST['condition_type'] ?? '')),
			'condition_value' => sanitize_text_field((string) wp_unslash($_POST['condition_value'] ?? '')),
		]));

		return $bumped;
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
		if (!$this->can_manage_scripts()) {
			return;
		}

		$action = isset($_GET['action']) ? sanitize_key((string) wp_unslash($_GET['action'])) : '';
		$id = isset($_GET['id']) ? sanitize_key((string) wp_unslash($_GET['id'])) : '';

		if ('add' === $action || ('edit' === $action && '' !== $id)) {
			$script = 'edit' === $action ? $this->scripts->get($id) : null;

			if ('edit' !== $action || null !== $script) {
				$categories = $this->categories->all();
				$services = $this->services->all();
				$prefill = 'add' === $action ? $this->prefill_from_request() : [];
				require \Consentimental\CMP_PLUGIN_DIR . 'templates/admin/script-form.php';

				return;
			}
		}

		$scripts = $this->scripts->all();
		require \Consentimental\CMP_PLUGIN_DIR . 'templates/admin/scripts.php';
	}

	/**
	 * @return array{name?: string, category?: string, service?: string}
	 */
	private function prefill_from_request(): array
	{
		if (!isset($_GET['service'])) {
			return [];
		}

		$service = $this->services->get(sanitize_key((string) wp_unslash($_GET['service'])));

		if (null === $service) {
			return [];
		}

		return ['name' => $service->name, 'category' => $service->category, 'service' => $service->id];
	}
}
