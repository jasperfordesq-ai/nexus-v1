<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiErrorCodes;
use Nexus\Models\ActivityLog;

/**
 * AdminGroupsApiController - V2 API for React admin group management
 *
 * Provides group listing, analytics, membership approvals, moderation,
 * and deletion for the admin panel.
 *
 * Endpoints:
 * - GET    /api/v2/admin/groups                        - List groups (paginated, filterable)
 * - GET    /api/v2/admin/groups/analytics               - Aggregate stats
 * - GET    /api/v2/admin/groups/approvals               - Pending membership requests
 * - POST   /api/v2/admin/groups/approvals/{id}/approve  - Approve membership
 * - POST   /api/v2/admin/groups/approvals/{id}/reject   - Reject membership
 * - GET    /api/v2/admin/groups/moderation              - Flagged/reported content
 * - DELETE /api/v2/admin/groups/{id}                    - Delete group
 */
class AdminGroupsApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/admin/groups
     *
     * Query params: page, limit, status, search
     */
    public function index(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $status = $_GET['status'] ?? null;
        $search = $_GET['search'] ?? null;

        try {
            $conditions = ['g.tenant_id = ?'];
            $params = [$tenantId];

            // Status filter (groups use is_active column, not status)
            if ($status === 'active') {
                $conditions[] = 'g.is_active = 1';
            } elseif ($status === 'inactive') {
                $conditions[] = 'g.is_active = 0';
            }

            // Search filter
            if ($search) {
                $conditions[] = '(g.name LIKE ? OR g.description LIKE ?)';
                $searchTerm = '%' . $search . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            $where = implode(' AND ', $conditions);

            // Total count
            $total = (int) Database::query(
                "SELECT COUNT(*) as cnt FROM `groups` g WHERE {$where}",
                $params
            )->fetch()['cnt'];

            // Paginated results with member count and creator name
            $items = Database::query(
                "SELECT g.*, CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as creator_name,
                    (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id AND gm.status = 'approved') as member_count
                 FROM `groups` g
                 LEFT JOIN users u ON g.owner_id = u.id
                 WHERE {$where}
                 ORDER BY g.created_at DESC
                 LIMIT ? OFFSET ?",
                array_merge($params, [$limit, $offset])
            )->fetchAll();

            // Format for frontend
            $formatted = array_map(function ($row) {
                return [
                    'id' => (int) $row['id'],
                    'name' => $row['name'] ?? '',
                    'description' => $row['description'] ?? '',
                    'image_url' => $row['image_url'] ?: null,
                    'visibility' => $row['visibility'] ?? 'public',
                    'status' => !empty($row['is_active']) ? 'active' : 'inactive',
                    'creator_name' => trim($row['creator_name'] ?? ''),
                    'member_count' => (int) ($row['member_count'] ?? 0),
                    'created_at' => $row['created_at'],
                ];
            }, $items);

            $this->respondWithPaginatedCollection($formatted, $total, $page, $limit);
        } catch (\Exception $e) {
            $this->respondWithError(
                'GROUPS_QUERY_ERROR',
                'Failed to load groups: ' . $e->getMessage(),
                null,
                500
            );
        }
    }

    /**
     * GET /api/v2/admin/groups/analytics
     *
     * Aggregate stats: total groups, total members, avg members per group, most active groups
     */
    public function analytics(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        try {
            // Total groups
            $totalGroups = (int) Database::query(
                "SELECT COUNT(*) as cnt FROM `groups` WHERE tenant_id = ?",
                [$tenantId]
            )->fetch()['cnt'];

            // Total approved members across all groups
            $totalMembers = (int) Database::query(
                "SELECT COUNT(*) as cnt FROM group_members gm
                 JOIN `groups` g ON gm.group_id = g.id
                 WHERE g.tenant_id = ? AND gm.status = 'approved'",
                [$tenantId]
            )->fetch()['cnt'];

            // Average members per group
            $avgMembers = $totalGroups > 0 ? round($totalMembers / $totalGroups, 1) : 0;

            // Active groups (groups with is_active = 1)
            $activeGroups = (int) Database::query(
                "SELECT COUNT(*) as cnt FROM `groups` WHERE tenant_id = ? AND is_active = 1",
                [$tenantId]
            )->fetch()['cnt'];

            // Pending approvals count
            $pendingApprovals = (int) Database::query(
                "SELECT COUNT(*) as cnt FROM group_members gm
                 JOIN `groups` g ON gm.group_id = g.id
                 WHERE g.tenant_id = ? AND gm.status = 'pending'",
                [$tenantId]
            )->fetch()['cnt'];

            // Most active groups (by member count, top 5)
            $mostActive = Database::query(
                "SELECT g.id, g.name,
                    (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id AND gm.status = 'approved') as member_count
                 FROM `groups` g
                 WHERE g.tenant_id = ?
                 ORDER BY member_count DESC
                 LIMIT 5",
                [$tenantId]
            )->fetchAll();

            $mostActiveFormatted = array_map(function ($row) {
                return [
                    'id' => (int) $row['id'],
                    'name' => $row['name'],
                    'member_count' => (int) $row['member_count'],
                ];
            }, $mostActive);

            $this->respondWithData([
                'total_groups' => $totalGroups,
                'total_members' => $totalMembers,
                'avg_members_per_group' => $avgMembers,
                'active_groups' => $activeGroups,
                'pending_approvals' => $pendingApprovals,
                'most_active_groups' => $mostActiveFormatted,
            ]);
        } catch (\Exception $e) {
            $this->respondWithError(
                'GROUPS_ANALYTICS_ERROR',
                'Failed to load group analytics: ' . $e->getMessage(),
                null,
                500
            );
        }
    }

    /**
     * GET /api/v2/admin/groups/approvals
     *
     * List pending group membership requests
     */
    public function approvals(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        try {
            $items = Database::query(
                "SELECT gm.*, g.name as group_name,
                    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user_name
                 FROM group_members gm
                 JOIN `groups` g ON gm.group_id = g.id
                 JOIN users u ON gm.user_id = u.id
                 WHERE g.tenant_id = ? AND gm.status = 'pending'
                 ORDER BY gm.created_at DESC",
                [$tenantId]
            )->fetchAll();

            $formatted = array_map(function ($row) {
                return [
                    'id' => (int) $row['id'],
                    'group_id' => (int) $row['group_id'],
                    'group_name' => $row['group_name'] ?? '',
                    'user_id' => (int) $row['user_id'],
                    'user_name' => trim($row['user_name'] ?? ''),
                    'status' => $row['status'] ?? 'pending',
                    'created_at' => $row['created_at'] ?? null,
                ];
            }, $items);

            $this->respondWithData($formatted);
        } catch (\Exception $e) {
            $this->respondWithError(
                'GROUPS_APPROVALS_ERROR',
                'Failed to load group approvals: ' . $e->getMessage(),
                null,
                500
            );
        }
    }

    /**
     * POST /api/v2/admin/groups/approvals/{id}/approve
     *
     * Approve a pending membership request
     */
    public function approveMember(int $id): void
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();

        try {
            // Verify the membership record exists and is pending
            $membership = Database::query(
                "SELECT gm.*, g.name as group_name
                 FROM group_members gm
                 JOIN `groups` g ON gm.group_id = g.id
                 WHERE gm.id = ? AND g.tenant_id = ? AND gm.status = 'pending'",
                [$id, $tenantId]
            )->fetch();

            if (!$membership) {
                $this->respondWithError(
                    ApiErrorCodes::RESOURCE_NOT_FOUND,
                    'Pending membership request not found',
                    null,
                    404
                );
                return;
            }

            Database::query(
                "UPDATE group_members SET status = 'approved', updated_at = NOW() WHERE id = ?",
                [$id]
            );

            ActivityLog::log(
                $adminId,
                'admin_approve_group_member',
                "Approved membership #{$id} for group \"{$membership['group_name']}\""
            );

            $this->respondWithData([
                'id' => $id,
                'status' => 'approved',
            ]);
        } catch (\Exception $e) {
            $this->respondWithError(
                'GROUPS_APPROVE_ERROR',
                'Failed to approve membership: ' . $e->getMessage(),
                null,
                500
            );
        }
    }

    /**
     * POST /api/v2/admin/groups/approvals/{id}/reject
     *
     * Reject a pending membership request
     */
    public function rejectMember(int $id): void
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();

        try {
            // Verify the membership record exists and is pending
            $membership = Database::query(
                "SELECT gm.*, g.name as group_name
                 FROM group_members gm
                 JOIN `groups` g ON gm.group_id = g.id
                 WHERE gm.id = ? AND g.tenant_id = ? AND gm.status = 'pending'",
                [$id, $tenantId]
            )->fetch();

            if (!$membership) {
                $this->respondWithError(
                    ApiErrorCodes::RESOURCE_NOT_FOUND,
                    'Pending membership request not found',
                    null,
                    404
                );
                return;
            }

            Database::query(
                "UPDATE group_members SET status = 'rejected', updated_at = NOW() WHERE id = ?",
                [$id]
            );

            ActivityLog::log(
                $adminId,
                'admin_reject_group_member',
                "Rejected membership #{$id} for group \"{$membership['group_name']}\""
            );

            $this->respondWithData([
                'id' => $id,
                'status' => 'rejected',
            ]);
        } catch (\Exception $e) {
            $this->respondWithError(
                'GROUPS_REJECT_ERROR',
                'Failed to reject membership: ' . $e->getMessage(),
                null,
                500
            );
        }
    }

    /**
     * GET /api/v2/admin/groups/moderation
     *
     * List groups with reported/flagged content (simplified view)
     */
    public function moderation(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        try {
            // Try to find groups referenced in the reports table
            // Fall back gracefully if the reports table schema differs
            $items = [];

            try {
                $items = Database::query(
                    "SELECT g.id, g.name, g.is_active, g.created_at,
                        COUNT(r.id) as report_count
                     FROM `groups` g
                     INNER JOIN reports r ON r.content_type = 'group' AND r.content_id = g.id
                     WHERE g.tenant_id = ? AND r.status = 'pending'
                     GROUP BY g.id, g.name, g.is_active, g.created_at
                     ORDER BY report_count DESC",
                    [$tenantId]
                )->fetchAll();
            } catch (\Exception $e) {
                // If the reports table or columns don't exist, return inactive groups
                $items = Database::query(
                    "SELECT g.id, g.name, g.is_active, g.created_at, 0 as report_count
                     FROM `groups` g
                     WHERE g.tenant_id = ? AND g.is_active = 0
                     ORDER BY g.created_at DESC",
                    [$tenantId]
                )->fetchAll();
            }

            $formatted = array_map(function ($row) {
                return [
                    'id' => (int) $row['id'],
                    'name' => $row['name'] ?? '',
                    'status' => !empty($row['is_active']) ? 'active' : 'inactive',
                    'report_count' => (int) ($row['report_count'] ?? 0),
                    'created_at' => $row['created_at'],
                ];
            }, $items);

            $this->respondWithData($formatted);
        } catch (\Exception $e) {
            $this->respondWithError(
                'GROUPS_MODERATION_ERROR',
                'Failed to load moderation data: ' . $e->getMessage(),
                null,
                500
            );
        }
    }

    /**
     * DELETE /api/v2/admin/groups/{id}
     *
     * Delete a group and its memberships
     */
    public function deleteGroup(int $id): void
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();

        try {
            $group = Database::query(
                "SELECT id, name FROM `groups` WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            )->fetch();

            if (!$group) {
                $this->respondWithError(
                    ApiErrorCodes::RESOURCE_NOT_FOUND,
                    'Group not found',
                    null,
                    404
                );
                return;
            }

            // Delete memberships first, then the group
            Database::query(
                "DELETE FROM group_members WHERE group_id = ?",
                [$id]
            );

            Database::query(
                "DELETE FROM `groups` WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );

            ActivityLog::log(
                $adminId,
                'admin_delete_group',
                "Deleted group #{$id}: {$group['name']}"
            );

            $this->respondWithData(['deleted' => true, 'id' => $id]);
        } catch (\Exception $e) {
            $this->respondWithError(
                'GROUPS_DELETE_ERROR',
                'Failed to delete group: ' . $e->getMessage(),
                null,
                500
            );
        }
    }
}
