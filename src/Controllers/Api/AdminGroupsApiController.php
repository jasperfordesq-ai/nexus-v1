<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

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
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $status = $_GET['status'] ?? null;
        $search = $_GET['search'] ?? null;

        try {
            $conditions = [];
            $params = [];

            // Tenant scoping: defaults to current tenant; super admins can pass ?tenant_id=all for cross-tenant
            $effectiveTenantId = $this->resolveAdminTenantFilter($isSuperAdmin, $tenantId);
            if ($effectiveTenantId !== null) {
                $conditions[] = 'g.tenant_id = ?';
                $params[] = $effectiveTenantId;
            }

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

            $where = !empty($conditions) ? implode(' AND ', $conditions) : '1=1';

            // Total count
            $total = (int) Database::query(
                "SELECT COUNT(*) as cnt FROM `groups` g WHERE {$where}",
                $params
            )->fetch()['cnt'];

            // Paginated results with member count, creator name, and tenant name
            $items = Database::query(
                "SELECT g.*, CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as creator_name,
                    t.name as tenant_name,
                    (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id AND gm.status = 'approved') as member_count
                 FROM `groups` g
                 LEFT JOIN users u ON g.owner_id = u.id
                 LEFT JOIN tenants t ON g.tenant_id = t.id
                 WHERE {$where}
                 ORDER BY g.created_at DESC
                 LIMIT ? OFFSET ?",
                array_merge($params, [$limit, $offset])
            )->fetchAll();

            // Format for frontend
            $formatted = array_map(function ($row) {
                return [
                    'id' => (int) $row['id'],
                    'tenant_id' => (int) $row['tenant_id'],
                    'tenant_name' => $row['tenant_name'] ?? 'Unknown',
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
            return;
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
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        // Determine scope: defaults to current tenant; super admins can pass ?tenant_id=all for cross-tenant
        $effectiveTenantId = $this->resolveAdminTenantFilter($isSuperAdmin, $tenantId);
        $tenantCondition = $effectiveTenantId !== null ? 'tenant_id = ?' : '1=1';
        $tenantJoinCondition = $effectiveTenantId !== null ? 'g.tenant_id = ?' : '1=1';
        $tenantParams = $effectiveTenantId !== null ? [$effectiveTenantId] : [];

        try {
            // Total groups
            $totalGroups = (int) Database::query(
                "SELECT COUNT(*) as cnt FROM `groups` WHERE {$tenantCondition}",
                $tenantParams
            )->fetch()['cnt'];

            // Total approved members across all groups
            $totalMembers = (int) Database::query(
                "SELECT COUNT(*) as cnt FROM group_members gm
                 JOIN `groups` g ON gm.group_id = g.id
                 WHERE {$tenantJoinCondition} AND gm.status = 'approved'",
                $tenantParams
            )->fetch()['cnt'];

            // Average members per group
            $avgMembers = $totalGroups > 0 ? round($totalMembers / $totalGroups, 1) : 0;

            // Active groups (groups with is_active = 1)
            $activeGroups = (int) Database::query(
                "SELECT COUNT(*) as cnt FROM `groups` WHERE {$tenantCondition} AND is_active = 1",
                $tenantParams
            )->fetch()['cnt'];

            // Pending approvals count
            $pendingApprovals = (int) Database::query(
                "SELECT COUNT(*) as cnt FROM group_members gm
                 JOIN `groups` g ON gm.group_id = g.id
                 WHERE {$tenantJoinCondition} AND gm.status = 'pending'",
                $tenantParams
            )->fetch()['cnt'];

            // Most active groups (by member count, top 5)
            $mostActive = Database::query(
                "SELECT g.id, g.name, t.name as tenant_name,
                    (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id AND gm.status = 'approved') as member_count
                 FROM `groups` g
                 LEFT JOIN tenants t ON g.tenant_id = t.id
                 WHERE {$tenantJoinCondition}
                 ORDER BY member_count DESC
                 LIMIT 5",
                $tenantParams
            )->fetchAll();

            $mostActiveFormatted = array_map(function ($row) {
                return [
                    'id' => (int) $row['id'],
                    'name' => $row['name'],
                    'tenant_name' => $row['tenant_name'] ?? 'Unknown',
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
            return;
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
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        try {
            $conditions = ['gm.status = \'pending\''];
            $params = [];

            // Tenant scoping: defaults to current tenant; super admins can pass ?tenant_id=all for cross-tenant
            $effectiveTenantId = $this->resolveAdminTenantFilter($isSuperAdmin, $tenantId);
            if ($effectiveTenantId !== null) {
                $conditions[] = 'g.tenant_id = ?';
                $params[] = $effectiveTenantId;
            }

            $where = implode(' AND ', $conditions);

            $items = Database::query(
                "SELECT gm.*, g.name as group_name, t.name as tenant_name, g.tenant_id,
                    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user_name
                 FROM group_members gm
                 JOIN `groups` g ON gm.group_id = g.id
                 JOIN users u ON gm.user_id = u.id
                 LEFT JOIN tenants t ON g.tenant_id = t.id
                 WHERE {$where}
                 ORDER BY gm.created_at DESC",
                $params
            )->fetchAll();

            $formatted = array_map(function ($row) {
                return [
                    'id' => (int) $row['id'],
                    'group_id' => (int) $row['group_id'],
                    'group_name' => $row['group_name'] ?? '',
                    'tenant_id' => (int) $row['tenant_id'],
                    'tenant_name' => $row['tenant_name'] ?? 'Unknown',
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
            return;
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
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        try {
            // Verify the membership record exists and is pending
            // Super admins can approve memberships from any tenant
            if ($isSuperAdmin) {
                $membership = Database::query(
                    "SELECT gm.*, g.name as group_name, g.tenant_id
                     FROM group_members gm
                     JOIN `groups` g ON gm.group_id = g.id
                     WHERE gm.id = ? AND gm.status = 'pending'",
                    [$id]
                )->fetch();
            } else {
                $membership = Database::query(
                    "SELECT gm.*, g.name as group_name, g.tenant_id
                     FROM group_members gm
                     JOIN `groups` g ON gm.group_id = g.id
                     WHERE gm.id = ? AND g.tenant_id = ? AND gm.status = 'pending'",
                    [$id, $tenantId]
                )->fetch();
            }

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

            $memberTenantId = (int) $membership['tenant_id'];
            ActivityLog::log(
                $adminId,
                'admin_approve_group_member',
                "Approved membership #{$id} for group \"{$membership['group_name']}\"" . ($isSuperAdmin ? " (tenant {$memberTenantId})" : '')
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
            return;
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
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        try {
            // Verify the membership record exists and is pending
            // Super admins can reject memberships from any tenant
            if ($isSuperAdmin) {
                $membership = Database::query(
                    "SELECT gm.*, g.name as group_name, g.tenant_id
                     FROM group_members gm
                     JOIN `groups` g ON gm.group_id = g.id
                     WHERE gm.id = ? AND gm.status = 'pending'",
                    [$id]
                )->fetch();
            } else {
                $membership = Database::query(
                    "SELECT gm.*, g.name as group_name, g.tenant_id
                     FROM group_members gm
                     JOIN `groups` g ON gm.group_id = g.id
                     WHERE gm.id = ? AND g.tenant_id = ? AND gm.status = 'pending'",
                    [$id, $tenantId]
                )->fetch();
            }

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

            $memberTenantId = (int) $membership['tenant_id'];
            ActivityLog::log(
                $adminId,
                'admin_reject_group_member',
                "Rejected membership #{$id} for group \"{$membership['group_name']}\"" . ($isSuperAdmin ? " (tenant {$memberTenantId})" : '')
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
            return;
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
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        // Determine tenant scoping: defaults to current tenant; super admins can pass ?tenant_id=all for cross-tenant
        $effectiveTenantId = $this->resolveAdminTenantFilter($isSuperAdmin, $tenantId);
        $tenantCondition = $effectiveTenantId !== null ? 'g.tenant_id = ?' : '1=1';
        $tenantParams = $effectiveTenantId !== null ? [$effectiveTenantId] : [];

        try {
            // Try to find groups referenced in the reports table
            // Fall back gracefully if the reports table schema differs
            $items = [];

            try {
                $items = Database::query(
                    "SELECT g.id, g.name, g.is_active, g.created_at, g.tenant_id, t.name as tenant_name,
                        COUNT(r.id) as report_count
                     FROM `groups` g
                     INNER JOIN reports r ON r.content_type = 'group' AND r.content_id = g.id
                     LEFT JOIN tenants t ON g.tenant_id = t.id
                     WHERE {$tenantCondition} AND r.status = 'pending'
                     GROUP BY g.id, g.name, g.is_active, g.created_at, g.tenant_id, t.name
                     ORDER BY report_count DESC",
                    $tenantParams
                )->fetchAll();
            } catch (\Exception $e) {
                // If the reports table or columns don't exist, return inactive groups
                $items = Database::query(
                    "SELECT g.id, g.name, g.is_active, g.created_at, g.tenant_id, t.name as tenant_name, 0 as report_count
                     FROM `groups` g
                     LEFT JOIN tenants t ON g.tenant_id = t.id
                     WHERE {$tenantCondition} AND g.is_active = 0
                     ORDER BY g.created_at DESC",
                    $tenantParams
                )->fetchAll();
            }

            $formatted = array_map(function ($row) {
                return [
                    'id' => (int) $row['id'],
                    'name' => $row['name'] ?? '',
                    'tenant_id' => (int) $row['tenant_id'],
                    'tenant_name' => $row['tenant_name'] ?? 'Unknown',
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
            return;
        }
    }

    /**
     * PUT /api/v2/admin/groups/{id}/status
     *
     * Update a group's status (activate/deactivate)
     */
    public function updateStatus(int $id): void
    {
        $adminId = $this->requireAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();
        $input = $this->getAllInput();

        $newStatus = $input['status'] ?? null;
        if (!in_array($newStatus, ['active', 'inactive'])) {
            $this->respondWithError('VALIDATION_ERROR', 'Status must be "active" or "inactive"', 'status', 422);
            return;
        }

        try {
            // Super admins can update groups from any tenant
            if ($isSuperAdmin) {
                $group = Database::query(
                    "SELECT id, name, tenant_id FROM `groups` WHERE id = ?",
                    [$id]
                )->fetch();
            } else {
                $group = Database::query(
                    "SELECT id, name, tenant_id FROM `groups` WHERE id = ? AND tenant_id = ?",
                    [$id, $tenantId]
                )->fetch();
            }

            if (!$group) {
                $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Group not found', null, 404);
                return;
            }

            // Use the record's own tenant_id for the UPDATE
            $groupTenantId = (int) $group['tenant_id'];
            $isActive = $newStatus === 'active' ? 1 : 0;
            Database::query(
                "UPDATE `groups` SET is_active = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?",
                [$isActive, $id, $groupTenantId]
            );

            ActivityLog::log(
                $adminId,
                'admin_update_group_status',
                "Set group #{$id} \"{$group['name']}\" to {$newStatus}" . ($isSuperAdmin ? " (tenant {$groupTenantId})" : '')
            );

            $this->respondWithData(['id' => $id, 'status' => $newStatus]);
        } catch (\Exception $e) {
            $this->respondWithError('GROUPS_STATUS_ERROR', 'Failed to update group status: ' . $e->getMessage(), null, 500);
            return;
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
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        try {
            // Super admins can delete groups from any tenant
            if ($isSuperAdmin) {
                $group = Database::query(
                    "SELECT id, name, tenant_id FROM `groups` WHERE id = ?",
                    [$id]
                )->fetch();
            } else {
                $group = Database::query(
                    "SELECT id, name, tenant_id FROM `groups` WHERE id = ? AND tenant_id = ?",
                    [$id, $tenantId]
                )->fetch();
            }

            if (!$group) {
                $this->respondWithError(
                    ApiErrorCodes::RESOURCE_NOT_FOUND,
                    'Group not found',
                    null,
                    404
                );
                return;
            }

            // Use the record's own tenant_id for safe DELETE
            $groupTenantId = (int) $group['tenant_id'];

            // Delete memberships first, then the group
            Database::query(
                "DELETE FROM group_members WHERE group_id = ? AND tenant_id = ?",
                [$id, $groupTenantId]
            );

            Database::query(
                "DELETE FROM `groups` WHERE id = ? AND tenant_id = ?",
                [$id, $groupTenantId]
            );

            ActivityLog::log(
                $adminId,
                'admin_delete_group',
                "Deleted group #{$id}: {$group['name']}" . ($isSuperAdmin ? " (tenant {$groupTenantId})" : '')
            );

            $this->respondWithData(['deleted' => true, 'id' => $id]);
        } catch (\Exception $e) {
            $this->respondWithError(
                'GROUPS_DELETE_ERROR',
                'Failed to delete group: ' . $e->getMessage(),
                null,
                500
            );
            return;
        }
    }

    // ==================== GROUP TYPES ====================

    /**
     * GET /api/v2/admin/groups/types
     *
     * List all group types with member/policy counts
     */
    public function getGroupTypes(): void
    {
        $this->requireAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        // Determine scope: defaults to current tenant; super admins can pass ?tenant_id=all for cross-tenant
        $scopeTenantId = $this->resolveAdminTenantFilter($isSuperAdmin, $tenantId);
        $tenantCondition = $scopeTenantId !== null ? 'gt.tenant_id = ?' : '1=1';
        $tenantParams = $scopeTenantId !== null ? [$scopeTenantId] : [];

        try {
            if ($scopeTenantId !== null) {
                $types = Database::query(
                    "SELECT gt.*, t.name as tenant_name,
                        (SELECT COUNT(*) FROM `groups` g WHERE g.type_id = gt.id AND g.tenant_id = ?) as member_count
                     FROM group_types gt
                     LEFT JOIN tenants t ON gt.tenant_id = t.id
                     WHERE {$tenantCondition}
                     ORDER BY gt.sort_order ASC, gt.name ASC",
                    array_merge([$scopeTenantId], $tenantParams)
                )->fetchAll();
            } else {
                // Super admin, no filter — show all types across all tenants
                $types = Database::query(
                    "SELECT gt.*, t.name as tenant_name,
                        (SELECT COUNT(*) FROM `groups` g WHERE g.type_id = gt.id) as member_count
                     FROM group_types gt
                     LEFT JOIN tenants t ON gt.tenant_id = t.id
                     ORDER BY gt.sort_order ASC, gt.name ASC"
                )->fetchAll();
            }

            // Get policy counts for each type
            $formatted = array_map(function ($row) use ($scopeTenantId) {
                if ($scopeTenantId !== null) {
                    $policyCount = (int) Database::query(
                        "SELECT COUNT(*) as cnt FROM group_policies WHERE tenant_id = ?",
                        [$scopeTenantId]
                    )->fetch()['cnt'];
                } else {
                    $policyCount = (int) Database::query(
                        "SELECT COUNT(*) as cnt FROM group_policies WHERE tenant_id = ?",
                        [(int) $row['tenant_id']]
                    )->fetch()['cnt'];
                }

                return [
                    'id' => (int) $row['id'],
                    'tenant_id' => (int) $row['tenant_id'],
                    'tenant_name' => $row['tenant_name'] ?? 'Unknown',
                    'name' => $row['name'],
                    'description' => $row['description'] ?? '',
                    'icon' => $row['icon'] ?? 'fa-layer-group',
                    'color' => $row['color'] ?? '#6366f1',
                    'member_count' => (int) $row['member_count'],
                    'policy_count' => $policyCount,
                    'created_at' => $row['created_at'],
                ];
            }, $types);

            $this->respondWithData($formatted);
        } catch (\Exception $e) {
            $this->respondWithError(
                'GROUP_TYPES_ERROR',
                'Failed to load group types: ' . $e->getMessage(),
                null,
                500
            );
            return;
        }
    }

    /**
     * POST /api/v2/admin/groups/types
     *
     * Create a new group type
     */
    public function createGroupType(): void
    {
        $adminId = $this->requireAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();
        $input = $this->getAllInput();

        $name = trim($input['name'] ?? '');
        if (empty($name)) {
            $this->respondWithError('VALIDATION_ERROR', 'Name is required', 'name', 422);
            return;
        }

        // Super admins can specify a target tenant_id; otherwise use current tenant
        $targetTenantId = ($isSuperAdmin && isset($input['tenant_id'])) ? (int) $input['tenant_id'] : $tenantId;

        try {
            Database::query(
                "INSERT INTO group_types (tenant_id, name, slug, description, icon, color, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())",
                [
                    $targetTenantId,
                    $name,
                    \Nexus\Helpers\TextHelper::slugify($name),
                    $input['description'] ?? null,
                    $input['icon'] ?? 'fa-layer-group',
                    $input['color'] ?? '#6366f1'
                ]
            );

            $id = Database::lastInsertId();

            ActivityLog::log(
                $adminId,
                'admin_create_group_type',
                "Created group type: {$name}" . ($isSuperAdmin ? " (tenant {$targetTenantId})" : '')
            );

            $this->respondWithData(['id' => $id, 'name' => $name], 201);
        } catch (\Exception $e) {
            $this->respondWithError(
                'GROUP_TYPE_CREATE_ERROR',
                'Failed to create group type: ' . $e->getMessage(),
                null,
                500
            );
            return;
        }
    }

    /**
     * PUT /api/v2/admin/groups/types/{id}
     *
     * Update a group type
     */
    public function updateGroupType(int $id): void
    {
        $adminId = $this->requireAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();
        $input = $this->getAllInput();

        try {
            // Super admins can update group types from any tenant
            if ($isSuperAdmin) {
                $existing = Database::query(
                    "SELECT id, name, tenant_id FROM group_types WHERE id = ?",
                    [$id]
                )->fetch();
            } else {
                $existing = Database::query(
                    "SELECT id, name, tenant_id FROM group_types WHERE id = ? AND tenant_id = ?",
                    [$id, $tenantId]
                )->fetch();
            }

            if (!$existing) {
                $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Group type not found', null, 404);
                return;
            }

            // Use the record's own tenant_id for safe UPDATE
            $recordTenantId = (int) $existing['tenant_id'];
            $name = trim($input['name'] ?? $existing['name']);
            Database::query(
                "UPDATE group_types
                 SET name = ?, description = ?, icon = ?, color = ?, updated_at = NOW()
                 WHERE id = ? AND tenant_id = ?",
                [
                    $name,
                    $input['description'] ?? null,
                    $input['icon'] ?? 'fa-layer-group',
                    $input['color'] ?? '#6366f1',
                    $id,
                    $recordTenantId
                ]
            );

            ActivityLog::log(
                $adminId,
                'admin_update_group_type',
                "Updated group type #{$id}: {$name}" . ($isSuperAdmin ? " (tenant {$recordTenantId})" : '')
            );

            $this->respondWithData(['id' => $id, 'name' => $name]);
        } catch (\Exception $e) {
            $this->respondWithError(
                'GROUP_TYPE_UPDATE_ERROR',
                'Failed to update group type: ' . $e->getMessage(),
                null,
                500
            );
            return;
        }
    }

    /**
     * DELETE /api/v2/admin/groups/types/{id}
     *
     * Delete a group type
     */
    public function deleteGroupType(int $id): void
    {
        $adminId = $this->requireAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        try {
            // Super admins can delete group types from any tenant
            if ($isSuperAdmin) {
                $type = Database::query(
                    "SELECT id, name, tenant_id FROM group_types WHERE id = ?",
                    [$id]
                )->fetch();
            } else {
                $type = Database::query(
                    "SELECT id, name, tenant_id FROM group_types WHERE id = ? AND tenant_id = ?",
                    [$id, $tenantId]
                )->fetch();
            }

            if (!$type) {
                $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Group type not found', null, 404);
                return;
            }

            // Use the record's own tenant_id for safe operations
            $recordTenantId = (int) $type['tenant_id'];

            // Check if any groups use this type
            $count = (int) Database::query(
                "SELECT COUNT(*) as cnt FROM `groups` WHERE type_id = ? AND tenant_id = ?",
                [$id, $recordTenantId]
            )->fetch()['cnt'];

            if ($count > 0) {
                $this->respondWithError(
                    'GROUP_TYPE_IN_USE',
                    "Cannot delete group type: {$count} groups are using it",
                    null,
                    422
                );
                return;
            }

            Database::query(
                "DELETE FROM group_types WHERE id = ? AND tenant_id = ?",
                [$id, $recordTenantId]
            );

            ActivityLog::log(
                $adminId,
                'admin_delete_group_type',
                "Deleted group type #{$id}: {$type['name']}" . ($isSuperAdmin ? " (tenant {$recordTenantId})" : '')
            );

            $this->respondWithData(['deleted' => true, 'id' => $id]);
        } catch (\Exception $e) {
            $this->respondWithError(
                'GROUP_TYPE_DELETE_ERROR',
                'Failed to delete group type: ' . $e->getMessage(),
                null,
                500
            );
            return;
        }
    }

    // ==================== GROUP POLICIES ====================

    /**
     * GET /api/v2/admin/groups/types/{id}/policies
     *
     * Get all policies for a group type (tenant-level)
     */
    public function getPolicies(int $typeId): void
    {
        $this->requireAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        // Determine scope — policies are always tenant-scoped, so fall back to current tenant if cross-tenant requested
        $scopeTenantId = $this->resolveAdminTenantFilter($isSuperAdmin, $tenantId) ?? $tenantId;

        try {
            $policies = \Nexus\Services\GroupPolicyRepository::getAllPolicies($scopeTenantId);

            // Transform to frontend format
            $formatted = [];
            foreach ($policies as $category => $categoryPolicies) {
                foreach ($categoryPolicies as $key => $policy) {
                    $formatted[] = [
                        'category' => $category,
                        'key' => $key,
                        'value' => $policy['value'],
                        'type' => $policy['type'],
                        'label' => ucwords(str_replace('_', ' ', $key)),
                        'description' => $policy['description'],
                    ];
                }
            }

            $this->respondWithData($formatted);
        } catch (\Exception $e) {
            $this->respondWithError(
                'POLICIES_ERROR',
                'Failed to load policies: ' . $e->getMessage(),
                null,
                500
            );
            return;
        }
    }

    /**
     * PUT /api/v2/admin/groups/types/{id}/policies
     *
     * Set a policy value
     */
    public function setPolicy(int $typeId): void
    {
        $adminId = $this->requireAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();
        $input = $this->getAllInput();

        $key = $input['key'] ?? null;
        $value = $input['value'] ?? null;

        if (empty($key)) {
            $this->respondWithError('VALIDATION_ERROR', 'Policy key is required', 'key', 422);
            return;
        }

        // Super admins can specify a target tenant_id; otherwise use current tenant
        $targetTenantId = ($isSuperAdmin && isset($input['tenant_id'])) ? (int) $input['tenant_id'] : $tenantId;

        try {
            $category = $input['category'] ?? \Nexus\Services\GroupPolicyRepository::CATEGORY_FEATURES;
            $type = $input['type'] ?? \Nexus\Services\GroupPolicyRepository::TYPE_STRING;

            \Nexus\Services\GroupPolicyRepository::setPolicy(
                $key,
                $value,
                $category,
                $type,
                $input['description'] ?? null,
                $targetTenantId
            );

            ActivityLog::log(
                $adminId,
                'admin_set_group_policy',
                "Set policy {$key} = " . json_encode($value) . ($isSuperAdmin ? " (tenant {$targetTenantId})" : '')
            );

            $this->respondWithData(['key' => $key, 'value' => $value]);
        } catch (\Exception $e) {
            $this->respondWithError(
                'POLICY_SET_ERROR',
                'Failed to set policy: ' . $e->getMessage(),
                null,
                500
            );
            return;
        }
    }

    // ==================== GROUP DETAIL & MEMBERS ====================

    /**
     * GET /api/v2/admin/groups/{id}
     *
     * Get group detail with full info
     */
    public function getGroup(int $id): void
    {
        $this->requireAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        try {
            $group = \Nexus\Services\GroupService::getById($id);

            // Super admins can view groups from any tenant
            if (!$group || (!$isSuperAdmin && ($group['tenant_id'] ?? null) != $tenantId)) {
                $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Group not found', null, 404);
                return;
            }

            // Add additional stats
            $stats = Database::query(
                "SELECT
                    (SELECT COUNT(*) FROM group_posts WHERE group_id = ?) as posts_count,
                    (SELECT COUNT(*) FROM events WHERE group_id = ?) as events_count
                ",
                [$id, $id]
            )->fetch();

            $group['stats'] = [
                'posts_count' => (int) ($stats['posts_count'] ?? 0),
                'events_count' => (int) ($stats['events_count'] ?? 0),
                'activity_score' => (int) ($group['member_count'] ?? 0) * 10,
            ];

            // Add tenant name for cross-tenant context
            if (isset($group['tenant_id'])) {
                $tenant = Database::query(
                    "SELECT name FROM tenants WHERE id = ?",
                    [(int) $group['tenant_id']]
                )->fetch();
                $group['tenant_name'] = $tenant['name'] ?? 'Unknown';
            }

            $this->respondWithData($group);
        } catch (\Exception $e) {
            $this->respondWithError(
                'GROUP_DETAIL_ERROR',
                'Failed to load group: ' . $e->getMessage(),
                null,
                500
            );
            return;
        }
    }

    /**
     * PUT /api/v2/admin/groups/{id}
     *
     * Update a group
     */
    public function updateGroup(int $id): void
    {
        $adminId = $this->requireAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();
        $input = $this->getAllInput();

        try {
            // Super admins can update groups from any tenant
            if ($isSuperAdmin) {
                $group = Database::query(
                    "SELECT id, name, tenant_id FROM `groups` WHERE id = ?",
                    [$id]
                )->fetch();
            } else {
                $group = Database::query(
                    "SELECT id, name, tenant_id FROM `groups` WHERE id = ? AND tenant_id = ?",
                    [$id, $tenantId]
                )->fetch();
            }

            if (!$group) {
                $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Group not found', null, 404);
                return;
            }

            // Use the record's own tenant_id for safe UPDATE
            $groupTenantId = (int) $group['tenant_id'];

            $updates = [];
            $params = [];

            if (isset($input['name'])) {
                $updates[] = 'name = ?';
                $params[] = trim($input['name']);
            }
            if (isset($input['description'])) {
                $updates[] = 'description = ?';
                $params[] = $input['description'];
            }
            if (isset($input['visibility'])) {
                $updates[] = 'visibility = ?';
                $params[] = $input['visibility'];
            }
            if (isset($input['location'])) {
                $updates[] = 'location = ?';
                $params[] = $input['location'];
            }

            if (empty($updates)) {
                $this->respondWithError('VALIDATION_ERROR', 'No fields to update', null, 422);
                return;
            }

            $updates[] = 'updated_at = NOW()';
            $params[] = $id;
            $params[] = $groupTenantId;

            Database::query(
                "UPDATE `groups` SET " . implode(', ', $updates) . " WHERE id = ? AND tenant_id = ?",
                $params
            );

            ActivityLog::log(
                $adminId,
                'admin_update_group',
                "Updated group #{$id}: {$group['name']}" . ($isSuperAdmin ? " (tenant {$groupTenantId})" : '')
            );

            $this->respondWithData(['id' => $id, 'updated' => true]);
        } catch (\Exception $e) {
            $this->respondWithError(
                'GROUP_UPDATE_ERROR',
                'Failed to update group: ' . $e->getMessage(),
                null,
                500
            );
            return;
        }
    }

    /**
     * GET /api/v2/admin/groups/{groupId}/members
     *
     * Get group members with filters
     */
    public function getMembers(int $groupId): void
    {
        $this->requireAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        $role = $_GET['role'] ?? null;
        $limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
        $offset = (int) ($_GET['offset'] ?? 0);

        try {
            $conditions = ['gm.group_id = ?'];
            $params = [$groupId];

            // Tenant scoping: super admins can view members from any tenant's groups
            if (!$isSuperAdmin) {
                $conditions[] = 'g.tenant_id = ?';
                $params[] = $tenantId;
            }

            if ($role) {
                $conditions[] = 'gm.role = ?';
                $params[] = $role;
            }

            $where = implode(' AND ', $conditions);

            $members = Database::query(
                "SELECT gm.user_id, gm.role, gm.joined_at,
                    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user_name,
                    u.avatar_url as user_avatar
                 FROM group_members gm
                 JOIN `groups` g ON gm.group_id = g.id
                 JOIN users u ON gm.user_id = u.id
                 WHERE {$where}
                 ORDER BY FIELD(gm.role, 'owner', 'admin', 'member'), gm.joined_at ASC
                 LIMIT ? OFFSET ?",
                array_merge($params, [$limit, $offset])
            )->fetchAll();

            $formatted = array_map(function ($row) {
                return [
                    'user_id' => (int) $row['user_id'],
                    'user_name' => trim($row['user_name']),
                    'user_avatar' => $row['user_avatar'],
                    'role' => $row['role'],
                    'joined_at' => $row['joined_at'],
                ];
            }, $members);

            $this->respondWithData($formatted);
        } catch (\Exception $e) {
            $this->respondWithError(
                'GROUP_MEMBERS_ERROR',
                'Failed to load members: ' . $e->getMessage(),
                null,
                500
            );
            return;
        }
    }

    /**
     * POST /api/v2/admin/groups/{groupId}/members/{userId}/promote
     *
     * Promote a member (member → admin or admin → owner)
     */
    public function promoteMember(int $groupId, int $userId): void
    {
        $adminId = $this->requireAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        try {
            // Super admins can promote members in groups from any tenant
            if ($isSuperAdmin) {
                $member = Database::query(
                    "SELECT gm.role, g.tenant_id FROM group_members gm
                     JOIN `groups` g ON gm.group_id = g.id
                     WHERE gm.group_id = ? AND gm.user_id = ?",
                    [$groupId, $userId]
                )->fetch();
            } else {
                $member = Database::query(
                    "SELECT gm.role, g.tenant_id FROM group_members gm
                     JOIN `groups` g ON gm.group_id = g.id
                     WHERE gm.group_id = ? AND gm.user_id = ? AND g.tenant_id = ?",
                    [$groupId, $userId, $tenantId]
                )->fetch();
            }

            if (!$member) {
                $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Member not found', null, 404);
                return;
            }

            $newRole = $member['role'] === 'member' ? 'admin' : 'owner';

            Database::query(
                "UPDATE group_members SET role = ? WHERE group_id = ? AND user_id = ?",
                [$newRole, $groupId, $userId]
            );

            $memberTenantId = (int) $member['tenant_id'];
            ActivityLog::log(
                $adminId,
                'admin_promote_group_member',
                "Promoted user #{$userId} to {$newRole} in group #{$groupId}" . ($isSuperAdmin ? " (tenant {$memberTenantId})" : '')
            );

            $this->respondWithData(['role' => $newRole]);
        } catch (\Exception $e) {
            $this->respondWithError(
                'PROMOTE_ERROR',
                'Failed to promote member: ' . $e->getMessage(),
                null,
                500
            );
            return;
        }
    }

    /**
     * POST /api/v2/admin/groups/{groupId}/members/{userId}/demote
     *
     * Demote a member (owner → admin or admin → member)
     */
    public function demoteMember(int $groupId, int $userId): void
    {
        $adminId = $this->requireAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        try {
            // Super admins can demote members in groups from any tenant
            if ($isSuperAdmin) {
                $member = Database::query(
                    "SELECT gm.role, g.tenant_id FROM group_members gm
                     JOIN `groups` g ON gm.group_id = g.id
                     WHERE gm.group_id = ? AND gm.user_id = ?",
                    [$groupId, $userId]
                )->fetch();
            } else {
                $member = Database::query(
                    "SELECT gm.role, g.tenant_id FROM group_members gm
                     JOIN `groups` g ON gm.group_id = g.id
                     WHERE gm.group_id = ? AND gm.user_id = ? AND g.tenant_id = ?",
                    [$groupId, $userId, $tenantId]
                )->fetch();
            }

            if (!$member) {
                $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Member not found', null, 404);
                return;
            }

            $newRole = $member['role'] === 'owner' ? 'admin' : 'member';

            Database::query(
                "UPDATE group_members SET role = ? WHERE group_id = ? AND user_id = ?",
                [$newRole, $groupId, $userId]
            );

            $memberTenantId = (int) $member['tenant_id'];
            ActivityLog::log(
                $adminId,
                'admin_demote_group_member',
                "Demoted user #{$userId} to {$newRole} in group #{$groupId}" . ($isSuperAdmin ? " (tenant {$memberTenantId})" : '')
            );

            $this->respondWithData(['role' => $newRole]);
        } catch (\Exception $e) {
            $this->respondWithError(
                'DEMOTE_ERROR',
                'Failed to demote member: ' . $e->getMessage(),
                null,
                500
            );
            return;
        }
    }

    /**
     * DELETE /api/v2/admin/groups/{groupId}/members/{userId}
     *
     * Kick a member from the group
     */
    public function kickMember(int $groupId, int $userId): void
    {
        $adminId = $this->requireAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        try {
            // Super admins can kick members from groups in any tenant
            if ($isSuperAdmin) {
                $member = Database::query(
                    "SELECT gm.role, g.tenant_id FROM group_members gm
                     JOIN `groups` g ON gm.group_id = g.id
                     WHERE gm.group_id = ? AND gm.user_id = ?",
                    [$groupId, $userId]
                )->fetch();
            } else {
                $member = Database::query(
                    "SELECT gm.role, g.tenant_id FROM group_members gm
                     JOIN `groups` g ON gm.group_id = g.id
                     WHERE gm.group_id = ? AND gm.user_id = ? AND g.tenant_id = ?",
                    [$groupId, $userId, $tenantId]
                )->fetch();
            }

            if (!$member) {
                $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Member not found', null, 404);
                return;
            }

            Database::query(
                "DELETE FROM group_members WHERE group_id = ? AND user_id = ?",
                [$groupId, $userId]
            );

            $memberTenantId = (int) $member['tenant_id'];
            ActivityLog::log(
                $adminId,
                'admin_kick_group_member',
                "Kicked user #{$userId} from group #{$groupId}" . ($isSuperAdmin ? " (tenant {$memberTenantId})" : '')
            );

            $this->respondWithData(['kicked' => true]);
        } catch (\Exception $e) {
            $this->respondWithError(
                'KICK_ERROR',
                'Failed to kick member: ' . $e->getMessage(),
                null,
                500
            );
            return;
        }
    }

    // ==================== GEOCODING ====================

    /**
     * POST /api/v2/admin/groups/{id}/geocode
     *
     * Geocode a single group's location
     */
    public function geocodeGroup(int $id): void
    {
        $adminId = $this->requireAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        try {
            // Super admins can geocode groups from any tenant
            if ($isSuperAdmin) {
                $group = Database::query(
                    "SELECT id, name, location, tenant_id FROM `groups` WHERE id = ?",
                    [$id]
                )->fetch();
            } else {
                $group = Database::query(
                    "SELECT id, name, location, tenant_id FROM `groups` WHERE id = ? AND tenant_id = ?",
                    [$id, $tenantId]
                )->fetch();
            }

            if (!$group) {
                $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Group not found', null, 404);
                return;
            }

            if (empty($group['location'])) {
                $this->respondWithError('VALIDATION_ERROR', 'Group has no location to geocode', null, 422);
                return;
            }

            $coords = \Nexus\Services\GeocodingService::geocode($group['location']);

            if (!$coords) {
                $this->respondWithError('GEOCODING_FAILED', 'Failed to geocode location', null, 500);
                return;
            }

            // Use the record's own tenant_id for safe UPDATE
            $groupTenantId = (int) $group['tenant_id'];
            Database::query(
                "UPDATE `groups` SET latitude = ?, longitude = ? WHERE id = ? AND tenant_id = ?",
                [$coords['latitude'], $coords['longitude'], $id, $groupTenantId]
            );

            ActivityLog::log(
                $adminId,
                'admin_geocode_group',
                "Geocoded group #{$id}: {$group['name']}" . ($isSuperAdmin ? " (tenant {$groupTenantId})" : '')
            );

            $this->respondWithData([
                'latitude' => $coords['latitude'],
                'longitude' => $coords['longitude'],
            ]);
        } catch (\Exception $e) {
            $this->respondWithError(
                'GEOCODE_ERROR',
                'Failed to geocode group: ' . $e->getMessage(),
                null,
                500
            );
            return;
        }
    }

    /**
     * POST /api/v2/admin/groups/batch-geocode
     *
     * Batch geocode all groups missing coordinates
     */
    public function batchGeocode(): void
    {
        $adminId = $this->requireAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        // Determine scope: defaults to current tenant; super admins can pass ?tenant_id=all for cross-tenant
        $effectiveTenantId = $this->resolveAdminTenantFilter($isSuperAdmin, $tenantId);
        $tenantCondition = $effectiveTenantId !== null ? 'tenant_id = ?' : '1=1';
        $tenantParams = $effectiveTenantId !== null ? [$effectiveTenantId] : [];

        try {
            $groups = Database::query(
                "SELECT id, location FROM `groups`
                 WHERE {$tenantCondition}
                 AND location IS NOT NULL
                 AND location != ''
                 AND (latitude IS NULL OR longitude IS NULL)
                 LIMIT 50",
                $tenantParams
            )->fetchAll();

            $success = 0;
            $failed = 0;

            foreach ($groups as $group) {
                $coords = \Nexus\Services\GeocodingService::geocode($group['location']);

                if ($coords) {
                    Database::query(
                        "UPDATE `groups` SET latitude = ?, longitude = ? WHERE id = ?",
                        [$coords['latitude'], $coords['longitude'], $group['id']]
                    );
                    $success++;
                } else {
                    $failed++;
                }

                usleep(100000); // 100ms delay
            }

            ActivityLog::log(
                $adminId,
                'admin_batch_geocode_groups',
                "Batch geocoded {$success} groups, {$failed} failed" . ($isSuperAdmin ? " (scope: " . ($effectiveTenantId ? "tenant {$effectiveTenantId}" : "all tenants") . ")" : '')
            );

            $this->respondWithData([
                'processed' => count($groups),
                'success' => $success,
                'failed' => $failed,
            ]);
        } catch (\Exception $e) {
            $this->respondWithError(
                'BATCH_GEOCODE_ERROR',
                'Failed to batch geocode: ' . $e->getMessage(),
                null,
                500
            );
            return;
        }
    }

    // ==================== RECOMMENDATIONS ====================

    /**
     * GET /api/v2/admin/groups/recommendations
     *
     * Get recommendation analytics data
     */
    public function getRecommendationData(): void
    {
        $this->requireAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        // Determine scope: defaults to current tenant; super admins can pass ?tenant_id=all for cross-tenant
        $effectiveTenantId = $this->resolveAdminTenantFilter($isSuperAdmin, $tenantId);
        $tenantCondition = $effectiveTenantId !== null ? 'g.tenant_id = ?' : '1=1';
        $tenantParams = $effectiveTenantId !== null ? [$effectiveTenantId] : [];

        $limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
        $offset = (int) ($_GET['offset'] ?? 0);

        try {
            // Try to get recommendation data from group_recommendations table
            try {
                $recommendations = Database::query(
                    "SELECT gr.user_id, gr.group_id, gr.score, gr.created_at, g.tenant_id, t.name as tenant_name,
                        CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user_name,
                        g.name as group_name,
                        (SELECT COUNT(*) FROM group_members gm WHERE gm.user_id = gr.user_id AND gm.group_id = gr.group_id) > 0 as joined
                     FROM group_recommendations gr
                     JOIN users u ON gr.user_id = u.id
                     JOIN `groups` g ON gr.group_id = g.id
                     LEFT JOIN tenants t ON g.tenant_id = t.id
                     WHERE {$tenantCondition}
                     ORDER BY gr.created_at DESC
                     LIMIT ? OFFSET ?",
                    array_merge($tenantParams, [$limit, $offset])
                )->fetchAll();

                $stats = Database::query(
                    "SELECT
                        COUNT(*) as total,
                        AVG(score) as avg_score,
                        SUM(CASE WHEN EXISTS(SELECT 1 FROM group_members gm WHERE gm.user_id = gr.user_id AND gm.group_id = gr.group_id) THEN 1 ELSE 0 END) as joined_count
                     FROM group_recommendations gr
                     JOIN `groups` g ON gr.group_id = g.id
                     WHERE {$tenantCondition}",
                    $tenantParams
                )->fetch();
            } catch (\Exception $e) {
                // Table doesn't exist or error - return mock data
                $recommendations = [];
                $stats = ['total' => 0, 'avg_score' => 0, 'joined_count' => 0];
            }

            $formatted = array_map(function ($row) {
                return [
                    'user_id' => (int) $row['user_id'],
                    'user_name' => trim($row['user_name']),
                    'group_id' => (int) $row['group_id'],
                    'group_name' => $row['group_name'],
                    'tenant_id' => (int) $row['tenant_id'],
                    'tenant_name' => $row['tenant_name'] ?? 'Unknown',
                    'score' => (float) $row['score'],
                    'joined' => (bool) $row['joined'],
                    'created_at' => $row['created_at'],
                ];
            }, $recommendations);

            $joinRate = $stats['total'] > 0 ? round(($stats['joined_count'] / $stats['total']) * 100, 1) : 0;

            $this->respondWithData([
                'recommendations' => $formatted,
                'stats' => [
                    'total' => (int) $stats['total'],
                    'avg_score' => round((float) $stats['avg_score'], 2),
                    'join_rate' => $joinRate,
                ],
            ]);
        } catch (\Exception $e) {
            $this->respondWithError(
                'RECOMMENDATIONS_ERROR',
                'Failed to load recommendations: ' . $e->getMessage(),
                null,
                500
            );
            return;
        }
    }

    // ==================== RANKING ====================

    /**
     * GET /api/v2/admin/groups/featured
     *
     * Get featured groups with ranking scores
     */
    public function getFeaturedGroups(): void
    {
        $this->requireAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        // Featured groups service requires a tenant_id — fall back to current tenant if cross-tenant requested
        $scopeTenantId = $this->resolveAdminTenantFilter($isSuperAdmin, $tenantId) ?? $tenantId;

        try {
            $groups = \Nexus\Services\SmartGroupRankingService::getFeaturedGroupsWithScores('local_hubs', $scopeTenantId);

            $this->respondWithData($groups);
        } catch (\Exception $e) {
            $this->respondWithError(
                'FEATURED_GROUPS_ERROR',
                'Failed to load featured groups: ' . $e->getMessage(),
                null,
                500
            );
            return;
        }
    }

    /**
     * POST /api/v2/admin/groups/featured/update
     *
     * Run the ranking algorithm to update featured groups
     */
    public function updateFeaturedGroups(): void
    {
        $adminId = $this->requireAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        // Super admins can specify a target tenant_id via request body or query param
        $input = $this->getAllInput();
        $targetTenantId = ($isSuperAdmin && isset($input['tenant_id'])) ? (int) $input['tenant_id'] : $tenantId;

        try {
            $result = \Nexus\Services\SmartGroupRankingService::updateFeaturedLocalHubs($targetTenantId);

            ActivityLog::log(
                $adminId,
                'admin_update_featured_groups',
                "Updated featured groups: {$result['featured']} groups featured, {$result['cleared']} cleared" . ($isSuperAdmin ? " (tenant {$targetTenantId})" : '')
            );

            $this->respondWithData($result);
        } catch (\Exception $e) {
            $this->respondWithError(
                'UPDATE_FEATURED_ERROR',
                'Failed to update featured groups: ' . $e->getMessage(),
                null,
                500
            );
            return;
        }
    }

    /**
     * PUT /api/v2/admin/groups/{id}/toggle-featured
     *
     * Toggle a group's featured status
     */
    public function toggleFeatured(int $id): void
    {
        $adminId = $this->requireAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        try {
            // Super admins can toggle featured status for groups from any tenant
            if ($isSuperAdmin) {
                $group = Database::query(
                    "SELECT id, name, is_featured, tenant_id FROM `groups` WHERE id = ?",
                    [$id]
                )->fetch();
            } else {
                $group = Database::query(
                    "SELECT id, name, is_featured, tenant_id FROM `groups` WHERE id = ? AND tenant_id = ?",
                    [$id, $tenantId]
                )->fetch();
            }

            if (!$group) {
                $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Group not found', null, 404);
                return;
            }

            // Use the record's own tenant_id for safe UPDATE
            $groupTenantId = (int) $group['tenant_id'];
            $newStatus = $group['is_featured'] ? 0 : 1;

            Database::query(
                "UPDATE `groups` SET is_featured = ? WHERE id = ? AND tenant_id = ?",
                [$newStatus, $id, $groupTenantId]
            );

            ActivityLog::log(
                $adminId,
                'admin_toggle_group_featured',
                ($newStatus ? 'Featured' : 'Unfeatured') . " group #{$id}: {$group['name']}" . ($isSuperAdmin ? " (tenant {$groupTenantId})" : '')
            );

            $this->respondWithData(['is_featured' => (bool) $newStatus]);
        } catch (\Exception $e) {
            $this->respondWithError(
                'TOGGLE_FEATURED_ERROR',
                'Failed to toggle featured status: ' . $e->getMessage(),
                null,
                500
            );
            return;
        }
    }
}
