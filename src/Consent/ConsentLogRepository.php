<?php

declare(strict_types=1);

namespace Consentimental\Consent;

if (!defined('ABSPATH')) {
	exit;
}

final class ConsentLogRepository
{
	public const CLEANUP_HOOK = 'cmp_consent_log_cleanup';
	private const DB_VERSION_OPTION = 'cmp_consent_log_db_version';
	private const DB_VERSION = 2;

	public function table_name(): string
	{
		global $wpdb;

		return $wpdb->prefix . 'cmp_consent_log';
	}

	public function create_table(): void
	{
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table = $this->table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			consent_id varchar(36) NOT NULL,
			recorded_at datetime NOT NULL,
			action varchar(20) NOT NULL,
			categories longtext NOT NULL,
			services longtext NOT NULL,
			config_version bigint(20) unsigned NOT NULL,
			ip_anonymized varchar(45) DEFAULT NULL,
			user_agent varchar(255) DEFAULT NULL,
			page_url varchar(255) DEFAULT NULL,
			disclosure_hash char(64) DEFAULT NULL,
			language varchar(20) DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY consent_id (consent_id),
			KEY recorded_at (recorded_at),
			KEY disclosure_hash (disclosure_hash)
		) {$charset_collate};";

		dbDelta($sql);

		update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
	}

	public function maybe_upgrade(): void
	{
		if ((int) get_option(self::DB_VERSION_OPTION, 0) < self::DB_VERSION) {
			$this->create_table();
		}
	}

	public function register(Settings $settings): void
	{
		add_action(self::CLEANUP_HOOK, function () use ($settings): void {
			$this->purge_older_than(max(1, (int) $settings->get('consent_log_retention_days')));
		});

		if (!wp_next_scheduled(self::CLEANUP_HOOK)) {
			wp_schedule_event(time(), 'daily', self::CLEANUP_HOOK);
		}
	}

	/**
	 * @param array<string, bool> $categories
	 * @param array<string, bool> $services
	 */
	public function record(
		string $consent_id,
		string $action,
		array $categories,
		array $services,
		int $config_version,
		?string $ip_anonymized,
		?string $user_agent,
		?string $page_url,
		?string $disclosure_hash = null,
		?string $language = null,
	): void {
		global $wpdb;

		if ($this->is_duplicate_of_latest($consent_id, $action, $categories, $services, $config_version, $disclosure_hash)) {
			return;
		}

		$inserted = $wpdb->insert($this->table_name(), [
			'consent_id' => $consent_id,
			'recorded_at' => current_time('mysql', true),
			'action' => $action,
			'categories' => (string) wp_json_encode($categories),
			'services' => (string) wp_json_encode($services),
			'config_version' => $config_version,
			'ip_anonymized' => $ip_anonymized,
			'user_agent' => $user_agent,
			'page_url' => $page_url,
			'disclosure_hash' => $disclosure_hash,
			'language' => null === $language ? null : substr($language, 0, 20),
		]);

		if (false === $inserted) {
			do_action('cmp_consent_log_insert_failed', $consent_id, $wpdb->last_error);
		}
	}

	/**
	 * @param array<string, bool> $categories
	 * @param array<string, bool> $services
	 */
	private function is_duplicate_of_latest(string $consent_id, string $action, array $categories, array $services, int $config_version, ?string $disclosure_hash): bool
	{
		global $wpdb;

		$latest = $wpdb->get_row($wpdb->prepare(
			"SELECT action, categories, services, config_version, disclosure_hash, recorded_at FROM {$this->table_name()} WHERE consent_id = %s ORDER BY id DESC LIMIT 1",
			$consent_id,
		), ARRAY_A);

		if (!is_array($latest)) {
			return false;
		}

		$recorded_at = strtotime((string) ($latest['recorded_at'] ?? '') . ' UTC');
		$age = false === $recorded_at ? -1 : time() - $recorded_at;

		return $age >= 0 && $age <= 5
			&& $latest['action'] === $action
			&& (int) $latest['config_version'] === $config_version
			&& ($latest['disclosure_hash'] ?? null) === $disclosure_hash
			&& $latest['categories'] === (string) wp_json_encode($categories)
			&& $latest['services'] === (string) wp_json_encode($services);
	}

	/**
	 * @return array{items: list<array<string, mixed>>, total: int}
	 */
	public function query(string $consent_id = '', int $page = 1, int $per_page = 20): array
	{
		global $wpdb;

		$table = $this->table_name();
		$page = max(1, $page);
		$offset = ($page - 1) * $per_page;

		if ('' !== $consent_id) {
			$total = (int) $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE consent_id = %s",
				$consent_id,
			));

			$rows = $wpdb->get_results($wpdb->prepare(
				"SELECT * FROM {$table} WHERE consent_id = %s ORDER BY recorded_at DESC, id DESC LIMIT %d OFFSET %d",
				$consent_id,
				$per_page,
				$offset,
			), ARRAY_A);
		} else {
			$total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

			$rows = $wpdb->get_results($wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY recorded_at DESC, id DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset,
			), ARRAY_A);
		}

		return [
			'items' => array_map($this->decode_row(...), is_array($rows) ? $rows : []),
			'total' => $total,
		];
	}

	/**
	 * @return \Generator<int, array<string, mixed>>
	 */
	public function each(string $consent_id = '', int $batch = 500): \Generator
	{
		global $wpdb;

		$table = $this->table_name();
		$after = 0;

		do {
			$rows = '' !== $consent_id
				? $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE consent_id = %s AND id > %d ORDER BY id ASC LIMIT %d", $consent_id, $after, $batch), ARRAY_A)
				: $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE id > %d ORDER BY id ASC LIMIT %d", $after, $batch), ARRAY_A);
			$rows = is_array($rows) ? $rows : [];

			foreach ($rows as $row) {
				$after = (int) $row['id'];
				yield $this->decode_row($row);
			}
		} while (count($rows) === $batch);
	}

	public function delete_by_consent_id(string $consent_id): int
	{
		global $wpdb;

		$deleted = $wpdb->delete($this->table_name(), ['consent_id' => $consent_id], ['%s']);

		return is_int($deleted) ? $deleted : 0;
	}

	public function purge_older_than(int $days): int
	{
		global $wpdb;

		$cutoff = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));

		$deleted = $wpdb->query($wpdb->prepare(
			"DELETE FROM {$this->table_name()} WHERE recorded_at < %s",
			$cutoff,
		));

		return is_int($deleted) ? $deleted : 0;
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function decode_row(array $row): array
	{
		$row['categories'] = json_decode((string) ($row['categories'] ?? '{}'), true) ?: [];
		$row['services'] = json_decode((string) ($row['services'] ?? '{}'), true) ?: [];
		$row['id'] = (int) ($row['id'] ?? 0);
		$row['config_version'] = (int) ($row['config_version'] ?? 0);

		return $row;
	}
}
