<?php

declare(strict_types=1);

namespace Consentimental\Consent;

if (!defined('ABSPATH')) {
	exit;
}

final class FrameworkPolicy
{
	public const EU_GERMANY = 'eu_germany';

	public static function normalize(mixed $value): string
	{
		return self::EU_GERMANY === $value ? self::EU_GERMANY : 'custom';
	}

	public static function settings(array $settings): array
	{
		$settings['legal_framework'] = self::normalize($settings['legal_framework'] ?? null);
		if (!empty($settings['california_enabled'])) {
			$settings['consent_mode_type'] = 'basic';
			$settings['reload_on_withdrawal'] = true;
			$settings['require_decision_before_interaction'] = false;
			$settings['floating_button_enabled'] = true;
		}
		if (self::EU_GERMANY === $settings['legal_framework']) {
			$settings['consent_mode_type'] = 'basic';
			$settings['floating_button_enabled'] = true;
			$settings['require_decision_before_interaction'] = false;
			$settings['reload_on_withdrawal'] = true;
		}
		return $settings;
	}

	public static function category(array $category, bool $enforced): array
	{
		if ($enforced && 'necessary' !== $category['slug']) {
			$category['required'] = false;
			$category['default_enabled'] = false;
		}
		return $category;
	}
}
