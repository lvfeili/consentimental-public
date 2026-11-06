<?php

declare(strict_types=1);
namespace Consentimental\Diagnostics;

use Consentimental\Scripts\CustomScript;
use Consentimental\Scripts\CustomScriptRepository;
use Consentimental\Scripts\ScriptProxy;
use Consentimental\Scripts\ProxyRoutes;
use WP_REST_Request;
if (!defined('ABSPATH')) { exit; }

final class ProxyHealth
{
    public const HOOK = 'cmp_proxy_health_check';
    public const OPTION = 'cmp_proxy_health';
    public const EMAIL_OPTION = 'cmp_proxy_health_email';
    private const LOCK = 'cmp_proxy_health_lock';

    public function __construct(private readonly CustomScriptRepository $scripts, private readonly ScriptProxy $proxy) {}

    public function register(): void
    {
        add_action('cmp_proxy_result', $this->record(...), 10, 6);
        add_action(self::HOOK, $this->run(...));
        if (!wp_next_scheduled(self::HOOK)) { wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', self::HOOK); }
    }

    /**
     * @return CustomScript[]
     */
    public function scripts(): array
    {
        return array_values(array_filter($this->scripts->all(), static fn ($s) => $s->proxy && CustomScript::TYPE_URL === $s->type));
    }

    public static function fingerprint(CustomScript $script): string
    {
        try { $routes = ProxyRoutes::parse($script->proxy_paths); }
        catch (\InvalidArgumentException) { $routes = ['invalid-configuration' => [$script->proxy_paths]]; }
        $paths = apply_filters('cmp_proxy_allowed_paths', [ScriptProxy::script_path($script) => ['GET', 'HEAD']] + $routes, $script);
        return hash('sha256', serialize([$script->url, $script->proxy_api_origin, $paths]));
    }

    /**
     * @return list<array{name: string, key: string, status: string, endpoint: array<string, mixed>}>
     */
    public function rows(): array
    {
        $rows = [];
        foreach ($this->scripts() as $script) {
            $state = $this->state($script);
            try { $paths = ProxyRoutes::parse($script->proxy_paths); }
            catch (\InvalidArgumentException) { $paths = []; }
            $paths = apply_filters('cmp_proxy_allowed_paths', [ScriptProxy::script_path($script) => ['GET']] + $paths, $script);
            foreach ((array) $paths as $path => $methods) {
                foreach ((array) $methods as $method) {
                    if ('HEAD' === $method && $path === ScriptProxy::script_path($script)) { continue; }
                    $key = $method . ' /' . $path;
                    $endpoint = $state['endpoints'][$key] ?? [];
                    $status = $endpoint['status'] ?? 'unknown';
                    if ('healthy' === $status && time() - ($endpoint['checked_at'] ?? 0) > DAY_IN_SECONDS) { $status = 'stale'; }
                    $rows[] = ['name' => $script->name ?: $script->id, 'key' => $key, 'status' => $status, 'endpoint' => $endpoint];
                }
            }
        }
        return $rows;
    }

    public function state(CustomScript $script): array
    {
        $all = get_option(self::OPTION, []);
        $state = is_array($all) ? ($all[$script->id] ?? []) : [];
        return ($state['fingerprint'] ?? '') === self::fingerprint($script) ? $state : [];
    }

    private function locked(callable $work): void
    {
        $started = (int) get_option(self::LOCK, 0);
        if ($started && $started < time() - 120) { delete_option(self::LOCK); }
        if (!add_option(self::LOCK, time(), '', false)) { return; }
        try { $work(); } finally { delete_option(self::LOCK); }
    }

    public function record(string $id, string $path, string $method, bool $ok, string $code, bool $fresh = false): void
    {
        $script = $this->scripts->get($id);
        if (!$script || !$script->proxy || !ScriptProxy::allows_request($script, $path, $method)) { return; }
        $static = $path === ScriptProxy::script_path($script);
        if (!$static && preg_match('/^http_4\d\d$/', $code) && !in_array($code, ['http_404', 'http_410', 'http_429'], true)) { return; }
        $key = ($static ? 'GET' : $method) . ' /' . $path;
        if ($static && 'HEAD' === $method) { return; }
        $this->locked(function () use ($script, $key, $ok, $code): void {
            $all = get_option(self::OPTION, []);
            $all = is_array($all) ? $all : [];
            $state = $this->state($script);
            $previous = $state['endpoints'][$key] ?? [];
            if ($ok && ($previous['status'] ?? '') === 'healthy' && time() - ($previous['checked_at'] ?? 0) < 300) { return; }
            $state['fingerprint'] = self::fingerprint($script);
            $state['endpoints'][$key] = ProxyHealthState::observe($previous, $ok, $code, time());
            $all[$script->id] = $state;
            update_option(self::OPTION, $all, false);
        });
    }

    public function run(): void
    {
        $scripts = $this->scripts();
        $ids = array_column(array_map(static fn ($s) => ['id' => $s->id], $scripts), 'id');
        $this->locked(function () use ($ids): void {
            $all = get_option(self::OPTION, []);
            if (is_array($all)) { update_option(self::OPTION, array_intersect_key($all, array_flip($ids)), false); }
        });
        $offset = (int) get_option('cmp_proxy_health_cursor', 0);
        $count = count($scripts);
        for ($i = 0; $i < min(5, $count); ++$i) {
            $script = $scripts[($offset + $i) % $count];
            $request = new WP_REST_Request('GET');
            $request->set_url_params(['id' => $script->id, 'path' => ScriptProxy::script_path($script)]);
            parse_str((string) parse_url($script->url, PHP_URL_QUERY), $query);
            $request->set_query_params($query);
            $this->proxy->handle($request, true);
        }
        update_option('cmp_proxy_health_cursor', $count ? ($offset + min(5, $count)) % $count : 0, false);
        $this->notify();
        update_option('cmp_proxy_health_last_run', time(), false);
    }

    public function notify(): void
    {
        if (!get_option(self::EMAIL_OPTION, false)) { return; }
        $this->locked(function (): void {
            $all = get_option(self::OPTION, []);
            $last_email = (int) get_option('cmp_proxy_health_last_email', 0);
            if (time() - $last_email < DAY_IN_SECONDS) { return; }
            $pending = [];
            foreach ($this->scripts() as $script) {
                foreach (($this->state($script)['endpoints'] ?? []) as $key => $state) {
                    if (ProxyHealthState::should_alert($state)) { $pending[$script->id][$key] = $script->name ?: $script->id; }
                }
            }
            if (!$pending) { return; }
            $recipient = sanitize_email((string) get_option('admin_email'));
            if (!is_email($recipient)) { return; }
            $lines = [__('Previously working proxy endpoints have failed at least three consecutive checks or observed requests:', 'consentimental')];
            foreach ($pending as $id => $endpoints) {
                foreach ($endpoints as $key => $name) { $lines[] = sanitize_text_field($name) . ' — ' . $key; }
            }
            $lines[] = admin_url('admin.php?page=consentimental-proxy-health');
            $sent = wp_mail($recipient, __('Consentimental: proxy regression detected', 'consentimental'), implode("\n", $lines));
            update_option('cmp_proxy_health_mail_status', $sent ? 'accepted' : 'failed', false);
            update_option('cmp_proxy_health_last_email', time(), false);
            if ($sent) {
                foreach ($pending as $id => $endpoints) {
                    foreach ($endpoints as $key => $name) { $all[$id]['endpoints'][$key]['alerted'] = true; }
                }
                update_option(self::OPTION, $all, false);
            }
        });
    }
}
