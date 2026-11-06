<?php


declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

/**
 * @var array<string, mixed> $GLOBALS['cmp_test_options']
 */
$GLOBALS['cmp_test_options'] = [];

function get_option(string $name, mixed $default = false): mixed
{
	return $GLOBALS['cmp_test_options'][$name] ?? $default;
}

function add_option(string $name, mixed $value): bool
{
	if (array_key_exists($name, $GLOBALS['cmp_test_options'])) {
		return false;
	}

	$GLOBALS['cmp_test_options'][$name] = $value;

	return true;
}

function update_option(string $name, mixed $value): bool
{
	$GLOBALS['cmp_test_options'][$name] = $value;

	return true;
}

function delete_option(string $name): bool
{
	unset($GLOBALS['cmp_test_options'][$name]);

	return true;
}

function __(string $text, string $domain = 'default'): string
{
	return $text;
}

function esc_html__(string $text, string $domain = 'default'): string
{
	return $text;
}

function esc_html(string $text): string
{
	return htmlspecialchars($text, ENT_QUOTES);
}

function wp_unslash(mixed $value): mixed
{
	return is_string($value) ? stripslashes($value) : $value;
}

function esc_attr(string $text): string
{
	return htmlspecialchars($text, ENT_QUOTES);
}

function esc_url(string $url): string
{
	return htmlspecialchars($url, ENT_QUOTES);
}

/**
 * @param array<string, mixed>|string $args
 * @param array<string, mixed> $defaults
 * @return array<string, mixed>
 */
function wp_parse_args(array|string $args, array $defaults = []): array
{
	if (is_string($args)) {
		parse_str($args, $args);
	}

	return array_merge($defaults, $args);
}

/**
 * @var array<string, array<int, array{callback: callable, priority: int, accepted_args: int}>>
 */
$GLOBALS['cmp_test_hooks'] = [];

function add_filter(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): bool
{
	$GLOBALS['cmp_test_hooks'][$hook][] = ['callback' => $callback, 'priority' => $priority, 'accepted_args' => $accepted_args];

	return true;
}

function add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): bool
{
	return add_filter($hook, $callback, $priority, $accepted_args);
}

function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
{
	$hooks = $GLOBALS['cmp_test_hooks'][$hook] ?? [];
	usort($hooks, static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);

	foreach ($hooks as $registered) {
		$call_args = array_slice([$value, ...$args], 0, max(1, $registered['accepted_args']));
		$value = ($registered['callback'])(...$call_args);
	}

	return $value;
}

function do_action(string $hook, mixed ...$args): void
{
	apply_filters($hook, null, ...$args);
}

defined('MINUTE_IN_SECONDS') || define('MINUTE_IN_SECONDS', 60);
defined('HOUR_IN_SECONDS') || define('HOUR_IN_SECONDS', 3600);
defined('DAY_IN_SECONDS') || define('DAY_IN_SECONDS', 86400);

define('Consentimental\CMP_PLUGIN_DIR', dirname(__DIR__) . '/');

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/src/functions.php';
