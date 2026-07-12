<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Core\TenantContext;
use App\Enums\GroupStatus;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * One tenant- and lifecycle-aware decision point for Groups authorization.
 *
 * Overview access is intentionally distinct from member-content access:
 * authenticated same-tenant users may see an active group's privacy-safe
 * overview, while every child resource requires active membership or an
 * explicit administrative role.
 */
final class GroupAccessService
{
    public static function canViewOverview(int $groupId, int|null $userId): bool
    {
        $group = self::findGroup($groupId);
        if ($group === null || ! self::isSameTenantUser($userId)) {
            return false;
        }

        if (self::isTenantAdmin((int) $userId)) {
            return true;
        }

        if ((string) $group->visibility === 'secret') {
            return self::isOwner($group, (int) $userId)
                || self::isActiveMemberOf($group, (int) $userId);
        }

        return match ($group->status) {
            GroupStatus::Active => true,
            GroupStatus::Dormant => self::isOwner($group, (int) $userId)
                || self::isActiveMemberOf($group, (int) $userId),
            GroupStatus::PendingReview,
            GroupStatus::Archived,
            GroupStatus::Rejected => self::isOwner($group, (int) $userId),
        };
    }

    public static function canViewMemberContent(int $groupId, int|null $userId): bool
    {
        $group = self::findGroup($groupId);
        if (
            $group === null
            || $group->status !== GroupStatus::Active
            || ! self::isSameTenantUser($userId)
        ) {
            return false;
        }

        return self::isTenantAdmin((int) $userId)
            || self::isOwner($group, (int) $userId)
            || self::isActiveMemberOf($group, (int) $userId);
    }

    public static function canJoin(int $groupId, int $userId): bool
    {
        $group = self::findGroup($groupId);

        return $group !== null
            && $group->status->isJoinable()
            && self::isSameTenantUser($userId)
            && (string) $group->visibility !== 'secret';
    }

    public static function canWriteContent(int $groupId, int $userId): bool
    {
        $group = self::findGroup($groupId);
        if (
            $group === null
            || ! $group->status->isWritable()
            || ! self::isSameTenantUser($userId)
        ) {
            return false;
        }

        return self::isTenantAdmin($userId)
            || self::isOwner($group, $userId)
            || self::isActiveMemberOf($group, $userId);
    }

    public static function canManage(int $groupId, int $userId): bool
    {
        $group = self::findGroup($groupId);
        if ($group === null || ! self::isSameTenantUser($userId)) {
            return false;
        }

        return self::isTenantAdmin($userId)
            || self::isOwner($group, $userId)
            || in_array(self::activeMembershipRole($group, $userId), ['owner', 'admin'], true);
    }

    public static function canManageMembers(int $groupId, int $userId): bool
    {
        $group = self::findGroup($groupId);

        return $group !== null
            && $group->status === GroupStatus::Active
            && self::canManage($groupId, $userId);
    }

    public static function canConfigure(int $groupId, int $userId): bool
    {
        return self::canManage($groupId, $userId);
    }

    public static function canExport(int $groupId, int $userId): bool
    {
        return self::canManage($groupId, $userId);
    }

    public static function canIntegrate(int $groupId, int $userId): bool
    {
        $group = self::findGroup($groupId);

        return $group !== null
            && $group->status === GroupStatus::Active
            && self::canManage($groupId, $userId);
    }

    public static function isActiveMember(int $groupId, int $userId): bool
    {
        $group = self::findGroup($groupId);

        return $group !== null && self::isActiveMemberOf($group, $userId);
    }

    public static function isTenantAdmin(int $userId): bool
    {
        $user = User::query()->find($userId);
        if ($user === null) {
            return false;
        }

        return in_array((string) ($user->role ?? ''), ['admin', 'super_admin', 'god'], true)
            || (bool) ($user->is_super_admin ?? false)
            || (bool) ($user->is_tenant_super_admin ?? false);
    }

    private static function findGroup(int $groupId): Group|null
    {
        /** @var Group|null $group */
        $group = Group::query()->find($groupId);

        return $group;
    }

    private static function isSameTenantUser(int|null $userId): bool
    {
        return $userId !== null && User::query()->whereKey($userId)->exists();
    }

    private static function isOwner(Group $group, int $userId): bool
    {
        return (int) $group->owner_id === $userId;
    }

    private static function isActiveMemberOf(Group $group, int $userId): bool
    {
        return self::activeMembershipRole($group, $userId) !== null;
    }

    private static function activeMembershipRole(Group $group, int $userId): string|null
    {
        $role = DB::table('group_members')
            ->where('tenant_id', (int) TenantContext::getId())
            ->where('group_id', (int) $group->id)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->value('role');

        return is_string($role) ? $role : null;
    }
}
