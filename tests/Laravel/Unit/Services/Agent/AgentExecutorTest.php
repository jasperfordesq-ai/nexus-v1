<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services\Agent;

use App\Core\TenantContext;
use App\Services\Agent\AgentExecutor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

/**
 * AgentExecutorTest
 *
 * Tests the approve / reject / editAndApprove lifecycle of AgentExecutor,
 * including DB state transitions, run counter increments, and decision
 * audit rows written to agent_decisions.
 *
 * All concrete action types (create_tandem, send_nudge, route_help_request)
 * are exercised via DB assertions where fixtures can be provided; the
 * dispatchAction paths that write to caring_* tables are covered with real
 * inserts so we assert side-effects without touching source code.
 */
class AgentExecutorTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    // -------------------------------------------------------------------------
    // setUp
    // -------------------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        TenantContext::setById(self::TENANT_ID);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Insert a minimal user and return its ID. */
    private function insertUser(string $role = 'member'): int
    {
        $uid = uniqid('exec_', true);
        return DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'Exec Test ' . $uid,
            'first_name' => 'Exec',
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

    /** Insert a minimal agent_runs row and return its ID. */
    private function seedRun(): int
    {
        return DB::table('agent_runs')->insertGetId([
            'tenant_id'           => self::TENANT_ID,
            'agent_type'          => 'activity_summary',
            'status'              => 'running',
            'triggered_by'        => 'schedule',
            'proposals_generated' => 0,
            'proposals_applied'   => 0,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
    }

    /**
     * Insert an agent_proposals row with a given type / data / status.
     *
     * @param array<string,mixed> $data
     */
    private function seedProposal(
        int $runId,
        string $type = 'send_nudge',
        array $data = [],
        string $status = 'pending_review',
        ?int $subjectUserId = null,
    ): int {
        if (empty($data)) {
            $data = ['title' => 'Hello', 'body' => 'Test nudge', 'extra' => []];
        }
        return DB::table('agent_proposals')->insertGetId([
            'tenant_id'        => self::TENANT_ID,
            'run_id'           => $runId,
            'proposal_type'    => $type,
            'proposal_data'    => json_encode($data),
            'status'           => $status,
            'confidence_score' => 0.7,
            'subject_user_id'  => $subjectUserId,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    // =========================================================================
    // approve() — happy path
    // =========================================================================

    public function test_approve_transitions_proposal_status_to_approved(): void
    {
        $runId      = $this->seedRun();
        $reviewerId = $this->insertUser('admin');
        $propId     = $this->seedProposal($runId);

        $result = AgentExecutor::approve($propId, self::TENANT_ID, $reviewerId, 'looks good');

        $this->assertSame('approved', $result['status']);
        $this->assertSame($reviewerId, (int) $result['reviewer_id']);
        $this->assertNotNull($result['reviewed_at']);
        $this->assertNotNull($result['applied_at']);
        $this->assertNotNull($result['executed_at']);
    }

    public function test_approve_increments_proposals_applied_on_run(): void
    {
        $runId      = $this->seedRun();
        $reviewerId = $this->insertUser('admin');
        $propId     = $this->seedProposal($runId);

        AgentExecutor::approve($propId, self::TENANT_ID, $reviewerId);

        $applied = (int) DB::table('agent_runs')->where('id', $runId)->value('proposals_applied');
        $this->assertSame(1, $applied);
    }

    public function test_approve_writes_decision_audit_row(): void
    {
        $runId      = $this->seedRun();
        $reviewerId = $this->insertUser('admin');
        $propId     = $this->seedProposal($runId);

        AgentExecutor::approve($propId, self::TENANT_ID, $reviewerId, 'audit note');

        $decision = DB::table('agent_decisions')
            ->where('proposal_id', $propId)
            ->where('tenant_id', self::TENANT_ID)
            ->first();

        $this->assertNotNull($decision);
        $this->assertSame('approve', $decision->decision);
        $this->assertSame($reviewerId, (int) $decision->decided_by);
        $this->assertSame('audit note', $decision->decision_note);
    }

    public function test_approve_returns_updated_proposal_array(): void
    {
        $runId      = $this->seedRun();
        $reviewerId = $this->insertUser('admin');
        $propId     = $this->seedProposal($runId);

        $result = AgentExecutor::approve($propId, self::TENANT_ID, $reviewerId);

        $this->assertIsArray($result);
        $this->assertSame($propId, (int) $result['id']);
        // proposal_data should be decoded as an array
        $this->assertIsArray($result['proposal_data']);
    }

    // =========================================================================
    // approve() — error paths
    // =========================================================================

    public function test_approve_throws_runtime_exception_for_missing_proposal(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/not found/i');

        AgentExecutor::approve(999999999, self::TENANT_ID, 1);
    }

    public function test_approve_throws_for_wrong_tenant(): void
    {
        $runId  = $this->seedRun();
        $propId = $this->seedProposal($runId);

        $this->expectException(\RuntimeException::class);

        // Proposal belongs to TENANT_ID=2 but we query tenant=999
        AgentExecutor::approve($propId, 999, 1);
    }

    public function test_approve_throws_when_proposal_is_not_pending_review(): void
    {
        $runId  = $this->seedRun();
        $propId = $this->seedProposal($runId, 'send_nudge', [], 'approved');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/not pending review/i');

        AgentExecutor::approve($propId, self::TENANT_ID, 1);
    }

    // =========================================================================
    // reject() — happy path
    // =========================================================================

    public function test_reject_transitions_proposal_status_to_rejected(): void
    {
        $runId      = $this->seedRun();
        $reviewerId = $this->insertUser('admin');
        $propId     = $this->seedProposal($runId);

        AgentExecutor::reject($propId, self::TENANT_ID, $reviewerId, 'not relevant');

        $row = DB::table('agent_proposals')->where('id', $propId)->first();
        $this->assertSame('rejected', $row->status);
        $this->assertSame($reviewerId, (int) $row->reviewer_id);
        $this->assertNotNull($row->reviewed_at);
    }

    public function test_reject_does_not_increment_run_proposals_applied(): void
    {
        $runId      = $this->seedRun();
        $reviewerId = $this->insertUser('admin');
        $propId     = $this->seedProposal($runId);

        AgentExecutor::reject($propId, self::TENANT_ID, $reviewerId);

        $applied = (int) DB::table('agent_runs')->where('id', $runId)->value('proposals_applied');
        $this->assertSame(0, $applied);
    }

    public function test_reject_writes_reject_decision_audit_row(): void
    {
        $runId      = $this->seedRun();
        $reviewerId = $this->insertUser('admin');
        $propId     = $this->seedProposal($runId);

        AgentExecutor::reject($propId, self::TENANT_ID, $reviewerId, 'rejected note');

        $decision = DB::table('agent_decisions')
            ->where('proposal_id', $propId)
            ->first();

        $this->assertNotNull($decision);
        $this->assertSame('reject', $decision->decision);
        $this->assertSame('rejected note', $decision->decision_note);
    }

    public function test_reject_throws_for_missing_proposal(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/not found/i');

        AgentExecutor::reject(999999999, self::TENANT_ID, 1);
    }

    // =========================================================================
    // editAndApprove() — happy path
    // =========================================================================

    public function test_editAndApprove_stores_edited_payload_and_approves(): void
    {
        $runId      = $this->seedRun();
        $reviewerId = $this->insertUser('admin');
        $propId     = $this->seedProposal($runId);

        $edited = ['title' => 'Edited', 'body' => 'Updated body', 'extra' => ['key' => 'val']];

        $result = AgentExecutor::editAndApprove($propId, self::TENANT_ID, $reviewerId, $edited, 'edited note');

        $this->assertSame('approved', $result['status']);
        // proposal_data should reflect the edited payload
        $this->assertSame('Edited', $result['proposal_data']['title']);
        $this->assertSame('Updated body', $result['proposal_data']['body']);
    }

    public function test_editAndApprove_writes_edit_decision_row_with_edited_payload(): void
    {
        $runId      = $this->seedRun();
        $reviewerId = $this->insertUser('admin');
        $propId     = $this->seedProposal($runId);

        $edited = ['title' => 'E2', 'body' => 'B2', 'extra' => []];

        AgentExecutor::editAndApprove($propId, self::TENANT_ID, $reviewerId, $edited);

        $decision = DB::table('agent_decisions')
            ->where('proposal_id', $propId)
            ->first();

        $this->assertNotNull($decision);
        $this->assertSame('edit', $decision->decision);
        $this->assertNotNull($decision->edited_payload);

        $decoded = json_decode((string) $decision->edited_payload, true);
        $this->assertSame('E2', $decoded['title']);
    }

    public function test_editAndApprove_throws_when_proposal_not_pending_review(): void
    {
        $runId  = $this->seedRun();
        $propId = $this->seedProposal($runId, 'send_nudge', [], 'rejected');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/not pending review/i');

        AgentExecutor::editAndApprove($propId, self::TENANT_ID, 1, ['x' => 1]);
    }

    // =========================================================================
    // dispatchAction: create_tandem side-effect
    // =========================================================================

    public function test_approve_create_tandem_inserts_support_relationship(): void
    {
        $runId      = $this->seedRun();
        $supporter  = $this->insertUser('member');
        $recipient  = $this->insertUser('member');
        $reviewerId = $this->insertUser('admin');

        $propId = $this->seedProposal($runId, 'create_tandem', [
            'supporter_id'   => $supporter,
            'recipient_id'   => $recipient,
            'supporter_name' => 'Supporter Sam',
            'recipient_name' => 'Recipient Rae',
        ]);

        AgentExecutor::approve($propId, self::TENANT_ID, $reviewerId);

        // The action must insert a caring_support_relationships row with the
        // required NOT NULL columns (title, start_date) populated — otherwise the
        // INSERT throws and is swallowed, leaving the tandem uncreated.
        $row = DB::table('caring_support_relationships')
            ->where('tenant_id', self::TENANT_ID)
            ->where('supporter_id', $supporter)
            ->where('recipient_id', $recipient)
            ->first();

        $this->assertNotNull($row, 'create_tandem must persist a caring_support_relationships row');
        $this->assertSame('active', $row->status);
        $this->assertNotEmpty($row->title, 'title (NOT NULL) must be populated');
        $this->assertNotNull($row->start_date, 'start_date (NOT NULL) must be populated');
        $this->assertSame('Supporter Sam & Recipient Rae', $row->title);

        $proposal = DB::table('agent_proposals')->where('id', $propId)->first();
        $this->assertSame('approved', $proposal->status);
    }

    // =========================================================================
    // dispatchAction: create_tandem idempotency
    // =========================================================================

    public function test_approve_create_tandem_does_not_duplicate_existing_relationship(): void
    {
        $runId      = $this->seedRun();
        $supporter  = $this->insertUser('member');
        $recipient  = $this->insertUser('member');
        $reviewerId = $this->insertUser('admin');

        // Pre-insert a relationship row with all required columns
        DB::table('caring_support_relationships')->insert([
            'tenant_id'      => self::TENANT_ID,
            'supporter_id'   => $supporter,
            'recipient_id'   => $recipient,
            'title'          => 'Pre-existing',
            'start_date'     => now()->toDateString(),
            'expected_hours' => 1.00,
            'status'         => 'active',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $countBefore = DB::table('caring_support_relationships')
            ->where('tenant_id', self::TENANT_ID)
            ->where('supporter_id', $supporter)
            ->where('recipient_id', $recipient)
            ->count();

        $propId = $this->seedProposal($runId, 'create_tandem', [
            'supporter_id' => $supporter,
            'recipient_id' => $recipient,
        ]);

        AgentExecutor::approve($propId, self::TENANT_ID, $reviewerId);

        $countAfter = DB::table('caring_support_relationships')
            ->where('tenant_id', self::TENANT_ID)
            ->where('supporter_id', $supporter)
            ->where('recipient_id', $recipient)
            ->count();

        // The idempotency check in dispatchAction should prevent a duplicate row
        $this->assertSame($countBefore, $countAfter);
    }

    // =========================================================================
    // dispatchAction: route_help_request side-effect
    // =========================================================================

    public function test_approve_route_help_request_transitions_status_to_matched(): void
    {
        $runId       = $this->seedRun();
        $coordinator = $this->insertUser('coordinator');
        $reviewerId  = $this->insertUser('admin');
        $requesterId = $this->insertUser('member');

        $requestId = DB::table('caring_help_requests')->insertGetId([
            'tenant_id'          => self::TENANT_ID,
            'user_id'            => $requesterId,
            'what'               => 'Need a lift to the clinic',
            'when_needed'        => 'Tuesday morning',
            'contact_preference' => 'either',
            'status'             => 'pending',
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $propId = $this->seedProposal($runId, 'route_help_request', [
            'request_id'     => $requestId,
            'coordinator_id' => $coordinator,
        ]);

        AgentExecutor::approve($propId, self::TENANT_ID, $reviewerId);

        // The original bug wrote a non-existent `assigned_to` column, so the
        // request was never updated. The dispatch must now transition the help
        // request out of `pending` into `matched`.
        $status = DB::table('caring_help_requests')
            ->where('id', $requestId)
            ->where('tenant_id', self::TENANT_ID)
            ->value('status');
        $this->assertSame('matched', $status);

        $proposal = DB::table('agent_proposals')->where('id', $propId)->first();
        $this->assertSame('approved', $proposal->status);
    }

    public function test_approve_route_help_request_does_not_touch_already_closed_request(): void
    {
        $runId       = $this->seedRun();
        $coordinator = $this->insertUser('coordinator');
        $reviewerId  = $this->insertUser('admin');
        $requesterId = $this->insertUser('member');

        // A request that is already closed must not be reopened/altered by the
        // `status = 'pending'` guarded update.
        $requestId = DB::table('caring_help_requests')->insertGetId([
            'tenant_id'          => self::TENANT_ID,
            'user_id'            => $requesterId,
            'what'               => 'Already handled',
            'when_needed'        => 'Last week',
            'contact_preference' => 'either',
            'status'             => 'closed',
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $propId = $this->seedProposal($runId, 'route_help_request', [
            'request_id'     => $requestId,
            'coordinator_id' => $coordinator,
        ]);

        AgentExecutor::approve($propId, self::TENANT_ID, $reviewerId);

        $status = DB::table('caring_help_requests')->where('id', $requestId)->value('status');
        $this->assertSame('closed', $status);
    }
}
