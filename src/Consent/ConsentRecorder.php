<?php

declare(strict_types=1);

namespace Consentimental\Consent;

if (!defined('ABSPATH')) {
	exit;
}

final class ConsentRecorder
{
	public function __construct(
		private readonly ConsentLogRepository $log,
		private readonly Settings $settings,
	) {
	}

	public function register(): void
	{
		add_action('cmp_consent_changed', $this->handle(...), 10, 3);
	}

	/**
	 * @param array{disclosure?: array{hash: string, language: string}|null} $context
	 */
	private function handle(ConsentState $current, ?ConsentState $previous, array $context = []): void
	{
		if (!$this->settings->get('consent_log_enabled')) {
			return;
		}

		$this->log->record(
			consent_id: $current->consent_id,
			action: null === $previous ? 'granted' : 'updated',
			categories: $current->categories,
			services: $current->services,
			config_version: $current->config_version,
			ip_anonymized: $this->anonymized_ip(),
			user_agent: $this->user_agent(),
			page_url: $this->page_url(),
			disclosure_hash: $context['disclosure']['hash'] ?? null,
			language: $context['disclosure']['language'] ?? null,
		);
	}

	private function anonymized_ip(): ?string
	{
		return IpAnonymizer::mask((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
	}

	private function user_agent(): ?string
	{
		$user_agent = sanitize_text_field((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));

		return '' === $user_agent ? null : substr($user_agent, 0, 255);
	}

	private function page_url(): ?string
	{
		$referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');

		if ('' === $referer) {
			return null;
		}

		$parts = wp_parse_url($referer);

		if (!is_array($parts) || empty($parts['host'])) {
			return null;
		}

		$clean = ($parts['scheme'] ?? 'https') . '://' . $parts['host'] . ($parts['path'] ?? '');
		$sanitized = esc_url_raw($clean);

		return '' === $sanitized ? null : substr($sanitized, 0, 255);
	}
}
