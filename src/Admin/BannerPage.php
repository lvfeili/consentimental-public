<?php

declare(strict_types=1);

namespace Consentimental\Admin;

use Consentimental\Consent\Settings;
use Consentimental\Frontend\BannerContent;
use Consentimental\Frontend\BannerStyle;

if (!defined('ABSPATH')) {
	exit;
}

final class BannerPage
{
	public const PAGE_SLUG = 'consentimental-banner';

	public function __construct(
		private readonly Settings $settings,
		private readonly BannerContent $content,
	) {
	}

	public function register(): void
	{
		add_action('admin_menu', $this->add_menu(...));
		add_action('admin_init', $this->maybe_save(...));
		add_action('admin_enqueue_scripts', $this->enqueue_assets(...));
	}

	/**
	 * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: bool}
	 */
	public static function preview_data(Settings $settings, BannerContent $content): array
	{
		$fallback = (int) get_option('wp_page_for_privacy_policy', 0);

		return [$content->resolve(translate: false), $settings->all(), $fallback > 0 && 'publish' === get_post_status($fallback)];
	}

	public static function enqueue_preview_assets(): void
	{
		wp_enqueue_style('cmp-banner', \Consentimental\CMP_PLUGIN_URL . 'assets/css/banner.css', [], \Consentimental\CMP_VERSION);
		wp_enqueue_script('cmp-banner-preview', \Consentimental\CMP_PLUGIN_URL . 'assets/js/admin-banner-preview.js', ['jquery'], \Consentimental\CMP_VERSION, true);
	}

	private function enqueue_assets(string $hook): void
	{
		if ('consentimental_page_' . self::PAGE_SLUG !== $hook) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style('wp-color-picker');
		wp_enqueue_script('wp-color-picker');

		wp_enqueue_style(
			'cmp-banner',
			\Consentimental\CMP_PLUGIN_URL . 'assets/css/banner.css',
			[],
			\Consentimental\CMP_VERSION,
		);

		wp_enqueue_script(
			'cmp-banner-preview',
			\Consentimental\CMP_PLUGIN_URL . 'assets/js/admin-banner-preview.js',
			['jquery', 'wp-color-picker'],
			\Consentimental\CMP_VERSION,
			true,
		);
	}

	private function add_menu(): void
	{
		add_submenu_page(
			parent_slug: AdminMenu::PAGE_SLUG,
			page_title: __('Banner', 'consentimental'),
			menu_title: __('Banner', 'consentimental'),
			capability: 'manage_options',
			menu_slug: self::PAGE_SLUG,
			callback: $this->render(...),
		);
	}

	private function maybe_save(): void
	{
		if (!current_user_can('manage_options') || !isset($_POST['cmp_save_banner'])) {
			return;
		}

		check_admin_referer('cmp_banner_save', 'cmp_banner_nonce');

		$icon = sanitize_key((string) wp_unslash($_POST['floating_button_icon'] ?? ''));
		$position = sanitize_key((string) wp_unslash($_POST['banner_position'] ?? ''));
		$shape = sanitize_key((string) wp_unslash($_POST['button_shape'] ?? ''));

		$this->settings->save([
			'banner_title' => sanitize_text_field((string) wp_unslash($_POST['banner_title'] ?? '')),
			'banner_text' => sanitize_textarea_field((string) wp_unslash($_POST['banner_text'] ?? '')),
			'customize_text' => sanitize_textarea_field((string) wp_unslash($_POST['customize_text'] ?? '')),
			'banner_logo_url' => esc_url_raw((string) wp_unslash($_POST['banner_logo_url'] ?? '')),
			'floating_button_icon' => in_array($icon, ['cookie', 'fingerprint', 'gear', 'emoji', 'image'], true) ? $icon : 'cookie',
			'floating_button_icon_emoji' => sanitize_text_field((string) wp_unslash($_POST['floating_button_icon_emoji'] ?? '')),
			'floating_button_icon_image_url' => esc_url_raw((string) wp_unslash($_POST['floating_button_icon_image_url'] ?? '')),
			'floating_button_desktop_side' => $this->sanitize_floating_side('floating_button_desktop_side'),
			'floating_button_desktop_edge' => $this->sanitize_px_field('floating_button_desktop_edge', 0, 1000),
			'floating_button_desktop_bottom' => $this->sanitize_px_field('floating_button_desktop_bottom', 0, 1000),
			'floating_button_mobile_side' => $this->sanitize_floating_side('floating_button_mobile_side'),
			'floating_button_mobile_edge' => $this->sanitize_px_field('floating_button_mobile_edge', 0, 1000),
			'floating_button_mobile_bottom' => $this->sanitize_px_field('floating_button_mobile_bottom', 0, 1000),
			'banner_color_bg' => $this->sanitize_color_field('banner_color_bg'),
			'banner_color_text' => $this->sanitize_color_field('banner_color_text'),
			'banner_color_muted' => $this->sanitize_color_field('banner_color_muted'),
			'banner_color_border' => $this->sanitize_color_field('banner_color_border'),
			'banner_color_primary' => $this->sanitize_color_field('banner_color_primary'),
			'banner_color_primary_text' => $this->sanitize_color_field('banner_color_primary_text'),
			'banner_color_focus' => $this->sanitize_color_field('banner_color_focus'),
			'banner_radius' => $this->sanitize_px_field('banner_radius', 0, 40),
			'banner_position' => in_array($position, BannerStyle::POSITIONS, true) ? $position : 'bottom_bar',
			'banner_font_family' => sanitize_text_field((string) wp_unslash($_POST['banner_font_family'] ?? '')),
			'banner_font_size' => $this->sanitize_px_field('banner_font_size', 11, 24),
			'button_shape' => in_array($shape, BannerStyle::BUTTON_SHAPES, true) ? $shape : 'rounded',
			'banner_logo_width' => $this->sanitize_px_field('banner_logo_width', 20, 400),
			'custom_css' => (string) wp_unslash($_POST['custom_css'] ?? ''),
		]);

		wp_safe_redirect(add_query_arg(['page' => self::PAGE_SLUG, 'updated' => 'saved'], admin_url('admin.php')));

		exit;
	}

