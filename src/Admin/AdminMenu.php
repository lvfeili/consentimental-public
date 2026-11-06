<?php

declare(strict_types=1);

namespace Consentimental\Admin;

use Consentimental\Consent\CategoryRepository;
use Consentimental\Consent\DraftManager;
use Consentimental\Consent\Settings;
use Consentimental\Services\ServiceRegistry;

if (!defined('ABSPATH')) {
	exit;
}

final class AdminMenu
{
	public const PAGE_SLUG = 'consentimental';

	public function __construct(
		private readonly CategoryRepository $categories,
		private readonly ServiceRegistry $services,
		private readonly Settings $settings,
		private readonly DraftManager $draft,
	) {
	}

	public function register(): void
	{
		add_action('admin_menu', $this->add_menu(...));
		add_action('admin_enqueue_scripts', $this->enqueue_assets(...));
		add_action('admin_init', $this->maybe_save_settings(...));
		add_action('admin_notices', $this->maybe_show_pending_notice(...));
	}

	private function maybe_show_pending_notice(): void
	{
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;

		if (null === $screen || !str_contains((string) $screen->id, 'consentimental')) {
			return;
		}

		if (PublishPage::PAGE_SLUG === ($_GET['page'] ?? '') || !$this->draft->has_pending()) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__('Consentimental has unpublished changes.', 'consentimental'),
			esc_url(admin_url('admin.php?page=' . PublishPage::PAGE_SLUG)),
			esc_html__('Review and publish', 'consentimental'),
		);
	}

	private function maybe_save_settings(): void
	{
		if (isset($_POST['cmp_save_california'])) {
			check_admin_referer('cmp_save_california');
			if (!current_user_can('manage_options')) { return; }
			$page_id = absint(wp_unslash($_POST['privacy_rights_page_id'] ?? 0));
			$this->settings->save([
				'california_enabled' => isset($_POST['california_enabled']),
				'privacy_rights_page_id' => $page_id > 0 && 'page' === get_post_type($page_id) && 'publish' === get_post_status($page_id) ? $page_id : 0,
			]);
			$this->notice_saved();
		}

		if (isset($_POST['cmp_save_framework'])) {
			check_admin_referer('cmp_save_framework');
			if (!current_user_can('manage_options')) {
				return;
			}
			$this->settings->save(['legal_framework' => wp_unslash($_POST['legal_framework'] ?? 'custom')]);
			$this->notice_saved();
		}

		if (isset($_POST['cmp_save_consent_mode'])) {
			check_admin_referer('cmp_save_consent_mode');

			if (!current_user_can('manage_options')) {
				return;
			}

			$previous_type = (string) $this->settings->get('consent_mode_type');
			$type = isset($_POST['consent_mode_type']) && 'advanced' === $_POST['consent_mode_type'] ? 'advanced' : 'basic';
			$this->settings->save(['consent_mode_type' => $type]);

			if ($this->settings->get('consent_mode_type') !== $previous_type) {
				$this->settings->bump_config_version();
			}

			$this->notice_saved();
		}

		if (isset($_POST['cmp_save_general_settings'])) {
			check_admin_referer('cmp_save_general_settings');

			if (!current_user_can('manage_options')) {
				return;
			}

			$expiry_days = isset($_POST['consent_expiry_days']) ? absint(wp_unslash($_POST['consent_expiry_days'])) : 180;

			$this->settings->save([
				'consent_expiry_days' => max(1, $expiry_days),
				'floating_button_enabled' => isset($_POST['floating_button_enabled']),
				'require_decision_before_interaction' => isset($_POST['require_decision_before_interaction']),
				'reload_on_withdrawal' => isset($_POST['reload_on_withdrawal']),
				'delete_data_on_uninstall' => isset($_POST['delete_data_on_uninstall']),
			]);

			$this->notice_saved();
		}
	}

	private function notice_saved(): void
	{
		add_action('admin_notices', static function (): void {
			echo '<div class="notice notice-success is-dismissible"><p>' .
				esc_html__('Settings saved.', 'consentimental') .
				'</p></div>';
		});
	}

	private function add_menu(): void
	{
		add_menu_page(
			page_title: __('Consentimental', 'consentimental'),
			menu_title: __('Consentimental', 'consentimental'),
			capability: 'manage_options',
			menu_slug: self::PAGE_SLUG,
			callback: $this->render_overview(...),
			icon_url: 'dashicons-privacy',
			position: 80,
		);
	}

	private function enqueue_assets(string $hook): void
	{
		if ('toplevel_page_' . self::PAGE_SLUG !== $hook && !str_starts_with($hook, 'consentimental_page_')) {
			return;
		}

		wp_enqueue_style(
			'cmp-admin',
			\Consentimental\CMP_PLUGIN_URL . 'assets/css/admin.css',
			[],
			\Consentimental\CMP_VERSION,
		);
	}

	private function render_overview(): void
	{
		if (!current_user_can('manage_options')) {
			return;
		}

		$categories = $this->categories->all();
		$legal_framework = (string) $this->settings->get('legal_framework');
		$framework_enforced = $this->settings->enforces_framework();
		$california_enabled = (bool) $this->settings->get('california_enabled');
		$privacy_rights_page_id = (int) $this->settings->get('privacy_rights_page_id');
		$unreviewed = array_filter($this->services->all(), static fn ($service) => $service->active && 'unreviewed' === $service->sale_sharing);
		$services = $this->services->all();
		$consent_mode_type = (string) $this->settings->get('consent_mode_type');
		$consent_expiry_days = (int) $this->settings->get('consent_expiry_days');
		$floating_button_enabled = (bool) $this->settings->get('floating_button_enabled');
		$require_decision_before_interaction = (bool) $this->settings->get('require_decision_before_interaction');
		$reload_on_withdrawal = (bool) $this->settings->get('reload_on_withdrawal');
		$delete_data_on_uninstall = (bool) $this->settings->get('delete_data_on_uninstall');

		require \Consentimental\CMP_PLUGIN_DIR . 'templates/admin/dashboard.php';
	}
}
