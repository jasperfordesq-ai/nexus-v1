<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Services\KiAgentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * KiAgentServiceTest
 *
 * Strategy: KiAgentService is a pure-DB orchestration service with no LLM
 * calls of its own — it delegates AI work to CaringTandemMatchingService,
 * CaringNudgeService, and CaringCommunityForecastService.  We test:
 *
 *   (a) Schema guard — isAvailable() and the "tables not available" fallbacks.
 *   (b) Config — defaults, updateConfig clamp/merge, round-trips.
 *   (c) Run lifecycle — createRun/startRun/completeRun/failRun DB transitions.
 *   (d) Proposals — createProposal, confidence clamping, listProposals filter.
 *   (e) approveProposal / rejectProposal — status transitions + run increment.
 *   (f) autoApplyEligible — threshold filter, expired exclusion.
 *   (g) listRuns / getRun — tenancy isolation.
 *   (h) runActivitySummary — creates proposals for coordinator users.
 *   (i) runAllAgents — disabled-tenant short-circuit.
 *
 * The agent-executor methods (runTandemMatching, runNudgeDispatch,
 * runDemandForecast) delegate to bound service classes that touch domain
 * tables requiring heavy fixtures; they are tested via the public surface
 * (proposal count) in integration rather than unit here — those sub-services
 * have their own test suites.
 */
class KiAgentServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Insert a minimal user and return the ID.
     */
    private function insertUser(string $role = 'member'): int
    {
        $uid = uniqid('ki_', true);
        return DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'KiTest ' . $uid,
            'first_name' => 'Ki',
            'last_name'  => 'Test',
            'email'      => $uid . '@example.test',
            'status'     => 'active',
            'balance'    => 0,
            'role'       => $role,
            'is_approved'=> 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Create a run row directly in the DB (enum must match agent_runs.agent_type).
     */
    private function seedRun(string $agentType = 'activity_summary', string $status = 'pending'): int
    {
        return DB::table('agent_runs')->insertGetId([
            'tenant_id'           => self::TENANT_ID,
            'agent_type'          => $agentType,
            'status'              => $status,
            'triggered_by'        => 'schedule',
            'proposals_generated' => 0,
            'proposals_applied'   => 0,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
    }

    /**
     * Create a proposal row directly in the DB.
     */
    private function seedProposal(
        int $runId,
        float $confidence = 0.7,
        string $status = 'pending_review',
        ?string $expiresAt = null,
    ): int {
        return DB::table('agent_proposals')->insertGetId([
            'tenant_id'       => self::TENANT_ID,
            'run_id'          => $runId,
            'proposal_type'   => 'send_nudge',
            'proposal_data'   => json_encode(['title' => 'Hello', 'body' => 'Test nudge', 'extra' => []]),
            'status'          => $status,
            'confidence_score'=> round($confidence, 4),
            'subject_user_id' => null,
            'target_user_id'  => null,
            'expires_at'      => $expiresAt,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    // =========================================================================
    // (a) Schema guard
    // =========================================================================

    public function test_isAvailable_returns_true_when_tables_exist(): void
    {
        // The test DB has agent_runs/agent_proposals/agent_config tables in the schema dump.
        $this->assertTrue(KiAgentService::isAvailable());
    }

    // =========================================================================
    // (b) Config
    // =========================================================================

    public function test_getConfig_returns_defaults_when_no_row_exists(): void
    {
        // Use a tenant ID that almost certainly has no agent_config row.
        $config = KiAgentService::getConfig(99999);

        $this->assertFalse($config['enabled']);
        $this->assertSame(0.9, $config['auto_apply_threshold']);
        $this->assertSame(2, $config['schedule_hour']);
        $this->assertSame(50, $config['max_proposals_per_run']);
        $this->assertNull($config['notification_email']);
    }

    public function test_updateConfig_creates_row_and_returns_merged_config(): void
    {
        $tid = self::TENANT_ID;
        // Ensure no pre-existing row for this tenant conflicts.
        DB::table('agent_config')->where('tenant_id', $tid)->delete();

        $result = KiAgentService::updateConfig($tid, [
            'enabled'      => true,
            'schedule_hour' => 4,
        ]);

        $this->assertTrue($result['enabled']);
        $this->assertSame(4, $result['schedule_hour']);
        // Unset keys keep their defaults.
        $this->assertSame(0.9, $result['auto_apply_threshold']);
    }

    public function test_updateConfig_clamps_auto_apply_threshold_to_unit_range(): void
    {
        DB::table('agent_config')->where('tenant_id', self::TENANT_ID)->delete();

        $result = KiAgentService::updateConfig(self::TENANT_ID, [
            'auto_apply_threshold' => 1.5,   // > 1 → clamp to 1.0
        ]);
        $this->assertSame(1.0, $result['auto_apply_threshold']);

        KiAgentService::updateConfig(self::TENANT_ID, [
            'auto_apply_threshold' => -0.5,  // < 0 → clamp to 0.0
        ]);
        $result2 = KiAgentService::getConfig(self::TENANT_ID);
        $this->assertSame(0.0, $result2['auto_apply_threshold']);
    }

    public function test_updateConfig_clamps_schedule_hour_to_0_to_23(): void
    {
        DB::table('agent_config')->where('tenant_id', self::TENANT_ID)->delete();

        KiAgentService::updateConfig(self::TENANT_ID, ['schedule_hour' => 99]);
        $r = KiAgentService::getConfig(self::TENANT_ID);
        $this->assertSame(23, $r['schedule_hour']);

        KiAgentService::updateConfig(self::TENANT_ID, ['schedule_hour' => -3]);
        $r2 = KiAgentService::getConfig(self::TENANT_ID);
        $this->assertSame(0, $r2['schedule_hour']);
    }

    public function test_updateConfig_clamps_max_proposals_per_run(): void
    {
        DB::table('agent_config')->where('tenant_id', self::TENANT_ID)->delete();

        KiAgentService::updateConfig(self::TENANT_ID, ['max_proposals_per_run' => 9999]);
        $r = KiAgentService::getConfig(self::TENANT_ID);
        $this->assertSame(500, $r['max_proposals_per_run']);

        KiAgentService::updateConfig(self::TENANT_ID, ['max_proposals_per_run' => 0]);
        $r2 = KiAgentService::getConfig(self::TENANT_ID);
        $this->assertSame(1, $r2['max_proposals_per_run']);
    }

    public function test_updateConfig_notification_email_stores_null_for_empty_string(): void
    {
        DB::table('agent_config')->where('tenant_id', self::TENANT_ID)->delete();

        $result = KiAgentService::updateConfig(self::TENANT_ID, [
            'notification_email' => '',
        ]);
        $this->assertNull($result['notification_email']);
    }

    // =========================================================================
    // (c) Run lifecycle
    // =========================================================================

    public function test_createRun_inserts_row_in_pending_status(): void
    {
        $runId = KiAgentService::createRun(self::TENANT_ID, 'activity_summary', 'schedule');

        $this->assertGreaterThan(0, $runId);

        $row = DB::table('agent_runs')->where('id', $runId)->first();
        $this->assertNotNull($row);
        $this->assertSame('pending', $row->status);
        $this->assertSame('activity_summary', $row->agent_type);
        $this->assertSame(self::TENANT_ID, (int) $row->tenant_id);
    }

    public function test_createRun_stores_input_context_as_json(): void
    {
        $ctx   = ['source' => 'test', 'value' => 42];
        $runId = KiAgentService::createRun(self::TENANT_ID, 'demand_forecast', 'admin', null, $ctx);

        $row = DB::table('agent_runs')->where('id', $runId)->first();
        $decoded = json_decode((string) $row->input_context, true);
        $this->assertSame($ctx, $decoded);
    }

    public function test_startRun_transitions_status_to_running(): void
    {
        $runId = KiAgentService::createRun(self::TENANT_ID, 'nudge_dispatch', 'schedule');
        KiAgentService::startRun($runId);

        $row = DB::table('agent_runs')->where('id', $runId)->first();
        $this->assertSame('running', $row->status);
        $this->assertNotNull($row->started_at);
    }

    public function test_completeRun_transitions_status_to_completed(): void
    {
        $runId = KiAgentService::createRun(self::TENANT_ID, 'activity_summary', 'schedule');
        KiAgentService::startRun($runId);
        KiAgentService::completeRun($runId, 5, 'All done.');

        $row = DB::table('agent_runs')->where('id', $runId)->first();
        $this->assertSame('completed', $row->status);
        $this->assertSame(5, (int) $row->proposals_generated);
        $this->assertSame('All done.', $row->output_summary);
        $this->assertNotNull($row->completed_at);
    }

    public function test_failRun_transitions_status_to_failed(): void
    {
        $runId = KiAgentService::createRun(self::TENANT_ID, 'activity_summary', 'schedule');
        KiAgentService::failRun($runId, 'Something exploded');

        $row = DB::table('agent_runs')->where('id', $runId)->first();
        $this->assertSame('failed', $row->status);
        $this->assertSame('Something exploded', $row->error_message);
    }

    // =========================================================================
    // (d) Proposals
    // =========================================================================

    public function test_createProposal_inserts_row_with_correct_fields(): void
    {
        $runId      = $this->seedRun();
        $data       = ['key' => 'value', 'count' => 3];
        $proposalId = KiAgentService::createProposal(
            tenantId: self::TENANT_ID,
            runId: $runId,
            type: 'send_nudge',
            data: $data,
            confidence: 0.75,
            subjectUserId: 123,
            targetUserId: 456,
        );

        $this->assertGreaterThan(0, $proposalId);
        $row = DB::table('agent_proposals')->where('id', $proposalId)->first();
        $this->assertNotNull($row);
        $this->assertSame('pending_review', $row->status);
        $this->assertSame('send_nudge', $row->proposal_type);
        $this->assertEqualsWithDelta(0.75, (float) $row->confidence_score, 0.0001);
        $this->assertSame(123, (int) $row->subject_user_id);
        $this->assertSame(456, (int) $row->target_user_id);
        $decoded = json_decode((string) $row->proposal_data, true);
        $this->assertSame($data, $decoded);
    }

    public function test_createProposal_clamps_confidence_to_unit_range(): void
    {
        $runId = $this->seedRun();

        $highId = KiAgentService::createProposal(self::TENANT_ID, $runId, 'send_nudge', [], 1.5);
        $lowId  = KiAgentService::createProposal(self::TENANT_ID, $runId, 'send_nudge', [], -0.5);

        $high = DB::table('agent_proposals')->where('id', $highId)->value('confidence_score');
        $low  = DB::table('agent_proposals')->where('id', $lowId)->value('confidence_score');

        $this->assertEqualsWithDelta(1.0, (float) $high, 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) $low, 0.0001);
    }

    public function test_listProposals_returns_all_for_tenant_without_filter(): void
    {
        $runId = $this->seedRun();
        $this->seedProposal($runId, 0.6, 'pending_review');
        $this->seedProposal($runId, 0.5, 'approved');

        $proposals = KiAgentService::listProposals(self::TENANT_ID);

        // At least the two we just inserted are present.
        $this->assertGreaterThanOrEqual(2, count($proposals));
        // Each should have run_agent_type from the join.
        $this->assertArrayHasKey('run_agent_type', $proposals[0]);
    }

    public function test_listProposals_filters_by_status(): void
    {
        $runId = $this->seedRun();
        $approvedId = $this->seedProposal($runId, 0.8, 'approved');
        $pendingId  = $this->seedProposal($runId, 0.7, 'pending_review');

        $approved = KiAgentService::listProposals(self::TENANT_ID, 'approved');
        $ids      = array_column($approved, 'id');

        $this->assertContains($approvedId, $ids);
        $this->assertNotContains($pendingId, $ids);
    }

    public function test_listProposals_decodes_proposal_data_array(): void
    {
        $runId = $this->seedRun();
        $this->seedProposal($runId);

        $proposals = KiAgentService::listProposals(self::TENANT_ID, 'pending_review');

        $this->assertIsArray($proposals[0]['proposal_data']);
        $this->assertArrayHasKey('title', $proposals[0]['proposal_data']);
    }

    // =========================================================================
    // (e) approveProposal / rejectProposal
    // =========================================================================

    public function test_approveProposal_transitions_status_to_approved(): void
    {
        $runId      = $this->seedRun();
        $reviewerId = $this->insertUser('admin');
        $propId     = $this->seedProposal($runId, 0.6, 'pending_review');

        $result = KiAgentService::approveProposal($propId, self::TENANT_ID, $reviewerId);

        $this->assertSame('approved', $result['status']);
        $this->assertSame($reviewerId, (int) $result['reviewer_id']);
        $this->assertNotNull($result['reviewed_at']);
        $this->assertNotNull($result['applied_at']);
    }

    public function test_approveProposal_increments_proposals_applied_on_run(): void
    {
        $runId      = $this->seedRun();
        $reviewerId = $this->insertUser('admin');
        $propId     = $this->seedProposal($runId, 0.6, 'pending_review');

        KiAgentService::approveProposal($propId, self::TENANT_ID, $reviewerId);

        $applied = DB::table('agent_runs')->where('id', $runId)->value('proposals_applied');
        $this->assertSame(1, (int) $applied);
    }

    public function test_approveProposal_throws_for_nonexistent_proposal(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/not found/i');

        KiAgentService::approveProposal(999999999, self::TENANT_ID, 1);
    }

    public function test_rejectProposal_transitions_status_to_rejected(): void
    {
        $runId      = $this->seedRun();
        $reviewerId = $this->insertUser('admin');
        $propId     = $this->seedProposal($runId, 0.6, 'pending_review');

        KiAgentService::rejectProposal($propId, self::TENANT_ID, $reviewerId);

        $row = DB::table('agent_proposals')->where('id', $propId)->first();
        $this->assertSame('rejected', $row->status);
        $this->assertSame($reviewerId, (int) $row->reviewer_id);
    }

    // =========================================================================
    // (f) autoApplyEligible
    // =========================================================================

    public function test_autoApplyEligible_returns_proposals_above_threshold(): void
    {
        $runId   = $this->seedRun();
        $highId  = $this->seedProposal($runId, 0.95);
        $lowId   = $this->seedProposal($runId, 0.4);

        $eligible = KiAgentService::autoApplyEligible(self::TENANT_ID, 0.9);
        $ids      = array_column($eligible, 'id');

        $this->assertContains($highId, $ids);
        $this->assertNotContains($lowId, $ids);
    }

    public function test_autoApplyEligible_excludes_expired_proposals(): void
    {
        $runId    = $this->seedRun();
        $expiredId = $this->seedProposal($runId, 0.95, 'pending_review', now()->subDay()->toDateTimeString());
        $validId   = $this->seedProposal($runId, 0.95, 'pending_review', now()->addDay()->toDateTimeString());

        $eligible = KiAgentService::autoApplyEligible(self::TENANT_ID, 0.9);
        $ids      = array_column($eligible, 'id');

        $this->assertContains($validId, $ids);
        $this->assertNotContains($expiredId, $ids);
    }

    public function test_autoApplyEligible_excludes_non_pending_review_proposals(): void
    {
        $runId      = $this->seedRun();
        $approvedId = $this->seedProposal($runId, 0.95, 'approved');

        $eligible = KiAgentService::autoApplyEligible(self::TENANT_ID, 0.9);
        $ids      = array_column($eligible, 'id');

        $this->assertNotContains($approvedId, $ids);
    }

    // =========================================================================
    // (g) listRuns / getRun
    // =========================================================================

    public function test_listRuns_returns_runs_for_tenant(): void
    {
        $runId = KiAgentService::createRun(self::TENANT_ID, 'activity_summary', 'schedule');

        $runs = KiAgentService::listRuns(self::TENANT_ID);

        $this->assertNotEmpty($runs);
        $ids = array_column($runs, 'id');
        $this->assertContains($runId, $ids);
    }

    public function test_listRuns_filters_by_agent_type(): void
    {
        $id1 = KiAgentService::createRun(self::TENANT_ID, 'activity_summary', 'schedule');
        $id2 = KiAgentService::createRun(self::TENANT_ID, 'demand_forecast', 'schedule');

        $runs = KiAgentService::listRuns(self::TENANT_ID, 'activity_summary');
        $ids  = array_column($runs, 'id');

        $this->assertContains($id1, $ids);
        $this->assertNotContains($id2, $ids);
    }

    public function test_listRuns_filters_by_status(): void
    {
        $runId = KiAgentService::createRun(self::TENANT_ID, 'activity_summary', 'schedule');
        KiAgentService::startRun($runId);

        $running = KiAgentService::listRuns(self::TENANT_ID, null, 'running');
        $ids     = array_column($running, 'id');
        $this->assertContains($runId, $ids);

        $pending = KiAgentService::listRuns(self::TENANT_ID, null, 'pending');
        $pendingIds = array_column($pending, 'id');
        $this->assertNotContains($runId, $pendingIds);
    }

    public function test_getRun_returns_run_with_proposals_array(): void
    {
        $runId  = $this->seedRun();
        $propId = $this->seedProposal($runId);

        $run = KiAgentService::getRun($runId, self::TENANT_ID);

        $this->assertNotNull($run);
        $this->assertSame($runId, (int) $run['id']);
        $this->assertArrayHasKey('proposals', $run);
        $propIds = array_column($run['proposals'], 'id');
        $this->assertContains($propId, $propIds);
    }

    public function test_getRun_returns_null_for_wrong_tenant(): void
    {
        $runId = $this->seedRun();

        $result = KiAgentService::getRun($runId, 99999);

        $this->assertNull($result);
    }

    public function test_getRun_decodes_input_context(): void
    {
        $ctx   = ['meta' => 'data'];
        $runId = KiAgentService::createRun(self::TENANT_ID, 'demand_forecast', 'admin', null, $ctx);

        $run = KiAgentService::getRun($runId, self::TENANT_ID);

        $this->assertSame($ctx, $run['input_context']);
    }

    // =========================================================================
    // (h) runActivitySummary
    // =========================================================================

    public function test_runActivitySummary_returns_zero_when_no_vol_logs(): void
    {
        // Use a high-ID tenant that has no vol_logs to avoid interference.
        $runId = $this->seedRun();
        // Pass a tenant ID that almost certainly has no vol_logs.
        $result = KiAgentService::runActivitySummary(99999, $runId);

        $this->assertSame(0, $result['proposals_created']);
    }

    public function test_runActivitySummary_creates_proposals_for_coordinators(): void
    {
        $this->markTestSkipped(
            'Quarantine [isolation-debt]: order-dependent — passes in full-suite run order, fails when run in a sharded subset under CI. Re-enable after fixing test isolation. Tracked in PR #130.'
        );

        // nexus_test has 148+ coordinator/admin users in tenant 2. Use max_proposals_per_run=1
        // so the run creates exactly 1 proposal. Use an EXISTING member from the test DB for
        // the vol_log to avoid any users-table lock contention from previous test runs.
        DB::table('agent_config')->where('tenant_id', self::TENANT_ID)->delete();
        KiAgentService::updateConfig(self::TENANT_ID, ['max_proposals_per_run' => 1]);

        // Reuse an existing active member from nexus_test (avoids users INSERT lock risk).
        $existingMemberId = DB::table('users')
            ->where('tenant_id', self::TENANT_ID)
            ->where('status', 'active')
            ->value('id');
        $this->assertNotNull($existingMemberId, 'nexus_test must have at least one user in tenant 2');

        // Insert an approved vol_log in the last 7 days.
        DB::table('vol_logs')->insert([
            'tenant_id'   => self::TENANT_ID,
            'user_id'     => (int) $existingMemberId,
            'date_logged' => now()->toDateString(),
            'hours'       => 2.0,
            'status'      => 'approved',
            'created_at'  => now(),
        ]);

        $runId  = $this->seedRun();
        $result = KiAgentService::runActivitySummary(self::TENANT_ID, $runId);

        // Exactly 1 proposal because max_proposals_per_run=1.
        $this->assertSame(1, $result['proposals_created']);

        // The proposal must be of type send_activity_summary with correct metadata.
        $proposal = DB::table('agent_proposals')
            ->where('run_id', $runId)
            ->where('proposal_type', 'send_activity_summary')
            ->first();
        $this->assertNotNull($proposal, 'A send_activity_summary proposal should be created');
        $this->assertSame('pending_review', $proposal->status);
        // confidence_score is hardcoded to 0.95 in runActivitySummary.
        $this->assertEqualsWithDelta(0.95, (float) $proposal->confidence_score, 0.0001);
        // proposal_data JSON must contain summary fields.
        $data = json_decode((string) $proposal->proposal_data, true);
        $this->assertArrayHasKey('total_sessions', $data);
        $this->assertArrayHasKey('total_hours', $data);
        $this->assertArrayHasKey('volunteer_count', $data);
    }

    // =========================================================================
    // (i) runAllAgents — disabled tenant short-circuit
    // =========================================================================

    public function test_runAllAgents_returns_skipped_when_agent_disabled(): void
    {
        // Ensure no leftover config for this tenant.
        DB::table('agent_config')->where('tenant_id', self::TENANT_ID)->delete();
        // Insert a config row with enabled=0 (the default).
        KiAgentService::updateConfig(self::TENANT_ID, ['enabled' => false]);

        $result = KiAgentService::runAllAgents(self::TENANT_ID);

        $this->assertTrue($result['skipped']);
        $this->assertSame('agent disabled', $result['reason']);
        $this->assertSame(self::TENANT_ID, $result['tenant_id']);
    }
}
