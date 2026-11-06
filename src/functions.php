<?php

declare(strict_types=1);

use Consentimental\Plugin;

if (!defined('ABSPATH')) {
	exit;
}


function cmp_has_consent(string $category): bool
{
	return Plugin::instance()->consent()->has_consent($category);
}

function cmp_has_service_consent(string $service_id): bool
{
	return Plugin::instance()->consent()->has_service_consent($service_id);
}

/**
 * @return array<string, mixed>|null
 */
function cmp_get_consent_state(): ?array
{
	return Plugin::instance()->consent()->get_state()?->to_array();
}

function cmp_needs_consent(): bool
{
	return Plugin::instance()->consent()->needs_consent();
}

function cmp_sale_sharing_opted_out(): bool
{
	return (bool) Plugin::instance()->settings()->get('california_enabled')
		&& \Consentimental\Consent\CaliforniaPolicy::opted_out();
}

/**
 * @param array{text?: string, class?: string} $args
 */
function cmp_privacy_settings_link(array $args = []): string
{
	$args = wp_parse_args($args, [
		'text' => __('Privacy settings', 'consentimental'),
		'class' => 'cmp-privacy-settings-link',
	]);

	return sprintf(
		'<button type="button" class="%1$s" data-cmp-open-settings>%2$s</button>',
		esc_attr((string) $args['class']),
		esc_html((string) $args['text']),
	);
}

/**
 * @param array{text?: string, class?: string} $args
 */
function cmp_the_privacy_settings_link(array $args = []): void
{
	echo cmp_privacy_settings_link($args); // phpcs:ignore WordPress.Security.EscapeOutput -- cmp_privacy_settings_link() already escapes its parts.
}

/**
 * @param string $category_or_service a category slug, or a registered service id
 *                                    (its category is used, and the tag is also
 *                                    tagged so service-level consent can override it)
 */
function cmp_register_script(string $handle, string $category_or_service): void
{
	Plugin::instance()->script_gate()->register($handle, $category_or_service);
}
