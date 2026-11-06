<?php

declare(strict_types=1);

namespace Consentimental\Blocking;

if (!defined('ABSPATH')) {
	exit;
}

final class PatternMatcher
{
	/**
	 * @param string[] $patterns plain substrings, or globs containing `*`
	 */
	public static function matches(string $haystack, array $patterns): bool
	{
		if ([] === $patterns) {
			return false;
		}

		foreach ($patterns as $pattern) {
			if ('' === $pattern) {
				continue;
			}

			$glob = str_contains($pattern, '*') ? $pattern : '*' . $pattern . '*';
			$regex = '/^' . str_replace('\*', '.*', preg_quote($glob, '/')) . '$/si';

			if (1 === preg_match($regex, $haystack)) {
				return true;
			}
		}

		return false;
	}
}
