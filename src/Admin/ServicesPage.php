<?php

declare(strict_types=1);

namespace Consentimental\Admin;

use Consentimental\Consent\CategoryRepository;
use Consentimental\Consent\DraftManager;
use Consentimental\Consent\Settings;
use Consentimental\Scripts\CustomScriptRepository;
use Consentimental\Services\CookieDatabaseLookup;
use Consentimental\Services\CustomServiceRepository;
use Consentimental\Services\Service;
use Consentimental\Services\ServiceActivator;
use Consentimental\Services\ServiceOverrideRepository;
use Consentimental\Services\ServiceRegistry;

if (!defined('ABSPATH')) {
	exit;
}

final class ServicesPage
{
	public const PAGE_SLUG = 'consentimental-services';

	public function __construct(
		private readonly ServiceRegistry $services,
		private readonly CustomServiceRepository $custom_services,
		private readonly ServiceOverrideRepository $overrides,
		private readonly CategoryRepository $categories,
		private readonly Settings $settings,
		private readonly CustomScriptRepository $scripts,
		private readonly ServiceActivator $activator,
		private readonly CookieDatabaseLookup $cookie_database,
		private readonly DraftManager $draft,
	) {
	}

	public function register(): void
	{
		add_action('admin_menu', $this->add_menu(...));
		add_action('admin_init', $this->maybe_handle_form(...));
		add_action('admin_enqueue_scripts', $this->maybe_enqueue_cookie_lookup(...));
	}

	private function maybe_enqueue_cookie_lookup(string $hook): void
	{
		if ('consentimental_page_' . self::PAGE_SLUG !== $hook) {
			return;
		}

		if (!in_array($this->current_action(), ['add', 'edit'], true)) {
			return;
		}

		wp_enqueue_script(
			'cmp-admin-cookie-lookup',
			\Consentimental\CMP_PLUGIN_URL . 'assets/js/admin-cookie-lookup.js',
			[],
			\Consentimental\CMP_VERSION,
			true,
		);

		wp_localize_script('cmp-admin-cookie-lookup', 'cmpCookieDatabase', $this->cookie_database->all());
		wp_localize_script('cmp-admin-cookie-lookup', 'cmpCookieLookupL10n', [
			'buttonLabel' => __('Suggest cookies from domains', 'consentimental'),
		]);
	}

	private function add_menu(): void
	{
		add_submenu_page(
			parent_slug: AdminMenu::PAGE_SLUG,
			page_title: __('Services', 'consentimental'),
			menu_title: __('Services', 'consentimental'),
			capability: 'manage_options',
			menu_slug: self::PAGE_SLUG,
			callback: $this->render(...),
		);
	}

	private function current_action(): string
	{
		return isset($_GET['action']) ? sanitize_key((string) wp_unslash($_GET['action'])) : '';
	}

	private function current_id(): string
	{
		return isset($_GET['id']) ? sanitize_key((string) wp_unslash($_GET['id'])) : '';
	}

