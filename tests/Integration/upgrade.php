<?php
declare(strict_types=1);
require getenv('CMP_WP_LOAD') ?: '/var/www/html/wp-load.php';

use Consentimental\Consent\ConsentLogRepository;
use Consentimental\Consent\DisclosureSnapshotRepository;
use Consentimental\Consent\Settings;

if ('1' !== (string) get_option('cmp_e2e_disposable')) {
    fwrite(STDERR, "Refusing to run: this check drops tables and only runs on the disposable e2e site.\n");
    exit(1);
}

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) { throw new RuntimeException('FAILED: ' . $message); }
    ++$checks;
}

global $wpdb;
$log = new ConsentLogRepository();
$snapshots = new DisclosureSnapshotRepository();
$table = $log->table_name();

$wpdb->query("DROP TABLE IF EXISTS {$table}");
$wpdb->query("DROP TABLE IF EXISTS {$snapshots->table_name()}");
$wpdb->query("CREATE TABLE {$table} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    consent_id varchar(36) NOT NULL,
    recorded_at datetime NOT NULL,
    action varchar(20) NOT NULL,
    categories longtext NOT NULL,
    services longtext NOT NULL,
    config_version bigint(20) unsigned NOT NULL,
    ip_anonymized varchar(45) DEFAULT NULL,
    user_agent varchar(255) DEFAULT NULL,
    page_url varchar(255) DEFAULT NULL,
    PRIMARY KEY  (id),
    KEY consent_id (consent_id),
    KEY recorded_at (recorded_at)
) {$wpdb->get_charset_collate()}");
$wpdb->insert($table, ['consent_id' => 'legacy-record', 'recorded_at' => '2025-01-01 00:00:00', 'action' => 'granted',
    'categories' => '{"analytics":true}', 'services' => '{}', 'config_version' => 7]);
update_option('cmp_consent_log_db_version', 1);
delete_option('cmp_disclosure_db_version');
delete_option(Settings::VERSION_HISTORY_OPTION);
$stored = get_option(Settings::OPTION_NAME, []);
unset($stored['privacy_policy_page_id'], $stored['delete_data_on_uninstall']);
update_option(Settings::OPTION_NAME, $stored);

$log->maybe_upgrade();
$snapshots->maybe_upgrade();

$columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}");
check(in_array('disclosure_hash', $columns, true) && in_array('language', $columns, true), 'Consent log gains disclosure columns');
check(2 === (int) get_option('cmp_consent_log_db_version'), 'Consent log schema version is recorded');
check((string) $snapshots->table_name() === (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $snapshots->table_name())), 'Snapshot table is created');

$legacy = $log->query('legacy-record')['items'][0] ?? null;
check(null !== $legacy && 7 === $legacy['config_version'], 'Existing records survive the upgrade');
check(null === $legacy['disclosure_hash'] && null === $legacy['language'], 'Existing records are not back-filled with a disclosure');

$settings = new Settings();
check(0 === $settings->get('privacy_policy_page_id') && false === $settings->get('delete_data_on_uninstall'), 'New settings take safe defaults');
check(null === $settings->published_at($settings->config_version()), 'Publication time of a pre-upgrade version is unknown, not invented');

$log->maybe_upgrade();
check(count($columns) === count($wpdb->get_col("SHOW COLUMNS FROM {$table}")), 'Running the upgrade again changes nothing');

echo "Passed {$checks} upgrade checks.\n";
