<?php

declare(strict_types=1);

namespace Consentimental\Tests;

use Consentimental\Consent\Category;
use Consentimental\Diagnostics\ConfigurationChecks;
use Consentimental\Diagnostics\Finding;
use Consentimental\Scripts\CustomScript;
use Consentimental\Services\Service;
use PHPUnit\Framework\TestCase;

final class ConfigurationChecksTest extends TestCase
{
    /**
     * @return Category[]
     */
    private function categories(): array
    {
        return [
            Category::from_array(['slug' => 'necessary', 'required' => true]),
            Category::from_array(['slug' => 'analytics']),
        ];
    }

    private function service(array $data, bool $built_in = false): Service
    {
        return Service::from_array($data + [
            'category' => 'analytics', 'purpose' => 'Statistics', 'privacy_policy_url' => 'https://x.test/privacy',
            'domains' => ['x.test'], 'active' => true,
        ], $built_in);
    }

    private function script(array $data): CustomScript
    {
        return CustomScript::from_array($data + ['id' => 's' . md5(serialize($data)), 'type' => 'url', 'url' => 'https://cdn.test/a.js', 'category' => 'analytics']);
    }

    /**
     * @return array<string, Finding>
     */
    private function run_checks(array $services = [], array $scripts = [], bool $policy = true, bool $pending = false): array
    {
        $findings = [];

        foreach (ConfigurationChecks::run($this->categories(), $services, $scripts, $policy, $pending, 5, 1000) as $finding) {
            $findings[$finding->id] = $finding;
        }

        return $findings;
    }

    public function test_clean_configuration_passes_every_applicable_check(): void
    {
        $findings = $this->run_checks([$this->service(['id' => 'svc'])], [$this->script(['service' => 'svc', 'name' => 'Svc'])]);

        foreach ($findings as $finding) {
            self::assertContains($finding->status, [Finding::PASSED, Finding::NOT_APPLICABLE], $finding->id);
            self::assertSame(1000, $finding->checked_at);
            self::assertNotSame('', $finding->scope);
        }
    }

    public function test_active_service_in_missing_category_fails_with_problem_impact_and_action(): void
    {
        $finding = $this->run_checks([$this->service(['id' => 'x', 'name' => 'Lost', 'category' => 'deleted'])])['service-categories'];

        self::assertSame(Finding::FAILED, $finding->status);
        self::assertSame(['Lost'], $finding->items);
        self::assertNotSame('', $finding->problem);
        self::assertNotSame('', $finding->impact);
        self::assertNotSame('', $finding->action);
    }

    public function test_inactive_service_in_missing_category_is_not_a_disclosure_problem(): void
    {
        $finding = $this->run_checks([$this->service(['id' => 'x', 'category' => 'deleted', 'active' => false])])['service-categories'];

        self::assertSame(Finding::PASSED, $finding->status);
    }

    public function test_script_bound_to_inactive_service_can_never_run(): void
    {
        $findings = $this->run_checks(
            [$this->service(['id' => 'off', 'active' => false])],
            [$this->script(['service' => 'off', 'name' => 'Dead']), $this->script(['category' => 'gone', 'name' => 'Orphan'])],
        );

        self::assertSame(Finding::FAILED, $findings['script-release']->status);
        self::assertSame(['Dead', 'Orphan'], $findings['script-release']->items);
    }

    public function test_script_without_service_and_script_in_required_category_are_warnings(): void
    {
        $findings = $this->run_checks([], [
            $this->script(['name' => 'Category only']),
            $this->script(['name' => 'Always', 'category' => 'necessary', 'url' => 'https://cdn.test/b.js']),
        ]);

        self::assertSame(['Category only'], $findings['script-service']->items);
        self::assertSame(['Always'], $findings['script-necessary']->items);
        self::assertSame(Finding::WARNING, $findings['script-necessary']->status);
    }

