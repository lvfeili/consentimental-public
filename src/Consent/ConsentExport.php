<?php

declare(strict_types=1);

namespace Consentimental\Consent;

if (!defined('ABSPATH')) {
	exit;
}

final class ConsentExport
{
	public const CSV_COLUMNS = [
		'id',
		'consent_id',
		'recorded_at_utc',
		'action',
		'config_version',
		'disclosure_hash',
		'language',
		'categories',
		'services',
		'ip_anonymized',
		'user_agent',
		'page_url',
	];

	/**
	 * @param array<string, mixed> $row a decoded ConsentLogRepository row
	 * @return list<string>
	 */
	public static function csv_row(array $row): array
	{
		$values = [
			(string) $row['id'],
			(string) $row['consent_id'],
			(string) $row['recorded_at'],
			(string) $row['action'],
			(string) $row['config_version'],
			(string) ($row['disclosure_hash'] ?? ''),
			(string) ($row['language'] ?? ''),
			(string) json_encode($row['categories'] ?? [], JSON_UNESCAPED_SLASHES),
			(string) json_encode($row['services'] ?? [], JSON_UNESCAPED_SLASHES),
			(string) ($row['ip_anonymized'] ?? ''),
			(string) ($row['user_agent'] ?? ''),
			(string) ($row['page_url'] ?? ''),
		];

		return array_map(self::spreadsheet_safe(...), $values);
	}

	public static function spreadsheet_safe(string $value): string
	{
		return '' !== $value && str_contains("=+-@\t\r", $value[0]) ? "'" . $value : $value;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function json_meta(string $site_url, string $plugin_version, int $generated_at, string $filter): array
	{
		return [
			'format' => 'consentimental-consent-export',
			'format_version' => 1,
			'site_url' => $site_url,
			'plugin_version' => $plugin_version,
			'generated_at_utc' => gmdate('Y-m-d\TH:i:s\Z', $generated_at),
			'filter_consent_id' => $filter,
			'notes' => [
				'Records are what a visitor\'s browser submitted to an anonymous endpoint. They are client assertions, not authenticated proof of identity.',
				'disclosure_hash identifies the stored snapshot of the wording, categories, services and language the page showed. It is only set when the browser named a snapshot stored for the same configuration version.',
				'A null disclosure_hash means the disclosure is unknown (for example, records made before snapshots were introduced). It has not been reconstructed from current settings.',
				'A snapshot\'s "intact" flag means its stored content still matches its hash. It does not protect against someone with database access rewriting both.',
			],
		];
	}
}
