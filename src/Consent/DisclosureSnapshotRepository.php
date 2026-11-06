<?php

declare(strict_types=1);

namespace Consentimental\Consent;

if (!defined('ABSPATH')) {
	exit;
}

final class DisclosureSnapshotRepository
{
	private const DB_VERSION_OPTION = 'cmp_disclosure_db_version';
	private const DB_VERSION = 1;

	private const KNOWN_OPTION = 'cmp_disclosure_known';
	private const KNOWN_LIMIT = 50;

	public const SOURCE_PUBLISH = 'publish';
	public const SOURCE_SETTINGS = 'settings';
	public const SOURCE_RENDER = 'render';

	public function table_name(): string
	{
		global $wpdb;

		return $wpdb->prefix . 'cmp_disclosure_snapshots';
	}

	public function create_table(): void
	{
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table = $this->table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			snapshot_hash char(64) NOT NULL,
			config_version bigint(20) unsigned NOT NULL,
			locale varchar(20) NOT NULL DEFAULT '',
			language_code varchar(20) NOT NULL DEFAULT '',
			source varchar(20) NOT NULL,
			captured_at datetime NOT NULL,
			config_published_at datetime DEFAULT NULL,
			content longtext NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY snapshot_hash (snapshot_hash),
			KEY config_version (config_version)
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

	/**
	 * @param array<string, mixed> $snapshot a DisclosureSnapshot::build() result
	 */
	public function ensure(array $snapshot, string $source, ?int $config_published_at): string
	{
		global $wpdb;

		$hash = DisclosureSnapshot::hash($snapshot);
		$known = get_option(self::KNOWN_OPTION, []);
		$known = is_array($known) ? $known : [];

		if (in_array($hash, $known, true)) {
			return $hash;
		}

		$exists = (int) $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table_name()} WHERE snapshot_hash = %s",
			$hash,
		));

		if (0 === $exists) {
			$wpdb->insert($this->table_name(), [
				'snapshot_hash' => $hash,
				'config_version' => (int) $snapshot['config_version'],
				'locale' => substr((string) $snapshot['language']['locale'], 0, 20),
				'language_code' => substr((string) $snapshot['language']['code'], 0, 20),
				'source' => $source,
				'captured_at' => gmdate('Y-m-d H:i:s'),
				'config_published_at' => null === $config_published_at ? null : gmdate('Y-m-d H:i:s', $config_published_at),
				'content' => DisclosureSnapshot::canonical_json($snapshot),
			]);
		}

		array_unshift($known, $hash);
		update_option(self::KNOWN_OPTION, array_slice(array_values(array_unique($known)), 0, self::KNOWN_LIMIT), true);

		return $hash;
	}

	/**
	 * @return array<string, mixed>|null the row, with 'content' decoded and an
	 *                                   'intact' flag (stored content still hashes
	 *                                   to its key)
	 */
	public function get(string $hash): ?array
	{
		global $wpdb;

		if (!DisclosureSnapshot::is_hash($hash)) {
			return null;
		}

		$row = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$this->table_name()} WHERE snapshot_hash = %s",
			$hash,
		), ARRAY_A);

		return is_array($row) ? $this->decode_row($row) : null;
	}

	/**
	 * @return list<array<string, mixed>> newest first, content not decoded
	 */
	public function recent(int $limit = 20): array
	{
		global $wpdb;

		$rows = $wpdb->get_results($wpdb->prepare(
			"SELECT id, snapshot_hash, config_version, locale, language_code, source, captured_at, config_published_at FROM {$this->table_name()} ORDER BY id DESC LIMIT %d",
			$limit,
		), ARRAY_A);

		return is_array($rows) ? $rows : [];
	}

	/**
	 * @param string[] $hashes
	 * @return list<array<string, mixed>>
	 */
	public function get_many(array $hashes): array
	{
		global $wpdb;

		$hashes = array_values(array_filter(array_unique($hashes), DisclosureSnapshot::is_hash(...)));

		if ([] === $hashes) {
			return [];
		}

		$placeholders = implode(',', array_fill(0, count($hashes), '%s'));
		$rows = $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM {$this->table_name()} WHERE snapshot_hash IN ({$placeholders}) ORDER BY id ASC",
			...$hashes,
		), ARRAY_A);

		return array_map($this->decode_row(...), is_array($rows) ? $rows : []);
	}

	public function purge_unreferenced(int $days, string $log_table): int
	{
		global $wpdb;

		$table = $this->table_name();
		$keep = array_map('intval', (array) $wpdb->get_col("SELECT MAX(id) FROM {$table} GROUP BY locale, language_code"));
		$keep_sql = [] === $keep ? '0' : implode(',', $keep);
		$cutoff = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));

		$deleted = $wpdb->query($wpdb->prepare(
			"DELETE s FROM {$table} s LEFT JOIN {$log_table} l ON l.disclosure_hash = s.snapshot_hash
			WHERE l.id IS NULL AND s.captured_at < %s AND s.id NOT IN ({$keep_sql})",
			$cutoff,
		));

		if (is_int($deleted) && $deleted > 0) {
			delete_option(self::KNOWN_OPTION);
		}

		return is_int($deleted) ? $deleted : 0;
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function decode_row(array $row): array
	{
		$raw = (string) ($row['content'] ?? '');
		$content = json_decode($raw, true);
		$row['content'] = is_array($content) ? $content : [];
		$row['intact'] = is_array($content) && hash('sha256', $raw) === $row['snapshot_hash'];
		$row['config_version'] = (int) $row['config_version'];

		return $row;
	}
}
