<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services\CaringCommunity;

use App\Core\TenantContext;
use App\Services\CaringCommunity\CommercialBoundaryService;
use App\Services\CaringCommunity\ExternalIntegrationBacklogService;
use App\Services\CaringCommunity\IsolatedNodeReadinessService;
use App\Services\CaringCommunity\OperatingPolicyService;
use App\Services\CaringCommunity\PilotDisclosurePackService;
use App\Services\CaringCommunity\PilotLaunchReadinessService;
use App\Services\CaringCommunity\PilotScoreboardService;
use App\Services\CaringCommunity\TenantDataQualityService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Mockery\MockInterface;
use Tests\Laravel\TestCase;

/**
 * PilotLaunchReadinessServiceTest
 *
 * Covers the AG95 readiness orchestrator:
 *  - report() overall structure and top-level keys
 *  - overall status computation (blocked, needs_review, not_started, ready)
 *  - can_launch logic (all sections ready → true; any blocker → false)
 *  - isolated_node_required flag derived from tenant_settings
 *  - isolated_node section skipped from blockers when hosted (not required)
 *  - launchPilot() happy path persists two rows and returns launched_at/launched_by_id
 *  - launchPilot() ALREADY_LAUNCHED guard
 *  - launchPilot() CANNOT_LAUNCH when not all sections ready
 *  - acknowledgeBoundary() persists flag and returns acknowledged=true
 *  - boundaryAcknowledged drives commercial_boundary to ready
 *  - section-level: each section status reflects mocked sub-service output
 *  - report() sections list has exactly 7 entries with required keys
 */
class PilotLaunchReadinessServiceTest extends TestCase
{
    use DatabaseTransactions;

    // High tenant ID to avoid colliding with any real fixture data.
    private const TENANT_ID = 8877;

    // ──────────────────────────────────────────────────────────────────────────
    // Sub-service mock fields (fresh Mockery mocks per test)
    // ──────────────────────────────────────────────────────────────────────────

    /** @var MockInterface&PilotDisclosurePackService */
    private MockInterface $disclosurePack;

    /** @var MockInterface&OperatingPolicyService */
    private MockInterface $operatingPolicy;

    /** @var MockInterface&CommercialBoundaryService */
    private MockInterface $commercialBoundary;

    /** @var MockInterface&PilotScoreboardService */
    private MockInterface $pilotScoreboard;

    /** @var MockInterface&TenantDataQualityService */
    private MockInterface $tenantDataQuality;

    /** @var MockInterface&IsolatedNodeReadinessService */
    private MockInterface $isolatedNode;

    /** @var MockInterface&ExternalIntegrationBacklogService */
    private MockInterface $externalIntegrations;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->testTenantId = self::TENANT_ID;
        TenantContext::setById(self::TENANT_ID);

