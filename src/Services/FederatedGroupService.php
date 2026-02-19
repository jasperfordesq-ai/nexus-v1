<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * FederatedGroupService
 *
 * Handles cross-tenant group discovery and membership.
 * Users can browse and join groups from partner timebanks.
 */
class FederatedGroupService
{
    /**
     * Get groups from partner timebanks
     */
    public static function getPartnerGroups(
        int $tenantId,
        int $page = 1,
        int $perPage = 12,
        ?string $search = null,
        ?int $partnerTenantId = null
    ): array {
        $offset = ($page - 1) * $perPage;

        // Get partner tenant IDs with groups enabled
        $partners = self::getPartnerTenantIds($tenantId);
        if (empty($partners)) {
            return [
                'groups' => [],
                'total' => 0,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => 0
            ];
        }

        $placeholders = implode(',', array_fill(0, count($partners), '?'));
        $params = $partners;

        // Check if federated_visibility column exists - fallback to basic query if not
        $hasFederatedVisibility = self::columnExists('groups', 'federated_visibility');

        // Build WHERE clause - visibility='public' and federated_visibility allows listing
        if ($hasFederatedVisibility) {
            $where = "g.tenant_id IN ($placeholders) AND g.is_active = 1 AND g.visibility = 'public' AND g.federated_visibility IN ('listed', 'joinable')";
        } else {
            // Fallback: just show public groups if column doesn't exist yet
            $where = "g.tenant_id IN ($placeholders) AND g.is_active = 1 AND g.visibility = 'public'";
        }

        if ($partnerTenantId && in_array($partnerTenantId, $partners)) {
            if ($hasFederatedVisibility) {
                $where = "g.tenant_id = ? AND g.is_active = 1 AND g.visibility = 'public' AND g.federated_visibility IN ('listed', 'joinable')";
            } else {
                $where = "g.tenant_id = ? AND g.is_active = 1 AND g.visibility = 'public'";
            }
            $params = [$partnerTenantId];
        }

        if ($search) {
            $where .= " AND (g.name LIKE ? OR g.description LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Get total count
        $countSql = "SELECT COUNT(*) FROM `groups` g WHERE $where";
        $total = (int) Database::query($countSql, $params)->fetchColumn();

        // Get groups with tenant info
        $sql = "SELECT g.*,
                       t.name as tenant_name,
                       (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id AND gm.status = 'active') as member_count
                FROM `groups` g
                JOIN tenants t ON g.tenant_id = t.id
                WHERE $where
                ORDER BY g.created_at DESC
                LIMIT ? OFFSET ?";

        $params[] = $perPage;
        $params[] = $offset;

        $groups = Database::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'groups' => $groups,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / max(1, $perPage))
        ];
    }

    /**
     * Check if a column exists in a table
     */
    private static function columnExists(string $table, string $column): bool
    {
        static $cache = [];
        $key = "{$table}.{$column}";

        if (!isset($cache[$key])) {
            try {
                $result = Database::query(
                    "SELECT COUNT(*) FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
                    [$table, $column]
                )->fetchColumn();
                $cache[$key] = (int)$result > 0;
            } catch (\Exception $e) {
                $cache[$key] = false;
            }
        }

        return $cache[$key];
    }

    /**
     * Get a single group from a partner timebank
     */
    public static function getPartnerGroup(int $groupId, int $groupTenantId, int $userTenantId): ?array
    {
        // Verify partnership allows groups
        if (!self::canAccessPartnerGroups($userTenantId, $groupTenantId)) {
            return null;
        }

        // Check if federated_visibility column exists
        $hasFederatedVisibility = self::columnExists('groups', 'federated_visibility');

        if ($hasFederatedVisibility) {
            $sql = "SELECT g.*,
                           t.name as tenant_name,
                           u.first_name as creator_first_name,
                           u.last_name as creator_last_name,
                           (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id AND gm.status = 'active') as member_count
                    FROM `groups` g
                    JOIN tenants t ON g.tenant_id = t.id
                    LEFT JOIN users u ON g.owner_id = u.id
                    WHERE g.id = ? AND g.tenant_id = ? AND g.is_active = 1 AND g.visibility = 'public' AND g.federated_visibility IN ('listed', 'joinable')";
        } else {
            $sql = "SELECT g.*,
                           t.name as tenant_name,
                           u.first_name as creator_first_name,
                           u.last_name as creator_last_name,
                           (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id AND gm.status = 'active') as member_count
                    FROM `groups` g
                    JOIN tenants t ON g.tenant_id = t.id
                    LEFT JOIN users u ON g.owner_id = u.id
                    WHERE g.id = ? AND g.tenant_id = ? AND g.is_active = 1 AND g.visibility = 'public'";
        }

        $result = Database::query($sql, [$groupId, $groupTenantId])->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Join a group from a partner timebank
     */
    public static function joinGroup(int $userId, int $userTenantId, int $groupId, int $groupTenantId): array
    {
        // Verify partnership
        if (!self::canAccessPartnerGroups($userTenantId, $groupTenantId)) {
            return ['success' => false, 'error' => 'Federation partnership does not allow group membership'];
        }

        // Verify group exists and is public with federation enabled
        $hasFederatedVisibility = self::columnExists('groups', 'federated_visibility');
        if ($hasFederatedVisibility) {
            $group = Database::query(
                "SELECT * FROM `groups` WHERE id = ? AND tenant_id = ? AND is_active = 1 AND visibility = 'public' AND federated_visibility = 'joinable'",
                [$groupId, $groupTenantId]
            )->fetch(\PDO::FETCH_ASSOC);
        } else {
            $group = Database::query(
                "SELECT * FROM `groups` WHERE id = ? AND tenant_id = ? AND is_active = 1 AND visibility = 'public'",
                [$groupId, $groupTenantId]
            )->fetch(\PDO::FETCH_ASSOC);
        }

        if (!$group) {
            return ['success' => false, 'error' => 'Group not found or is not available'];
        }

        // Check if already a member
        $existingMember = Database::query(
            "SELECT * FROM group_members WHERE group_id = ? AND user_id = ? AND is_federated = 1 AND source_tenant_id = ?",
            [$groupId, $userId, $userTenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        if ($existingMember) {
            return ['success' => false, 'error' => 'You are already a member of this group'];
        }

        // Determine status - federated members auto-approved for public groups with joinable visibility
        $status = 'active';

        // Add member
        Database::query(
            "INSERT INTO group_members (group_id, user_id, role, status, is_federated, source_tenant_id, joined_at)
             VALUES (?, ?, 'member', ?, 1, ?, NOW())",
            [$groupId, $userId, $status, $userTenantId]
        );

        return [
            'success' => true,
            'status' => $status,
            'message' => $status === 'pending'
                ? 'Your membership request has been submitted for approval'
                : 'You have successfully joined the group'
        ];
    }

    /**
     * Leave a group from a partner timebank
     */
    public static function leaveGroup(int $userId, int $userTenantId, int $groupId): array
    {
        Database::query(
            "DELETE FROM group_members WHERE group_id = ? AND user_id = ? AND is_federated = 1 AND source_tenant_id = ?",
            [$groupId, $userId, $userTenantId]
        );

        return ['success' => true, 'message' => 'You have left the group'];
    }

    /**
     * Check if user is a federated member of a group
     */
    public static function isFederatedMember(int $userId, int $userTenantId, int $groupId): ?array
    {
        $result = Database::query(
            "SELECT * FROM group_members WHERE group_id = ? AND user_id = ? AND is_federated = 1 AND source_tenant_id = ?",
            [$groupId, $userId, $userTenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Get user's federated group memberships
     */
    public static function getUserFederatedGroups(int $userId, int $userTenantId): array
    {
        $sql = "SELECT g.*,
                       t.name as tenant_name,
                       gm.status as membership_status,
                       gm.joined_at,
                       (SELECT COUNT(*) FROM group_members gm2 WHERE gm2.group_id = g.id AND gm2.status = 'active') as member_count
                FROM group_members gm
                JOIN `groups` g ON gm.group_id = g.id
                JOIN tenants t ON g.tenant_id = t.id
                WHERE gm.user_id = ? AND gm.is_federated = 1 AND gm.source_tenant_id = ?
                ORDER BY gm.joined_at DESC";

        return Database::query($sql, [$userId, $userTenantId])->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Check if partnership allows groups
     */
    public static function canAccessPartnerGroups(int $tenantId, int $partnerTenantId): bool
    {
        $partnership = Database::query(
            "SELECT * FROM federation_partnerships
             WHERE ((tenant_id = ? AND partner_tenant_id = ?) OR (tenant_id = ? AND partner_tenant_id = ?))
             AND status = 'active' AND groups_enabled = 1",
            [$tenantId, $partnerTenantId, $partnerTenantId, $tenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        return $partnership !== false && $partnership !== null;
    }

    /**
     * Get partner tenant IDs with groups enabled
     */
    private static function getPartnerTenantIds(int $tenantId): array
    {
        $sql = "SELECT CASE
                    WHEN tenant_id = ? THEN partner_tenant_id
                    ELSE tenant_id
                END as partner_id
                FROM federation_partnerships
                WHERE (tenant_id = ? OR partner_tenant_id = ?)
                AND status = 'active' AND groups_enabled = 1";

        $results = Database::query($sql, [$tenantId, $tenantId, $tenantId])->fetchAll(\PDO::FETCH_ASSOC);

        return array_column($results, 'partner_id');
    }

    /**
     * Get list of partner tenants for filtering
     */
    public static function getPartnerTenants(int $tenantId): array
    {
        $sql = "SELECT t.id, t.name
                FROM federation_partnerships fp
                JOIN tenants t ON (
                    (fp.tenant_id = ? AND t.id = fp.partner_tenant_id) OR
                    (fp.partner_tenant_id = ? AND t.id = fp.tenant_id)
                )
                WHERE (fp.tenant_id = ? OR fp.partner_tenant_id = ?)
                AND fp.status = 'active' AND fp.groups_enabled = 1
                ORDER BY t.name";

        return Database::query($sql, [$tenantId, $tenantId, $tenantId, $tenantId])->fetchAll(\PDO::FETCH_ASSOC);
    }
}
