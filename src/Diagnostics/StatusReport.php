<?php

declare(strict_types=1);

namespace Consentimental\Diagnostics;

use Consentimental\Admin\ScannerPage;
use Consentimental\Consent\CategoryRepository;
use Consentimental\Consent\DraftManager;
use Consentimental\Consent\Settings;
use Consentimental\Frontend\BannerContent;
use Consentimental\Scripts\CustomScriptRepository;
use Consentimental\Services\Service;
use Consentimental\Services\ServiceRegistry;

if (!defined('ABSPATH')) {
	exit;
}

final class StatusReport
{
	private const SCAN_MAX_AGE_DAYS = 30;

	public function __construct(
		private readonly CategoryRepository $categories,
		private readonly ServiceRegistry $services,
		private readonly CustomScriptRepository $scripts,
		private readonly Settings $settings,
		private readonly DraftManager $draft,
		private readonly ?ProxyHealth $proxy_health,
		private readonly VerificationLog $verifications,
	) {
	}

	/**
	 * @return Finding[] for the published (live) configuration
	 */
	public function findings(): array
	{
		$now = time();
		$version = $this->settings->config_version();
		$page = static fn (string $slug): string => admin_url('admin.php?page=' . $slug);

		$findings = ConfigurationChecks::run(
			$this->categories->all(),
			$this->services->all(),
			$this->scripts->all(),
			BannerContent::has_published_policy($this->settings),
			$this->draft->has_pending(),
			$version,
			$now,
			$page('consentimental-services'),
			$page('consentimental-scripts'),
			$page('consentimental-banner'),
			$page('consentimental-publish'),
		);
		if ($this->settings->get('california_enabled')) {
			$unreviewed = array_values(array_map(static fn ($service) => $service->name,
				array_filter($this->services->all(), static fn ($service) => $service->active && 'unreviewed' === $service->sale_sharing)));
			$unassigned = array_values(array_map(static fn ($script) => $script->name,
				array_filter($this->scripts->all(), static fn ($script) => '' === $script->service)));
			$findings[] = new Finding(
				id: 'california-classification', area: Finding::AREA_CONFIGURATION,
				status: $unreviewed || $unassigned ? Finding::FAILED : Finding::PASSED,
				title: __('California service review', 'consentimental'),
				scope: __('Published service classifications and script assignments', 'consentimental'), checked_at: $now,
				problem: $unreviewed || $unassigned ? __('Services or scripts still need review.', 'consentimental') : '',
				impact: __('Unreviewed services and scripts without a service assignment are blocked while California controls are enabled.', 'consentimental'),
				action: __('Classify services, assign scripts to reviewed services, then publish and verify.', 'consentimental'),
				action_url: $page('consentimental-services'), items: [...$unreviewed, ...$unassigned],
			);
		}

		$scan = ScannerPage::load($this->services);
		$scan_summary = null === $scan ? null : [
			'scanned_at' => $scan['scanned_at'],
			'pages_scanned' => $scan['pages_scanned'],
			'unconfirmed' => array_values(array_map(
				static fn (array $entry): string => $entry['service']->name,
				array_filter($scan['matched'], static fn (array $entry): bool => !$entry['service']->active),
			)),
			'unknown_hosts' => array_keys($scan['unknown_domains']),
		];

		array_push($findings, ...self::discovery_findings($scan_summary, $now, $page(ScannerPage::PAGE_SLUG)));
		$findings[] = self::proxy_finding(null === $this->proxy_health ? [] : $this->proxy_health->rows(), $now, $page('consentimental-proxy-health'));
		$findings[] = self::verification_finding(VerificationLog::TYPE_BROWSER, $this->verifications->latest(VerificationLog::TYPE_BROWSER), $version, $now, $page('consentimental-status') . '#cmp-verify-browser');
		$findings[] = self::verification_finding(VerificationLog::TYPE_VENDOR, $this->verifications->latest(VerificationLog::TYPE_VENDOR), $version, $now, $page('consentimental-status') . '#cmp-verify-vendor');

		return $findings;
	}


