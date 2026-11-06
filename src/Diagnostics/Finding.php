<?php

declare(strict_types=1);

namespace Consentimental\Diagnostics;

if (!defined('ABSPATH')) {
	exit;
}

final class Finding
{
	public const PASSED = 'passed';
	public const FAILED = 'failed';
	public const WARNING = 'warning';
	public const NOT_CHECKED = 'not_checked';
	public const NOT_APPLICABLE = 'not_applicable';

	public const AREA_CONFIGURATION = 'configuration';
	public const AREA_DISCOVERY = 'discovery';
	public const AREA_PROXY = 'proxy';
	public const AREA_BROWSER = 'browser';
	public const AREA_VENDOR = 'vendor';

	public const EVIDENCE_AUTOMATED = 'automated';
	public const EVIDENCE_ADMINISTRATOR = 'administrator';
	public const EVIDENCE_NONE = 'none';

	public function __construct(
		public readonly string $id,
		public readonly string $area,
		public readonly string $status,
		public readonly string $title,
		public readonly string $scope,
		public readonly ?int $checked_at,
		public readonly string $evidence = self::EVIDENCE_AUTOMATED,
		public readonly string $problem = '',
		public readonly string $impact = '',
		public readonly string $action = '',
		public readonly string $action_url = '',
		/**
		 * @var string[] specific items (service names, script names, hosts)
		 */
		public readonly array $items = [],
		/**
		 * @var array<int, string> item index => URL of the screen that fixes it
		 */
		public readonly array $item_urls = [],
	) {
	}

	/**
	 * @param Finding[] $findings
	 * @return array{state: string, counts: array<string, int>}
	 *         state: 'problems' if anything failed, 'incomplete' if anything was
	 *         not checked or has a warning, else 'passed'. Never 'passed' while an
	 *         applicable check was skipped.
	 */
	public static function summarize(array $findings): array
	{
		$counts = [self::PASSED => 0, self::FAILED => 0, self::WARNING => 0, self::NOT_CHECKED => 0, self::NOT_APPLICABLE => 0];

		foreach ($findings as $finding) {
			++$counts[$finding->status];
		}

		$state = match (true) {
			$counts[self::FAILED] > 0 => 'problems',
			$counts[self::NOT_CHECKED] > 0 || $counts[self::WARNING] > 0 => 'incomplete',
			default => 'passed',
		};

		return ['state' => $state, 'counts' => $counts];
	}
}
