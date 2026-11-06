<?php
declare(strict_types=1);
require __DIR__ . '/harness.php';

use Consentimental\Consent\Settings;
use Consentimental\Services\CustomServiceRepository;
use Consentimental\Services\FirstPartyServices;
use Consentimental\Services\ServiceRegistry;

cmp_require_innodb([$wpdb->options]);

$wpdb->query('START TRANSACTION');
try {
    $settings = new Settings();
    $build = static function () use ($settings): ServiceRegistry {
        FirstPartyServices::flush_cache();
        $registry = new ServiceRegistry(new CustomServiceRepository('cmp_integration_none'));
        (new FirstPartyServices($settings))->register($registry);
        return $registry;
    };

    $settings->save(['consent_expiry_days' => 30]);
    $registry = $build();
    $consent = $registry->get('consentimental');
    check(null !== $consent && $consent->active && 'necessary' === $consent->category, 'The consent cookie service is disclosed as necessary by default');
    check('cmp_consent' === $consent->cookies[0]['name'] && str_contains($consent->cookies[0]['duration'], '30'), 'Its duration follows the consent expiry setting');

    $core = $registry->get('wordpress');
    $names = array_column($core->cookies, 'name');
    foreach (['wordpress_logged_in_*', 'wordpress_sec_*', 'wordpress_test_cookie', 'wp-settings-*', 'wp-postpass_*'] as $cookie) {
        check(in_array($cookie, $names, true), "WordPress core cookie {$cookie} is listed");
    }
    check([] === $core->domains && [] === $core->script_patterns, 'First-party services block nothing');

    update_option('show_comments_cookies_opt_in', '1');
    update_option('default_comment_status', 'open');
    check(null !== $build()->get('wordpress-comments'), 'Comment cookies are listed while comments can save them');

    update_option('show_comments_cookies_opt_in', '0');
    check(null === $build()->get('wordpress-comments'), 'Without the save-my-details checkbox WordPress sets no comment cookies, so none are listed');

    update_option('show_comments_cookies_opt_in', '1');
    update_option('default_comment_status', 'closed');
    $wpdb->query("UPDATE {$wpdb->posts} SET comment_status = 'closed'");
    check(null === $build()->get('wordpress-comments'), 'With comments closed everywhere, no comment cookies are listed');

    $page = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Policy', 'comment_status' => 'closed']);
    update_option('wp_page_for_privacy_policy', $page);
    check(get_permalink($page) === $build()->get('wordpress')->privacy_policy_url, 'First-party services link the site’s own privacy policy');

    echo "Passed {$checks} first-party service checks.\n";
} finally {
    $wpdb->query('ROLLBACK');
    FirstPartyServices::flush_cache();
}
