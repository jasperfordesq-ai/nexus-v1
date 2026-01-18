<?php

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\AuditLogService;

/**
 * OrgMember Model
 *
 * Handles organization membership with role-based access control.
 * Roles: owner, admin, member
 * Status: active, pending, invited, removed
 */
class OrgMember
{
    /**
     * Add a member to an organization
     */
    public static function add($organizationId, $userId, $role = 'member', $status = 'active', $addedBy = null)
    {
        $tenantId = TenantContext::getId();

        Database::query(
            "INSERT INTO org_members (tenant_id, organization_id, user_id, role, status)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE role = VALUES(role), status = VALUES(status), updated_at = NOW()",
            [$tenantId, $organizationId, $userId, $role, $status]
        );

        $insertId = Database::getInstance()->lastInsertId();

        // Audit log if someone is adding (not self-registration)
        if ($addedBy && $addedBy != $userId) {
            AuditLogService::logMemberAdded($organizationId, $addedBy, $userId, $role);
        }

        return $insertId;
    }

    /**
     * Remove a member from an organization (soft remove)
     */
    public static function remove($organizationId, $userId, $removedBy = null, $reason = '')
    {
        $tenantId = TenantContext::getId();

        Database::query(
            "UPDATE org_members SET status = 'removed', updated_at = NOW()
             WHERE tenant_id = ? AND organization_id = ? AND user_id = ?",
            [$tenantId, $organizationId, $userId]
        );

        // Audit log
        if ($removedBy) {
            AuditLogService::logMemberRemoved($organizationId, $removedBy, $userId, $reason);
        }
    }

    /**
     * Update member role
     */
    public static function updateRole($organizationId, $userId, $role, $changedBy = null)
    {
        $tenantId = TenantContext::getId();

        // Get old role for audit
        $oldRole = self::getRole($organizationId, $userId);

        Database::query(
            "UPDATE org_members SET role = ?, updated_at = NOW()
             WHERE tenant_id = ? AND organization_id = ? AND user_id = ?",
            [$role, $tenantId, $organizationId, $userId]
        );

        // Audit log
        if ($changedBy && $oldRole && $oldRole !== $role) {
            AuditLogService::logRoleChanged($organizationId, $changedBy, $userId, $oldRole, $role);
        }
    }

    /**
     * Update member status
     */
    public static function updateStatus($organizationId, $userId, $status)
    {
        $tenantId = TenantContext::getId();

        Database::query(
            "UPDATE org_members SET status = ?, updated_at = NOW()
             WHERE tenant_id = ? AND organization_id = ? AND user_id = ?",
            [$status, $tenantId, $organizationId, $userId]
        );
    }

    /**
     * Check if user is the owner of the organization
     */
    public static function isOwner($organizationId, $userId)
    {
        $tenantId = TenantContext::getId();

        $role = Database::query(
            "SELECT role FROM org_members
             WHERE tenant_id = ? AND organization_id = ? AND user_id = ? AND status = 'active'",
            [$tenantId, $organizationId, $userId]
        )->fetchColumn();

        return $role === 'owner';
    }

    /**
     * Check if user is an admin (owner or admin role)
     */
    public static function isAdmin($organizationId, $userId)
    {
        // Site admins always have access
        $userRole = Database::query(
            "SELECT role FROM users WHERE id = ?",
            [$userId]
        )->fetchColumn();

        if (in_array($userRole, ['super_admin', 'admin', 'tenant_admin'])) {
            return true;
        }

        $tenantId = TenantContext::getId();

        $role = Database::query(
            "SELECT role FROM org_members
             WHERE tenant_id = ? AND organization_id = ? AND user_id = ? AND status = 'active'",
            [$tenantId, $organizationId, $userId]
        )->fetchColumn();

        return in_array($role, ['owner', 'admin']);
    }