	private function maybe_handle_form(): void
	{
		if (!current_user_can('manage_options') || !isset($_POST['cmp_services_nonce'])) {
			return;
		}

		check_admin_referer('cmp_services_save', 'cmp_services_nonce');

		if (isset($_POST['cmp_delete_custom_service'])) {
			$this->custom_services->delete(sanitize_key((string) wp_unslash($_POST['id'])));
			$this->draft->mark_meaningful();
			$this->redirect(['updated' => 'deleted', 'version_bumped' => '1']);
		}

		if (isset($_POST['cmp_reset_override'])) {
			$this->overrides->delete(sanitize_key((string) wp_unslash($_POST['id'])));
			$this->draft->mark_meaningful();
			$this->redirect(['updated' => 'reset']);
		}

		if (isset($_POST['cmp_save_override'])) {
			$id = sanitize_key((string) wp_unslash($_POST['id']));
			$service = $this->services->get($id);
			$new_category = sanitize_key((string) wp_unslash($_POST['category'] ?? ''));
			$new_active = isset($_POST['active']);
			$sale_sharing = \Consentimental\Consent\CaliforniaPolicy::classification(wp_unslash($_POST['sale_sharing'] ?? null));

			$bumped = null !== $service && (
				('' !== $new_category && $service->category !== $new_category)
				|| $service->active !== $new_active
				|| $service->sale_sharing !== $sale_sharing
			);

			if ($bumped) {
				$this->draft->mark_meaningful();
			}

			$override = [
				'category' => '' !== $new_category ? $new_category : ($service->category ?? ''),
				'domains' => $this->lines_from_post('domains'),
				'script_patterns' => $this->lines_from_post('script_patterns'),
				'iframe_patterns' => $this->lines_from_post('iframe_patterns'),
				'active' => $new_active,
				'sale_sharing' => $sale_sharing,
			];

			$purpose = sanitize_textarea_field((string) wp_unslash($_POST['purpose'] ?? ''));
			$privacy_policy_url = esc_url_raw((string) wp_unslash($_POST['privacy_policy_url'] ?? ''));

			if ('' !== $purpose) {
				$override['purpose'] = $purpose;
			}

			if ('' !== $privacy_policy_url) {
				$override['privacy_policy_url'] = $privacy_policy_url;
			}

			$this->overrides->save($id, $override);
			$this->redirect($bumped ? ['updated' => 'saved', 'version_bumped' => '1'] : ['updated' => 'saved']);
		}

		if (isset($_POST['cmp_save_custom_service'])) {
			$bumped = $this->save_custom_service();
			$this->redirect($bumped ? ['updated' => 'saved', 'version_bumped' => '1'] : ['updated' => 'saved']);
		}

		if (isset($_POST['cmp_toggle_active'])) {
			$this->toggle_active();
			$this->redirect(['updated' => 'saved', 'version_bumped' => '1']);
		}

		if (isset($_POST['cmp_create_cookie_policy_page'])) {
			wp_safe_redirect($this->cookie_policy_page_edit_link());

			exit;
		}
	}

	private function cookie_policy_page_edit_link(): string
	{
		$page_id = (int) $this->settings->get('cookie_policy_page_id');

		if ($page_id > 0 && 'page' === get_post_type($page_id)) {
			return (string) get_edit_post_link($page_id, 'raw');
		}

		$page_id = wp_insert_post([
			'post_type' => 'page',
			'post_status' => 'draft',
			'post_title' => __('Cookie Policy', 'consentimental'),
			'post_content' => sprintf(
				"<!-- wp:paragraph -->\n<p>%s</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n[cmp_services]\n<!-- /wp:shortcode -->",
				esc_html__('Below is a list of the cookies and similar technologies we use on this site, what they do, and how long they last.', 'consentimental'),
			),
		], true);

		if (is_wp_error($page_id)) {
			return admin_url('admin.php?page=' . self::PAGE_SLUG);
		}

		$this->settings->save(['cookie_policy_page_id' => $page_id]);

		return (string) get_edit_post_link($page_id, 'raw');
	}

	private function toggle_active(): void
	{
		$id = sanitize_key((string) wp_unslash($_POST['id'] ?? ''));
		$new_active = '1' === ($_POST['cmp_toggle_active'] ?? '');

		$this->activator->set_active($id, $new_active);
	}

