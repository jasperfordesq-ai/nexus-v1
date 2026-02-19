<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\User;

/**
 * GroupPermissionManager
 *
 * Centralized permission management for groups module.
 * Handles role-based access control, custom permissions, and authorization checks.
 */
class GroupPermissionManager
{
    // Core permissions
    const PERM_CREATE_GROUP = 'create_group';
    const PERM_CREATE_HUB = 'create_hub';
    const PERM_EDIT_ANY_GROUP = 'edit_any_group';
    const PERM_DELETE_ANY_GROUP = 'delete_any_group';
    const PERM_MODERATE_CONTENT = 'moderate_content';
    const PERM_MANAGE_MEMBERS = 'manage_members';
    const PERM_MANAGE_SETTINGS = 'manage_settings';
    const PERM_VIEW_ANALYTICS = 'view_analytics';
    const PERM_APPROVE_GROUPS = 'approve_groups';
    const PERM_FEATURE_GROUPS = 'feature_groups';
    const PERM_BAN_MEMBERS = 'ban_members';
    const PERM_VIEW_AUDIT_LOG = 'view_audit_log';
    const PERM_EXPORT_DATA = 'export_data';
    const PERM_MANAGE_GROUP_TYPES = 'manage_group_types';
    const PERM_OVERRIDE_LIMITS = 'override_limits';

    // Group-level permissions
    const PERM_GROUP_EDIT = 'group_edit';
    const PERM_GROUP_DELETE = 'group_delete';
    const PERM_GROUP_MANAGE_MEMBERS = 'group_manage_members';
    const PERM_GROUP_POST_DISCUSSION = 'group_post_discussion';
    const PERM_GROUP_INVITE_MEMBERS = 'group_invite_members';
    const PERM_GROUP_APPROVE_MEMBERS = 'group_approve_members';
    const PERM_GROUP_KICK_MEMBERS = 'group_kick_members';
    const PERM_GROUP_CHANGE_SETTINGS = 'group_change_settings';

    // Role definitions
    private static $rolePermissions = [
        'super_admin' => [
            self::PERM_CREATE_GROUP,
            self::PERM_CREATE_HUB,
            self::PERM_EDIT_ANY_GROUP,
            self::PERM_DELETE_ANY_GROUP,
            self::PERM_MODERATE_CONTENT,
            self::PERM_MANAGE_MEMBERS,
            self::PERM_MANAGE_SETTINGS,
            self::PERM_VIEW_ANALYTICS,
            self::PERM_APPROVE_GROUPS,
            self::PERM_FEATURE_GROUPS,
            self::PERM_BAN_MEMBERS,
            self::PERM_VIEW_AUDIT_LOG,
            self::PERM_EXPORT_DATA,
            self::PERM_MANAGE_GROUP_TYPES,
            self::PERM_OVERRIDE_LIMITS,
        ],
        'admin' => [
            self::PERM_CREATE_GROUP,
            self::PERM_CREATE_HUB,
            self::PERM_EDIT_ANY_GROUP,
            self::PERM_DELETE_ANY_GROUP,
            self::PERM_MODERATE_CONTENT,
            self::PERM_MANAGE_MEMBERS,
            self::PERM_MANAGE_SETTINGS,
            self::PERM_VIEW_ANALYTICS,
            self::PERM_APPROVE_GROUPS,
            self::PERM_FEATURE_GROUPS,
            self::PERM_BAN_MEMBERS,
            self::PERM_VIEW_AUDIT_LOG,
            self::PERM_EXPORT_DATA,
            self::PERM_MANAGE_GROUP_TYPES,
            self::PERM_OVERRIDE_LIMITS,
        ],
        'tenant_admin' => [
            self::PERM_CREATE_GROUP,
            self::PERM_CREATE_HUB,
            self::PERM_EDIT_ANY_GROUP,
            self::PERM_DELETE_ANY_GROUP,
            self::PERM_MODERATE_CONTENT,
            self::PERM_MANAGE_MEMBERS,
            self::PERM_MANAGE_SETTINGS,
            self::PERM_VIEW_ANALYTICS,
            self::PERM_APPROVE_GROUPS,
            self::PERM_FEATURE_GROUPS,
            self::PERM_VIEW_AUDIT_LOG,
            self::PERM_EXPORT_DATA,
            self::PERM_MANAGE_GROUP_TYPES,
        ],
        'user' => [
            self::PERM_CREATE_GROUP, // Subject to configuration
        ],
    ];

