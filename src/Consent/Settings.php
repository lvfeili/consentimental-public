<?php

declare(strict_types=1);

namespace Consentimental\Consent;

if (!defined('ABSPATH')) {
	exit;
}

final class Settings
{
	public const OPTION_NAME = 'cmp_settings';
	public const VERSION_OPTION_NAME = 'cmp_version';

	public const VERSION_HISTORY_OPTION = 'cmp_version_history';

	/**
	 * @return array<string, mixed>
	 */
	public static function defaults(): array
	{
		return [
			'legal_framework' => 'custom',
			'california_enabled' => false,
			'privacy_rights_page_id' => 0,
			'consent_expiry_days' => 180,
			'floating_button_enabled' => true,
			'require_decision_before_interaction' => true,
			'reload_on_withdrawal' => true,
			'consent_mode_type' => 'basic',
			'banner_title' => '',
			'banner_text' => '',
			'customize_text' => '',
			'banner_logo_url' => '',
			'floating_button_icon' => 'cookie',
			'floating_button_icon_emoji' => '',
			'floating_button_icon_image_url' => '',
			'floating_button_desktop_side' => 'right',
			'floating_button_desktop_edge' => '16',
			'floating_button_desktop_bottom' => '16',
			'floating_button_mobile_side' => 'right',
			'floating_button_mobile_edge' => '16',
			'floating_button_mobile_bottom' => '16',
			'banner_color_bg' => '',
			'banner_color_text' => '',
			'banner_color_muted' => '',
			'banner_color_border' => '',
			'banner_color_primary' => '',
			'banner_color_primary_text' => '',
			'banner_color_focus' => '',
			'banner_radius' => '',
			'banner_position' => 'bottom_bar',
			'banner_font_family' => '',
			'banner_font_size' => '',
			'button_shape' => 'rounded',
			'banner_logo_width' => '',
			'custom_css' => '',
			'cookie_policy_page_id' => 0,
			'privacy_policy_page_id' => 0,
			'delete_data_on_uninstall' => false,
			'consent_log_retention_days' => 1095,
			'consent_log_enabled' => true,
		];
	}

	public function seed_defaults(): void
	{
		if (false === get_option(self::OPTION_NAME, false)) {
			add_option(self::OPTION_NAME, self::defaults());
		}

		if (false === get_option(self::VERSION_OPTION_NAME, false)) {
			add_option(self::VERSION_OPTION_NAME, 1);
			$this->record_published(1);
		}
	}

	private function record_published(int $version): void
	{
		$history = get_option(self::VERSION_HISTORY_OPTION, []);
		$history = is_array($history) ? $history : [];
		$history[$version] = time();
		update_option(self::VERSION_HISTORY_OPTION, $history, false);
	}

	public function published_at(int $version): ?int
	{
		$history = get_option(self::VERSION_HISTORY_OPTION, []);

		return is_array($history) && isset($history[$version]) ? (int) $history[$version] : null;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function all(): array
	{
		$stored = get_option(self::OPTION_NAME, self::defaults());

		return FrameworkPolicy::settings(is_array($stored) ? array_merge(self::defaults(), $stored) : self::defaults());
	}

	public function get(string $key): mixed
	{
		return $this->all()[$key] ?? null;
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	public function save(array $settings): void
	{
		$previous = $this->all();
		if ($this->enforces_framework() && !array_key_exists('legal_framework', $settings)) {
			foreach (['consent_mode_type', 'floating_button_enabled', 'require_decision_before_interaction', 'reload_on_withdrawal'] as $key) {
				unset($settings[$key]);
			}
		}
		if ($previous['california_enabled'] && !array_key_exists('california_enabled', $settings)) {
			unset($settings['consent_mode_type'], $settings['reload_on_withdrawal'], $settings['require_decision_before_interaction'], $settings['floating_button_enabled']);
		}
		$stored = get_option(self::OPTION_NAME, []);
		$next = array_merge(self::defaults(), is_array($stored) ? $stored : [], $settings);
		$next['legal_framework'] = FrameworkPolicy::normalize($next['legal_framework']);
		$next['california_enabled'] = (bool) $next['california_enabled'];
		update_option(self::OPTION_NAME, $next);

		if ($previous['legal_framework'] !== $next['legal_framework'] || $previous['california_enabled'] !== $next['california_enabled']) {
			$this->bump_config_version();
		} elseif ($previous !== FrameworkPolicy::settings($next)) {
			do_action('cmp_settings_changed');
		}
	}

	public function enforces_framework(): bool
	{
		return FrameworkPolicy::EU_GERMANY === $this->get('legal_framework');
	}

	public function config_version(): int
	{
		return (int) get_option(self::VERSION_OPTION_NAME, 1);
	}

	public function bump_config_version(): int
	{
		$next = $this->config_version() + 1;
		update_option(self::VERSION_OPTION_NAME, $next);
		$this->record_published($next);
		do_action('cmp_settings_changed');

		return $next;
	}
}