	private function save_custom_service(): bool
	{
		$id = sanitize_key((string) wp_unslash($_POST['id'] ?? ''));
		$is_new = '' === $id;
		$existing = $is_new ? null : $this->custom_services->find($id);

		if ($is_new) {
			$id = sanitize_key((string) wp_unslash($_POST['name'] ?? '')) . '-' . strtolower(wp_generate_password(6, false, false));
		}

		$new_category = sanitize_key((string) wp_unslash($_POST['category'] ?? 'necessary'));
		$new_active = isset($_POST['active']);
		$sale_sharing = \Consentimental\Consent\CaliforniaPolicy::classification(wp_unslash($_POST['sale_sharing'] ?? null));

		$bumped = $is_new || (null !== $existing && (
			$existing->category !== $new_category
			|| $existing->active !== $new_active
			|| $existing->sale_sharing !== $sale_sharing
		));

		if ($bumped) {
			$this->draft->mark_meaningful();
		}

		$cookies = [];

		foreach ($this->lines_from_post('cookies') as $line) {
			$parts = array_map('trim', explode('|', $line));

			if ('' === ($parts[0] ?? '')) {
				continue;
			}

			$cookies[] = [
				'name' => $parts[0],
				'duration' => $parts[1] ?? '',
				'purpose' => $parts[2] ?? '',
				'domain' => '',
			];
		}

		$this->custom_services->save_one(Service::from_array([
			'id' => $id,
			'name' => sanitize_text_field((string) wp_unslash($_POST['name'] ?? '')),
			'provider' => sanitize_text_field((string) wp_unslash($_POST['provider'] ?? '')),
			'purpose' => sanitize_textarea_field((string) wp_unslash($_POST['purpose'] ?? '')),
			'category' => $new_category,
			'privacy_policy_url' => esc_url_raw((string) wp_unslash($_POST['privacy_policy_url'] ?? '')),
			'domains' => $this->lines_from_post('domains'),
			'script_patterns' => $this->lines_from_post('script_patterns'),
			'iframe_patterns' => $this->lines_from_post('iframe_patterns'),
			'cookies' => $cookies,
			'active' => $new_active,
			'sale_sharing' => $sale_sharing,
		]));

		return $bumped;
	}

	/**
	 * @return array<string, string> service id => script id
	 */
	private function linked_scripts(): array
	{
		$linked = [];

		foreach ($this->scripts->all() as $script) {
			if ('' !== $script->service && !isset($linked[$script->service])) {
				$linked[$script->service] = $script->id;
			}
		}

		return $linked;
	}

	/**
	 * @return string[]
	 */
	private function lines_from_post(string $field): array
	{
		$raw = isset($_POST[$field]) ? (string) wp_unslash($_POST[$field]) : '';

		return array_values(array_filter(array_map('trim', explode("\n", $raw)), static fn (string $line): bool => '' !== $line));
	}

	/**
	 * @return array{name?: string, domains?: string}
	 */
	private function prefill_from_request(): array
	{
		if (!isset($_GET['domain'])) {
			return [];
		}

		$domain = sanitize_text_field((string) wp_unslash($_GET['domain']));

		return '' === $domain ? [] : ['name' => $domain, 'domains' => $domain];
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

		$action = $this->current_action();
		$id = $this->current_id();

		if ('add' === $action) {
			$service = null;
			$categories = $this->categories->all();
			$prefill = $this->prefill_from_request();
			require \Consentimental\CMP_PLUGIN_DIR . 'templates/admin/service-form.php';

			return;
		}

		if ('edit' === $action && '' !== $id) {
			$service = $this->custom_services->find($id);

			if (null !== $service) {
				$categories = $this->categories->all();
				$prefill = [];
				require \Consentimental\CMP_PLUGIN_DIR . 'templates/admin/service-form.php';

				return;
			}
		}

		if ('override' === $action && '' !== $id) {
			$service = $this->services->get($id);

			if (null !== $service && $service->built_in) {
				$override = $this->overrides->get($id);
				$categories = $this->categories->all();
				require \Consentimental\CMP_PLUGIN_DIR . 'templates/admin/service-override-form.php';

				return;
			}
		}

		$services = $this->services->all();
		$override_ids = array_keys($this->overrides->all());
		$linked_scripts = $this->linked_scripts();
		$cookie_policy_page_id = (int) $this->settings->get('cookie_policy_page_id');
		$cookie_policy_page_exists = $cookie_policy_page_id > 0 && 'page' === get_post_type($cookie_policy_page_id);
		require \Consentimental\CMP_PLUGIN_DIR . 'templates/admin/services.php';
	}
}
