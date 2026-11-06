<?php

declare(strict_types=1);

namespace Consentimental\Integrations;

use Consentimental\Services\ServiceRegistry;

if (!defined('ABSPATH')) {
	exit;
}

final class GoogleServices
{
	public function __construct(private readonly ConsentMode $consent_mode)
	{
	}

	public function register(ServiceRegistry $registry): void
	{
		$registry->register([
			'id' => 'google-tag-manager',
			'name' => 'Google Tag Manager',
			'provider' => 'Google',
			'purpose' => __('Loads and manages other tracking and marketing tags.', 'consentimental'),
			'category' => 'marketing',
			'privacy_policy_url' => 'https://policies.google.com/privacy',
			'domains' => $this->consent_mode->is_strict_gtm_blocking()
				? ['googletagmanager.com/gtm.js']
				: [],
			'script_patterns' => $this->consent_mode->is_strict_gtm_blocking()
				? ['googletagmanager.com/gtm.js']
				: [],
			'iframe_patterns' => $this->consent_mode->is_strict_gtm_blocking()
				? ['googletagmanager.com/ns.html']
				: [],
		]);

		$registry->register([
			'id' => 'google-analytics',
			'name' => 'Google Analytics 4',
			'provider' => 'Google',
			'purpose' => __('Measures website usage and traffic.', 'consentimental'),
			'category' => 'analytics',
			'privacy_policy_url' => 'https://policies.google.com/privacy',
			'domains' => [
				'google-analytics.com',
				'googletagmanager.com/gtag/js?id=G-',
			],
			'cookies' => [
				[
					'name' => '_ga',
					'purpose' => __('Distinguishes unique visitors.', 'consentimental'),
					'duration' => __('2 years', 'consentimental'),
					'domain' => '',
				],
				[
					'name' => '_ga_*',
					'purpose' => __('Persists session state for a specific GA4 property.', 'consentimental'),
					'duration' => __('2 years', 'consentimental'),
					'domain' => '',
				],
			],
		]);

		$registry->register([
			'id' => 'google-ads',
			'name' => 'Google Ads',
			'provider' => 'Google',
			'purpose' => __('Tracks conversions and enables remarketing.', 'consentimental'),
			'category' => 'marketing',
			'privacy_policy_url' => 'https://policies.google.com/privacy',
			'domains' => [
				'googleadservices.com',
				'googlesyndication.com',
				'googletagmanager.com/gtag/js?id=AW-',
			],
			'cookies' => [
				[
					'name' => '_gcl_au',
					'purpose' => __('Stores and tracks conversions.', 'consentimental'),
					'duration' => __('90 days', 'consentimental'),
					'domain' => '',
				],
			],
		]);
	}
}
