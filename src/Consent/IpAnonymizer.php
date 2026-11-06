<?php

declare(strict_types=1);

namespace Consentimental\Consent;

if (!defined('ABSPATH')) {
	exit;
}

final class IpAnonymizer
{
	public static function mask(string $ip): ?string
	{
		$validated = filter_var($ip, FILTER_VALIDATE_IP);

		if (false === $validated) {
			return null;
		}

		$packed = inet_pton($validated);

		if (false === $packed) {
			return null;
		}

		if (4 === strlen($packed)) {
			$masked = substr($packed, 0, 3) . "\x00";
			$result = inet_ntop($masked);

			return false === $result ? null : $result;
		}

		$masked = substr($packed, 0, 8) . str_repeat("\x00", 8);
		$result = inet_ntop($masked);

		return false === $result ? null : $result . '/64';
	}
}
