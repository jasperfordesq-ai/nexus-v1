<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Integration;

use App\Core\TenantContext;
use App\Enums\GroupStatus;
use App\Models\Group;
use App\Models\User;
use App\Services\GroupAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

final class GroupAccessLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    private User $nonMember;
    private User $pendingMember;
    private User $activeMember;
    private User $groupAdmin;
    private User $owner;
    private User $tenantAdmin;
    private User $crossTenantActor;

    /** @var array<string, int> */
    private array $groups = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->nonMember = User::factory()->forTenant($this->testTenantId)->create();
        $this->pendingMember = User::factory()->forTenant($this->testTenantId)->create();
        $this->activeMember = User::factory()->forTenant($this->testTenantId)->create();
        $this->groupAdmin = User::factory()->forTenant($this->testTenantId)->create();
        $this->owner = User::factory()->forTenant($this->testTenantId)->create();
        $this->tenantAdmin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $this->crossTenantActor = User::factory()->forTenant(999)->admin()->create();

        TenantContext::setById($this->testTenantId);

        $this->groups = [
            'active_public' => $this->insertGroup(GroupStatus::Active, 'public'),
            'active_private' => $this->insertGroup(GroupStatus::Active, 'private'),
            'dormant' => $this->insertGroup(GroupStatus::Dormant, 'private'),
            'archived' => $this->insertGroup(GroupStatus::Archived, 'private'),
            'pending_review' => $this->insertGroup(GroupStatus::PendingReview, 'private'),
            'rejected' => $this->insertGroup(GroupStatus::Rejected, 'private'),
            'cross_tenant' => $this->insertGroup(
                GroupStatus::Active,
                'public',
                999,
                (int) $this->crossTenantActor->id,
            ),
        ];

        foreach ($this->localGroupIds() as $groupId) {
            $this->insertMembership($groupId, $this->pendingMember, 'member', 'pending');
            $this->insertMembership($groupId, $this->activeMember, 'member', 'active');
            $this->insertMembership($groupId, $this->groupAdmin, 'admin', 'active');
        }
    }

    public function test_overview_and_member_content_are_separate_across_roles_and_lifecycle(): void
    {
        TenantContext::setById($this->testTenantId);

        $activeActors = [
            'same-tenant nonmember' => [$this->nonMember, true, false],
            'pending member' => [$this->pendingMember, true, false],
            'active member' => [$this->activeMember, true, true],
            'group admin' => [$this->groupAdmin, true, true],
            'owner' => [$this->owner, true, true],
            'tenant admin' => [$this->tenantAdmin, true, true],
            'cross-tenant actor' => [$this->crossTenantActor, false, false],
        ];

        foreach (['active_public', 'active_private'] as $groupKey) {
            foreach ($activeActors as $actorLabel => [$actor, $overview, $memberContent]) {
                $this->assertAccess(
                    $this->groups[$groupKey],
                    $actor,
                    $overview,
                    $memberContent,
                    "{$groupKey}: {$actorLabel}",
                );
            }
        }

        $dormantActors = [
            'same-tenant nonmember' => [$this->nonMember, false],
            'pending member' => [$this->pendingMember, false],
            'active member' => [$this->activeMember, true],
            'group admin' => [$this->groupAdmin, true],
            'owner' => [$this->owner, true],
            'tenant admin' => [$this->tenantAdmin, true],
            'cross-tenant actor' => [$this->crossTenantActor, false],
        ];
        foreach ($dormantActors as $actorLabel => [$actor, $overview]) {
            $this->assertAccess(
                $this->groups['dormant'],
                $actor,
                $overview,
                false,
                "dormant: {$actorLabel}",
            );
        }

        foreach (['archived', 'pending_review', 'rejected'] as $groupKey) {
            foreach ([
                'same-tenant nonmember' => [$this->nonMember, false],
                'pending member' => [$this->pendingMember, false],
                'active member' => [$this->activeMember, false],
                'group admin' => [$this->groupAdmin, false],
                'owner' => [$this->owner, true],
                'tenant admin' => [$this->tenantAdmin, true],
                'cross-tenant actor' => [$this->crossTenantActor, false],
            ] as $actorLabel => [$actor, $overview]) {
                $this->assertAccess(
                    $this->groups[$groupKey],
                    $actor,
                    $overview,
                    false,
                    "{$groupKey}: {$actorLabel}",
                );
            }
        }

        self::assertFalse(GroupAccessService::canViewOverview($this->groups['active_public'], null));
        self::assertFalse(GroupAccessService::canViewMemberContent($this->groups['active_public'], null));
    }

    public function test_only_active_groups_are_joinable_or_writable_in_enum_service_and_model_scopes(): void
    {
        TenantContext::setById($this->testTenantId);

        foreach ($this->localGroupIdsByKey() as $groupKey => $groupId) {
            $isActive = in_array($groupKey, ['active_public', 'active_private'], true);

            self::assertSame($isActive, GroupAccessService::canJoin($groupId, (int) $this->nonMember->id), $groupKey);
            self::assertSame($isActive, GroupAccessService::canWriteContent($groupId, (int) $this->activeMember->id), $groupKey);
            self::assertSame($isActive, GroupAccessService::canWriteContent($groupId, (int) $this->groupAdmin->id), $groupKey);
            self::assertSame($isActive, GroupAccessService::canWriteContent($groupId, (int) $this->owner->id), $groupKey);
            self::assertSame($isActive, GroupAccessService::canWriteContent($groupId, (int) $this->tenantAdmin->id), $groupKey);
            self::assertFalse(GroupAccessService::canWriteContent($groupId, (int) $this->pendingMember->id), $groupKey);
            self::assertFalse(GroupAccessService::canWriteContent($groupId, (int) $this->nonMember->id), $groupKey);
        }

        self::assertFalse(GroupAccessService::canJoin($this->groups['active_public'], (int) $this->crossTenantActor->id));
        self::assertFalse(GroupAccessService::canWriteContent($this->groups['active_public'], (int) $this->crossTenantActor->id));
        self::assertFalse(GroupAccessService::canJoin($this->groups['cross_tenant'], (int) $this->nonMember->id));

        $expectedActiveIds = [$this->groups['active_public'], $this->groups['active_private']];
        sort($expectedActiveIds);
        foreach (['active', 'joinable', 'writable'] as $scope) {
            $actualIds = Group::query()
                ->whereIn('id', $this->localGroupIds())
                ->{$scope}()
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();
            sort($actualIds);
            self::assertSame($expectedActiveIds, $actualIds, $scope);
        }

        foreach (GroupStatus::cases() as $status) {
            self::assertSame($status === GroupStatus::Active, $status->isJoinable(), $status->value);
            self::assertSame($status === GroupStatus::Active, $status->isWritable(), $status->value);
        }
    }

    public function test_management_is_tenant_scoped_before_admin_override_and_cross_tenant_ids_are_concealed(): void
    {
        TenantContext::setById($this->testTenantId);

        foreach ($this->localGroupIdsByKey() as $groupKey => $groupId) {
            self::assertTrue(GroupAccessService::canManage($groupId, (int) $this->owner->id), "owner: {$groupKey}");
            self::assertTrue(GroupAccessService::canManage($groupId, (int) $this->groupAdmin->id), "group admin: {$groupKey}");
            self::assertTrue(GroupAccessService::canManage($groupId, (int) $this->tenantAdmin->id), "tenant admin: {$groupKey}");
            self::assertFalse(GroupAccessService::canManage($groupId, (int) $this->activeMember->id), "member: {$groupKey}");
            self::assertFalse(GroupAccessService::canManage($groupId, (int) $this->pendingMember->id), "pending: {$groupKey}");
            self::assertFalse(GroupAccessService::canManage($groupId, (int) $this->crossTenantActor->id), "foreign admin: {$groupKey}");

            $isActive = in_array($groupKey, ['active_public', 'active_private'], true);
            self::assertSame($isActive, GroupAccessService::canManageMembers($groupId, (int) $this->owner->id), $groupKey);
            self::assertSame($isActive, GroupAccessService::canIntegrate($groupId, (int) $this->groupAdmin->id), $groupKey);
        }

        self::assertFalse(GroupAccessService::canManage($this->groups['cross_tenant'], (int) $this->tenantAdmin->id));
        self::assertFalse(GroupAccessService::canViewOverview($this->groups['cross_tenant'], (int) $this->tenantAdmin->id));
        self::assertFalse(GroupAccessService::canViewMemberContent($this->groups['cross_tenant'], (int) $this->tenantAdmin->id));

        $adminVisibleIds = Group::query()
            ->whereIn('id', array_values($this->groups))
            ->manageableBy((int) $this->tenantAdmin->id, true)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        self::assertEqualsCanonicalizing($this->localGroupIds(), $adminVisibleIds);
        self::assertNotContains($this->groups['cross_tenant'], $adminVisibleIds);

        self::assertFalse(
            Group::query()
                ->manageableBy((int) $this->crossTenantActor->id, true)
                ->whereKey($this->groups['cross_tenant'])
                ->exists(),
        );

        try {
            TenantContext::setById(999);
            self::assertTrue(GroupAccessService::canManage($this->groups['cross_tenant'], (int) $this->crossTenantActor->id));
            self::assertFalse(GroupAccessService::canManage($this->groups['active_public'], (int) $this->crossTenantActor->id));
        } finally {
            TenantContext::setById($this->testTenantId);
        }
    }

    public function test_membership_pivots_are_tenant_scoped_for_access_scopes_relations_and_attach(): void
    {
        $wrongTenantAdmin = User::factory()->forTenant($this->testTenantId)->create();
        TenantContext::setById($this->testTenantId);
        $this->insertMembership(
            $this->groups['dormant'],
            $wrongTenantAdmin,
            'admin',
            'active',
            999,
        );

        self::assertSame($this->testTenantId, TenantContext::getId());
        self::assertSame(
            $this->testTenantId,
            (int) DB::table('groups')->where('id', $this->groups['dormant'])->value('tenant_id'),
        );

        self::assertFalse(GroupAccessService::isActiveMember($this->groups['dormant'], (int) $wrongTenantAdmin->id));
        self::assertFalse(GroupAccessService::canViewOverview($this->groups['dormant'], (int) $wrongTenantAdmin->id));
        self::assertFalse(GroupAccessService::canManage($this->groups['dormant'], (int) $wrongTenantAdmin->id));
        self::assertFalse(
            Group::query()->viewableBy((int) $wrongTenantAdmin->id)->whereKey($this->groups['dormant'])->exists(),
        );
        self::assertFalse(
            Group::query()->manageableBy((int) $wrongTenantAdmin->id)->whereKey($this->groups['dormant'])->exists(),
        );

        $dormantGroup = Group::query()->findOrFail($this->groups['dormant']);
        self::assertFalse($dormantGroup->members()->whereKey($wrongTenantAdmin->id)->exists());
        self::assertFalse($dormantGroup->activeMembers()->whereKey($wrongTenantAdmin->id)->exists());

        $attachedMember = User::factory()->forTenant($this->testTenantId)->create();
        TenantContext::setById($this->testTenantId);
        $dormantGroup->attachMember((int) $attachedMember->id, [
            'role' => 'member',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('group_members', [
            'tenant_id' => $this->testTenantId,
            'group_id' => $this->groups['dormant'],
            'user_id' => $attachedMember->id,
            'role' => 'member',
            'status' => 'active',
        ]);
    }

    public function test_group_status_and_compatibility_fields_are_cast_and_queryable_from_real_rows(): void
    {
        TenantContext::setById($this->testTenantId);

        foreach ($this->localGroupIdsByKey() as $groupKey => $groupId) {
            $group = Group::query()->findOrFail($groupId);
            $expectedStatus = match ($groupKey) {
                'active_public', 'active_private' => GroupStatus::Active,
                'dormant' => GroupStatus::Dormant,
                'archived' => GroupStatus::Archived,
                'pending_review' => GroupStatus::PendingReview,
                'rejected' => GroupStatus::Rejected,
            };

            self::assertSame($expectedStatus, $group->status, $groupKey);
            self::assertSame($expectedStatus === GroupStatus::Active, $group->is_active, $groupKey);
        }

        $moderationIds = Group::query()
            ->whereIn('id', $this->localGroupIds())
            ->inStates([GroupStatus::PendingReview, GroupStatus::Rejected->value])
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        self::assertEqualsCanonicalizing(
            [$this->groups['pending_review'], $this->groups['rejected']],
            $moderationIds,
        );
    }

    private function assertAccess(
        int $groupId,
        User $actor,
        bool $canViewOverview,
        bool $canViewMemberContent,
        string $message,
    ): void {
        $actorId = (int) $actor->id;
        self::assertSame(
            $canViewOverview,
            GroupAccessService::canViewOverview($groupId, $actorId),
            "service overview: {$message}",
        );
        self::assertSame(
            $canViewMemberContent,
            GroupAccessService::canViewMemberContent($groupId, $actorId),
            "service member content: {$message}",
        );

        $isCurrentTenantAdmin = $actorId === (int) $this->tenantAdmin->id;
        self::assertSame(
            $canViewOverview,
            Group::query()
                ->viewableBy($actorId, $isCurrentTenantAdmin)
                ->whereKey($groupId)
                ->exists(),
            "model overview scope: {$message}",
        );
    }

    private function insertGroup(
        GroupStatus $status,
        string $visibility,
        int|null $tenantId = null,
        int|null $ownerId = null,
    ): int {
        $tenantId ??= $this->testTenantId;
        $ownerId ??= (int) $this->owner->id;

        return (int) DB::table('groups')->insertGetId([
            'tenant_id' => $tenantId,
            'owner_id' => $ownerId,
            'name' => 'Access matrix ' . $status->value . ' ' . $visibility . ' ' . uniqid('', true),
            'description' => 'Real database GroupAccessService lifecycle fixture.',
            'visibility' => $visibility,
            'status' => $status->value,
            'is_active' => $status->legacyIsActive(),
            'cached_member_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertMembership(
        int $groupId,
        User $user,
        string $role,
        string $status,
        int|null $tenantId = null,
    ): void {
        DB::table('group_members')->insert([
            'tenant_id' => $tenantId ?? $this->testTenantId,
            'group_id' => $groupId,
            'user_id' => $user->id,
            'role' => $role,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return list<int> */
    private function localGroupIds(): array
    {
        return array_values($this->localGroupIdsByKey());
    }

    /** @return array<string, int> */
    private function localGroupIdsByKey(): array
    {
        return array_filter(
            $this->groups,
            static fn (string $key): bool => $key !== 'cross_tenant',
            ARRAY_FILTER_USE_KEY,
        );
    }
}
