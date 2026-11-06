<?php
declare(strict_types=1);
require getenv('CMP_WP_LOAD') ?: '/var/www/html/wp-load.php';

if (wp_using_ext_object_cache()) {
    throw new RuntimeException('Run on an isolated development site without a persistent object cache.');
}

/**
 * @param string[] $tables
 */
function cmp_require_innodb(array $tables): void
{
    global $wpdb;
    foreach ($tables as $table) {
        $status = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $table));
        if (!$status || 'InnoDB' !== $status->Engine) {
            throw new RuntimeException("Rollback tests require an existing InnoDB table: {$table}");
        }
    }
}

$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) { throw new RuntimeException('FAILED: ' . $message); }
    ++$checks;
}

remove_all_actions('cmp_settings_changed');
remove_all_actions('cmp_config_published');