	/**
	 * @param array{scanned_at: int, pages_scanned: int, unconfirmed: string[], unknown_hosts: string[]}|null $scan
	 * @return Finding[]
	 */
	public static function discovery_findings(?array $scan, int $now, string $url = ''): array
	{
		if (null === $scan) {
			return [new Finding(
				'scan',
				Finding::AREA_DISCOVERY,
				Finding::NOT_CHECKED,
				__('The site has not been scanned', 'consentimental'),
				__('No scan results', 'consentimental'),
				null,
				Finding::EVIDENCE_NONE,
				problem: __('No scan has looked for tracking services on your pages.', 'consentimental'),
				impact: __('Services that are on the site but not configured are blocked but never offered to visitors, and unknown vendors may load without consent.', 'consentimental'),
				action: __('Run a site scan and review the results.', 'consentimental'),
				action_url: $url,
			)];
		}

		$scope = sprintf(
			/* translators: %d: number of pages */
			__('%d published pages, as served to a logged-out visitor. Static HTML only: content added later by JavaScript, pages behind a login, and checkout steps are not seen.', 'consentimental'),
			$scan['pages_scanned'],
		);
		$findings = [];

		if ($now - $scan['scanned_at'] > self::SCAN_MAX_AGE_DAYS * DAY_IN_SECONDS) {
			$findings[] = new Finding(
				'scan-age',
				Finding::AREA_DISCOVERY,
				Finding::WARNING,
				__('The last scan is out of date', 'consentimental'),
				$scope,
				$scan['scanned_at'],
				problem: __('Themes, plugins and content may have changed since the last scan.', 'consentimental'),
				impact: __('Newly added services would not be listed below.', 'consentimental'),
				action: __('Scan the site again.', 'consentimental'),
				action_url: $url,
			);
		}

		$findings[] = [] === $scan['unconfirmed']
			? new Finding('scan-unconfirmed', Finding::AREA_DISCOVERY, Finding::PASSED, __('Every service the scan detected is confirmed', 'consentimental'), $scope, $scan['scanned_at'])
			: new Finding(
				'scan-unconfirmed',
				Finding::AREA_DISCOVERY,
				Finding::WARNING,
				__('Detected services are not confirmed', 'consentimental'),
				$scope,
				$scan['scanned_at'],
				problem: __('The scan found these services on your pages, but they are not active in the configuration.', 'consentimental'),
				impact: __('They stay blocked, but the banner does not list them, so visitors cannot allow them and the site feature they provide will not work.', 'consentimental'),
				action: __('Confirm each service and its category, or remove it from your pages. Then publish.', 'consentimental'),
				action_url: $url,
				items: $scan['unconfirmed'],
			);

		if ([] !== $scan['unknown_hosts']) {
			$findings[] = new Finding(
				'scan-unknown',
				Finding::AREA_DISCOVERY,
				Finding::WARNING,
				__('Unrecognized external hosts', 'consentimental'),
				$scope,
				$scan['scanned_at'],
				problem: __('Your pages load scripts, frames or images from these hosts, and no configured service matches them.', 'consentimental'),
				impact: __('If any of them sets cookies or collects personal data, it loads before and regardless of consent.', 'consentimental'),
				action: __('For each host, decide whether it needs consent. If it does, create a service with its domain.', 'consentimental'),
				action_url: $url,
				items: $scan['unknown_hosts'],
			);
		}

		return $findings;
	}

	/**
	 * @param list<array{name: string, key: string, status: string, endpoint: array<string, mixed>}> $rows ProxyHealth::rows()
	 */
	public static function proxy_finding(array $rows, int $now, string $url = ''): Finding
	{
		$scope = sprintf(
			/* translators: %d: number of endpoints */
			__('HTTP forwarding from this server to the vendor, %d endpoints. Does not show that browsers block trackers before consent, or that the vendor accepted the data.', 'consentimental'),
			count($rows),
		);

		if ([] === $rows) {
			return new Finding('proxy-availability', Finding::AREA_PROXY, Finding::NOT_APPLICABLE, __('Proxy availability', 'consentimental'), __('No published script uses the proxy', 'consentimental'), $now);
		}

		$by_status = [];

		foreach ($rows as $row) {
			$by_status[$row['status']][] = $row['name'] . ' — ' . $row['key'];
		}

		$times = array_filter(array_map(static fn (array $row): int => (int) ($row['endpoint']['checked_at'] ?? 0), $rows));
		$oldest = [] === $times ? null : min($times);

		if (isset($by_status['failing'])) {
			return new Finding(
				'proxy-availability',
				Finding::AREA_PROXY,
				Finding::FAILED,
				__('Proxy endpoints are failing', 'consentimental'),
				$scope,
				[] === $times ? null : max($times),
				problem: __('Three or more consecutive requests to these endpoints failed.', 'consentimental'),
				impact: __('The affected integrations cannot load or send data, even for visitors who consented.', 'consentimental'),
				action: __('Open Proxy diagnostics for the error, check the vendor URL and your server’s outbound connectivity, then check again.', 'consentimental'),
				action_url: $url,
				items: $by_status['failing'],
			);
		}

		$unverified = array_merge($by_status['unknown'] ?? [], $by_status['stale'] ?? [], $by_status['suspect'] ?? []);

		if ([] !== $unverified) {
			return new Finding(
				'proxy-availability',
				Finding::AREA_PROXY,
				Finding::NOT_CHECKED,
				__('Some proxy endpoints have no recent successful check', 'consentimental'),
				$scope,
				$oldest,
				Finding::EVIDENCE_NONE,
				problem: __('These endpoints were never checked, have not been used in the last 24 hours, or just failed once.', 'consentimental'),
				impact: __('Their availability is unknown. Runtime endpoints are only observed in real consented traffic.', 'consentimental'),
				action: __('Run a check from Proxy diagnostics, then allow the service in a browser to generate real traffic.', 'consentimental'),
				action_url: $url,
				items: $unverified,
			);
		}

		return new Finding('proxy-availability', Finding::AREA_PROXY, Finding::PASSED, __('Proxy endpoints responded successfully in the last 24 hours', 'consentimental'), $scope, $oldest);
	}

