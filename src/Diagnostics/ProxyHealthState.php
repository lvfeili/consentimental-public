<?php

declare(strict_types=1);
namespace Consentimental\Diagnostics;
if (!defined('ABSPATH')) { exit; }

final class ProxyHealthState
{
    public static function observe(array $previous, bool $ok, string $code, int $now): array
    {
        $state = array_merge(['last_success' => 0, 'failures' => 0, 'alerted' => false], $previous);
        $state['checked_at'] = $now;
        $state['code'] = $code;
        if ($ok) {
            $state['last_success'] = $now;
            $state['failures'] = 0;
            $state['alerted'] = false;
        } else {
            $state['failures'] = min(1000, (int) $state['failures'] + 1);
        }
        $state['status'] = $ok ? 'healthy' : ($state['failures'] >= 3 ? 'failing' : 'suspect');
        return $state;
    }

    public static function should_alert(array $state): bool
    {
        return ($state['last_success'] ?? 0) > 0 && ($state['failures'] ?? 0) >= 3 && empty($state['alerted']);
    }
}