	private function sanitize_color_field(string $field): string
	{
		$value = sanitize_hex_color((string) wp_unslash($_POST[$field] ?? ''));

		return $value ?: '';
	}

	private function sanitize_floating_side(string $field): string
	{
		$side = sanitize_key((string) wp_unslash($_POST[$field] ?? ''));

		return in_array($side, ['left', 'right'], true) ? $side : 'right';
	}

	private function sanitize_px_field(string $field, int $min, int $max): string
	{
		$raw = (string) wp_unslash($_POST[$field] ?? '');

		if ('' === trim($raw)) {
			return '';
		}

		return (string) max($min, min($max, (int) $raw));
	}

	private function render(): void
	{
		if (!current_user_can('manage_options')) {
			return;
		}

		$banner_title = (string) $this->settings->get('banner_title');
		$banner_text = (string) $this->settings->get('banner_text');
		$customize_text = (string) $this->settings->get('customize_text');
		$banner_logo_url = (string) $this->settings->get('banner_logo_url');
		$floating_button_icon = (string) $this->settings->get('floating_button_icon');
		$floating_button_icon_emoji = (string) $this->settings->get('floating_button_icon_emoji');
		$floating_button_icon_image_url = (string) $this->settings->get('floating_button_icon_image_url');
		$floating_button_positions = [];
		foreach (['desktop', 'mobile'] as $device) {
			foreach (['side', 'edge', 'bottom'] as $field) {
				$floating_button_positions[$device][$field] = (string) $this->settings->get('floating_button_' . $device . '_' . $field);
			}
		}
		$banner_color_bg = (string) $this->settings->get('banner_color_bg');
		$banner_color_text = (string) $this->settings->get('banner_color_text');
		$banner_color_muted = (string) $this->settings->get('banner_color_muted');
		$banner_color_border = (string) $this->settings->get('banner_color_border');
		$banner_color_primary = (string) $this->settings->get('banner_color_primary');
		$banner_color_primary_text = (string) $this->settings->get('banner_color_primary_text');
		$banner_color_focus = (string) $this->settings->get('banner_color_focus');
		$banner_radius = (string) $this->settings->get('banner_radius');
		$banner_position = (string) $this->settings->get('banner_position');
		$banner_font_family = (string) $this->settings->get('banner_font_family');
		$banner_font_size = (string) $this->settings->get('banner_font_size');
		$button_shape = (string) $this->settings->get('button_shape');
		$banner_logo_width = (string) $this->settings->get('banner_logo_width');
		$custom_css = (string) $this->settings->get('custom_css');
		[$preview_content, $preview_settings, $preview_has_fallback_policy] = self::preview_data($this->settings, $this->content);

		require \Consentimental\CMP_PLUGIN_DIR . 'templates/admin/banner.php';
	}
}
