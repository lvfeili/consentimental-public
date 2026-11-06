<?php

declare(strict_types=1);

namespace Consentimental\Admin;

use Consentimental\Diagnostics\SiteScanner;
use Consentimental\Services\ServiceActivator;
use Consentimental\Services\ServiceRegistry;

if (!defined('ABSPATH')) {
	exit;
}

final class ScannerPage
{
	public const PAGE_SLUG = 'consentimental-scanner';
	public const RESULTS_OPTION = 'cmp_scan_results';

	public function __construct(
		private readonly SiteScanner $scanner,
		private readonly ServiceActivator $activator,
		private readonly ServiceRegistry $services,
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
			page_title: __('Scan site', 'consentimental'),
			menu_title: __('Scan site', 'consentimental'),
			capability: 'manage_options',
			menu_slug: self::PAGE_SLUG,
			callback: $this->render(...),
		);
	}

	private function maybe_handle_form(): void
	{
		if (!current_user_can('manage_options') || !isset($_POST['cmp_scanner_nonce'])) {
			return;
		}

		check_admin_referer('cmp_scanner', 'cmp_scanner_nonce');

		if (isset($_POST['cmp_run_scan'])) {
			if (function_exists('set_time_limit')) {
				@set_time_limit(120);
			}

			self::store($this->scanner->scan());
			$this->redirect(['scanned' => '1']);
		}

		if (isset($_POST['cmp_scanner_activate'])) {
			$id = sanitize_key((string) wp_unslash($_POST['id'] ?? ''));
			$new_active = '1' === (string) wp_unslash($_POST['cmp_scanner_activate']);
			$this->activator->set_active($id, $new_active);
			$this->redirect(['updated' => 'saved', 'version_bumped' => '1']);
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

		$results = self::load($this->services);

		require \Consentimental\CMP_PLUGIN_DIR . 'templates/admin/scanner.php';
	}

	/**
	 * @param array{pages_scanned: int, matched: array<string, array{service: \Consentimental\Services\Service, pages: string[]}>, unknown_domains: array<string, string[]>} $results
	 */
	public static function store(array $results): void
	{
		update_option(self::RESULTS_OPTION, [
			'scanned_at' => time(),
			'pages_scanned' => $results['pages_scanned'],
			'matched' => array_map(static fn (array $entry): array => $entry['pages'], $results['matched']),
			'unknown_domains' => $results['unknown_domains'],
		], false);
	}

	/**
	 * @return array{scanned_at: int, pages_scanned: int, matched: array<string, array{service: \Consentimental\Services\Service, pages: string[]}>, unknown_domains: array<string, string[]>}|null
	 */
	public static function load(\Consentimental\Services\ServiceRegistry $services): ?array
	{
		$stored = get_option(self::RESULTS_OPTION, null);

		if (!is_array($stored) || !isset($stored['scanned_at'], $stored['matched'])) {
			return null;
		}

		$matched = [];

		foreach ((array) $stored['matched'] as $id => $pages) {
			$service = $services->get((string) $id);

			if (null !== $service) {
				$matched[(string) $id] = ['service' => $service, 'pages' => (array) $pages];
			}
		}

		return [
			'scanned_at' => (int) $stored['scanned_at'],
			'pages_scanned' => (int) ($stored['pages_scanned'] ?? 0),
			'matched' => $matched,
			'unknown_domains' => (array) ($stored['unknown_domains'] ?? []),
		];
	}
}
