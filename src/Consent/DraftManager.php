<?php

declare(strict_types=1);

namespace Consentimental\Consent;

use Consentimental\Scripts\CustomScriptRepository;
use Consentimental\Services\CustomServiceRepository;
use Consentimental\Services\ServiceOverrideRepository;

if (!defined('ABSPATH')) {
	exit;
}

final class DraftManager
{
	private const MEANINGFUL_OPTION = 'cmp_draft_meaningful';

	/**
	 * @var array<string, string> section label => live option name
	 */
	private const SECTIONS = [
		'categories' => CategoryRepository::OPTION_NAME,
		'custom_services' => CustomServiceRepository::OPTION_NAME,
		'service_overrides' => ServiceOverrideRepository::OPTION_NAME,
		'custom_scripts' => CustomScriptRepository::OPTION_NAME,
	];

	public function __construct(private readonly Settings $settings)
	{
	}

	public static function draft_option_name(string $live_option_name): string
	{
		return $live_option_name . '_draft';
	}

	public function mark_meaningful(): void
	{
		update_option(self::MEANINGFUL_OPTION, true);
	}

	public function is_meaningful(): bool
	{
		return (bool) get_option(self::MEANINGFUL_OPTION, false);
	}

	/**
	 * @return array<string, bool> section => has a pending (unpublished) draft
	 */
	public function pending_sections(): array
	{
		$pending = [];

		foreach (self::SECTIONS as $section => $live_option) {
			$pending[$section] = null !== get_option(self::draft_option_name($live_option), null);
		}

		return $pending;
	}

	public function has_pending(): bool
	{
		return in_array(true, $this->pending_sections(), true);
	}

	/**
	 * @return array{version_bumped: bool, version: int}
	 */
	public function publish(): array
	{
		foreach (self::SECTIONS as $live_option) {
			$draft_option = self::draft_option_name($live_option);
			$draft_value = get_option($draft_option, null);

			if (null !== $draft_value) {
				update_option($live_option, $draft_value);
				delete_option($draft_option);
			}
		}

		$meaningful = $this->is_meaningful();
		$version = $meaningful ? $this->settings->bump_config_version() : $this->settings->config_version();

		delete_option(self::MEANINGFUL_OPTION);

		do_action('cmp_config_published', $meaningful, $version);

		return ['version_bumped' => $meaningful, 'version' => $version];
	}

	public function discard(): void
	{
		foreach (self::SECTIONS as $live_option) {
			delete_option(self::draft_option_name($live_option));
		}

		delete_option(self::MEANINGFUL_OPTION);
	}
}
