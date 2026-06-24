<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services\Agent\Agents;

use App\Core\TenantContext;
use App\Services\Agent\Agents\ActivitySummariserAgent;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * Tests for ActivitySummariserAgent (AG61 weekly activity summariser).
 *
 * The agent:
 *  1. Collects last-7-day vol_log stats for the tenant.
 *  2. Finds admin/coordinator/broker users to send the summary to.
 *  3. Calls the LLM to generate a narrative; falls back to a deterministic string.
 *  4. Creates one agent_proposals row per admin/coordinator.
 *
 * NOTE: OpenAIProvider uses raw curl_exec, not Laravel's Http facade. Http::fake
 * cannot intercept LLM calls. LLM tests are limited to the no-API-key fallback path.
 *
 * Each test allocates its own isolated tenant via DB auto-increment, so there is no
 * shared idle-user or admin-user pollution from tenant 2's real dataset.
 */
class ActivitySummariserAgentTest extends TestCase
{
    use DatabaseTransactions;

    /** Tenant ID allocated in setUp(); rolled back with the transaction. */
    private int $thisTenant;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        putenv('OPENAI_API_KEY=');

        $this->thisTenant = (int) DB::table('tenants')->insertGetId([
            'name'              => 'Act Test ' . uniqid(),
            'slug'              => 'act-test-' . uniqid(),
            'domain'            => null,
            'is_active'         => true,
            'depth'             => 0,
            'allows_subtenants' => false,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        TenantContext::setById($this->thisTenant);
    }

    protected function tearDown(): void
    {
        putenv('OPENAI_API_KEY=');
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeAgent(array $config = []): ActivitySummariserAgent
    {
        $runId = DB::table('agent_runs')->insertGetId([
            'tenant_id'           => $this->thisTenant,
            'agent_type'          => 'activity_summary',
            'status'              => 'running',
            'triggered_by'        => 'schedule',
            'proposals_generated' => 0,
            'proposals_applied'   => 0,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
        return new ActivitySummariserAgent($this->thisTenant, $runId, 0, $config);
    }

    private function insertUser(string $role = 'admin'): int
    {
        $uid = uniqid('act_', true);
        return (int) DB::table('users')->insertGetId([
            'tenant_id'  => $this->thisTenant,
            'name'       => 'Act ' . $uid,
            'first_name' => 'Act',
            'last_name'  => 'User',
            'email'      => $uid . '@example.test',
            'role'       => $role,
            'status'     => 'active',
            'balance'    => 0,
            'is_approved'=> 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Insert a vol_log row dated within the last 7 days.
     */
    private function insertVolLog(int $userId, float $hours = 2.0): void
    {
        DB::table('vol_logs')->insertOrIgnore([
            'tenant_id'   => $this->thisTenant,
            'user_id'     => $userId,
            'date_logged' => now()->subDays(2)->toDateString(),
            'hours'       => $hours,
            'status'      => 'approved',
            'created_at'  => now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Identity
    // -------------------------------------------------------------------------

    public function test_agent_is_instantiable(): void
    {
        $agent = $this->makeAgent();
        $this->assertInstanceOf(ActivitySummariserAgent::class, $agent);
    }

    public function test_run_returns_required_keys(): void
    {
        // This tenant has no users → emptyResult (no admin recipients).
        $agent  = $this->makeAgent();
        $result = $agent->run();

        $this->assertArrayHasKey('proposals_created', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('llm_input_tokens', $result);
        $this->assertArrayHasKey('llm_output_tokens', $result);
        $this->assertArrayHasKey('cost_cents', $result);
    }

    // -------------------------------------------------------------------------
    // Early-exit: no activity
    // -------------------------------------------------------------------------

    public function test_no_proposals_when_no_activity_in_last_7_days(): void
    {
        // Insert an admin but no vol_logs for them.
        $this->insertUser('admin');

        $agent  = $this->makeAgent();
        $result = $agent->run();

        $this->assertSame(0, $result['proposals_created']);
        $this->assertStringContainsString('no activity', $result['summary']);
    }

    // -------------------------------------------------------------------------
    // Early-exit: no admin recipients
    // -------------------------------------------------------------------------

    public function test_no_proposals_when_no_admins(): void
    {
        // Insert a regular member with vol_logs but no admin.
        $memberId = $this->insertUser('member');
        $this->insertVolLog($memberId, 3.0);

        $agent  = $this->makeAgent();
        $result = $agent->run();

        $this->assertSame(0, $result['proposals_created']);
        $this->assertStringContainsString('no admin recipients', $result['summary']);
    }

    // -------------------------------------------------------------------------
    // Happy path: one admin, activity exists
    // -------------------------------------------------------------------------

    public function test_one_proposal_per_admin_when_activity_exists(): void
    {
        $this->insertUser('admin');
        $memberId = $this->insertUser('member');
        $this->insertVolLog($memberId, 2.5);

        $agent  = $this->makeAgent();
        $result = $agent->run();

        $this->assertSame(1, $result['proposals_created']);
    }

    // -------------------------------------------------------------------------
    // Multiple admins → one proposal each
    // -------------------------------------------------------------------------

    public function test_one_proposal_per_coordinator_role(): void
    {
        $this->insertUser('admin');
        $this->insertUser('coordinator');
        $this->insertUser('broker');
        $member = $this->insertUser('member');
        $this->insertVolLog($member, 1.0);

        $agent  = $this->makeAgent();
        $result = $agent->run();

        $this->assertSame(3, $result['proposals_created']);
    }

    // -------------------------------------------------------------------------
    // Proposal shape
    // -------------------------------------------------------------------------

    public function test_proposal_type_and_status(): void
    {
        $adminId  = $this->insertUser('admin');
        $memberId = $this->insertUser('member');
        $this->insertVolLog($memberId, 2.0);

        $agent = $this->makeAgent();
        $agent->run();

        $proposal = DB::table('agent_proposals')
            ->where('subject_user_id', $adminId)
            ->orderBy('id', 'desc')
            ->first();

        $this->assertNotNull($proposal);
        $this->assertSame('send_activity_summary', $proposal->proposal_type);
        $this->assertSame('pending_review', $proposal->status);
        $this->assertEqualsWithDelta(0.95, (float) $proposal->confidence_score, 0.001);
    }

    public function test_proposal_data_contains_period_and_stats(): void
    {
        $adminId  = $this->insertUser('admin');
        $memberId = $this->insertUser('member');
        $this->insertVolLog($memberId, 4.0);

        $agent = $this->makeAgent();
        $agent->run();

        $proposal = DB::table('agent_proposals')
            ->where('subject_user_id', $adminId)
            ->orderBy('id', 'desc')
            ->first();

        $this->assertNotNull($proposal);
        $data = json_decode($proposal->proposal_data, true);

        $this->assertArrayHasKey('period_start', $data);
        $this->assertArrayHasKey('period_end', $data);
        $this->assertArrayHasKey('total_sessions', $data);
        $this->assertArrayHasKey('total_hours', $data);
        $this->assertArrayHasKey('volunteer_count', $data);
        $this->assertSame('Weekly Community Activity Summary', $data['title']);
        $this->assertSame('activity_summary', $data['extra']['type'] ?? null);
    }

    // -------------------------------------------------------------------------
    // Fallback narrative when no LLM key
    // -------------------------------------------------------------------------

    public function test_fallback_narrative_used_when_no_api_key(): void
    {
        $adminId  = $this->insertUser('admin');
        $memberId = $this->insertUser('member');
        $this->insertVolLog($memberId, 3.0);

        $agent = $this->makeAgent();
        $agent->run();

        $proposal = DB::table('agent_proposals')
            ->where('subject_user_id', $adminId)
            ->orderBy('id', 'desc')
            ->first();

        $this->assertNotNull($proposal);
        $data = json_decode($proposal->proposal_data, true);

        // Fallback body contains "In the last 7 days, N volunteers logged M sessions"
        $this->assertStringContainsString('In the last 7 days', $data['body']);
        $this->assertStringContainsString('sessions', $data['body']);
    }

    // -------------------------------------------------------------------------
    // Fallback narrative contains correct hours figure
    // -------------------------------------------------------------------------

    public function test_fallback_narrative_body_contains_hours_figure(): void
    {
        // NOTE: OpenAIProvider uses raw curl_exec (not Laravel Http facade), so
        // Http::fake cannot intercept LLM calls. We exercise the deterministic
        // fallback path (no OPENAI_API_KEY) and verify the narrative is built
        // from the real collected stats.
        $adminId  = $this->insertUser('admin');
        $memberId = $this->insertUser('member');
        $this->insertVolLog($memberId, 5.5);

        $agent = $this->makeAgent();
        $agent->run();

        $proposal = DB::table('agent_proposals')
            ->where('subject_user_id', $adminId)
            ->orderBy('id', 'desc')
            ->first();

        $this->assertNotNull($proposal);
        $data = json_decode($proposal->proposal_data, true);
        // Fallback: "In the last 7 days, N volunteers logged M sessions totalling X.X hours."
        $this->assertStringContainsString('5.5', $data['body']);
    }

    // -------------------------------------------------------------------------
    // Reasoning string format
    // -------------------------------------------------------------------------

    public function test_reasoning_contains_period_and_session_stats(): void
    {
        $adminId  = $this->insertUser('admin');
        $memberId = $this->insertUser('member');
        $this->insertVolLog($memberId, 1.5);

        $agent = $this->makeAgent();
        $agent->run();

        $proposal = DB::table('agent_proposals')
            ->where('subject_user_id', $adminId)
            ->orderBy('id', 'desc')
            ->first();

        $this->assertNotNull($proposal);
        // Reasoning format: "Weekly digest for YYYY-MM-DD..YYYY-MM-DD — N sessions, X.X hours, N volunteers."
        $this->assertStringContainsString('Weekly digest for', $proposal->reasoning);
        $this->assertStringContainsString('sessions', $proposal->reasoning);
        $this->assertStringContainsString('hours', $proposal->reasoning);
    }

    // -------------------------------------------------------------------------
    // Summary string
    // -------------------------------------------------------------------------

    public function test_summary_mentions_admin_count(): void
    {
        $this->insertUser('admin');
        $memberId = $this->insertUser('member');
        $this->insertVolLog($memberId, 2.0);

        $agent  = $this->makeAgent();
        $result = $agent->run();

        $this->assertStringContainsString('1', $result['summary']);
    }
}