    public function test_incomplete_disclosure_is_reported(): void
    {
        $finding = $this->run_checks([$this->service(['id' => 'x', 'name' => 'Bare', 'purpose' => '', 'privacy_policy_url' => ''])])['service-disclosure'];

        self::assertSame(Finding::WARNING, $finding->status);
        self::assertSame(['Bare'], $finding->items);
    }

    public function test_service_findings_link_to_the_right_edit_screen(): void
    {
        $findings = ConfigurationChecks::run($this->categories(), [
            $this->service(['id' => 'custom-bare', 'name' => 'Custom', 'purpose' => '']),
            $this->service(['id' => 'code-bare', 'name' => 'From code', 'privacy_policy_url' => ''], built_in: true),
        ], [], true, false, 5, 1000, 'https://site.test/wp-admin/admin.php?page=consentimental-services');
        $finding = array_values(array_filter($findings, static fn (Finding $f) => 'service-disclosure' === $f->id))[0];

        self::assertSame(['Custom', 'From code'], $finding->items);
        self::assertSame([
            'https://site.test/wp-admin/admin.php?page=consentimental-services&action=edit&id=custom-bare',
            'https://site.test/wp-admin/admin.php?page=consentimental-services&action=override&id=code-bare',
        ], $finding->item_urls);
        self::assertStringContainsString('publish', $finding->action);
    }

    public function test_custom_service_without_detection_rules_is_reported_but_built_ins_are_not(): void
    {
        $findings = $this->run_checks([
            $this->service(['id' => 'blind', 'name' => 'Blind', 'domains' => []]),
            $this->service(['id' => 'scripted', 'name' => 'Scripted', 'domains' => []]),
            $this->service(['id' => 'core', 'name' => 'Core', 'domains' => []], built_in: true),
        ], [$this->script(['service' => 'scripted'])]);

        self::assertSame(['Blind'], $findings['service-detection']->items);
    }

    public function test_invalid_proxy_configuration_fails(): void
    {
        $findings = $this->run_checks([], [
            $this->script(['name' => 'Plain', 'proxy' => true, 'url' => 'http://cdn.test/a.js']),
            $this->script(['name' => 'Routes', 'proxy' => true, 'url' => 'https://cdn.test/c.js', 'proxy_paths' => 'DELETE /x']),
            $this->script(['name' => 'Origin', 'proxy' => true, 'url' => 'https://cdn.test/d.js', 'proxy_api_origin' => 'ftp://x']),
        ]);

        self::assertSame(Finding::FAILED, $findings['proxy-configuration']->status);
        self::assertCount(3, $findings['proxy-configuration']->items);
    }

    public function test_no_proxy_in_use_is_not_applicable_rather_than_passed(): void
    {
        self::assertSame(Finding::NOT_APPLICABLE, $this->run_checks()['proxy-configuration']->status);
    }

    public function test_duplicates_by_url_and_google_tag_id(): void
    {
        $lines = ConfigurationChecks::duplicates([
            $this->script(['name' => 'A', 'url' => 'https://www.googletagmanager.com/gtag/js?id=G-ABC123']),
            $this->script(['name' => 'B', 'type' => 'inline', 'url' => '', 'code' => "gtag('config', 'G-ABC123');"]),
            $this->script(['name' => 'C', 'url' => 'https://cdn.test/lib.js']),
            $this->script(['name' => 'D', 'url' => 'http://cdn.test/lib.js/']),
        ]);

        self::assertContains('G-ABC123: A, B', $lines);
        self::assertContains('cdn.test/lib.js: C, D', $lines);
        self::assertCount(2, $lines);
    }

    public function test_missing_policy_link_and_pending_draft_are_warnings(): void
    {
        $findings = $this->run_checks(policy: false, pending: true);

        self::assertSame(Finding::WARNING, $findings['policy-link']->status);
        self::assertSame(Finding::WARNING, $findings['pending-draft']->status);
        self::assertArrayNotHasKey('pending-draft', $this->run_checks());
    }
}
