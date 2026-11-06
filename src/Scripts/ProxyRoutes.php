<?php

declare(strict_types=1);
namespace Consentimental\Scripts;
if (!defined('ABSPATH')) { exit; }

final class ProxyRoutes
{
    /**
     * @return array<string, string[]>
     */
    public static function parse(string $text): array
    {
        $routes = [];
        $lines = preg_split('/\R/', trim($text));
        if (count($lines) > 32 || strlen($text) > 8192) {
            throw new \InvalidArgumentException('Use at most 32 proxy route lines (8 KiB total).');
        }
        foreach ($lines as $line) {
            if ('' === trim($line)) { continue; }
            if (!preg_match('~^(GET|HEAD|POST)\s+/([a-zA-Z0-9_./-]+)$~D', trim($line), $match)
                || str_starts_with($match[2], '/') || preg_match('~(?:^|/)\.\.?(?:/|$)|//~', $match[2])) {
                throw new \InvalidArgumentException('Use one method and exact path per line, for example POST /api/send. Queries, wildcards, and traversal are not allowed.');
            }
            $routes[$match[2]][] = $match[1];
            $routes[$match[2]] = array_values(array_unique($routes[$match[2]]));
        }
        return $routes;
    }

    public static function legacy(string $url): string
    {
        $path = ltrim((string) parse_url($url, PHP_URL_PATH), '/');
        if (in_array($path, ['gtag/js', 'gtm.js'], true)) {
            return "GET /g/collect\nPOST /g/collect\nGET /collect\nPOST /collect";
        }
        return match (basename($path)) {
            'script.js' => 'POST /api/send',
            'matomo.js', 'piwik.js' => "GET /matomo.php\nPOST /matomo.php\nGET /piwik.php\nPOST /piwik.php",
            default => '',
        };
    }
}