    // Group role permissions
    private static $groupRolePermissions = [
        'owner' => [
            self::PERM_GROUP_EDIT,
            self::PERM_GROUP_DELETE,
            self::PERM_GROUP_MANAGE_MEMBERS,
            self::PERM_GROUP_POST_DISCUSSION,
            self::PERM_GROUP_INVITE_MEMBERS,
            self::PERM_GROUP_APPROVE_MEMBERS,
            self::PERM_GROUP_KICK_MEMBERS,
            self::PERM_GROUP_CHANGE_SETTINGS,
        ],
        'admin' => [
            self::PERM_GROUP_EDIT,
            self::PERM_GROUP_MANAGE_MEMBERS,
            self::PERM_GROUP_POST_DISCUSSION,
            self::PERM_GROUP_INVITE_MEMBERS,
            self::PERM_GROUP_APPROVE_MEMBERS,
            self::PERM_GROUP_KICK_MEMBERS,
            self::PERM_GROUP_CHANGE_SETTINGS,
        ],
        'member' => [
            self::PERM_GROUP_POST_DISCUSSION,
            self::PERM_GROUP_INVITE_MEMBERS, // Subject to configuration
        ],
    ];

    /**
     * Check if user has a global permission
     *
     * @param int $userId User ID
     * @param string $permission Permission constant
     * @return bool
     */
    public static function hasPermission($userId, $permission)
    {
        $user = User::find($userId);
        if (!$user) {
            return false;
        }

        $userRole = $user['role'] ?? 'user';

        // Check role-based permissions
        if (isset(self::$rolePermissions[$userRole])) {
            if (in_array($permission, self::$rolePermissions[$userRole])) {
                // Apply configuration checks for certain permissions
                if ($permission === self::PERM_CREATE_GROUP) {
                    return GroupConfigurationService::get(
                        GroupConfigurationService::CONFIG_ALLOW_USER_GROUP_CREATION
                    );
                }
                return true;
            }
        }

        // Check custom permissions
        return self::hasCustomPermission($userId, $permission);
    }

    /**
     * Check if user has permission within a specific group
     *
     * @param int $groupId Group ID
     * @param int $userId User ID
     * @param string $permission Group permission constant
     * @return bool
     */
    public static function hasGroupPermission($groupId, $userId, $permission)
    {
        // Global admins have all group permissions
        if (self::isGlobalAdmin($userId)) {
            return true;
        }

        // Get user's role in the group
        $groupRole = self::getUserGroupRole($groupId, $userId);
        if (!$groupRole) {
            return false;
        }

        // Check role-based group permissions
        if (isset(self::$groupRolePermissions[$groupRole])) {
            if (in_array($permission, self::$groupRolePermissions[$groupRole])) {
                // Apply configuration checks
                if ($permission === self::PERM_GROUP_INVITE_MEMBERS) {
                    return GroupConfigurationService::get(
                        GroupConfigurationService::CONFIG_ALLOW_MEMBER_INVITES
                    );
                }
                return true;
            }
        }

        // Check custom group permissions
        return self::hasCustomGroupPermission($groupId, $userId, $permission);
    }

