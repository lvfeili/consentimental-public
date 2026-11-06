<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }
$labels = ['unknown' => __('Not yet verified', 'consentimental'), 'healthy' => __('HTTP checks passing', 'consentimental'), 'suspect' => __('Failure observed — confirming', 'consentimental'), 'failing' => __('Failing', 'consentimental'), 'stale' => __('No recent evidence', 'consentimental')];
$reasons = [
    'cmp_proxy_upstream_unreachable' => __('Connection failed or the destination is not a permitted public address. Check the upstream URL and server connectivity.', 'consentimental'),
    'cmp_proxy_redirect' => __('Upstream redirected. Configure its final URL.', 'consentimental'),
    'cmp_proxy_disallowed_content_type' => __('Upstream returned an unsupported content type.', 'consentimental'),
    'cmp_proxy_response_too_large' => __('Upstream response exceeded the 2 MiB limit.', 'consentimental'),
];

?>
<div class="wrap">
<h1><?php esc_html_e('Proxy diagnostics', 'consentimental'); ?></h1>
<p><?php esc_html_e('Published integrations only. Script files are fetched fresh hourly in batches of five. Runtime endpoints are observed during real proxy traffic; checks never send synthetic tracking events. No traffic means not yet verified, not healthy.', 'consentimental'); ?></p>
<p><?php esc_html_e('These checks verify server-to-vendor HTTP forwarding, not browser execution, consent compliance, or receipt in the vendor dashboard. Use the frontend CMP Debug panel and your browser Network tab to verify those separately.', 'consentimental'); ?></p>
<p><?php esc_html_e('Schedules depend on WordPress cron running. On low-traffic sites, configure a system cron. Cached script responses do not count as fresh health checks.', 'consentimental'); ?></p>
<p><?php esc_html_e('Last scheduled/manual batch:', 'consentimental'); ?> <?php $last = (int) get_option('cmp_proxy_health_last_run', 0); echo esc_html($last ? gmdate('Y-m-d H:i:s', $last) . ' UTC' : __('Never', 'consentimental')); ?></p>
<form method="post">
<?php wp_nonce_field('cmp_proxy_health', 'cmp_proxy_health_nonce'); ?>
<button class="button" name="check_proxy_health" value="1"><?php esc_html_e('Check next five scripts now', 'consentimental'); ?></button>
<p class="description"><?php esc_html_e('At most once per minute. Collection endpoints are not probed.', 'consentimental'); ?></p>
</form>
<table class="widefat striped">
<thead><tr><th><?php esc_html_e('Script', 'consentimental'); ?></th><th><?php esc_html_e('Endpoint', 'consentimental'); ?></th><th><?php esc_html_e('Status', 'consentimental'); ?></th><th><?php esc_html_e('Last observation (UTC)', 'consentimental'); ?></th><th><?php esc_html_e('Result', 'consentimental'); ?></th></tr></thead>
<tbody>
<?php foreach ($rows as $row) : ?>
<tr><td><?php echo esc_html($row['name']); ?></td><td><code><?php echo esc_html($row['key']); ?></code></td><td><?php echo esc_html($labels[$row['status']] ?? $row['status']); ?></td><td><?php echo esc_html(isset($row['endpoint']['checked_at']) ? gmdate('Y-m-d H:i:s', $row['endpoint']['checked_at']) : '—'); ?></td><td><?php $code = $row['endpoint']['code'] ?? ''; echo esc_html($reasons[$code] ?? (str_starts_with($code, 'http_') ? 'HTTP ' . substr($code, 5) : ($code ?: '—'))); ?></td></tr>
<?php endforeach; ?>
<?php if (!$rows) : ?><tr><td colspan="5"><?php esc_html_e('No published proxied scripts. Configure an integration under Scripts and publish it first.', 'consentimental'); ?></td></tr><?php endif; ?>
</tbody></table>
<h2><?php esc_html_e('Regression email alerts', 'consentimental'); ?></h2>
<form method="post">
<?php wp_nonce_field('cmp_proxy_health', 'cmp_proxy_health_nonce'); ?>
<label><input type="checkbox" name="proxy_email" value="1" <?php checked($email); ?> /> <?php esc_html_e('Email the WordPress administration address when a previously working endpoint fails three consecutive checks or observed requests.', 'consentimental'); ?></label>
<p><?php echo esc_html((string) get_option('admin_email')); ?></p>
<p class="description"><?php esc_html_e('Off by default. One digest at most per day, once per incident. Recovery permits a future alert. Changing an integration resets its baseline. Initial setup failures show notices but do not trigger regression mail. WordPress mail acceptance does not guarantee delivery.', 'consentimental'); ?></p>
<?php if ('failed' === get_option('cmp_proxy_health_mail_status')) : ?><p class="notice notice-error"><?php esc_html_e('WordPress could not send the last alert. Check your mail configuration; a later monitoring run will retry after the daily cooldown.', 'consentimental'); ?></p><?php endif; ?>
<button class="button button-primary" name="save_proxy_health" value="1"><?php esc_html_e('Save alert settings', 'consentimental'); ?></button>
</form>
<?php require __DIR__ . '/proxy-guides.php'; ?>
</div>
