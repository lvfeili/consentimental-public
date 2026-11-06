<?php

declare(strict_types=1);

namespace Consentimental\Admin;

use Consentimental\Consent\Settings;
use Consentimental\Diagnostics\Finding;
use Consentimental\Diagnostics\StatusReport;
use Consentimental\Diagnostics\VerificationLog;

if (!defined('ABSPATH')) {
	exit;
}

final class StatusPage
{
	public const PAGE_SLUG = 'consentimental-status';

	public function __construct(
		private readonly StatusReport $report,
		private readonly VerificationLog $verifications,
		private readonly Settings $settings,
	) {
	}

	public function register(): void
	{
		add_action('admin_menu', $this->add_menu(...), 15);
		add_action('admin_init', $this->maybe_record(...));
	}

	private function add_menu(): void
	{
		add_submenu_page(
			parent_slug: AdminMenu::PAGE_SLUG,
			page_title: __('Status & verification', 'consentimental'),
			menu_title: __('Status', 'consentimental'),
			capability: 'manage_options',
			menu_slug: self::PAGE_SLUG,
			callback: $this->render(...),
		);
	}

	private function maybe_record(): void
	{
		if (!isset($_POST['cmp_record_verification']) || !current_user_can('manage_options')) {
			return;
		}

		check_admin_referer('cmp_record_verification', 'cmp_verification_nonce');

		$type = sanitize_key((string) wp_unslash($_POST['verification_type'] ?? ''));

		if (!isset(VerificationLog::steps()[$type])) {
			wp_die(esc_html__('Unknown verification type.', 'consentimental'), '', ['response' => 400]);
		}

		$user = wp_get_current_user();
		$this->verifications->record(
			$type,
			'problem' === ($_POST['verification_result'] ?? ''),
			array_map('sanitize_key', (array) wp_unslash($_POST['confirmed'] ?? [])),
			sanitize_textarea_field((string) wp_unslash($_POST['notes'] ?? '')),
			$this->settings->config_version(),
			(int) $user->ID,
			(string) $user->user_login,
		);

		$redirect = wp_get_referer() ?: admin_url('admin.php?page=' . self::PAGE_SLUG);
		wp_safe_redirect(add_query_arg('recorded', $type, $redirect) . '#cmp-verify-' . $type);

		exit;
	}

	private function render(): void
	{
		if (!current_user_can('manage_options')) {
			return;
		}

		$findings = $this->report->findings();
		$summary = Finding::summarize($findings);
		$verifications = $this->verifications->all();
		$config_version = $this->settings->config_version();

		require \Consentimental\CMP_PLUGIN_DIR . 'templates/admin/status.php';
	}
}