        // Ensure the tenant row exists so FK constraints don't fire.
        DB::table('tenants')->updateOrInsert(
            ['id' => self::TENANT_ID],
            [
                'name'              => 'PilotTest Tenant',
                'slug'              => 'pilot-test-' . self::TENANT_ID,
                'domain'            => null,
                'is_active'         => true,
                'depth'             => 0,
                'allows_subtenants' => false,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );

        // Clean any launch/setting state from prior non-transactional runs.
        DB::table('tenant_settings')
            ->where('tenant_id', self::TENANT_ID)
            ->whereIn('setting_key', [
                'caring_community.pilot_launched_at',
                'caring_community.pilot_launched_by',
                'caring.launch_readiness.boundary_acknowledged',
                'caring.isolated_node.deployment_mode',
            ])
            ->delete();

        // Create fresh mocks for each test.
        $this->disclosurePack       = Mockery::mock(PilotDisclosurePackService::class);
        $this->operatingPolicy      = Mockery::mock(OperatingPolicyService::class);
        $this->commercialBoundary   = Mockery::mock(CommercialBoundaryService::class);
        $this->pilotScoreboard      = Mockery::mock(PilotScoreboardService::class);
        $this->tenantDataQuality    = Mockery::mock(TenantDataQualityService::class);
        $this->isolatedNode         = Mockery::mock(IsolatedNodeReadinessService::class);
        $this->externalIntegrations = Mockery::mock(ExternalIntegrationBacklogService::class);

        // Register all-ready defaults on every mock (any number of calls).
        $this->setDisclosurePackReady();
        $this->setOperatingPolicyReady();
        $this->setCommercialBoundaryReady();
        $this->setPilotScoreboardReady();
        $this->setDataQualityReady();
        $this->setIsolatedNodeReady();
        $this->setExternalIntegrationsReady();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Default-stub helpers — each sets the "ready" response on the relevant mock.
    // Call one of these in a test to OVERRIDE the default.
    // ──────────────────────────────────────────────────────────────────────────

    private function setDisclosurePackReady(): void
    {
        $this->disclosurePack->allows('get')->andReturn([
            'pack' => [
                'controller' => [
                    'name'                    => 'Test Corp',
                    'contact_email'           => 'dpo@test.com',
                    'data_protection_officer' => 'Jane DPO',
                ],
                'incident_response' => [
                    'contact_email' => 'incident@test.com',
                ],
            ],
            'last_updated_at' => now()->toIso8601String(),
            'is_customised'   => true,
        ]);
    }

    private function setOperatingPolicyReady(): void
    {
        $this->operatingPolicy->allows('get')->andReturn([
            'policy' => [
                'policy_appendix_url'             => 'https://example.com/appendix.pdf',
                'safeguarding_escalation_user_id' => 5,
            ],
            'last_updated_at' => now()->toIso8601String(),
        ]);
    }

    private function setCommercialBoundaryReady(): void
    {
        $this->commercialBoundary->allows('matrix')->andReturn([
            'last_updated_at' => now()->toIso8601String(),
            'overrides_count' => 1,
        ]);
    }

    private function setPilotScoreboardReady(): void
    {
        $this->pilotScoreboard->allows('scoreboard')->andReturn([
            'pre_pilot_baseline' => ['captured_at' => now()->toIso8601String()],
            'quarterly_review'   => ['is_overdue' => false, 'next_due_at' => null],
        ]);
    }

    private function setDataQualityReady(): void
    {
        $this->tenantDataQuality->allows('runChecks')->andReturn([
            'generated_at' => now()->toIso8601String(),
            'totals'       => ['danger' => 0, 'warning' => 0],
        ]);
    }

    private function setIsolatedNodeReady(): void
    {
        $this->isolatedNode->allows('get')->andReturn([
            'gate' => [
                'closed'        => true,
                'blockers'      => [],
                'decided_count' => 11,
                'total_count'   => 11,
            ],
            'last_updated_at' => now()->toIso8601String(),
        ]);
    }

    private function setExternalIntegrationsReady(): void
    {
        $this->externalIntegrations->allows('list')->andReturn([
            'items'           => [['status' => 'complete']],
            'last_updated_at' => now()->toIso8601String(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Override helpers — create a NEW mock for the service under test
    // ──────────────────────────────────────────────────────────────────────────

    private function makeService(): PilotLaunchReadinessService
    {
        return new PilotLaunchReadinessService(
            $this->disclosurePack,
            $this->operatingPolicy,
            $this->commercialBoundary,
            $this->pilotScoreboard,
            $this->tenantDataQuality,
            $this->isolatedNode,
            $this->externalIntegrations,
        );
    }

    /**
     * Replace a mock entirely so the next test gets a fresh object with no
     * accumulated expectations from setUp's `allows()` calls.
     *
     * Usage in a test:
     *   $this->tenantDataQuality = $this->freshMock(TenantDataQualityService::class);
     *   $this->tenantDataQuality->allows('runChecks')->andReturn([...]);
     */
    private function freshMock(string $class): MockInterface
    {
        return Mockery::mock($class);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // report() — structure
    // ──────────────────────────────────────────────────────────────────────────

    public function test_report_returns_required_top_level_keys(): void
    {
        $report = $this->makeService()->report(self::TENANT_ID);

        $this->assertArrayHasKey('generated_at', $report);
        $this->assertArrayHasKey('overall', $report);
        $this->assertArrayHasKey('sections', $report);
        $this->assertArrayHasKey('isolated_node_required', $report);
        $this->assertArrayHasKey('can_launch', $report);
        $this->assertArrayHasKey('blockers', $report);
        $this->assertArrayHasKey('launched', $report);
    }

    public function test_report_returns_seven_sections(): void
    {
        $report = $this->makeService()->report(self::TENANT_ID);

        $this->assertCount(7, $report['sections']);
    }

    public function test_report_sections_have_required_keys(): void
    {
        $report = $this->makeService()->report(self::TENANT_ID);

        foreach ($report['sections'] as $section) {
            $this->assertArrayHasKey('key', $section, "Section missing 'key'");
            $this->assertArrayHasKey('label_code', $section, "Section missing 'label_code'");
            $this->assertArrayHasKey('status', $section, "Section missing 'status'");
            $this->assertArrayHasKey('summary_code', $section, "Section missing 'summary_code'");
            $this->assertArrayHasKey('summary_params', $section, "Section missing 'summary_params'");
            $this->assertArrayHasKey('admin_path', $section, "Section missing 'admin_path'");
        }
    }

    public function test_report_section_keys_match_expected_order(): void
    {
        $report = $this->makeService()->report(self::TENANT_ID);

        $keys = array_column($report['sections'], 'key');
        $this->assertSame([
            'disclosure_pack',
            'operating_policy',
            'commercial_boundary',
            'pilot_scoreboard',
            'data_quality',
            'isolated_node',
            'external_integrations',
        ], $keys);
    }

    public function test_report_overall_has_required_keys(): void
    {
        $report  = $this->makeService()->report(self::TENANT_ID);
        $overall = $report['overall'];

        $this->assertArrayHasKey('status', $overall);
        $this->assertArrayHasKey('ready_section_count', $overall);
        $this->assertArrayHasKey('total_section_count', $overall);
        $this->assertArrayHasKey('summary_code', $overall);
        $this->assertArrayHasKey('summary_params', $overall);
        $this->assertSame(7, $overall['total_section_count']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // overall status computation
    // ──────────────────────────────────────────────────────────────────────────

    public function test_overall_is_ready_when_all_sections_ready(): void
    {
        // All defaults are "ready" — no overrides needed.
        $report = $this->makeService()->report(self::TENANT_ID);

        $this->assertSame('ready', $report['overall']['status']);
        $this->assertSame(7, $report['overall']['ready_section_count']);
    }

    public function test_overall_is_blocked_when_data_quality_has_danger(): void
    {
        $this->tenantDataQuality = $this->freshMock(TenantDataQualityService::class);
        $this->tenantDataQuality->allows('runChecks')->andReturn([
            'generated_at' => now()->toIso8601String(),
            'totals'       => ['danger' => 2, 'warning' => 0],
        ]);

        $report = $this->makeService()->report(self::TENANT_ID);

        $this->assertSame('blocked', $report['overall']['status']);
    }

    public function test_overall_is_needs_review_when_a_section_needs_review(): void
    {
        // Scoreboard: baseline present but quarterly review overdue → needs_review
        $this->pilotScoreboard = $this->freshMock(PilotScoreboardService::class);
        $this->pilotScoreboard->allows('scoreboard')->andReturn([
            'pre_pilot_baseline' => ['captured_at' => now()->toIso8601String()],
            'quarterly_review'   => ['is_overdue' => true, 'next_due_at' => null],
        ]);

        $report = $this->makeService()->report(self::TENANT_ID);

        $this->assertSame('needs_review', $report['overall']['status']);
    }

    public function test_overall_is_not_started_when_disclosure_pack_not_customised(): void
    {
        $this->disclosurePack = $this->freshMock(PilotDisclosurePackService::class);
        $this->disclosurePack->allows('get')->andReturn([
            'pack'            => ['controller' => [], 'incident_response' => []],
            'last_updated_at' => null,
            'is_customised'   => false,
        ]);

        $report = $this->makeService()->report(self::TENANT_ID);

        // not_started from disclosure_pack; all others ready → overall not_started
        $this->assertSame('not_started', $report['overall']['status']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // can_launch logic
    // ──────────────────────────────────────────────────────────────────────────

    public function test_can_launch_is_true_when_all_sections_ready_and_not_yet_launched(): void
    {
        $report = $this->makeService()->report(self::TENANT_ID);

        $this->assertTrue($report['can_launch']);
        $this->assertSame([], $report['blockers']);
        $this->assertNull($report['launched']);
    }

    public function test_can_launch_is_false_when_a_section_is_blocked(): void
    {
        $this->externalIntegrations = $this->freshMock(ExternalIntegrationBacklogService::class);
        $this->externalIntegrations->allows('list')->andReturn([
            'items'           => [['status' => 'blocked']],
            'last_updated_at' => null,
        ]);

        $report = $this->makeService()->report(self::TENANT_ID);

        $this->assertFalse($report['can_launch']);
        $blockerKeys = array_column($report['blockers'], 'key');
        $this->assertContains('external_integrations', $blockerKeys);
    }

    public function test_can_launch_is_false_when_already_launched(): void
    {
        // Seed a launch record directly.
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => self::TENANT_ID, 'setting_key' => 'caring_community.pilot_launched_at'],
            ['setting_value' => now()->toIso8601String(), 'setting_type' => 'string', 'category' => 'caring_community', 'updated_at' => now()]
        );
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => self::TENANT_ID, 'setting_key' => 'caring_community.pilot_launched_by'],
            ['setting_value' => '42', 'setting_type' => 'integer', 'category' => 'caring_community', 'updated_at' => now()]
        );

        $report = $this->makeService()->report(self::TENANT_ID);

        $this->assertFalse($report['can_launch']);
        $this->assertNotNull($report['launched']);
        $this->assertSame(42, $report['launched']['launched_by_id']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // isolated_node_required flag
    // ──────────────────────────────────────────────────────────────────────────

    public function test_isolated_node_not_required_by_default(): void
    {
        $report = $this->makeService()->report(self::TENANT_ID);

        $this->assertFalse($report['isolated_node_required']);
    }

    public function test_isolated_node_required_when_setting_is_canton_isolated_node(): void
    {
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => self::TENANT_ID, 'setting_key' => 'caring.isolated_node.deployment_mode'],
            [
                'setting_value' => json_encode(['value' => 'canton_isolated_node']),
                'setting_type'  => 'json',
                'category'      => 'caring_community',
                'updated_at'    => now(),
            ]
        );

        $report = $this->makeService()->report(self::TENANT_ID);

        $this->assertTrue($report['isolated_node_required']);
    }

    public function test_isolated_node_open_gate_does_not_block_hosted_launch(): void
    {
        // Hosted mode: isolated_node NOT required. Gate open (not_started).
        // All other sections ready → can_launch must still be true.
        $this->isolatedNode = $this->freshMock(IsolatedNodeReadinessService::class);
        $this->isolatedNode->allows('get')->andReturn([
            'gate' => [
                'closed'        => false,
                'blockers'      => [],
                'decided_count' => 0,
                'total_count'   => 11,
            ],
            'last_updated_at' => null,
        ]);

        $report = $this->makeService()->report(self::TENANT_ID);

        $this->assertFalse($report['isolated_node_required']);
        $this->assertTrue($report['can_launch'], 'Hosted deployment must be launchable even with open isolated-node gate');
        $blockerKeys = array_column($report['blockers'], 'key');
        $this->assertNotContains('isolated_node', $blockerKeys);
    }

    public function test_isolated_node_blockers_block_canton_launch(): void
    {
        // Set deployment mode = canton_isolated_node so the gate is REQUIRED.
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => self::TENANT_ID, 'setting_key' => 'caring.isolated_node.deployment_mode'],
            [
                'setting_value' => json_encode(['value' => 'canton_isolated_node']),
                'setting_type'  => 'json',
                'category'      => 'caring_community',
                'updated_at'    => now(),
            ]
        );

        $this->isolatedNode = $this->freshMock(IsolatedNodeReadinessService::class);
        $this->isolatedNode->allows('get')->andReturn([
            'gate' => [
                'closed'        => false,
                'blockers'      => ['dpo_appointed'],
                'decided_count' => 10,
                'total_count'   => 11,
            ],
            'last_updated_at' => now()->toIso8601String(),
        ]);

        $report = $this->makeService()->report(self::TENANT_ID);

        $this->assertTrue($report['isolated_node_required']);
        $this->assertFalse($report['can_launch']);
        $blockerKeys = array_column($report['blockers'], 'key');
        $this->assertContains('isolated_node', $blockerKeys);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Section-level status signals
    // ──────────────────────────────────────────────────────────────────────────

    public function test_disclosure_pack_section_is_not_started_when_not_customised(): void
    {
        $this->disclosurePack = $this->freshMock(PilotDisclosurePackService::class);
        $this->disclosurePack->allows('get')->andReturn([
            'pack'            => ['controller' => [], 'incident_response' => []],
            'last_updated_at' => null,
            'is_customised'   => false,
        ]);

        $report  = $this->makeService()->report(self::TENANT_ID);
        $section = collect($report['sections'])->firstWhere('key', 'disclosure_pack');

        $this->assertSame('not_started', $section['status']);
    }

    public function test_disclosure_pack_section_needs_review_when_required_fields_missing(): void
    {
        $this->disclosurePack = $this->freshMock(PilotDisclosurePackService::class);
        $this->disclosurePack->allows('get')->andReturn([
            'pack' => [
                'controller' => [
                    'name'                    => 'Test',
                    'contact_email'           => 'e@e.com',
                    'data_protection_officer' => '',   // DPO missing
                ],
                'incident_response' => ['contact_email' => 'i@i.com'],
            ],
            'last_updated_at' => now()->toIso8601String(),
            'is_customised'   => true,
        ]);

        $report  = $this->makeService()->report(self::TENANT_ID);
        $section = collect($report['sections'])->firstWhere('key', 'disclosure_pack');

        $this->assertSame('needs_review', $section['status']);
        $this->assertContains('controller.data_protection_officer', $section['missing']);
    }

    public function test_data_quality_section_is_blocked_when_danger_count_greater_than_zero(): void
    {
        $this->tenantDataQuality = $this->freshMock(TenantDataQualityService::class);
        $this->tenantDataQuality->allows('runChecks')->andReturn([
            'generated_at' => now()->toIso8601String(),
            'totals'       => ['danger' => 3, 'warning' => 1],
        ]);

        $report  = $this->makeService()->report(self::TENANT_ID);
        $section = collect($report['sections'])->firstWhere('key', 'data_quality');

        $this->assertSame('blocked', $section['status']);
        $this->assertSame('data_quality.blocked', $section['summary_code']);
        $this->assertSame(['count' => 3], $section['summary_params']);
    }

    public function test_data_quality_section_needs_review_when_warning_only(): void
    {
        $this->tenantDataQuality = $this->freshMock(TenantDataQualityService::class);
        $this->tenantDataQuality->allows('runChecks')->andReturn([
            'generated_at' => now()->toIso8601String(),
            'totals'       => ['danger' => 0, 'warning' => 2],
        ]);

        $report  = $this->makeService()->report(self::TENANT_ID);
        $section = collect($report['sections'])->firstWhere('key', 'data_quality');

        $this->assertSame('needs_review', $section['status']);
    }

    public function test_operating_policy_section_is_not_started_when_never_updated(): void
    {
        $this->operatingPolicy = $this->freshMock(OperatingPolicyService::class);
        $this->operatingPolicy->allows('get')->andReturn([
            'policy'          => ['policy_appendix_url' => '', 'safeguarding_escalation_user_id' => 0],
            'last_updated_at' => null,
        ]);

        $report  = $this->makeService()->report(self::TENANT_ID);
        $section = collect($report['sections'])->firstWhere('key', 'operating_policy');

        $this->assertSame('not_started', $section['status']);
        $this->assertContains('workshop_not_run', $section['missing']);
    }

    public function test_pilot_scoreboard_section_is_not_started_without_baseline(): void
    {
        $this->pilotScoreboard = $this->freshMock(PilotScoreboardService::class);
        $this->pilotScoreboard->allows('scoreboard')->andReturn([
            'pre_pilot_baseline' => null,
            'quarterly_review'   => [],
        ]);

        $report  = $this->makeService()->report(self::TENANT_ID);
        $section = collect($report['sections'])->firstWhere('key', 'pilot_scoreboard');

        $this->assertSame('not_started', $section['status']);
        $this->assertContains('pre_pilot_baseline', $section['missing']);
    }

    public function test_external_integrations_section_is_not_started_when_backlog_empty(): void
    {
        $this->externalIntegrations = $this->freshMock(ExternalIntegrationBacklogService::class);
        $this->externalIntegrations->allows('list')->andReturn([
            'items'           => [],
            'last_updated_at' => null,
        ]);

        $report  = $this->makeService()->report(self::TENANT_ID);
        $section = collect($report['sections'])->firstWhere('key', 'external_integrations');

        $this->assertSame('not_started', $section['status']);
    }

    public function test_commercial_boundary_ready_when_overrides_applied(): void
    {
        // Default already has overrides_count=1 via setCommercialBoundaryReady().
        $report  = $this->makeService()->report(self::TENANT_ID);
        $section = collect($report['sections'])->firstWhere('key', 'commercial_boundary');

        $this->assertSame('ready', $section['status']);
        $this->assertSame('commercial_boundary.ready_with_overrides', $section['summary_code']);
        $this->assertSame(['count' => 1], $section['summary_params']);
    }

    public function test_commercial_boundary_needs_review_when_not_acknowledged_and_no_overrides(): void
    {
        $this->commercialBoundary = $this->freshMock(CommercialBoundaryService::class);
        $this->commercialBoundary->allows('matrix')->andReturn([
            'last_updated_at' => null,
            'overrides_count' => 0,
        ]);
        // No acknowledgement flag in DB for this tenant (cleaned in setUp).

        $report  = $this->makeService()->report(self::TENANT_ID);
        $section = collect($report['sections'])->firstWhere('key', 'commercial_boundary');

        $this->assertSame('needs_review', $section['status']);
        $this->assertContains('acknowledgement', $section['missing']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // launchPilot()
    // ──────────────────────────────────────────────────────────────────────────

    public function test_launch_pilot_persists_launch_state_and_returns_timestamps(): void
    {
        $svc = $this->makeService();

        $result = $svc->launchPilot(self::TENANT_ID, 99);

        $this->assertArrayHasKey('launched_at', $result);
        $this->assertArrayHasKey('launched_by_id', $result);
        $this->assertSame(99, $result['launched_by_id']);
        $this->assertIsString($result['launched_at']);

        // Verify the rows were actually persisted.
        $row = DB::table('tenant_settings')
            ->where('tenant_id', self::TENANT_ID)
            ->where('setting_key', 'caring_community.pilot_launched_at')
            ->first();
        $this->assertNotNull($row);
        $this->assertNotEmpty($row->setting_value);
    }

    public function test_launch_pilot_returns_already_launched_when_previously_launched(): void
    {
        // Seed existing launch rows.
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => self::TENANT_ID, 'setting_key' => 'caring_community.pilot_launched_at'],
            ['setting_value' => now()->toIso8601String(), 'setting_type' => 'string', 'category' => 'caring_community', 'updated_at' => now()]
        );
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => self::TENANT_ID, 'setting_key' => 'caring_community.pilot_launched_by'],
            ['setting_value' => '7', 'setting_type' => 'integer', 'category' => 'caring_community', 'updated_at' => now()]
        );

        $result = $this->makeService()->launchPilot(self::TENANT_ID, 8);

        $this->assertArrayHasKey('error', $result);
        $this->assertSame('ALREADY_LAUNCHED', $result['error']);
        $this->assertArrayHasKey('launched', $result);
    }

    public function test_launch_pilot_returns_cannot_launch_when_sections_not_ready(): void
    {
        $this->tenantDataQuality = $this->freshMock(TenantDataQualityService::class);
        $this->tenantDataQuality->allows('runChecks')->andReturn([
            'generated_at' => now()->toIso8601String(),
            'totals'       => ['danger' => 1, 'warning' => 0],
        ]);

        $result = $this->makeService()->launchPilot(self::TENANT_ID, 1);

        $this->assertArrayHasKey('error', $result);
        $this->assertSame('CANNOT_LAUNCH', $result['error']);
        $this->assertArrayHasKey('blockers', $result);
        $this->assertNotEmpty($result['blockers']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // acknowledgeBoundary()
    // ──────────────────────────────────────────────────────────────────────────

    public function test_acknowledge_boundary_persists_flag_and_returns_acknowledged_true(): void
    {
        $svc = $this->makeService();

        $result = $svc->acknowledgeBoundary(self::TENANT_ID);

        $this->assertArrayHasKey('acknowledged', $result);
        $this->assertTrue($result['acknowledged']);

        $row = DB::table('tenant_settings')
            ->where('tenant_id', self::TENANT_ID)
            ->where('setting_key', 'caring.launch_readiness.boundary_acknowledged')
            ->first();
        $this->assertNotNull($row);
        $this->assertSame('1', (string) $row->setting_value);
    }

    public function test_commercial_boundary_is_ready_when_boundary_acknowledged(): void
    {
        // Override commercial boundary: no overrides, but acknowledgement flag set in DB.
        $this->commercialBoundary = $this->freshMock(CommercialBoundaryService::class);
        $this->commercialBoundary->allows('matrix')->andReturn([
            'last_updated_at' => null,
            'overrides_count' => 0,
        ]);

        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => self::TENANT_ID, 'setting_key' => 'caring.launch_readiness.boundary_acknowledged'],
            ['setting_value' => '1', 'setting_type' => 'boolean', 'category' => 'caring_community', 'updated_at' => now()]
        );

        $report  = $this->makeService()->report(self::TENANT_ID);
        $section = collect($report['sections'])->firstWhere('key', 'commercial_boundary');

        $this->assertSame('ready', $section['status']);
        // Summary says "Default matrix acknowledged." (not "override")
        $this->assertSame('commercial_boundary.ready_default', $section['summary_code']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // launched state in report()
    // ──────────────────────────────────────────────────────────────────────────

    public function test_report_launched_field_is_null_before_launch(): void
    {
        $report = $this->makeService()->report(self::TENANT_ID);

        $this->assertNull($report['launched']);
    }

    public function test_report_launched_field_is_populated_after_launch(): void
    {
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => self::TENANT_ID, 'setting_key' => 'caring_community.pilot_launched_at'],
            ['setting_value' => '2026-01-15T10:00:00+00:00', 'setting_type' => 'string', 'category' => 'caring_community', 'updated_at' => now()]
        );
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => self::TENANT_ID, 'setting_key' => 'caring_community.pilot_launched_by'],
            ['setting_value' => '55', 'setting_type' => 'integer', 'category' => 'caring_community', 'updated_at' => now()]
        );

        $report = $this->makeService()->report(self::TENANT_ID);

        $this->assertNotNull($report['launched']);
        $this->assertSame('2026-01-15T10:00:00+00:00', $report['launched']['launched_at']);
        $this->assertSame(55, $report['launched']['launched_by_id']);
    }
}
