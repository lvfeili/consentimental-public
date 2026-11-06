<?php

declare(strict_types=1);

namespace Consentimental\Consent;

use Consentimental\Services\Service;

if (!defined('ABSPATH')) {
	exit;
}

final class CaliforniaPolicy
{
	public const COOKIE_NAME = 'cmp_sale_opt_out';
	public const COOKIE_DAYS = 400;

	public static function labels(): array
	{
		return [
			'unreviewed' => __('Unreviewed', 'consentimental'),
			'involved' => __('Sale/sharing involved', 'consentimental'),
			'not_involved' => __('No sale/sharing', 'consentimental'),
		];
	}

	public static function classification(mixed $value): string
	{
		return in_array($value, ['involved', 'not_involved'], true) ? $value : 'unreviewed';
	}

	public static function gpc(): bool
	{
		return '1' === ($_SERVER['HTTP_SEC_GPC'] ?? null);
	}

	public static function opted_out(): bool
	{
		return self::gpc() || '1' === ($_COOKIE[self::COOKIE_NAME] ?? null);
	}

	public static function blocks(Service $service, Settings $settings): bool
	{
		if (!$settings->get('california_enabled')) {
			return false;
		}
		return 'unreviewed' === self::classification($service->sale_sharing)
			|| ('involved' === $service->sale_sharing && self::opted_out());
	}
}
