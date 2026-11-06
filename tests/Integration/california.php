<?php
declare(strict_types=1);
require __DIR__ . '/harness.php';

use Consentimental\Admin\AdminMenu;
use Consentimental\Api\ConsentController;
use Consentimental\Consent\CaliforniaPolicy;
use Consentimental\Consent\CategoryRepository;
use Consentimental\Consent\ConsentManager;
use Consentimental\Consent\DraftManager;
use Consentimental\Consent\Settings;
use Consentimental\Frontend\Banner;
use Consentimental\Frontend\BannerContent;
use Consentimental\Frontend\ConsentBootstrap;
use Consentimental\Integrations\MultilingualStrings;
use Consentimental\Services\CustomServiceRepository;
use Consentimental\Services\ServiceRegistry;
use Consentimental\Services\ServiceOverrideRepository;

cmp_require_innodb([$wpdb->options]);
$wpdb->query('START TRANSACTION');
try {
    remove_all_actions('cmp_consent_changed');
    remove_all_actions('cmp_consent_granted');
    remove_all_actions('cmp_consent_withdrawn');
    remove_all_actions('cmp_sale_sharing_opt_out');
    unset($_SERVER['REMOTE_ADDR']);
    $settings = new Settings();
    $categories = new CategoryRepository();
    $services = new ServiceRegistry(new CustomServiceRepository());
    $services->register(['id'=>'it-ads','name'=>'IT Ads','category'=>'marketing','active'=>true,'sale_sharing'=>'involved']);
    $services->register(['id'=>'it-safe','name'=>'IT Safe','category'=>'marketing','active'=>true,'sale_sharing'=>'not_involved']);
    $services->register(['id'=>'it-unknown','name'=>'IT Unknown','category'=>'marketing','active'=>true]);
    $manager = new ConsentManager($categories, $services, $settings);
    $controller = new ConsentController($categories, $services, $settings, $manager);
    $version = $settings->config_version();
    $settings->save(['legal_framework'=>'eu_germany']);
    $menu = new AdminMenu($categories, $services, $settings, new DraftManager($settings));
    wp_set_current_user(1);
    check(current_user_can('manage_options'), 'Development admin exists');
    $_POST = ['cmp_save_california'=>'1', 'california_enabled'=>'1', '_wpnonce'=>wp_create_nonce('cmp_save_california')];
    $_REQUEST = $_POST;
    $save = new ReflectionMethod($menu, 'maybe_save_settings');
    $save->setAccessible(true);
    $save->invoke($menu);
    check($settings->get('california_enabled') === true && $settings->enforces_framework(), 'Admin enables California alongside EU');
    check($settings->config_version() > $version, 'Admin toggle renews cookie consent');

    $render = new ReflectionMethod($menu, 'render_overview');
    $render->setAccessible(true);
    ob_start(); $render->invoke($menu); $admin = ob_get_clean();
    check(str_contains($admin, 'name="california_enabled"') && str_contains($admin, 'IT Unknown'), 'Admin shows toggle and unreviewed service');

    $draft = new ServiceOverrideRepository(DraftManager::draft_option_name(ServiceOverrideRepository::OPTION_NAME), ServiceOverrideRepository::OPTION_NAME);
    $draft->save('it-unknown', ['sale_sharing'=>'not_involved']);
    check(null === (new ServiceOverrideRepository())->get('it-unknown'), 'Classification stays staged');
    $publisher = new DraftManager($settings);
    $publisher->mark_meaningful();
    $publisher->publish();
    check('not_involved' === (new ServiceOverrideRepository())->get('it-unknown')['sale_sharing'], 'Classification publishes');

    $_SERVER['HTTP_SEC_GPC'] = '1';
    $response = $controller->handle_privacy_read();
    check($response->get_data()['gpc'] && $response->get_data()['opted_out'], 'Header GPC is returned');
    check(str_contains($response->get_headers()['Cache-Control'], 'no-store'), 'Privacy response forbids caching');
    check($response->get_headers()['Vary'] === 'Sec-GPC, Cookie', 'Privacy response varies on request privacy state');
    $request = new WP_REST_Request('POST');
    $request->set_body_params(['categories'=>['marketing'=>true],'services'=>['it-ads'=>true,'it-safe'=>true,'it-unknown'=>true]]);
    $response = $controller->handle_update($request);
    $state = $response->get_data()['state'];
    check(!$state['services']['it-ads'] && !$state['services']['it-unknown'] && $state['services']['it-safe'], 'REST cannot grant GPC-blocked or unreviewed services');
    check(!$state['categories']['marketing'], 'REST does not grant category-wide tracking past GPC');

    $seen = null;
    add_action('cmp_sale_sharing_opt_out', function ($context) use (&$seen) { $seen = $context; });
    $request = new WP_REST_Request('POST');
    $request->set_body_params(['opted_out'=>false]);
    check(is_wp_error($controller->handle_privacy_opt_out($request)), 'Endpoint refuses opt-in');
    $request->set_body_params(['opted_out'=>true,'source'=>'gpc']);
    $response = $controller->handle_privacy_opt_out($request);
    check($seen['source'] === 'gpc' && $response->get_data()['opted_out'], 'Opt-out dispatches backend event');
    check(!isset($response->get_headers()['Set-Cookie']), 'REST cannot race browser cookie writes');
    unset($_SERVER['HTTP_SEC_GPC']);
    check(CaliforniaPolicy::opted_out(), 'Stored choice survives GPC disappearing');

    $content = new BannerContent($categories, $settings, $services, new MultilingualStrings());
    $resolved = $content->resolve();
    check(isset($resolved['labels']['sale_gpc']), 'Disclosure includes California wording');
    $banner = new Banner($settings, $content);
    $render = new ReflectionMethod($banner, 'render');
    $render->setAccessible(true);
    ob_start(); $render->invoke($banner); $html = ob_get_clean();
    check(substr_count($html, 'data-cmp-sale-opt-out') === 2, 'Both footer and settings expose opt-out');
    check(str_contains($html, 'Do Not Sell or Share My Personal Information'), 'Visitor control has explicit wording');
    $bootstrap = new ConsentBootstrap($categories, $settings, $services, new MultilingualStrings());
    $render = new ReflectionMethod($bootstrap, 'print_bootstrap');
    $render->setAccessible(true);
    ob_start(); $render->invoke($bootstrap); $html = ob_get_clean();
    check(str_contains($html, '"californiaEnabled":true') && str_contains($html, '"saleSharing":"involved"'), 'Cached bootstrap carries policy and classifications');
    check(!str_contains($html, '"gpc":true'), 'Personal GPC state is not baked into shared HTML');
    echo "Passed {$checks} California integration checks.\n";
} finally {
    $wpdb->query('ROLLBACK');
}