    /**
     * Check if user is a member (any active role)
     */
    public static function isMember($organizationId, $userId)
    {
        $tenantId = TenantContext::getId();

        $result = Database::query(
            "SELECT id FROM org_members
             WHERE tenant_id = ? AND organization_id = ? AND user_id = ? AND status = 'active'",
            [$tenantId, $organizationId, $userId]
        )->fetch();

        return (bool) $result;
    }

    /**
     * Get member's role in an organization
     */
    public static function getRole($organizationId, $userId)
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT role FROM org_members
             WHERE tenant_id = ? AND organization_id = ? AND user_id = ? AND status = 'active'",
            [$tenantId, $organizationId, $userId]
        )->fetchColumn();
    }

    /**
     * Get all active members of an organization
     */
    public static function getMembers($organizationId)
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT om.*, u.first_name, u.last_name, u.email, u.avatar_url,
                    CONCAT(u.first_name, ' ', u.last_name) as display_name
             FROM org_members om
             JOIN users u ON om.user_id = u.id
             WHERE om.tenant_id = ? AND om.organization_id = ? AND om.status = 'active'
             ORDER BY FIELD(om.role, 'owner', 'admin', 'member'), u.first_name",
            [$tenantId, $organizationId]
        )->fetchAll();
    }

    /**
     * Get admins and owners of an organization
     */
    public static function getAdmins($organizationId)
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT om.*, u.first_name, u.last_name, u.email, u.avatar_url,
                    CONCAT(u.first_name, ' ', u.last_name) as display_name
             FROM org_members om
             JOIN users u ON om.user_id = u.id
             WHERE om.tenant_id = ? AND om.organization_id = ?
             AND om.status = 'active' AND om.role IN ('owner', 'admin')
             ORDER BY FIELD(om.role, 'owner', 'admin'), u.first_name",
            [$tenantId, $organizationId]
        )->fetchAll();
    }

    /**
     * Get pending membership requests
     */
    public static function getPendingRequests($organizationId)
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT om.*, u.first_name, u.last_name, u.email, u.avatar_url,
                    CONCAT(u.first_name, ' ', u.last_name) as display_name
             FROM org_members om
             JOIN users u ON om.user_id = u.id
             WHERE om.tenant_id = ? AND om.organization_id = ? AND om.status = 'pending'
             ORDER BY om.created_at DESC",
            [$tenantId, $organizationId]
        )->fetchAll();
    }

    /**
     * Get all organizations a user is a member of
     */
    public static function getUserOrganizations($userId)
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT vo.*, om.role as member_role
             FROM org_members om
             JOIN vol_organizations vo ON om.organization_id = vo.id
             WHERE om.tenant_id = ? AND om.user_id = ? AND om.status = 'active'
             ORDER BY vo.name",
            [$tenantId, $userId]
        )->fetchAll();
    }

    /**
     * Count active members in an organization
     */
    public static function countMembers($organizationId)
    {
        $tenantId = TenantContext::getId();

        return (int) Database::query(
            "SELECT COUNT(*) FROM org_members
             WHERE tenant_id = ? AND organization_id = ? AND status = 'active'",
            [$tenantId, $organizationId]
        )->fetchColumn();
    }

    /**
     * Initialize owner as first member when org is created
     */
    public static function initializeOwner($organizationId, $ownerId)
    {
        return self::add($organizationId, $ownerId, 'owner', 'active');
    }

    /**
     * Get a specific member record (including non-active statuses)
     */
    public static function getMemberRecord($organizationId, $userId)
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT * FROM org_members
             WHERE tenant_id = ? AND organization_id = ? AND user_id = ?",
            [$tenantId, $organizationId, $userId]
        )->fetch();
    }

    /**
     * Count pending membership requests for an organization
     */
    public static function countPendingRequests($organizationId)
    {
        $tenantId = TenantContext::getId();

        return (int) Database::query(
            "SELECT COUNT(*) FROM org_members
             WHERE tenant_id = ? AND organization_id = ? AND status = 'pending'",
            [$tenantId, $organizationId]
        )->fetchColumn();
    }

    /**
     * Alias for getAdmins - get all owners and admins for notifications
     */
    public static function getAdminsAndOwners($organizationId)
    {
        return self::getAdmins($organizationId);
    }

    /**
     * Transfer ownership from current owner to another member
     * The current owner becomes an admin after transfer
     */
    public static function transferOwnership($organizationId, $currentOwnerId, $newOwnerId)
    {
        $tenantId = TenantContext::getId();

        // Verify current owner is actually the owner
        if (!self::isOwner($organizationId, $currentOwnerId)) {
            return ['success' => false, 'message' => 'Only the owner can transfer ownership'];
        }

        // Verify new owner is an active member
        if (!self::isMember($organizationId, $newOwnerId)) {
            return ['success' => false, 'message' => 'New owner must be an active member'];
        }

        // Cannot transfer to yourself
        if ($currentOwnerId == $newOwnerId) {
            return ['success' => false, 'message' => 'Cannot transfer ownership to yourself'];
        }

        try {
            // Start transaction
            Database::getInstance()->beginTransaction();

            // Demote current owner to admin
            Database::query(
                "UPDATE org_members SET role = 'admin', updated_at = NOW()
                 WHERE tenant_id = ? AND organization_id = ? AND user_id = ?",
                [$tenantId, $organizationId, $currentOwnerId]
            );

            // Promote new owner
            Database::query(
                "UPDATE org_members SET role = 'owner', updated_at = NOW()
                 WHERE tenant_id = ? AND organization_id = ? AND user_id = ?",
                [$tenantId, $organizationId, $newOwnerId]
            );

            // Also update the vol_organizations.user_id to reflect new owner
            Database::query(
                "UPDATE vol_organizations SET user_id = ?, updated_at = NOW()
                 WHERE id = ? AND tenant_id = ?",
                [$newOwnerId, $organizationId, $tenantId]
            );

            Database::getInstance()->commit();

            // Audit log
            AuditLogService::logOwnershipTransfer($organizationId, $currentOwnerId, $newOwnerId);

            return ['success' => true, 'message' => 'Ownership transferred successfully'];
        } catch (\Throwable $e) {
            Database::getInstance()->rollBack();
            error_log("[OrgMember::transferOwnership] Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to transfer ownership'];
        }
    }

    /**
     * Get the current owner of an organization
     */
    public static function getOwner($organizationId)
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT om.*, u.first_name, u.last_name, u.email, u.avatar_url,
                    CONCAT(u.first_name, ' ', u.last_name) as display_name
             FROM org_members om
             JOIN users u ON om.user_id = u.id
             WHERE om.tenant_id = ? AND om.organization_id = ? AND om.role = 'owner' AND om.status = 'active'
             LIMIT 1",
            [$tenantId, $organizationId]
        )->fetch();
    }

    /**
     * Get organization roles (owner/admin only) for a batch of user IDs
     * Returns array keyed by user_id with array of orgs they lead
     */
    public static function getLeadershipRolesForUsers(array $userIds)
    {
        if (empty($userIds)) {
            return [];
        }

        $tenantId = TenantContext::getId();
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));

        $params = [$tenantId];
        $params = array_merge($params, $userIds);

        $rows = Database::query(
            "SELECT om.user_id, om.role, vo.id as org_id, vo.name as org_name
             FROM org_members om
             JOIN vol_organizations vo ON om.organization_id = vo.id AND vo.tenant_id = om.tenant_id
             WHERE om.tenant_id = ?
               AND om.user_id IN ($placeholders)
               AND om.role IN ('owner', 'admin')
               AND om.status = 'active'
               AND vo.status = 'approved'
             ORDER BY om.role ASC, vo.name ASC",
            $params
        )->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $userId = $row['user_id'];
            if (!isset($result[$userId])) {
                $result[$userId] = [];
            }
            $result[$userId][] = [
                'org_id' => $row['org_id'],
                'org_name' => $row['org_name'],
                'role' => $row['role'],
            ];
        }

        return $result;
    }
}
