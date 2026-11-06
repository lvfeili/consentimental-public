<?php

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

wp_clear_scheduled_hook('cmp_consent_log_cleanup');
wp_clear_scheduled_hook('cmp_proxy_health_check');

$cmp_settings = get_option('cmp_settings', []);

if (!is_array($cmp_settings) || empty($cmp_settings['delete_data_on_uninstall'])) {
	return;
}

global $wpdb;

$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}cmp_consent_log");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}cmp_disclosure_snapshots");

$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'cmp\\_%' OR option_name LIKE '\\_transient\\_cmp\\_%' OR option_name LIKE '\\_transient\\_timeout\\_cmp\\_%'");