    /**
     * Get user's role in a group
     *
     * @param int $groupId Group ID
     * @param int $userId User ID
     * @return string|null Role name or null
     */
    public static function getUserGroupRole($groupId, $userId)
    {
        try {
            // Check if owner
            $group = Database::query(
                "SELECT owner_id FROM groups WHERE id = ?",
                [$groupId]
            )->fetch();

            if ($group && $group['owner_id'] == $userId) {
                return 'owner';
            }

            // Check membership
            $member = Database::query(
                "SELECT role FROM group_members
                 WHERE group_id = ? AND user_id = ? AND status = 'active'",
                [$groupId, $userId]
            )->fetch();

            return $member ? ($member['role'] ?? 'member') : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if user is a global admin
     *
     * @param int $userId User ID
     * @return bool
     */
    public static function isGlobalAdmin($userId)
    {
        $user = User::find($userId);
        if (!$user) {
            return false;
        }

        return in_array($user['role'], ['super_admin', 'admin', 'tenant_admin']);
    }

    /**
     * Check if user can create a hub
     *
     * @param int $userId User ID
     * @return bool
     */
    public static function canCreateHub($userId)
    {
        return self::hasPermission($userId, self::PERM_CREATE_HUB);
    }

    /**
     * Check if user can edit a group
     *
     * @param int $groupId Group ID
     * @param int $userId User ID
     * @return bool
     */
    public static function canEditGroup($groupId, $userId)
    {
        return self::hasPermission($userId, self::PERM_EDIT_ANY_GROUP) ||
               self::hasGroupPermission($groupId, $userId, self::PERM_GROUP_EDIT);
    }

    /**
     * Check if user can delete a group
     *
     * @param int $groupId Group ID
     * @param int $userId User ID
     * @return bool
     */
    public static function canDeleteGroup($groupId, $userId)
    {
        return self::hasPermission($userId, self::PERM_DELETE_ANY_GROUP) ||
               self::hasGroupPermission($groupId, $userId, self::PERM_GROUP_DELETE);
    }

    /**
     * Check if user can manage members in a group
     *
     * @param int $groupId Group ID
     * @param int $userId User ID
     * @return bool
     */
    public static function canManageMembers($groupId, $userId)
    {
        return self::hasPermission($userId, self::PERM_MANAGE_MEMBERS) ||
               self::hasGroupPermission($groupId, $userId, self::PERM_GROUP_MANAGE_MEMBERS);
    }

    /**
     * Grant custom permission to user
     *
     * @param int $userId User ID
     * @param string $permission Permission name
     * @param int|null $tenantId Tenant ID
     * @return bool Success
     */
    public static function grantPermission($userId, $permission, $tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        self::ensurePermissionsTableExists();

        try {
            Database::query(
                "INSERT IGNORE INTO group_user_permissions (tenant_id, user_id, permission, granted_at)
                 VALUES (?, ?, ?, NOW())",
                [$tenantId, $userId, $permission]
            );
            return true;
        } catch (\Exception $e) {
            error_log("GroupPermissionManager: Failed to grant permission - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Revoke custom permission from user
     *
     * @param int $userId User ID
     * @param string $permission Permission name
     * @return bool Success
     */
    public static function revokePermission($userId, $permission)
    {
        $tenantId = TenantContext::getId();

        try {
            Database::query(
                "DELETE FROM group_user_permissions
                 WHERE tenant_id = ? AND user_id = ? AND permission = ?",
                [$tenantId, $userId, $permission]
            );
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if user has custom permission
     *
     * @param int $userId User ID
     * @param string $permission Permission name
     * @return bool
     */
    private static function hasCustomPermission($userId, $permission)
    {
        $tenantId = TenantContext::getId();
        self::ensurePermissionsTableExists();

        try {
            $result = Database::query(
                "SELECT 1 FROM group_user_permissions
                 WHERE tenant_id = ? AND user_id = ? AND permission = ?",
                [$tenantId, $userId, $permission]
            )->fetch();

            return (bool) $result;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Grant custom group permission to user
     *
     * @param int $groupId Group ID
     * @param int $userId User ID
     * @param string $permission Permission name
     * @return bool Success
     */
    public static function grantGroupPermission($groupId, $userId, $permission)
    {
        self::ensureGroupPermissionsTableExists();

        try {
            Database::query(
                "INSERT IGNORE INTO group_member_permissions (group_id, user_id, permission, granted_at)
                 VALUES (?, ?, ?, NOW())",
                [$groupId, $userId, $permission]
            );
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Revoke custom group permission from user
     *
     * @param int $groupId Group ID
     * @param int $userId User ID
     * @param string $permission Permission name
     * @return bool Success
     */
    public static function revokeGroupPermission($groupId, $userId, $permission)
    {
        try {
            Database::query(
                "DELETE FROM group_member_permissions
                 WHERE group_id = ? AND user_id = ? AND permission = ?",
                [$groupId, $userId, $permission]
            );
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if user has custom group permission
     *
     * @param int $groupId Group ID
     * @param int $userId User ID
     * @param string $permission Permission name
     * @return bool
     */
    private static function hasCustomGroupPermission($groupId, $userId, $permission)
    {
        self::ensureGroupPermissionsTableExists();

        try {
            $result = Database::query(
                "SELECT 1 FROM group_member_permissions
                 WHERE group_id = ? AND user_id = ? AND permission = ?",
                [$groupId, $userId, $permission]
            )->fetch();

            return (bool) $result;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get all permissions for a user
     *
     * @param int $userId User ID
     * @return array Permission list
     */
    public static function getUserPermissions($userId)
    {
        $user = User::find($userId);
        $userRole = $user['role'] ?? 'user';

        $permissions = self::$rolePermissions[$userRole] ?? [];

        // Add custom permissions
        $tenantId = TenantContext::getId();
        self::ensurePermissionsTableExists();

        try {
            $custom = Database::query(
                "SELECT permission FROM group_user_permissions
                 WHERE tenant_id = ? AND user_id = ?",
                [$tenantId, $userId]
            )->fetchAll(\PDO::FETCH_COLUMN);

            $permissions = array_merge($permissions, $custom);
        } catch (\Exception $e) {
            // Ignore
        }

        return array_unique($permissions);
    }

    /**
     * Get all group permissions for a user in a group
     *
     * @param int $groupId Group ID
     * @param int $userId User ID
     * @return array Permission list
     */
    public static function getUserGroupPermissions($groupId, $userId)
    {
        $role = self::getUserGroupRole($groupId, $userId);
        if (!$role) {
            return [];
        }

        $permissions = self::$groupRolePermissions[$role] ?? [];

        // Add custom group permissions
        self::ensureGroupPermissionsTableExists();

        try {
            $custom = Database::query(
                "SELECT permission FROM group_member_permissions
                 WHERE group_id = ? AND user_id = ?",
                [$groupId, $userId]
            )->fetchAll(\PDO::FETCH_COLUMN);

            $permissions = array_merge($permissions, $custom);
        } catch (\Exception $e) {
            // Ignore
        }

        return array_unique($permissions);
    }

    /**
     * Get permission definitions with descriptions
     *
     * @return array Permission definitions
     */
    public static function getPermissionDefinitions()
    {
        return [
            self::PERM_CREATE_GROUP => 'Create new groups',
            self::PERM_CREATE_HUB => 'Create hub groups',
            self::PERM_EDIT_ANY_GROUP => 'Edit any group',
            self::PERM_DELETE_ANY_GROUP => 'Delete any group',
            self::PERM_MODERATE_CONTENT => 'Moderate group content',
            self::PERM_MANAGE_MEMBERS => 'Manage members across all groups',
            self::PERM_MANAGE_SETTINGS => 'Manage group module settings',
            self::PERM_VIEW_ANALYTICS => 'View group analytics',
            self::PERM_APPROVE_GROUPS => 'Approve new groups',
            self::PERM_FEATURE_GROUPS => 'Feature/unfeature groups',
            self::PERM_BAN_MEMBERS => 'Ban members from groups',
            self::PERM_VIEW_AUDIT_LOG => 'View group audit logs',
            self::PERM_EXPORT_DATA => 'Export group data',
            self::PERM_MANAGE_GROUP_TYPES => 'Manage group types',
            self::PERM_OVERRIDE_LIMITS => 'Override group creation limits',
        ];
    }

    /**
     * Get group permission definitions with descriptions
     *
     * @return array Group permission definitions
     */
    public static function getGroupPermissionDefinitions()
    {
        return [
            self::PERM_GROUP_EDIT => 'Edit group details',
            self::PERM_GROUP_DELETE => 'Delete group',
            self::PERM_GROUP_MANAGE_MEMBERS => 'Add/remove members',
            self::PERM_GROUP_POST_DISCUSSION => 'Post in discussions',
            self::PERM_GROUP_INVITE_MEMBERS => 'Invite new members',
            self::PERM_GROUP_APPROVE_MEMBERS => 'Approve member requests',
            self::PERM_GROUP_KICK_MEMBERS => 'Kick members',
            self::PERM_GROUP_CHANGE_SETTINGS => 'Change group settings',
        ];
    }

    /**
     * Ensure user permissions table exists
     */
    private static function ensurePermissionsTableExists()
    {
        static $checked = false;
        if ($checked) return;

        try {
            Database::query("SELECT 1 FROM group_user_permissions LIMIT 1");
        } catch (\Exception $e) {
            Database::query("
                CREATE TABLE IF NOT EXISTS group_user_permissions (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT NOT NULL,
                    user_id INT NOT NULL,
                    permission VARCHAR(100) NOT NULL,
                    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_user_permission (tenant_id, user_id, permission),
                    INDEX idx_user (tenant_id, user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        $checked = true;
    }

    /**
     * Ensure group member permissions table exists
     */
    private static function ensureGroupPermissionsTableExists()
    {
        static $checked = false;
        if ($checked) return;

        try {
            Database::query("SELECT 1 FROM group_member_permissions LIMIT 1");
        } catch (\Exception $e) {
            Database::query("
                CREATE TABLE IF NOT EXISTS group_member_permissions (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    group_id INT NOT NULL,
                    user_id INT NOT NULL,
                    permission VARCHAR(100) NOT NULL,
                    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_member_permission (group_id, user_id, permission),
                    INDEX idx_group_user (group_id, user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        $checked = true;
    }
}
