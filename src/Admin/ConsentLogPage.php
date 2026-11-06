<?php

declare(strict_types=1);

namespace Consentimental\Admin;

use Consentimental\Consent\ConsentExport;
use Consentimental\Consent\ConsentLogRepository;
use Consentimental\Consent\DisclosureSnapshotRepository;
use Consentimental\Consent\Settings;

if (!defined('ABSPATH')) {
	exit;
}

final class ConsentLogPage
{
	public const PAGE_SLUG = 'consentimental-consent-log';
	private const PER_PAGE = 20;

	public function __construct(
		private readonly ConsentLogRepository $log,
		private readonly Settings $settings,
		private readonly DisclosureSnapshotRepository $snapshots,
	) {
	}

	public function register(): void
	{
		add_action('admin_menu', $this->add_menu(...));
		add_action('admin_init', $this->maybe_handle_erasure(...));
		add_action('admin_init', $this->maybe_save_settings(...));
		add_action('admin_post_cmp_export_consent_log', $this->export(...));
	}

	private function export(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to export consent records.', 'consentimental'), '', ['response' => 403]);
		}

		check_admin_referer('cmp_export_consent_log');

		$consent_id = sanitize_text_field((string) wp_unslash($_GET['consent_id'] ?? ''));
		$format = 'json' === ($_GET['format'] ?? '') ? 'json' : 'csv';
		$filename = 'consent-log-' . gmdate('Ymd-His') . '.' . $format;

		nocache_headers();
		header('Content-Type: ' . ('json' === $format ? 'application/json' : 'text/csv') . '; charset=utf-8');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('X-Content-Type-Options: nosniff');

		$out = fopen('php://output', 'w');

		if ('csv' === $format) {
			fputcsv($out, ConsentExport::CSV_COLUMNS, ',', '"', '');

			foreach ($this->log->each($consent_id) as $row) {
				fputcsv($out, ConsentExport::csv_row($row), ',', '"', '');
			}
		} else {
			$hashes = [];
			$flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
			fwrite($out, '{"meta":' . wp_json_encode(ConsentExport::json_meta(home_url('/'), \Consentimental\CMP_VERSION, time(), $consent_id), $flags) . ',"records":[');
			$first = true;

			foreach ($this->log->each($consent_id) as $row) {
				fwrite($out, ($first ? '' : ',') . wp_json_encode($row, $flags));
				$first = false;

				if (!empty($row['disclosure_hash'])) {
					$hashes[$row['disclosure_hash']] = true;
				}
			}

			fwrite($out, '],"snapshots":[');
			$first = true;

			foreach (array_chunk(array_keys($hashes), 100) as $chunk) {
				foreach ($this->snapshots->get_many($chunk) as $snapshot) {
					fwrite($out, ($first ? '' : ',') . wp_json_encode($snapshot, $flags));
					$first = false;
				}
			}

			fwrite($out, ']}');
		}

		fclose($out);

		exit;
	}

	private function maybe_save_settings(): void
	{
		if (!current_user_can('manage_options') || !isset($_POST['cmp_save_consent_log_settings'])) {
			return;
		}

		check_admin_referer('cmp_consent_log_settings', 'cmp_consent_log_settings_nonce');

		$retention_days = isset($_POST['consent_log_retention_days']) ? absint(wp_unslash($_POST['consent_log_retention_days'])) : 1095;

		$this->settings->save([
			'consent_log_enabled' => isset($_POST['consent_log_enabled']),
			'consent_log_retention_days' => max(1, $retention_days),
		]);

		wp_safe_redirect(add_query_arg(['page' => self::PAGE_SLUG, 'settings_updated' => '1'], admin_url('admin.php')));

		exit;
	}

	private function maybe_handle_erasure(): void
	{
		if (!current_user_can('manage_options') || !isset($_POST['cmp_delete_consent_records'])) {
			return;
		}

		check_admin_referer('cmp_consent_log_erase', 'cmp_consent_log_erase_nonce');

		$consent_id = sanitize_text_field((string) wp_unslash($_POST['consent_id'] ?? ''));

		if ('' !== $consent_id) {
			$this->log->delete_by_consent_id($consent_id);
		}

		wp_safe_redirect(add_query_arg(['page' => self::PAGE_SLUG, 'erased' => '1'], admin_url('admin.php')));

		exit;
	}

	public static function source_label(string $source): string
	{
		return match ($source) {
			DisclosureSnapshotRepository::SOURCE_PUBLISH => __('First stored at publish (site default language)', 'consentimental'),
			DisclosureSnapshotRepository::SOURCE_SETTINGS => __('First stored when settings changed (site default language)', 'consentimental'),
			DisclosureSnapshotRepository::SOURCE_RENDER => __('First stored when shown to a visitor', 'consentimental'),
			default => $source,
		};
	}

	private function add_menu(): void
	{
		add_submenu_page(
			parent_slug: AdminMenu::PAGE_SLUG,
			page_title: __('Consent log', 'consentimental'),
			menu_title: __('Consent log', 'consentimental'),
			capability: 'manage_options',
			menu_slug: self::PAGE_SLUG,
			callback: $this->render(...),
		);
	}

	private function render(): void
	{
		if (!current_user_can('manage_options')) {
			return;
		}

		$snapshot_hash = isset($_GET['snapshot']) ? sanitize_key((string) wp_unslash($_GET['snapshot'])) : '';

		if ('' !== $snapshot_hash) {
			$snapshot = $this->snapshots->get($snapshot_hash);
			require \Consentimental\CMP_PLUGIN_DIR . 'templates/admin/disclosure-snapshot.php';

			return;
		}

		$consent_id = isset($_GET['consent_id']) ? sanitize_text_field((string) wp_unslash($_GET['consent_id'])) : '';
		$recent_snapshots = $this->snapshots->recent(10);
		$page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;

		$result = $this->log->query($consent_id, $page, self::PER_PAGE);
		$items = $result['items'];
		$total = $result['total'];
		$total_pages = (int) max(1, ceil($total / self::PER_PAGE));
		$consent_log_enabled = (bool) $this->settings->get('consent_log_enabled');
		$consent_log_retention_days = (int) $this->settings->get('consent_log_retention_days');

		require \Consentimental\CMP_PLUGIN_DIR . 'templates/admin/consent-log.php';
	}
}
