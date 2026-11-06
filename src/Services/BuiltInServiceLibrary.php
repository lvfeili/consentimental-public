<?php

declare(strict_types=1);

namespace Consentimental\Services;

if (!defined('ABSPATH')) {
	exit;
}

final class BuiltInServiceLibrary
{
	public function register(ServiceRegistry $registry): void
	{
		$registry->register([
			'id' => 'meta-pixel',
			'name' => 'Meta Pixel',
			'provider' => 'Meta',
			'purpose' => __('Tracks conversions and enables remarketing on Facebook/Instagram.', 'consentimental'),
			'category' => 'marketing',
			'privacy_policy_url' => 'https://www.facebook.com/privacy/policy/',
			'domains' => ['connect.facebook.net', 'facebook.com/tr'],
			'cookies' => [
				['name' => '_fbp', 'purpose' => __('Identifies browsers for ad delivery.', 'consentimental'), 'duration' => __('90 days', 'consentimental'), 'domain' => ''],
				['name' => '_fbc', 'purpose' => __('Stores the last Facebook ad click.', 'consentimental'), 'duration' => __('90 days', 'consentimental'), 'domain' => ''],
			],
		]);

		$registry->register([
			'id' => 'microsoft-clarity',
			'name' => 'Microsoft Clarity',
			'provider' => 'Microsoft',
			'purpose' => __('Session recordings and heatmaps to analyse visitor behaviour.', 'consentimental'),
			'category' => 'analytics',
			'privacy_policy_url' => 'https://privacy.microsoft.com/privacystatement',
			'domains' => ['clarity.ms'],
			'cookies' => [
				['name' => '_clck', 'purpose' => __('Persists the Clarity user id across visits.', 'consentimental'), 'duration' => __('1 year', 'consentimental'), 'domain' => ''],
				['name' => '_clsk', 'purpose' => __('Connects page views into a single session recording.', 'consentimental'), 'duration' => __('1 day', 'consentimental'), 'domain' => ''],
			],
		]);

		$registry->register([
			'id' => 'youtube',
			'name' => 'YouTube',
			'provider' => 'Google',
			'purpose' => __('Embeds and plays YouTube videos.', 'consentimental'),
			'category' => 'marketing',
			'privacy_policy_url' => 'https://policies.google.com/privacy',
			'domains' => ['youtube.com', 'youtube-nocookie.com', 'ytimg.com'],
			'iframe_patterns' => ['youtube.com/embed', 'youtube-nocookie.com/embed'],
			'cookies' => [
				['name' => 'VISITOR_INFO1_LIVE', 'purpose' => __('Estimates bandwidth for the video player.', 'consentimental'), 'duration' => __('6 months', 'consentimental'), 'domain' => ''],
				['name' => 'YSC', 'purpose' => __('Tracks embedded video views.', 'consentimental'), 'duration' => __('session', 'consentimental'), 'domain' => ''],
			],
		]);

		$registry->register([
			'id' => 'vimeo',
			'name' => 'Vimeo',
			'provider' => 'Vimeo',
			'purpose' => __('Embeds and plays Vimeo videos.', 'consentimental'),
			'category' => 'marketing',
			'privacy_policy_url' => 'https://vimeo.com/privacy',
			'domains' => ['vimeo.com', 'vimeocdn.com'],
			'iframe_patterns' => ['player.vimeo.com/video'],
			'cookies' => [
				['name' => 'vuid', 'purpose' => __('Identifies the visitor across Vimeo embeds.', 'consentimental'), 'duration' => __('2 years', 'consentimental'), 'domain' => ''],
			],
		]);

		$registry->register([
			'id' => 'google-maps',
			'name' => 'Google Maps',
			'provider' => 'Google',
			'purpose' => __('Embeds interactive maps.', 'consentimental'),
			'category' => 'functional',
			'privacy_policy_url' => 'https://policies.google.com/privacy',
			'domains' => ['maps.googleapis.com', 'maps.google.com'],
			'iframe_patterns' => ['google.com/maps/embed'],
		]);

		$registry->register([
			'id' => 'recaptcha',
			'name' => 'reCAPTCHA',
			'provider' => 'Google',
			'purpose' => __('Protects forms from spam and abuse.', 'consentimental'),
			'category' => 'functional',
			'privacy_policy_url' => 'https://policies.google.com/privacy',
			'domains' => ['google.com/recaptcha', 'gstatic.com/recaptcha'],
			'script_patterns' => ['grecaptcha'],
			'cookies' => [
				['name' => '_GRECAPTCHA', 'purpose' => __('Distinguishes humans from bots.', 'consentimental'), 'duration' => __('6 months', 'consentimental'), 'domain' => ''],
			],
		]);

		$registry->register([
			'id' => 'cloudflare-turnstile',
			'name' => 'Cloudflare Turnstile',
			'provider' => 'Cloudflare',
			'purpose' => __('Protects forms from spam and abuse without tracking cookies.', 'consentimental'),
			'category' => 'functional',
			'privacy_policy_url' => 'https://www.cloudflare.com/privacypolicy/',
			'domains' => ['challenges.cloudflare.com'],
			'script_patterns' => ['turnstile'],
		]);

		$registry->register([
			'id' => 'hotjar',
			'name' => 'Hotjar',
			'provider' => 'Hotjar',
			'purpose' => __('Session recordings, heatmaps and on-site surveys.', 'consentimental'),
			'category' => 'analytics',
			'privacy_policy_url' => 'https://www.hotjar.com/legal/policies/privacy/',
			'domains' => ['hotjar.com'],
			'cookies' => [
				['name' => '_hjSession*', 'purpose' => __('Holds the current Hotjar session state.', 'consentimental'), 'duration' => __('30 minutes', 'consentimental'), 'domain' => ''],
				['name' => '_hjid', 'purpose' => __('Identifies the visitor across sessions.', 'consentimental'), 'duration' => __('1 year', 'consentimental'), 'domain' => ''],
			],
		]);

		$registry->register([
			'id' => 'linkedin-insight',
			'name' => 'LinkedIn Insight Tag',
			'provider' => 'LinkedIn',
			'purpose' => __('Tracks conversions and enables retargeting on LinkedIn.', 'consentimental'),
			'category' => 'marketing',
			'privacy_policy_url' => 'https://www.linkedin.com/legal/privacy-policy',
			'domains' => ['snap.licdn.com', 'px.ads.linkedin.com'],
			'cookies' => [
				['name' => 'bcookie', 'purpose' => __('Identifies the browser for LinkedIn ads.', 'consentimental'), 'duration' => __('2 years', 'consentimental'), 'domain' => ''],
				['name' => 'UserMatchHistory', 'purpose' => __('LinkedIn Ads retargeting.', 'consentimental'), 'duration' => __('30 days', 'consentimental'), 'domain' => ''],
			],
		]);

		$registry->register([
			'id' => 'tiktok-pixel',
			'name' => 'TikTok Pixel',
			'provider' => 'TikTok',
			'purpose' => __('Tracks conversions and enables retargeting on TikTok.', 'consentimental'),
			'category' => 'marketing',
			'privacy_policy_url' => 'https://www.tiktok.com/legal/privacy-policy',
			'domains' => ['analytics.tiktok.com'],
			'cookies' => [
				['name' => '_ttp', 'purpose' => __('Identifies the browser for TikTok ads.', 'consentimental'), 'duration' => __('13 months', 'consentimental'), 'domain' => ''],
			],
		]);

		$registry->register([
			'id' => 'matomo',
			'name' => 'Matomo',
			'provider' => __('Self-hosted', 'consentimental'),
			'purpose' => __('Measures website usage and traffic on infrastructure the site operator controls.', 'consentimental'),
			'category' => 'analytics',
			'script_patterns' => ['matomo.js', 'piwik.js'],
			'cookies' => [
				['name' => '_pk_id*', 'purpose' => __('Identifies the visitor across sessions.', 'consentimental'), 'duration' => __('13 months', 'consentimental'), 'domain' => ''],
				['name' => '_pk_ses*', 'purpose' => __('Holds the current Matomo session state.', 'consentimental'), 'duration' => __('30 minutes', 'consentimental'), 'domain' => ''],
			],
		]);
	}
}
