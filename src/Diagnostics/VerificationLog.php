<?php

declare(strict_types=1);

namespace Consentimental\Diagnostics;

if (!defined('ABSPATH')) {
	exit;
}

final class VerificationLog
{
	public const OPTION = 'cmp_verifications';
	public const TYPE_BROWSER = 'browser';
	public const TYPE_VENDOR = 'vendor';

	public const MAX_AGE_DAYS = 90;

	private const HISTORY_LIMIT = 20;

	/**
	 * @return array<string, array<string, string>> type => step key => instruction
	 */
	public static function steps(): array
	{
		return [
			self::TYPE_BROWSER => [
				'first_visit' => __('In a private window, before making any choice: the browser Network tab shows no requests to optional vendors, and no optional cookies or storage entries exist.', 'consentimental'),
				'reject' => __('After “Reject all”, and after reloading: still no optional requests, cookies or storage entries.', 'consentimental'),
				'accept' => __('After “Accept all”, or after allowing one service: that service’s scripts and embeds load, and nothing you did not allow loads.', 'consentimental'),
				'withdraw' => __('After withdrawing through the privacy settings: the service stops (the page reloads if configured) and its cookies are removed.', 'consentimental'),
				'reopen' => __('The privacy settings can be reopened with the keyboard alone, on each page template you use.', 'consentimental'),
			],
			self::TYPE_VENDOR => [
				'received' => __('After allowing the service, a test page view or event appeared in the vendor’s own dashboard or debug view.', 'consentimental'),
				'not_received' => __('A visit that rejected consent produced no page view or event in the vendor’s dashboard.', 'consentimental'),
			],
		];
	}

	/**
	 * @param string[] $confirmed step keys the administrator ticked
	 * @return array<string, mixed> the stored record
	 */
	public function record(string $type, bool $problem_found, array $confirmed, string $notes, int $config_version, int $user_id, string $user_login): array
	{
		$steps = array_keys(self::steps()[$type] ?? []);
		$confirmed = array_values(array_intersect($steps, $confirmed));
		$entry = [
			'type' => $type,
			'result' => $problem_found ? Finding::FAILED : ([] === array_diff($steps, $confirmed) ? Finding::PASSED : 'partial'),
			'confirmed' => $confirmed,
			'notes' => mb_substr($notes, 0, 1000),
			'config_version' => $config_version,
			'checked_at' => time(),
			'user_id' => $user_id,
			'user_login' => $user_login,
		];

		$all = $this->all();
		array_unshift($all, $entry);
		update_option(self::OPTION, array_slice($all, 0, self::HISTORY_LIMIT), false);

		return $entry;
	}

	/**
	 * @return list<array<string, mixed>> newest first
	 */
	public function all(): array
	{
		$stored = get_option(self::OPTION, []);

		return is_array($stored) ? array_values(array_filter($stored, 'is_array')) : [];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function latest(string $type): ?array
	{
		foreach ($this->all() as $entry) {
			if (($entry['type'] ?? '') === $type) {
				return $entry;
			}
		}

		return null;
	}
}
