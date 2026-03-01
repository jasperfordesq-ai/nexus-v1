<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\Notification;

/**
 * SubAccountService - Family/guardian account management
 *
 * Allows a parent account to manage child accounts (families, care homes).
 * Relationship types: family, guardian, carer, organization
 *
 * Provides:
 * - Create/manage account relationships
 * - Parent can view child activity
 * - Permission management
 * - Child accounts have limited permissions
 */
class SubAccountService
{
    private static array $errors = [];

    public const RELATIONSHIP_TYPES = ['family', 'guardian', 'carer', 'organization'];

    public const DEFAULT_PERMISSIONS = [
        'can_view_activity' => true,
        'can_manage_listings' => false,
        'can_transact' => false,
        'can_view_messages' => false,
    ];

    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * Request a parent-child relationship
     *
     * @param int $parentUserId The parent/guardian user
     * @param int $childUserId The child/managed user
     * @param string $relationshipType One of RELATIONSHIP_TYPES
     * @param array $permissions Permission overrides
     * @return int|null Relationship ID
     */
    public static function requestRelationship(int $parentUserId, int $childUserId, string $relationshipType = 'family', array $permissions = []): ?int
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        // Validate relationship type
        if (!in_array($relationshipType, self::RELATIONSHIP_TYPES, true)) {
            self::$errors[] = ['code' => 'INVALID_TYPE', 'message' => 'Invalid relationship type', 'field' => 'relationship_type'];
            return null;
        }

        // Cannot create relationship with self
        if ($parentUserId === $childUserId) {
            self::$errors[] = ['code' => 'SELF_RELATIONSHIP', 'message' => 'Cannot create a relationship with yourself'];
            return null;
        }

        // Verify both users exist in same tenant
        $parent = Database::query(
            "SELECT id FROM users WHERE id = ? AND tenant_id = ?",
            [$parentUserId, $tenantId]
        )->fetch();

