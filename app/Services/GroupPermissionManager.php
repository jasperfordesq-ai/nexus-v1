<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * GroupPermissionManager — tenant-scoped permission checking for groups.
 *
 * Handles both tenant-wide permissions (based on users.role) and
 * group-level permissions (based on group_members.role).
 */
class GroupPermissionManager
{
    // ── Core permissions (tenant-wide) ────────────────────────────
    public const PERM_CREATE_GROUP     = 'create_group';
    public const PERM_CREATE_HUB       = 'create_hub';
    public const PERM_EDIT_ANY_GROUP   = 'edit_any_group';
    public const PERM_DELETE_ANY_GROUP = 'delete_any_group';
    public const PERM_MODERATE_CONTENT = 'moderate_content';
    public const PERM_MANAGE_MEMBERS   = 'manage_members';
    public const PERM_MANAGE_SETTINGS  = 'manage_settings';
    public const PERM_VIEW_ANALYTICS   = 'view_analytics';
    public const PERM_APPROVE_GROUPS   = 'approve_groups';
    public const PERM_BAN_MEMBERS      = 'ban_members';

    // ── Group-level permissions ───────────────────────────────────
    public const PERM_GROUP_EDIT            = 'group_edit';
    public const PERM_GROUP_DELETE          = 'group_delete';
    public const PERM_GROUP_MANAGE_MEMBERS  = 'group_manage_members';
    public const PERM_GROUP_POST_DISCUSSION = 'group_post_discussion';
    public const PERM_GROUP_INVITE_MEMBERS  = 'group_invite_members';

    /** All tenant-wide permissions. */
    private const TENANT_PERMISSIONS = [
        self::PERM_CREATE_GROUP,
        self::PERM_CREATE_HUB,
        self::PERM_EDIT_ANY_GROUP,
        self::PERM_DELETE_ANY_GROUP,
        self::PERM_MODERATE_CONTENT,
        self::PERM_MANAGE_MEMBERS,
        self::PERM_MANAGE_SETTINGS,
        self::PERM_VIEW_ANALYTICS,
        self::PERM_APPROVE_GROUPS,
        self::PERM_BAN_MEMBERS,
    ];

    /** All group-level permissions. */
    private const GROUP_PERMISSIONS = [
        self::PERM_GROUP_EDIT,
        self::PERM_GROUP_DELETE,
        self::PERM_GROUP_MANAGE_MEMBERS,
        self::PERM_GROUP_POST_DISCUSSION,
        self::PERM_GROUP_INVITE_MEMBERS,
    ];

    /** Permissions granted to each group-level role. */
    private const ROLE_PERMISSIONS = [
        'owner' => [
            self::PERM_GROUP_EDIT,
            self::PERM_GROUP_DELETE,
            self::PERM_GROUP_MANAGE_MEMBERS,
            self::PERM_GROUP_POST_DISCUSSION,
            self::PERM_GROUP_INVITE_MEMBERS,
        ],
        'admin' => [
            self::PERM_GROUP_EDIT,
            self::PERM_GROUP_MANAGE_MEMBERS,
            self::PERM_GROUP_POST_DISCUSSION,
            self::PERM_GROUP_INVITE_MEMBERS,
        ],
        'member' => [
            self::PERM_GROUP_POST_DISCUSSION,
            self::PERM_GROUP_INVITE_MEMBERS,
        ],
        'viewer' => [
            // Read-only — no write permissions
        ],
    ];

    public function __construct()
    {
    }

    /**
     * Check if a user has a given permission.
     *
     * For tenant-wide permissions, checks the user's `role` column —
     * admin and super_admin get all tenant-wide permissions.
     *
     * For group-level permissions, delegates to hasGroupPermission()
     * (requires a non-null $groupId).
     *
     * @param int         $userId     The user to check
     * @param string      $permission One of the PERM_* constants
     * @param int|null    $groupId    Required for group-level permissions
     * @return bool
     */
    public static function can(int $userId, string $permission, ?int $groupId = null): bool
    {
        // Tenant-wide permissions — check users.role
        if (in_array($permission, self::TENANT_PERMISSIONS, true)) {
            return self::isTenantAdmin($userId);
        }

        // Group-level permissions — require a group context
        if (in_array($permission, self::GROUP_PERMISSIONS, true)) {
            // Tenant admins implicitly have all group-level permissions
            if (self::isTenantAdmin($userId)) {
                return true;
            }

            if ($groupId === null) {
                return false;
            }

            return self::hasGroupPermission($userId, $groupId, $permission);
        }

        // Unknown permission — deny by default
        return false;
    }

    /**
     * Check a group-level permission based on the user's membership role.
     *
     * @param int    $userId     The user to check
     * @param int    $groupId    The group to check in
     * @param string $permission One of the PERM_GROUP_* constants
     * @return bool
     */
    public static function hasGroupPermission(int $userId, int $groupId, string $permission): bool
    {
        $role = self::getUserGroupRole($userId, $groupId);

        if ($role === null) {
            return false;
        }

        $allowed = self::getPermissionsForRole($role);

        return in_array($permission, $allowed, true);
    }

    /**
     * Return the array of permission strings granted to a given role.
     *
     * Roles: 'owner' (all perms), 'admin' (all except delete),
     * 'member' (post_discussion, invite_members).
     *
     * @param string $role One of 'owner', 'admin', 'member'
     * @return array<string>
     */
    public static function getPermissionsForRole(string $role): array
    {
        return self::ROLE_PERMISSIONS[$role] ?? [];
    }

    /**
     * Get a user's role in a specific group from the group_members table.
     *
     * Only considers active memberships (status = 'active').
     *
     * @param int $userId  The user ID
     * @param int $groupId The group ID
     * @return string|null The role string, or null if not a member
     */
    public static function getUserGroupRole(int $userId, int $groupId): ?string
    {
        $tenantId = TenantContext::getId();

        try {
            $row = DB::selectOne(
                "SELECT role FROM group_members
                 WHERE tenant_id = ? AND group_id = ? AND user_id = ? AND status = 'active'
                 LIMIT 1",
                [$tenantId, $groupId, $userId]
            );

            return $row ? $row->role : null;
        } catch (\Throwable $e) {
            Log::error('GroupPermissionManager::getUserGroupRole failed', [
                'user_id'   => $userId,
                'group_id'  => $groupId,
                'tenant_id' => $tenantId,
                'error'     => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Check if the user is a tenant admin (role = 'admin' or 'super_admin').
     *
     * @param int $userId The user to check
     * @return bool
     */
    public static function isTenantAdmin(int $userId): bool
    {
        $tenantId = TenantContext::getId();

        try {
            $row = DB::selectOne(
                "SELECT role FROM users WHERE id = ? AND tenant_id = ? LIMIT 1",
                [$userId, $tenantId]
            );

            if (!$row) {
                return false;
            }

            return in_array($row->role, ['admin', 'super_admin'], true);
        } catch (\Throwable $e) {
            Log::error('GroupPermissionManager::isTenantAdmin failed', [
                'user_id'   => $userId,
                'tenant_id' => $tenantId,
                'error'     => $e->getMessage(),
            ]);

            return false;
        }
    }
}
