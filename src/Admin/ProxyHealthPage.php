<?php

declare(strict_types=1);
namespace Consentimental\Admin;
use Consentimental\Diagnostics\ProxyHealth;
if (!defined('ABSPATH')) { exit; }

final class ProxyHealthPage
{
    public const SLUG = 'consentimental-proxy-health';
    public function __construct(private readonly ProxyHealth $health) {}
    public function register(): void
    {
        add_action('admin_menu', function (): void {
            add_submenu_page(AdminMenu::PAGE_SLUG, __('Proxy diagnostics', 'consentimental'), __('Proxy diagnostics', 'consentimental'), 'manage_options', self::SLUG, $this->render(...));
        }, 20); // The parent menu must be registered before this submenu.
        add_action('admin_init', $this->handle(...));
        add_action('admin_notices', $this->notice(...));
    }
    private function handle(): void
    {
        if (!isset($_POST['cmp_proxy_health_nonce']) || !current_user_can('manage_options')) { return; }
        check_admin_referer('cmp_proxy_health', 'cmp_proxy_health_nonce');
        if (isset($_POST['save_proxy_health'])) {
            update_option(ProxyHealth::EMAIL_OPTION, isset($_POST['proxy_email']), false);
        }
        if (isset($_POST['check_proxy_health']) && !get_transient('cmp_proxy_manual_check')) {
            set_transient('cmp_proxy_manual_check', 1, 60);
            $this->health->run();
        }
        wp_safe_redirect(admin_url('admin.php?page=' . self::SLUG));
        exit;
    }
    private function notice(): void
    {
        if (!current_user_can('manage_options')) { return; }
        foreach ($this->health->scripts() as $script) {
            foreach (($this->health->state($script)['endpoints'] ?? []) as $endpoint) {
                if (($endpoint['status'] ?? '') === 'failing') {
                    echo '<div class="notice notice-error"><p>' . esc_html__('Consentimental: proxy forwarding is failing. Review the diagnostics before relying on this integration.', 'consentimental') . ' <a href="' . esc_url(admin_url('admin.php?page=' . self::SLUG)) . '">' . esc_html__('View diagnostics', 'consentimental') . '</a></p></div>';
                    return;
                }
            }
        }
    }
    private function render(): void
    {
        if (!current_user_can('manage_options')) { return; }
        $rows = $this->health->rows();
        $email = (bool) get_option(ProxyHealth::EMAIL_OPTION, false);
        require \Consentimental\CMP_PLUGIN_DIR . 'templates/admin/proxy-health.php';
    }
}