        $child = Database::query(
            "SELECT id, first_name, last_name FROM users WHERE id = ? AND tenant_id = ?",
            [$childUserId, $tenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$parent || !$child) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'User not found'];
            return null;
        }

        // Check for existing relationship
        $existing = Database::query(
            "SELECT id, status FROM account_relationships
             WHERE parent_user_id = ? AND child_user_id = ? AND tenant_id = ?",
            [$parentUserId, $childUserId, $tenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        if ($existing) {
            if ($existing['status'] === 'active') {
                self::$errors[] = ['code' => 'ALREADY_EXISTS', 'message' => 'Relationship already exists'];
                return (int)$existing['id'];
            }

            if ($existing['status'] === 'pending') {
                self::$errors[] = ['code' => 'PENDING', 'message' => 'Relationship request is already pending'];
                return (int)$existing['id'];
            }

            // If revoked, allow re-request
            Database::query(
                "UPDATE account_relationships SET status = 'pending', relationship_type = ?, permissions = ?, approved_at = NULL
                 WHERE id = ? AND tenant_id = ?",
                [$relationshipType, json_encode(array_merge(self::DEFAULT_PERMISSIONS, $permissions)), $existing['id'], $tenantId]
            );

            return (int)$existing['id'];
        }

        // Prevent circular: child cannot also be parent
        $circular = Database::query(
            "SELECT id FROM account_relationships
             WHERE parent_user_id = ? AND child_user_id = ? AND tenant_id = ? AND status IN ('active', 'pending')",
            [$childUserId, $parentUserId, $tenantId]
        )->fetch();

        if ($circular) {
            self::$errors[] = ['code' => 'CIRCULAR', 'message' => 'This user already manages your account'];
            return null;
        }

        $mergedPermissions = array_merge(self::DEFAULT_PERMISSIONS, $permissions);

        Database::query(
            "INSERT INTO account_relationships (parent_user_id, child_user_id, tenant_id, relationship_type, permissions, status)
             VALUES (?, ?, ?, ?, ?, 'pending')",
            [
                $parentUserId,
                $childUserId,
                $tenantId,
                $relationshipType,
                json_encode($mergedPermissions),
            ]
        );

        $relationshipId = (int)Database::lastInsertId();

        // Notify the child user
        try {
            $parentName = Database::query(
                "SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = ?",
                [$parentUserId]
            )->fetchColumn();

            $basePath = TenantContext::getSlugPrefix();
            Notification::create(
                $childUserId,
                "{$parentName} has requested to manage your account as a {$relationshipType}",
                "{$basePath}/settings/sub-accounts",
                'account'
            );
        } catch (\Exception $e) {
            // Non-critical
        }

        return $relationshipId;
    }

    /**
     * Approve a relationship request (by the child user)
     */
    public static function approveRelationship(int $childUserId, int $relationshipId): bool
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        $result = Database::query(
            "UPDATE account_relationships
             SET status = 'active', approved_at = NOW()
             WHERE id = ? AND child_user_id = ? AND tenant_id = ? AND status = 'pending'",
            [$relationshipId, $childUserId, $tenantId]
        );

        return true;
    }

    /**
     * Reject/revoke a relationship
     */
    public static function revokeRelationship(int $userId, int $relationshipId): bool
    {
        $tenantId = TenantContext::getId();

        // Either parent or child can revoke
        Database::query(
            "UPDATE account_relationships
             SET status = 'revoked'
             WHERE id = ? AND tenant_id = ? AND (parent_user_id = ? OR child_user_id = ?)",
            [$relationshipId, $tenantId, $userId, $userId]
        );

        return true;
    }

    /**
     * Get child accounts managed by a parent user
     */
    public static function getChildAccounts(int $parentUserId): array
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT ar.id as relationship_id, ar.relationship_type, ar.permissions, ar.status, ar.approved_at, ar.created_at,
                    u.id as user_id, u.first_name, u.last_name, u.avatar_url, u.email
             FROM account_relationships ar
             JOIN users u ON ar.child_user_id = u.id
             WHERE ar.parent_user_id = ? AND ar.tenant_id = ? AND ar.status IN ('active', 'pending')
             ORDER BY ar.created_at DESC",
            [$parentUserId, $tenantId]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get parent accounts that manage this user
     */
    public static function getParentAccounts(int $childUserId): array
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT ar.id as relationship_id, ar.relationship_type, ar.permissions, ar.status, ar.approved_at, ar.created_at,
                    u.id as user_id, u.first_name, u.last_name, u.avatar_url, u.email
             FROM account_relationships ar
             JOIN users u ON ar.parent_user_id = u.id
             WHERE ar.child_user_id = ? AND ar.tenant_id = ? AND ar.status IN ('active', 'pending')
             ORDER BY ar.created_at DESC",
            [$childUserId, $tenantId]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Update permissions for a relationship (parent only)
     */
    public static function updatePermissions(int $parentUserId, int $relationshipId, array $permissions): bool
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        $existing = Database::query(
            "SELECT id, permissions FROM account_relationships
             WHERE id = ? AND parent_user_id = ? AND tenant_id = ? AND status = 'active'",
            [$relationshipId, $parentUserId, $tenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$existing) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Relationship not found'];
            return false;
        }

        $currentPermissions = json_decode($existing['permissions'] ?? '{}', true) ?: [];
        $mergedPermissions = array_merge($currentPermissions, $permissions);

        Database::query(
            "UPDATE account_relationships SET permissions = ? WHERE id = ? AND tenant_id = ?",
            [json_encode($mergedPermissions), $relationshipId, $tenantId]
        );

        return true;
    }

    /**
     * Check if a parent has a specific permission for a child
     */
    public static function hasPermission(int $parentUserId, int $childUserId, string $permission): bool
    {
        $tenantId = TenantContext::getId();

        $row = Database::query(
            "SELECT permissions FROM account_relationships
             WHERE parent_user_id = ? AND child_user_id = ? AND tenant_id = ? AND status = 'active'",
            [$parentUserId, $childUserId, $tenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return false;
        }

        $perms = json_decode($row['permissions'] ?? '{}', true) ?: [];
        return !empty($perms[$permission]);
    }

    /**
     * Get activity summary for a child account (parent view)
     */
    public static function getChildActivitySummary(int $parentUserId, int $childUserId): ?array
    {
        $tenantId = TenantContext::getId();

        // Verify parent has permission
        if (!self::hasPermission($parentUserId, $childUserId, 'can_view_activity')) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You do not have permission to view this activity'];
            return null;
        }

        return MemberActivityService::getDashboardData($childUserId);
    }
}
