<?php

declare(strict_types=1);

namespace Consentimental\Tests;

use Consentimental\Diagnostics\Finding;
use Consentimental\Diagnostics\StatusReport;
use Consentimental\Diagnostics\VerificationLog;
use Consentimental\Services\Service;
use PHPUnit\Framework\TestCase;

final class StatusReportTest extends TestCase
{
    private const NOW = 10_000_000;

    public function test_unscanned_site_is_not_checked_not_passed(): void
    {
        [$finding] = StatusReport::discovery_findings(null, self::NOW);

        self::assertSame(Finding::NOT_CHECKED, $finding->status);
        self::assertNull($finding->checked_at);
        self::assertSame(Finding::EVIDENCE_NONE, $finding->evidence);
    }

    public function test_scan_results_distinguish_unconfirmed_and_unknown(): void
    {
        $findings = StatusReport::discovery_findings([
            'scanned_at' => self::NOW - 60, 'pages_scanned' => 12,
            'unconfirmed' => ['YouTube'], 'unknown_hosts' => ['cdn.unknown.test'],
        ], self::NOW);
        $by_id = array_column(array_map(static fn (Finding $f) => ['id' => $f->id, 'f' => $f], $findings), 'f', 'id');

        self::assertArrayNotHasKey('scan-age', $by_id);
        self::assertSame(['YouTube'], $by_id['scan-unconfirmed']->items);
        self::assertSame(['cdn.unknown.test'], $by_id['scan-unknown']->items);
        self::assertStringContainsString('12', $by_id['scan-unconfirmed']->scope);
        self::assertSame(self::NOW - 60, $by_id['scan-unknown']->checked_at);
    }

    public function test_old_scan_is_flagged(): void
    {
        $findings = StatusReport::discovery_findings(['scanned_at' => self::NOW - 31 * DAY_IN_SECONDS, 'pages_scanned' => 1, 'unconfirmed' => [], 'unknown_hosts' => []], self::NOW);

        self::assertSame('scan-age', $findings[0]->id);
        self::assertSame(Finding::PASSED, $findings[1]->status);
    }

    private function row(string $status, int $checked_at = self::NOW): array
    {
        return ['name' => 'Umami', 'key' => 'GET /script.js', 'status' => $status, 'endpoint' => 'unknown' === $status ? [] : ['checked_at' => $checked_at]];
    }

    public function test_proxy_states(): void
    {
        self::assertSame(Finding::NOT_APPLICABLE, StatusReport::proxy_finding([], self::NOW)->status);
        self::assertSame(Finding::PASSED, StatusReport::proxy_finding([$this->row('healthy')], self::NOW)->status);
        self::assertSame(Finding::NOT_CHECKED, StatusReport::proxy_finding([$this->row('healthy'), $this->row('unknown')], self::NOW)->status);
        self::assertSame(Finding::NOT_CHECKED, StatusReport::proxy_finding([$this->row('stale')], self::NOW)->status);
        self::assertSame(Finding::FAILED, StatusReport::proxy_finding([$this->row('failing'), $this->row('unknown')], self::NOW)->status);
    }

    public function test_proxy_scope_never_claims_enforcement_or_receipt(): void
    {
        $scope = StatusReport::proxy_finding([$this->row('healthy')], self::NOW)->scope;

        self::assertStringContainsString('Does not show', $scope);
        self::assertStringContainsString('accepted the data', $scope);
    }

    private function record(array $overrides = []): array
    {
        return $overrides + ['result' => Finding::PASSED, 'checked_at' => self::NOW - 100, 'config_version' => 7, 'user_login' => 'admin', 'notes' => ''];
    }

    public function test_browser_verification_states(): void
    {
        $type = VerificationLog::TYPE_BROWSER;

        $none = StatusReport::verification_finding($type, null, 7, self::NOW);
        self::assertSame(Finding::NOT_CHECKED, $none->status);
        self::assertSame(Finding::AREA_BROWSER, $none->area);

        $passed = StatusReport::verification_finding($type, $this->record(), 7, self::NOW);
        self::assertSame(Finding::PASSED, $passed->status);
        self::assertSame(Finding::EVIDENCE_ADMINISTRATOR, $passed->evidence, 'Manual results are never presented as automated');

        self::assertSame(Finding::NOT_CHECKED, StatusReport::verification_finding($type, $this->record(), 8, self::NOW)->status, 'Republishing invalidates it');
        self::assertSame(Finding::NOT_CHECKED, StatusReport::verification_finding($type, $this->record(['checked_at' => self::NOW - 91 * DAY_IN_SECONDS]), 7, self::NOW)->status);
        self::assertSame(Finding::NOT_CHECKED, StatusReport::verification_finding($type, $this->record(['result' => 'partial']), 7, self::NOW)->status);

        $failed = StatusReport::verification_finding($type, $this->record(['result' => Finding::FAILED, 'notes' => 'GA loads early']), 7, self::NOW);
        self::assertSame(Finding::FAILED, $failed->status);
        self::assertStringContainsString('GA loads early', $failed->problem);
    }

    public function test_vendor_receipt_is_its_own_area(): void
    {
        self::assertSame(Finding::AREA_VENDOR, StatusReport::verification_finding(VerificationLog::TYPE_VENDOR, null, 1, self::NOW)->area);
    }

    public function test_summary_is_never_passed_while_something_was_not_checked(): void
    {
        $passed = new Finding('a', Finding::AREA_CONFIGURATION, Finding::PASSED, 't', 's', 1);
        $skipped = new Finding('b', Finding::AREA_VENDOR, Finding::NOT_CHECKED, 't', 's', null);
        $na = new Finding('c', Finding::AREA_PROXY, Finding::NOT_APPLICABLE, 't', 's', 1);
        $failed = new Finding('d', Finding::AREA_PROXY, Finding::FAILED, 't', 's', 1);

        self::assertSame('passed', Finding::summarize([$passed, $na])['state']);
        self::assertSame('incomplete', Finding::summarize([$passed, $skipped])['state']);
        self::assertSame('problems', Finding::summarize([$passed, $skipped, $failed])['state']);
    }

    public function test_service_states_distinguish_detected_and_confirmed(): void
    {
        $services = [
            Service::from_array(['id' => 'both', 'category' => 'analytics', 'active' => true]),
            Service::from_array(['id' => 'detected', 'category' => 'analytics', 'active' => false]),
            Service::from_array(['id' => 'confirmed', 'category' => 'analytics', 'active' => true]),
            Service::from_array(['id' => 'neither', 'category' => 'analytics', 'active' => false]),
        ];

        $states = StatusReport::service_states($services, ['both', 'detected']);

        self::assertSame(['both', 'detected', 'confirmed'], array_keys($states));
        self::assertTrue($states['both']['detected'] && $states['both']['confirmed']);
        self::assertFalse($states['detected']['confirmed']);
        self::assertFalse($states['confirmed']['detected']);
    }
}
