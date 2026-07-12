<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Enums\GroupStatus;
use App\Models\Group;
use App\Models\User;
use App\Services\GroupApprovalWorkflowService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Tests\Laravel\TestCase;

final class GroupApprovalWorkflowServiceTest extends TestCase
{
    use DatabaseTransactions;

    private User $owner;
    private User $reviewer;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById($this->testTenantId);
        $this->owner = User::factory()->forTenant($this->testTenantId)->create();
        $this->reviewer = User::factory()->forTenant($this->testTenantId)->create([
            'role' => 'admin',
        ]);
        TenantContext::setById($this->testTenantId);
        Queue::fake();
    }

    public function test_status_constants_are_stable(): void
    {
        self::assertSame('pending', GroupApprovalWorkflowService::STATUS_PENDING);
        self::assertSame('approved', GroupApprovalWorkflowService::STATUS_APPROVED);
        self::assertSame('rejected', GroupApprovalWorkflowService::STATUS_REJECTED);
        self::assertSame('changes_requested', GroupApprovalWorkflowService::STATUS_CHANGES_REQUESTED);
    }

    public function test_submission_is_parent_locked_and_idempotent(): void
    {
        $group = $this->pendingGroup();

        $first = GroupApprovalWorkflowService::submitForApproval(
            $group->id,
            $this->owner->id,
            'Please review',
        );
        $second = GroupApprovalWorkflowService::submitForApproval($group->id, $this->owner->id);

        self::assertSame($first, $second);
        self::assertSame(1, DB::table('group_approval_requests')
            ->where('tenant_id', $this->testTenantId)
            ->where('group_id', $group->id)
            ->where('status', GroupApprovalWorkflowService::STATUS_PENDING)
            ->count());
    }

    public function test_active_or_foreign_group_cannot_be_submitted_as_pending(): void
    {
        $active = Group::factory()->forTenant($this->testTenantId)->create([
            'owner_id' => $this->owner->id,
            'status' => GroupStatus::Active->value,
            'is_active' => true,
        ]);

        $this->expectException(InvalidArgumentException::class);
        GroupApprovalWorkflowService::submitForApproval($active->id, $this->owner->id);
    }

    public function test_approval_changes_request_and_group_in_one_canonical_transition(): void
    {
        $group = $this->pendingGroup();
        $requestId = GroupApprovalWorkflowService::submitForApproval($group->id, $this->owner->id);

        self::assertTrue(GroupApprovalWorkflowService::approveGroup(
            $requestId,
            $this->reviewer->id,
            'Approved',
        ));

        $this->assertRequestStatus($requestId, GroupApprovalWorkflowService::STATUS_APPROVED);
        $this->assertGroupStatus($group->id, GroupStatus::Active, true);
        $this->assertLifecycleAudit($group->id, GroupStatus::PendingReview, GroupStatus::Active);
        self::assertFalse(GroupApprovalWorkflowService::approveGroup($requestId, $this->reviewer->id));
    }

    public function test_rejection_changes_request_and_group_in_one_canonical_transition(): void
    {
        $group = $this->pendingGroup();
        $requestId = GroupApprovalWorkflowService::submitForApproval($group->id, $this->owner->id);

        self::assertTrue(GroupApprovalWorkflowService::rejectGroup(
            $requestId,
            $this->reviewer->id,
            'Insufficient detail',
        ));

        $this->assertRequestStatus($requestId, GroupApprovalWorkflowService::STATUS_REJECTED);
        $this->assertGroupStatus($group->id, GroupStatus::Rejected, false);
        $this->assertLifecycleAudit($group->id, GroupStatus::PendingReview, GroupStatus::Rejected);
        self::assertFalse(GroupApprovalWorkflowService::rejectGroup($requestId, $this->reviewer->id));
    }

    public function test_unknown_or_other_tenant_request_is_concealed(): void
    {
        self::assertFalse(GroupApprovalWorkflowService::approveGroup(PHP_INT_MAX, $this->reviewer->id));

        $foreignOwner = User::factory()->forTenant(999)->create();
        $foreignGroup = Group::factory()->forTenant(999)->create([
            'owner_id' => $foreignOwner->id,
            'status' => GroupStatus::PendingReview->value,
            'is_active' => false,
        ]);
        $foreignRequestId = (int) DB::table('group_approval_requests')->insertGetId([
            'tenant_id' => 999,
            'group_id' => $foreignGroup->id,
            'submitted_by' => $foreignOwner->id,
            'status' => GroupApprovalWorkflowService::STATUS_PENDING,
            'created_at' => now(),
        ]);
        TenantContext::setById($this->testTenantId);

        self::assertFalse(GroupApprovalWorkflowService::approveGroup($foreignRequestId, $this->reviewer->id));
        self::assertSame(GroupApprovalWorkflowService::STATUS_PENDING, DB::table('group_approval_requests')
            ->where('id', $foreignRequestId)
            ->value('status'));
    }

    private function pendingGroup(): Group
    {
        /** @var Group $group */
        $group = Group::factory()->forTenant($this->testTenantId)->create([
            'owner_id' => $this->owner->id,
            'status' => GroupStatus::PendingReview->value,
            'is_active' => false,
        ]);

        return $group;
    }

    private function assertRequestStatus(int $requestId, string $status): void
    {
        self::assertSame($status, DB::table('group_approval_requests')
            ->where('id', $requestId)
            ->value('status'));
    }

    private function assertGroupStatus(int $groupId, GroupStatus $status, bool $isActive): void
    {
        $group = DB::table('groups')->where('id', $groupId)->first();
        self::assertSame($status->value, $group->status);
        self::assertSame($isActive, (bool) $group->is_active);
    }

    private function assertLifecycleAudit(
        int $groupId,
        GroupStatus $oldStatus,
        GroupStatus $newStatus,
    ): void {
        $audit = DB::table('group_audit_log')
            ->where('tenant_id', $this->testTenantId)
            ->where('group_id', $groupId)
            ->where('action', 'group_status_changed')
            ->sole();
        $details = json_decode((string) $audit->details, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($oldStatus->value, $details['old_status']);
        self::assertSame($newStatus->value, $details['new_status']);
    }
}
