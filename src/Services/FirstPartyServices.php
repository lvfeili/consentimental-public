<?php

declare(strict_types=1);

namespace Consentimental\Services;

use Consentimental\Consent\ConsentManager;
use Consentimental\Consent\Settings;

if (!defined('ABSPATH')) {
	exit;
}

final class FirstPartyServices
{
	private const COMMENTS_TRANSIENT = 'cmp_comments_in_use';

	public function __construct(private readonly Settings $settings)
	{
	}

	public function register(ServiceRegistry $registry, string $required_category = 'necessary'): void
	{
		$provider = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
		$provider = '' !== $provider ? $provider : __('This website', 'consentimental');
		$privacy_url = (string) get_privacy_policy_url();
		$consent_days = max(1, (int) $this->settings->get('consent_expiry_days'));

		$registry->register([
			'id' => 'consentimental',
			'name' => __('Consent settings', 'consentimental'),
			'provider' => $provider,
			'purpose' => __('Remembers your cookie and privacy choices on this site, so they are respected on every page and you are not asked again on each visit.', 'consentimental'),
			'category' => $required_category,
			'privacy_policy_url' => $privacy_url,
			'cookies' => [
				/* translators: %d: number of days */
				['name' => ConsentManager::COOKIE_NAME, 'purpose' => __('Stores your consent choices, an anonymous consent ID and when you made the choice.', 'consentimental'), 'duration' => sprintf(_n('%d day', '%d days', $consent_days, 'consentimental'), $consent_days), 'domain' => ''],
				['name' => \Consentimental\Consent\CaliforniaPolicy::COOKIE_NAME, 'purpose' => __('Remembers a sale/sharing opt-out independently of cookie consent. Set when California controls are enabled.', 'consentimental'), 'duration' => __('400 days, renewed on visits', 'consentimental'), 'domain' => ''],
			],
			'active' => true,
			'sale_sharing' => 'not_involved',
		]);

		$registry->register([
			'id' => 'wordpress',
			'name' => __('WordPress', 'consentimental'),
			'provider' => $provider,
			'purpose' => __('The software this site runs on. Sets cookies only for people who log in, and to remember access to password-protected content.', 'consentimental'),
			'category' => $required_category,
			'privacy_policy_url' => $privacy_url,
			'cookies' => [
				['name' => 'wordpress_logged_in_*', 'purpose' => __('Keeps a logged-in user signed in. Only for users who log in.', 'consentimental'), 'duration' => __('Session, or 14 days with “Remember me”', 'consentimental'), 'domain' => ''],
				['name' => 'wordpress_sec_*', 'purpose' => __('Secure sign-in to the administration area. Only for users who log in.', 'consentimental'), 'duration' => __('Session, or 14 days with “Remember me”', 'consentimental'), 'domain' => ''],
				['name' => 'wordpress_test_cookie', 'purpose' => __('Checks on the login page that the browser accepts cookies.', 'consentimental'), 'duration' => __('Session', 'consentimental'), 'domain' => ''],
				['name' => 'wp-settings-*', 'purpose' => __('Stores administration screen preferences of a logged-in user.', 'consentimental'), 'duration' => __('1 year', 'consentimental'), 'domain' => ''],
				['name' => 'wp-settings-time-*', 'purpose' => __('Records when those preferences were last saved.', 'consentimental'), 'duration' => __('1 year', 'consentimental'), 'domain' => ''],
				['name' => 'wp-postpass_*', 'purpose' => __('Remembers that you entered the password of a protected page or post.', 'consentimental'), 'duration' => __('10 days', 'consentimental'), 'domain' => ''],
				['name' => 'wp_lang', 'purpose' => __('Remembers the language chosen on the login page.', 'consentimental'), 'duration' => __('Session', 'consentimental'), 'domain' => ''],
			],
			'active' => true,
			'sale_sharing' => 'not_involved',
		]);

		if ($this->comment_cookies_possible()) {
			$registry->register([
				'id' => 'wordpress-comments',
				'name' => __('WordPress comments', 'consentimental'),
				'provider' => $provider,
				'purpose' => __('Remembers your name, email address and website for your next comment. Only set when you tick “Save my name, email, and website in this browser for the next time I comment.”', 'consentimental'),
				'category' => $required_category,
				'privacy_policy_url' => $privacy_url,
				'cookies' => [
					['name' => 'comment_author_*', 'purpose' => __('Your name, as entered in the comment form.', 'consentimental'), 'duration' => __('347 days', 'consentimental'), 'domain' => ''],
					['name' => 'comment_author_email_*', 'purpose' => __('Your email address, as entered in the comment form.', 'consentimental'), 'duration' => __('347 days', 'consentimental'), 'domain' => ''],
					['name' => 'comment_author_url_*', 'purpose' => __('Your website, as entered in the comment form.', 'consentimental'), 'duration' => __('347 days', 'consentimental'), 'domain' => ''],
				],
				'active' => true,
			'sale_sharing' => 'not_involved',
			]);
		}
	}

	private function comment_cookies_possible(): bool
	{
		if (!get_option('show_comments_cookies_opt_in')) {
			return false;
		}

		if ('open' === get_option('default_comment_status')) {
			return true;
		}

		$cached = get_transient(self::COMMENTS_TRANSIENT);

		if (false !== $cached) {
			return '1' === $cached;
		}

		global $wpdb;
		$open = null !== $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE comment_status = 'open' AND post_status = 'publish' LIMIT 1");
		set_transient(self::COMMENTS_TRANSIENT, $open ? '1' : '0', 12 * HOUR_IN_SECONDS);

		return $open;
	}

	public static function flush_cache(): void
	{
		delete_transient(self::COMMENTS_TRANSIENT);
	}
}
