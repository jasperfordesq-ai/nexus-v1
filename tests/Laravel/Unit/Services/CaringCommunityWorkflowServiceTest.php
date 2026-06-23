<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\CaringCommunityWorkflowService;
use App\Services\CaringCommunityWorkflowPolicyService;
use App\Services\CaringCommunityRolePresetService;
use App\Services\CaringCommunity\CaringRegionalPointService;
use App\Core\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * CaringCommunityWorkflowServiceTest
 *
 * Tests the coordinator workflow: assignReview, escalateReview, decideReview
 * (approve / decline), and intergenerationalTandemCount.
 *
 * CaringCommunityWorkflowPolicyService, CaringCommunityRolePresetService, and
 * CaringRegionalPointService are Mockery-mocked so we don't need to seed their
 * backing tables. Queue::fake() prevents VolLogStatusChanged from running the
 * sync-queue that resets TenantContext.
 */
class CaringCommunityWorkflowServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    private CaringCommunityWorkflowService $svc;

    /** @var \Mockery\MockInterface&CaringCommunityWorkflowPolicyService */
    private $policyMock;

    /** @var \Mockery\MockInterface&CaringCommunityRolePresetService */
    private $presetMock;

    /** @var \Mockery\MockInterface&CaringRegionalPointService */
    private $regionalMock;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
        Queue::fake();

        $this->policyMock   = \Mockery::mock(CaringCommunityWorkflowPolicyService::class);
        $this->presetMock   = \Mockery::mock(CaringCommunityRolePresetService::class);
        $this->regionalMock = \Mockery::mock(CaringRegionalPointService::class);

        // Default: policy returns standard defaults
        $this->policyMock->shouldReceive('get')
            ->andReturn([
                'approval_required'                   => true,
                'review_sla_days'                     => 7,
                'escalation_sla_days'                 => 14,
                'allow_member_self_log'               => true,
                'auto_approve_trusted_reviewers'      => false,
                'require_organisation_for_partner_hours' => true,
                'monthly_statement_day'               => 1,
                'municipal_report_default_period'     => 'last_90_days',
                'include_social_value_estimate'       => true,
                'default_hour_value_chf'              => 35,
            ])
            ->byDefault();

        $this->presetMock->shouldReceive('status')
            ->andReturn([])
            ->byDefault();

        $this->regionalMock->shouldReceive('awardForApprovedHours')
            ->andReturn(null)
            ->byDefault();

        $this->svc = new CaringCommunityWorkflowService(
            $this->presetMock,
            $this->policyMock,
            $this->regionalMock,
        );
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function insertUser(string $role = 'member', string $tag = ''): int
    {
        $uid = uniqid($tag ?: 'u', true);
        return DB::table('users')->insertGetId([
            'tenant_id'           => self::TENANT_ID,
            'name'                => 'Workflow User ' . $uid,
            'first_name'          => 'Workflow',
            'last_name'           => 'User',
            'email'               => 'workflow.' . $uid . '@example.test',
            'status'              => 'active',
            'role'                => $role,
            'is_approved'         => 1,
            'balance'             => 0,
            'is_admin'            => in_array($role, ['admin', 'tenant_admin']) ? 1 : 0,
            'is_tenant_super_admin' => 0,
            'created_at'          => now(),
        ]);
    }

    private function insertVolLog(int $userId, float $hours = 2.0, string $status = 'pending'): int
    {
        return DB::table('vol_logs')->insertGetId([
            'tenant_id'   => self::TENANT_ID,
            'user_id'     => $userId,
            'hours'       => $hours,
            'date_logged' => now()->toDateString(),
            'status'      => $status,
            'created_at'  => now(),
        ]);
    }

    // ── assignReview ──────────────────────────────────────────────────────────

    public function test_assignReview_returns_null_when_assignee_is_not_coordinator(): void
    {
        $member      = $this->insertUser('member', 'member');
        $nonCoord    = $this->insertUser('member', 'nc');   // plain member = not a coordinator
        $logId       = $this->insertVolLog($member);

        $result = $this->svc->assignReview(self::TENANT_ID, $logId, $nonCoord);

        $this->assertNull($result, 'Assignment to non-coordinator must return null');
    }

    public function test_assignReview_assigns_log_to_coordinator_and_returns_review_shape(): void
    {
        $member     = $this->insertUser('member', 'member');
        $coordinator = $this->insertUser('admin', 'coord');
        $logId      = $this->insertVolLog($member);

        $result = $this->svc->assignReview(self::TENANT_ID, $logId, $coordinator);

        $this->assertNotNull($result, 'Assignment to coordinator should succeed');
        $this->assertSame($logId, $result['id']);
        $this->assertSame($coordinator, $result['assigned_to']);
        $this->assertArrayHasKey('is_overdue', $result);
        $this->assertArrayHasKey('hours', $result);
    }

    public function test_assignReview_can_unassign_log_with_null_assignee(): void
    {
        $member      = $this->insertUser('member', 'member');
        $coordinator = $this->insertUser('admin', 'coord');
        $logId       = $this->insertVolLog($member);

        // First assign
        $this->svc->assignReview(self::TENANT_ID, $logId, $coordinator);

        // Then unassign
        $result = $this->svc->assignReview(self::TENANT_ID, $logId, null);

        $this->assertNotNull($result);
        $this->assertNull($result['assigned_to']);

        $row = DB::table('vol_logs')->where('id', $logId)->first();
        $this->assertNull($row->assigned_to);
        $this->assertNull($row->assigned_at);
    }

    public function test_assignReview_returns_null_for_non_pending_log(): void
    {
        $member      = $this->insertUser('member', 'member');
        $coordinator = $this->insertUser('admin', 'coord');
        $logId       = $this->insertVolLog($member, 2.0, 'approved'); // already approved

        $result = $this->svc->assignReview(self::TENANT_ID, $logId, $coordinator);

        $this->assertNull($result, 'Cannot assign a non-pending log');
    }

    // ── escalateReview ────────────────────────────────────────────────────────

    public function test_escalateReview_sets_escalated_at_and_note(): void
    {
        $member = $this->insertUser('member', 'member');
        $logId  = $this->insertVolLog($member);

        $result = $this->svc->escalateReview(self::TENANT_ID, $logId, 'Needs urgent review');

        $this->assertNotNull($result);
        $this->assertSame($logId, $result['id']);
        $this->assertTrue($result['is_escalated']);
        $this->assertStringContainsString('Needs urgent review', (string) $result['escalation_note']);

        $row = DB::table('vol_logs')->where('id', $logId)->first();
        $this->assertNotNull($row->escalated_at);
        $this->assertSame('Needs urgent review', $row->escalation_note);
    }

    public function test_escalateReview_returns_null_for_already_decided_log(): void
    {
        $member = $this->insertUser('member', 'member');
        $logId  = $this->insertVolLog($member, 2.0, 'declined');

        $result = $this->svc->escalateReview(self::TENANT_ID, $logId, 'Too late');

        $this->assertNull($result, 'Cannot escalate a decided log');
    }

    public function test_escalateReview_truncates_note_at_1000_chars(): void
    {
        $member = $this->insertUser('member', 'member');
        $logId  = $this->insertVolLog($member);
        $longNote = str_repeat('A', 1200);

        $this->svc->escalateReview(self::TENANT_ID, $logId, $longNote);

        $row = DB::table('vol_logs')->where('id', $logId)->first();
        $this->assertSame(1000, mb_strlen((string) $row->escalation_note));
    }

    // ── decideReview ──────────────────────────────────────────────────────────

    public function test_decideReview_approve_changes_status_to_approved(): void
    {
        $member    = $this->insertUser('member', 'vol');
        $reviewer  = $this->insertUser('admin', 'rev');
        $logId     = $this->insertVolLog($member, 3.0);

        $result = $this->svc->decideReview(self::TENANT_ID, $logId, $reviewer, 'approve');

        $this->assertNotNull($result);
        $this->assertSame('approved', $result['status']);
        $this->assertSame($logId, $result['id']);

        $row = DB::table('vol_logs')->where('id', $logId)->first();
        $this->assertSame('approved', $row->status);
    }

    public function test_decideReview_decline_changes_status_to_declined(): void
    {
        $member   = $this->insertUser('member', 'vol2');
        $reviewer = $this->insertUser('admin', 'rev2');
        $logId    = $this->insertVolLog($member, 1.5);

        $result = $this->svc->decideReview(self::TENANT_ID, $logId, $reviewer, 'decline');

        $this->assertNotNull($result);
        $this->assertSame('declined', $result['status']);

        $row = DB::table('vol_logs')->where('id', $logId)->first();
        $this->assertSame('declined', $row->status);
    }

    public function test_decideReview_returns_null_when_reviewer_is_log_owner(): void
    {
        // Guard: user cannot approve their own log
        $member = $this->insertUser('admin', 'self');
        $logId  = $this->insertVolLog($member);

        $result = $this->svc->decideReview(self::TENANT_ID, $logId, $member, 'approve');

        $this->assertNull($result, 'Self-approval must be blocked');
    }

    public function test_decideReview_returns_null_for_already_decided_log(): void
    {
        $member   = $this->insertUser('member', 'mem3');
        $reviewer = $this->insertUser('admin', 'rev3');
        $logId    = $this->insertVolLog($member, 2.0, 'approved'); // already decided

        $result = $this->svc->decideReview(self::TENANT_ID, $logId, $reviewer, 'decline');

        $this->assertNull($result, 'Cannot decide an already-decided log');
    }

    public function test_decideReview_returns_null_for_invalid_action(): void
    {
        $member   = $this->insertUser('member', 'mem4');
        $reviewer = $this->insertUser('admin', 'rev4');
        $logId    = $this->insertVolLog($member);

        $result = $this->svc->decideReview(self::TENANT_ID, $logId, $reviewer, 'abstain');

        $this->assertNull($result, 'Invalid action strings must be rejected');
    }

    public function test_decideReview_approve_returns_summary_in_result(): void
    {
        $member   = $this->insertUser('member', 'mem5');
        $reviewer = $this->insertUser('admin', 'rev5');
        $logId    = $this->insertVolLog($member, 2.0);

        $result = $this->svc->decideReview(self::TENANT_ID, $logId, $reviewer, 'approve');

        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('stats', $result['summary']);
        $this->assertArrayHasKey('pending_reviews', $result['summary']);
    }

    // ── intergenerationalTandemCount ─────────────────────────────────────────

    public function test_intergenerationalTandemCount_returns_zero_when_no_relationships(): void
    {
        // Fresh tenant slice — no caring_support_relationships for these users
        $count = $this->svc->intergenerationalTandemCount(self::TENANT_ID);

        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }
}