	/**
	 * @param array<string, mixed>|null $latest VerificationLog::latest()
	 */
	public static function verification_finding(string $type, ?array $latest, int $config_version, int $now, string $url = ''): Finding
	{
		$browser = VerificationLog::TYPE_BROWSER === $type;
		$area = $browser ? Finding::AREA_BROWSER : Finding::AREA_VENDOR;
		$id = $browser ? 'browser-enforcement' : 'vendor-receipt';
		$name = $browser ? __('Consent enforcement in a browser', 'consentimental') : __('Data received by vendors', 'consentimental');
		$scope = $browser
			? __('Manual check by an administrator. The plugin cannot observe visitors’ browsers from the server.', 'consentimental')
			: __('Manual check by an administrator in each vendor’s dashboard. The plugin cannot see whether a vendor accepted data.', 'consentimental');
		$action = $browser
			? __('Follow the browser verification steps and record the result.', 'consentimental')
			: __('Check each vendor’s dashboard or debug view and record the result.', 'consentimental');

		if (null === $latest) {
			return new Finding($id, $area, Finding::NOT_CHECKED, $name, $scope, null, Finding::EVIDENCE_NONE,
				problem: __('No verification has been recorded.', 'consentimental'),
				impact: __('Nothing on this page shows that this works for visitors.', 'consentimental'),
				action: $action, action_url: $url);
		}

		$checked_at = (int) $latest['checked_at'];
		/* translators: 1: user login, 2: configuration version */
		$who = sprintf(__('Recorded by %1$s against configuration v%2$d.', 'consentimental'), (string) $latest['user_login'], (int) $latest['config_version']);

		if ((int) $latest['config_version'] !== $config_version) {
			return new Finding($id, $area, Finding::NOT_CHECKED, $name, $scope . ' ' . $who, $checked_at, Finding::EVIDENCE_ADMINISTRATOR,
				problem: __('The configuration has been published again since this was verified.', 'consentimental'),
				impact: __('The earlier result does not cover the current services and categories.', 'consentimental'),
				action: $action, action_url: $url);
		}

		if ($now - $checked_at > VerificationLog::MAX_AGE_DAYS * DAY_IN_SECONDS) {
			return new Finding($id, $area, Finding::NOT_CHECKED, $name, $scope . ' ' . $who, $checked_at, Finding::EVIDENCE_ADMINISTRATOR,
				/* translators: %d: number of days */
				problem: sprintf(__('The last verification is more than %d days old.', 'consentimental'), VerificationLog::MAX_AGE_DAYS),
				impact: __('Theme, plugin and vendor changes since then are not covered.', 'consentimental'),
				action: $action, action_url: $url);
		}

		return match ($latest['result']) {
			Finding::FAILED => new Finding($id, $area, Finding::FAILED, $name, $scope . ' ' . $who, $checked_at, Finding::EVIDENCE_ADMINISTRATOR,
				problem: __('The administrator reported a problem.', 'consentimental') . ('' !== (string) $latest['notes'] ? ' ' . (string) $latest['notes'] : ''),
				impact: $browser ? __('Visitors’ choices may not be respected.', 'consentimental') : __('Data may be missing, or sent without consent.', 'consentimental'),
				action: __('Fix the problem, then verify again. The troubleshooting guide lists common causes.', 'consentimental'), action_url: $url),
			Finding::PASSED => new Finding($id, $area, Finding::PASSED, $name, $scope . ' ' . $who, $checked_at, Finding::EVIDENCE_ADMINISTRATOR),
			default => new Finding($id, $area, Finding::NOT_CHECKED, $name, $scope . ' ' . $who, $checked_at, Finding::EVIDENCE_ADMINISTRATOR,
				problem: __('Only some verification steps were confirmed.', 'consentimental'),
				impact: __('The unconfirmed steps remain unverified.', 'consentimental'),
				action: $action, action_url: $url),
		};
	}

	/**
	 * @param Service[] $services
	 * @param string[] $detected_ids
	 * @return array<string, array{service: Service, detected: bool, confirmed: bool}>
	 */
	public static function service_states(array $services, array $detected_ids): array
	{
		$states = [];

		foreach ($services as $service) {
			$detected = in_array($service->id, $detected_ids, true);

			if ($detected || $service->active) {
				$states[$service->id] = ['service' => $service, 'detected' => $detected, 'confirmed' => $service->active];
			}
		}

		return $states;
	}
}
