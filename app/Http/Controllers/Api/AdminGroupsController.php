<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\GeocodingService;
use App\Services\GroupPolicyRepository;
use App\Services\GroupService;
use App\Services\SmartGroupRankingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Nexus\Core\TenantContext;
use App\Models\ActivityLog;

/**
 * AdminGroupsController -- Admin group management (list, analytics, approvals, moderation,
 * group types, policies, members, geocoding, recommendations, featured).
 *
 * All methods require admin authentication.
 */
class AdminGroupsController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly GroupService $groupService,
        private readonly GeocodingService $geocodingService,
        private readonly GroupPolicyRepository $groupPolicyRepository,
        private readonly SmartGroupRankingService $smartGroupRankingService,
    ) {}

    // =========================================================================
    // Groups List & Detail
    // =========================================================================

    /** GET /api/v2/admin/groups */
    public function index(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $page = $this->queryInt('page', 1, 1);
        $limit = $this->queryInt('limit', 20, 1, 100);
        $offset = ($page - 1) * $limit;
        $status = $this->query('status');
        $search = $this->query('search');

        $conditions = ['g.tenant_id = ?'];
        $params = [$tenantId];

        if ($status === 'active') {
            $conditions[] = 'g.is_active = 1';
        } elseif ($status === 'inactive') {
            $conditions[] = 'g.is_active = 0';
        }

        if ($search) {
            $conditions[] = '(g.name LIKE ? OR g.description LIKE ?)';
            $searchTerm = '%' . $search . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $where = implode(' AND ', $conditions);

        try {
            $total = (int) DB::selectOne("SELECT COUNT(*) as cnt FROM `groups` g WHERE {$where}", $params)->cnt;

            $items = DB::select(
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
            );

            $formatted = array_map(fn($row) => [
                'id' => (int) $row->id,
                'tenant_id' => (int) $row->tenant_id,
                'tenant_name' => $row->tenant_name ?? 'Unknown',
                'name' => $row->name ?? '',
                'description' => $row->description ?? '',
                'image_url' => $row->image_url ?: null,
                'visibility' => $row->visibility ?? 'public',
                'status' => !empty($row->is_active) ? 'active' : 'inactive',
                'creator_name' => trim($row->creator_name ?? ''),
                'member_count' => (int) ($row->member_count ?? 0),
                'created_at' => $row->created_at,
            ], $items);

            return $this->respondWithPaginatedCollection($formatted, $total, $page, $limit);
        } catch (\Exception $e) {
            return $this->respondWithError('GROUPS_QUERY_ERROR', 'Failed to load groups: ' . $e->getMessage(), null, 500);
        }
    }

    /** GET /api/v2/admin/groups/{id} (detail with stats) */
    public function getGroup($id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            $group = $this->groupService->getById((int) $id);

            if (!$group || ($group['tenant_id'] ?? null) != $tenantId) {
                return $this->respondWithError('NOT_FOUND', 'Group not found', null, 404);
            }

            $stats = DB::selectOne(
                "SELECT
                    (SELECT COUNT(*) FROM group_posts WHERE group_id = ?) as posts_count,
                    (SELECT COUNT(*) FROM events WHERE group_id = ?) as events_count",
                [$id, $id]
            );

            $group['stats'] = [
                'posts_count' => (int) ($stats->posts_count ?? 0),
                'events_count' => (int) ($stats->events_count ?? 0),
                'activity_score' => (int) ($group['member_count'] ?? 0) * 10,
            ];

            $tenant = DB::selectOne("SELECT name FROM tenants WHERE id = ?", [(int) $group['tenant_id']]);
            $group['tenant_name'] = $tenant->name ?? 'Unknown';

            return $this->respondWithData($group);
        } catch (\Exception $e) {
            return $this->respondWithError('GROUP_DETAIL_ERROR', 'Failed to load group: ' . $e->getMessage(), null, 500);
        }
    }

    /** GET /api/v2/admin/groups/{id} (simple) */
    public function show(int $id): JsonResponse
    {
        return $this->getGroup($id);
    }

    // =========================================================================
    // Analytics
    // =========================================================================

    /** GET /api/v2/admin/groups/analytics */
    public function analytics(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            $totalGroups = (int) DB::selectOne("SELECT COUNT(*) as cnt FROM `groups` WHERE tenant_id = ?", [$tenantId])->cnt;
            $totalMembers = (int) DB::selectOne(
                "SELECT COUNT(*) as cnt FROM group_members gm JOIN `groups` g ON gm.group_id = g.id WHERE g.tenant_id = ? AND gm.status = 'approved'",
                [$tenantId]
            )->cnt;
            $avgMembers = $totalGroups > 0 ? round($totalMembers / $totalGroups, 1) : 0;
            $activeGroups = (int) DB::selectOne("SELECT COUNT(*) as cnt FROM `groups` WHERE tenant_id = ? AND is_active = 1", [$tenantId])->cnt;
            $pendingApprovals = (int) DB::selectOne(
                "SELECT COUNT(*) as cnt FROM group_members gm JOIN `groups` g ON gm.group_id = g.id WHERE g.tenant_id = ? AND gm.status = 'pending'",
                [$tenantId]
            )->cnt;

            $mostActive = DB::select(
                "SELECT g.id, g.name,
                    (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id AND gm.status = 'approved') as member_count
                 FROM `groups` g WHERE g.tenant_id = ? ORDER BY member_count DESC LIMIT 5",
                [$tenantId]
            );

            return $this->respondWithData([
                'total_groups' => $totalGroups,
                'total_members' => $totalMembers,
                'avg_members_per_group' => $avgMembers,
                'active_groups' => $activeGroups,
                'pending_approvals' => $pendingApprovals,
                'most_active_groups' => array_map(fn($r) => [
                    'id' => (int) $r->id, 'name' => $r->name, 'member_count' => (int) $r->member_count,
                ], $mostActive),
            ]);
        } catch (\Exception $e) {
            return $this->respondWithError('GROUPS_ANALYTICS_ERROR', 'Failed to load group analytics: ' . $e->getMessage(), null, 500);
        }
    }

    // =========================================================================
    // Approvals
    // =========================================================================

    /** GET /api/v2/admin/groups/approvals */
    public function approvals(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            $items = DB::select(
                "SELECT gm.*, g.name as group_name, t.name as tenant_name, g.tenant_id,
                    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user_name
                 FROM group_members gm
                 JOIN `groups` g ON gm.group_id = g.id
                 JOIN users u ON gm.user_id = u.id
                 LEFT JOIN tenants t ON g.tenant_id = t.id
                 WHERE gm.status = 'pending' AND g.tenant_id = ?
                 ORDER BY gm.created_at DESC",
                [$tenantId]
            );

            $formatted = array_map(fn($row) => [
                'id' => (int) $row->id,
                'group_id' => (int) $row->group_id,
                'group_name' => $row->group_name ?? '',
                'tenant_id' => (int) $row->tenant_id,
                'tenant_name' => $row->tenant_name ?? 'Unknown',
                'user_id' => (int) $row->user_id,
                'user_name' => trim($row->user_name ?? ''),
                'status' => $row->status ?? 'pending',
                'created_at' => $row->created_at ?? null,
            ], $items);

            return $this->respondWithData($formatted);
        } catch (\Exception $e) {
            return $this->respondWithError('GROUPS_APPROVALS_ERROR', 'Failed to load group approvals: ' . $e->getMessage(), null, 500);
        }
    }

    /** POST /api/v2/admin/groups/approvals/{id}/approve */
    public function approveMember($id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            $membership = DB::selectOne(
                "SELECT gm.*, g.name as group_name, g.tenant_id
                 FROM group_members gm JOIN `groups` g ON gm.group_id = g.id
                 WHERE gm.id = ? AND g.tenant_id = ? AND gm.status = 'pending'",
                [(int) $id, $tenantId]
            );

            if (!$membership) {
                return $this->respondWithError('NOT_FOUND', 'Pending membership request not found', null, 404);
            }

            DB::update("UPDATE group_members SET status = 'approved', updated_at = NOW() WHERE id = ?", [(int) $id]);
            ActivityLog::log($adminId, 'admin_approve_group_member', "Approved membership #{$id} for group \"{$membership->group_name}\"");

            return $this->respondWithData(['id' => (int) $id, 'status' => 'approved']);
        } catch (\Exception $e) {
            return $this->respondWithError('GROUPS_APPROVE_ERROR', 'Failed to approve membership: ' . $e->getMessage(), null, 500);
        }
    }

    /** POST /api/v2/admin/groups/approvals/{id}/reject */
    public function rejectMember($id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            $membership = DB::selectOne(
                "SELECT gm.*, g.name as group_name, g.tenant_id
                 FROM group_members gm JOIN `groups` g ON gm.group_id = g.id
                 WHERE gm.id = ? AND g.tenant_id = ? AND gm.status = 'pending'",
                [(int) $id, $tenantId]
            );

            if (!$membership) {
                return $this->respondWithError('NOT_FOUND', 'Pending membership request not found', null, 404);
            }

            DB::update("UPDATE group_members SET status = 'rejected', updated_at = NOW() WHERE id = ?", [(int) $id]);
            ActivityLog::log($adminId, 'admin_reject_group_member', "Rejected membership #{$id} for group \"{$membership->group_name}\"");

            return $this->respondWithData(['id' => (int) $id, 'status' => 'rejected']);
        } catch (\Exception $e) {
            return $this->respondWithError('GROUPS_REJECT_ERROR', 'Failed to reject membership: ' . $e->getMessage(), null, 500);
        }
    }

    /** POST /api/v2/admin/groups/{id}/approve */
    public function approve(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $affected = DB::update("UPDATE `groups` SET is_active = 1 WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
        if ($affected === 0) {
            return $this->respondWithError('NOT_FOUND', 'Group not found', null, 404);
        }

        return $this->respondWithData(['id' => $id, 'status' => 'active']);
    }

    // =========================================================================
    // Moderation
    // =========================================================================

    /** GET /api/v2/admin/groups/moderation */
    public function moderation(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            $items = [];
            try {
                $items = DB::select(
                    "SELECT g.id, g.name, g.is_active, g.created_at, g.tenant_id, t.name as tenant_name,
                        COUNT(r.id) as report_count
                     FROM `groups` g
                     INNER JOIN reports r ON r.content_type = 'group' AND r.content_id = g.id
                     LEFT JOIN tenants t ON g.tenant_id = t.id
                     WHERE g.tenant_id = ? AND r.status = 'pending'
                     GROUP BY g.id, g.name, g.is_active, g.created_at, g.tenant_id, t.name
                     ORDER BY report_count DESC",
                    [$tenantId]
                );
            } catch (\Exception $e) {
                $items = DB::select(
                    "SELECT g.id, g.name, g.is_active, g.created_at, g.tenant_id, t.name as tenant_name, 0 as report_count
                     FROM `groups` g LEFT JOIN tenants t ON g.tenant_id = t.id
                     WHERE g.tenant_id = ? AND g.is_active = 0 ORDER BY g.created_at DESC",
                    [$tenantId]
                );
            }

            $formatted = array_map(fn($row) => [
                'id' => (int) $row->id,
                'name' => $row->name ?? '',
                'tenant_id' => (int) $row->tenant_id,
                'tenant_name' => $row->tenant_name ?? 'Unknown',
                'status' => !empty($row->is_active) ? 'active' : 'inactive',
                'report_count' => (int) ($row->report_count ?? 0),
                'created_at' => $row->created_at,
            ], $items);

            return $this->respondWithData($formatted);
        } catch (\Exception $e) {
            return $this->respondWithError('GROUPS_MODERATION_ERROR', 'Failed to load moderation data: ' . $e->getMessage(), null, 500);
        }
    }

    // =========================================================================
    // Status & CRUD
    // =========================================================================

    /** PUT /api/v2/admin/groups/{id}/status */
    public function updateStatus($id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $newStatus = $this->input('status');

        if (!in_array($newStatus, ['active', 'inactive'])) {
            return $this->respondWithError('VALIDATION_ERROR', 'Status must be "active" or "inactive"', 'status', 422);
        }

        $group = DB::selectOne("SELECT id, name, tenant_id FROM `groups` WHERE id = ? AND tenant_id = ?", [(int) $id, $tenantId]);
        if (!$group) {
            return $this->respondWithError('NOT_FOUND', 'Group not found', null, 404);
        }

        $isActive = $newStatus === 'active' ? 1 : 0;
        DB::update("UPDATE `groups` SET is_active = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?", [$isActive, (int) $id, $tenantId]);
        ActivityLog::log($adminId, 'admin_update_group_status', "Set group #{$id} \"{$group->name}\" to {$newStatus}");

        return $this->respondWithData(['id' => (int) $id, 'status' => $newStatus]);
    }

    /** PUT /api/v2/admin/groups/{id} */
    public function updateGroup($id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $input = $this->getAllInput();

        $group = DB::selectOne("SELECT id, name, tenant_id FROM `groups` WHERE id = ? AND tenant_id = ?", [(int) $id, $tenantId]);
        if (!$group) {
            return $this->respondWithError('NOT_FOUND', 'Group not found', null, 404);
        }

        $updates = [];
        $params = [];
        foreach (['name', 'description', 'visibility', 'location'] as $field) {
            if (isset($input[$field])) {
                $updates[] = "{$field} = ?";
                $params[] = $field === 'name' ? trim($input[$field]) : $input[$field];
            }
        }

        if (empty($updates)) {
            return $this->respondWithError('VALIDATION_ERROR', 'No fields to update', null, 422);
        }

        $updates[] = 'updated_at = NOW()';
        $params[] = (int) $id;
        $params[] = $tenantId;

        DB::update("UPDATE `groups` SET " . implode(', ', $updates) . " WHERE id = ? AND tenant_id = ?", $params);
        ActivityLog::log($adminId, 'admin_update_group', "Updated group #{$id}: {$group->name}");

        return $this->respondWithData(['id' => (int) $id, 'updated' => true]);
    }

    /** DELETE /api/v2/admin/groups/{id} */
    public function destroy(int $id): JsonResponse
    {
        return $this->deleteGroup($id);
    }

    /** DELETE /api/v2/admin/groups/{id} (named) */
    public function deleteGroup($id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $group = DB::selectOne("SELECT id, name, tenant_id FROM `groups` WHERE id = ? AND tenant_id = ?", [(int) $id, $tenantId]);
        if (!$group) {
            return $this->respondWithError('NOT_FOUND', 'Group not found', null, 404);
        }

        DB::delete("DELETE FROM group_members WHERE group_id = ? AND tenant_id = ?", [(int) $id, $tenantId]);
        DB::delete("DELETE FROM `groups` WHERE id = ? AND tenant_id = ?", [(int) $id, $tenantId]);
        ActivityLog::log($adminId, 'admin_delete_group', "Deleted group #{$id}: {$group->name}");

        return $this->respondWithData(['deleted' => true, 'id' => (int) $id]);
    }

    // =========================================================================
    // Group Types
    // =========================================================================

    /** GET /api/v2/admin/groups/types */
    public function getGroupTypes(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            $types = DB::select(
                "SELECT gt.*, t.name as tenant_name,
                    (SELECT COUNT(*) FROM `groups` g WHERE g.type_id = gt.id AND g.tenant_id = ?) as member_count
                 FROM group_types gt
                 LEFT JOIN tenants t ON gt.tenant_id = t.id
                 WHERE gt.tenant_id = ?
                 ORDER BY gt.sort_order ASC, gt.name ASC",
                [$tenantId, $tenantId]
            );

            $formatted = array_map(function ($row) use ($tenantId) {
                $policyCount = 0;
                try {
                    $policyCount = (int) (DB::selectOne("SELECT COUNT(*) as cnt FROM group_policies WHERE tenant_id = ?", [$tenantId])->cnt ?? 0);
                } catch (\Throwable $e) {}

                return [
                    'id' => (int) $row->id,
                    'tenant_id' => (int) $row->tenant_id,
                    'tenant_name' => $row->tenant_name ?? 'Unknown',
                    'name' => $row->name,
                    'description' => $row->description ?? '',
                    'icon' => $row->icon ?? 'fa-layer-group',
                    'color' => $row->color ?? '#6366f1',
                    'member_count' => (int) $row->member_count,
                    'policy_count' => $policyCount,
                    'created_at' => $row->created_at,
                ];
            }, $types);

            return $this->respondWithData($formatted);
        } catch (\Throwable $e) {
            return $this->respondWithError('GROUP_TYPES_ERROR', 'Failed to load group types: ' . $e->getMessage(), null, 500);
        }
    }

    /** POST /api/v2/admin/groups/types */
    public function createGroupType(): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $name = trim($this->input('name', ''));

        if (empty($name)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Name is required', 'name', 422);
        }

        try {
            DB::insert(
                "INSERT INTO group_types (tenant_id, name, slug, description, icon, color, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())",
                [$tenantId, $name, \Nexus\Helpers\TextHelper::slugify($name), $this->input('description'), $this->input('icon', 'fa-layer-group'), $this->input('color', '#6366f1')]
            );
            $id = DB::getPdo()->lastInsertId();
            ActivityLog::log($adminId, 'admin_create_group_type', "Created group type: {$name}");
            return $this->respondWithData(['id' => $id, 'name' => $name]);
        } catch (\Throwable $e) {
            return $this->respondWithError('GROUP_TYPE_CREATE_ERROR', 'Failed to create group type: ' . $e->getMessage(), null, 500);
        }
    }

    /** PUT /api/v2/admin/groups/types/{id} */
    public function updateGroupType($id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $id = (int) $id;

        try {
            $existing = DB::selectOne("SELECT id, name, tenant_id FROM group_types WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
            if (!$existing) {
                return $this->respondWithError('NOT_FOUND', 'Group type not found', null, 404);
            }

            $name = trim($this->input('name', $existing->name));
            DB::update(
                "UPDATE group_types SET name = ?, description = ?, icon = ?, color = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?",
                [$name, $this->input('description'), $this->input('icon', 'fa-layer-group'), $this->input('color', '#6366f1'), $id, $tenantId]
            );
            ActivityLog::log($adminId, 'admin_update_group_type', "Updated group type #{$id}: {$name}");
            return $this->respondWithData(['id' => $id, 'name' => $name]);
        } catch (\Throwable $e) {
            return $this->respondWithError('GROUP_TYPE_UPDATE_ERROR', 'Failed to update group type: ' . $e->getMessage(), null, 500);
        }
    }

    /** DELETE /api/v2/admin/groups/types/{id} */
    public function deleteGroupType($id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $id = (int) $id;

        try {
            $type = DB::selectOne("SELECT id, name FROM group_types WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
            if (!$type) {
                return $this->respondWithError('NOT_FOUND', 'Group type not found', null, 404);
            }

            $count = (int) (DB::selectOne("SELECT COUNT(*) as cnt FROM `groups` WHERE type_id = ? AND tenant_id = ?", [$id, $tenantId])->cnt ?? 0);
            if ($count > 0) {
                return $this->respondWithError('GROUP_TYPE_IN_USE', "Cannot delete group type: {$count} groups are using it", null, 422);
            }

            DB::delete("DELETE FROM group_types WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
            ActivityLog::log($adminId, 'admin_delete_group_type', "Deleted group type #{$id}: {$type->name}");
            return $this->respondWithData(['deleted' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            return $this->respondWithError('GROUP_TYPE_DELETE_ERROR', 'Failed to delete group type: ' . $e->getMessage(), null, 500);
        }
    }

    // =========================================================================
    // Policies
    // =========================================================================

    /** GET /api/v2/admin/groups/types/{id}/policies */
    public function getPolicies($id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            $policies = $this->groupPolicyRepository->getAllPolicies($tenantId);
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
            return $this->respondWithData($formatted);
        } catch (\Throwable $e) {
            return $this->respondWithError('POLICIES_ERROR', 'Failed to load policies: ' . $e->getMessage(), null, 500);
        }
    }

    /** PUT /api/v2/admin/groups/types/{id}/policies */
    public function setPolicy($id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $key = $this->input('key');
        $value = $this->input('value');

        if (empty($key)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Policy key is required', 'key', 422);
        }

        try {
            $category = $this->input('category', GroupPolicyRepository::CATEGORY_FEATURES);
            $type = $this->input('type', GroupPolicyRepository::TYPE_STRING);
            $this->groupPolicyRepository->setPolicy($key, $value, $category, $type, $this->input('description'), $tenantId);
            ActivityLog::log($adminId, 'admin_set_group_policy', "Set policy {$key} = " . json_encode($value));
            return $this->respondWithData(['key' => $key, 'value' => $value]);
        } catch (\Throwable $e) {
            return $this->respondWithError('POLICY_SET_ERROR', 'Failed to set policy: ' . $e->getMessage(), null, 500);
        }
    }

    // =========================================================================
    // Members
    // =========================================================================

    /** GET /api/v2/admin/groups/{groupId}/members */
    public function getMembers($groupId): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $role = $this->query('role');
        $limit = $this->queryInt('limit', 20, 1, 100);
        $offset = $this->queryInt('offset', 0, 0);

        try {
            $conditions = ['gm.group_id = ?', 'g.tenant_id = ?'];
            $params = [(int) $groupId, $tenantId];

            if ($role) {
                $conditions[] = 'gm.role = ?';
                $params[] = $role;
            }

            $where = implode(' AND ', $conditions);

            $members = DB::select(
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
            );

            $formatted = array_map(fn($row) => [
                'user_id' => (int) $row->user_id,
                'user_name' => trim($row->user_name),
                'user_avatar' => $row->user_avatar,
                'role' => $row->role,
                'joined_at' => $row->joined_at,
            ], $members);

            return $this->respondWithData($formatted);
        } catch (\Exception $e) {
            return $this->respondWithError('GROUP_MEMBERS_ERROR', 'Failed to load members: ' . $e->getMessage(), null, 500);
        }
    }

    /** POST /api/v2/admin/groups/{groupId}/members/{userId}/promote */
    public function promoteMember($groupId, $userId): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            $member = DB::selectOne(
                "SELECT gm.role, g.tenant_id FROM group_members gm
                 JOIN `groups` g ON gm.group_id = g.id
                 WHERE gm.group_id = ? AND gm.user_id = ? AND g.tenant_id = ?",
                [(int) $groupId, (int) $userId, $tenantId]
            );

            if (!$member) {
                return $this->respondWithError('NOT_FOUND', 'Member not found', null, 404);
            }

            $newRole = $member->role === 'member' ? 'admin' : 'owner';
            DB::update("UPDATE group_members SET role = ? WHERE group_id = ? AND user_id = ?", [$newRole, (int) $groupId, (int) $userId]);
            ActivityLog::log($adminId, 'admin_promote_group_member', "Promoted user #{$userId} to {$newRole} in group #{$groupId}");

            return $this->respondWithData(['role' => $newRole]);
        } catch (\Exception $e) {
            return $this->respondWithError('PROMOTE_ERROR', 'Failed to promote member: ' . $e->getMessage(), null, 500);
        }
    }

    /** POST /api/v2/admin/groups/{groupId}/members/{userId}/demote */
    public function demoteMember($groupId, $userId): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            $member = DB::selectOne(
                "SELECT gm.role, g.tenant_id FROM group_members gm
                 JOIN `groups` g ON gm.group_id = g.id
                 WHERE gm.group_id = ? AND gm.user_id = ? AND g.tenant_id = ?",
                [(int) $groupId, (int) $userId, $tenantId]
            );

            if (!$member) {
                return $this->respondWithError('NOT_FOUND', 'Member not found', null, 404);
            }

            $newRole = $member->role === 'owner' ? 'admin' : 'member';
            DB::update("UPDATE group_members SET role = ? WHERE group_id = ? AND user_id = ?", [$newRole, (int) $groupId, (int) $userId]);
            ActivityLog::log($adminId, 'admin_demote_group_member', "Demoted user #{$userId} to {$newRole} in group #{$groupId}");

            return $this->respondWithData(['role' => $newRole]);
        } catch (\Exception $e) {
            return $this->respondWithError('DEMOTE_ERROR', 'Failed to demote member: ' . $e->getMessage(), null, 500);
        }
    }

    /** DELETE /api/v2/admin/groups/{groupId}/members/{userId} */
    public function kickMember($groupId, $userId): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            $member = DB::selectOne(
                "SELECT gm.role, g.tenant_id FROM group_members gm
                 JOIN `groups` g ON gm.group_id = g.id
                 WHERE gm.group_id = ? AND gm.user_id = ? AND g.tenant_id = ?",
                [(int) $groupId, (int) $userId, $tenantId]
            );

            if (!$member) {
                return $this->respondWithError('NOT_FOUND', 'Member not found', null, 404);
            }

            DB::delete("DELETE FROM group_members WHERE group_id = ? AND user_id = ?", [(int) $groupId, (int) $userId]);
            ActivityLog::log($adminId, 'admin_kick_group_member', "Kicked user #{$userId} from group #{$groupId}");

            return $this->respondWithData(['kicked' => true]);
        } catch (\Exception $e) {
            return $this->respondWithError('KICK_ERROR', 'Failed to kick member: ' . $e->getMessage(), null, 500);
        }
    }

    // =========================================================================
    // Geocoding
    // =========================================================================

    /** POST /api/v2/admin/groups/{id}/geocode */
    public function geocodeGroup($id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $id = (int) $id;

        try {
            $group = DB::selectOne("SELECT id, name, location, tenant_id FROM `groups` WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
            if (!$group) {
                return $this->respondWithError('NOT_FOUND', 'Group not found', null, 404);
            }
            if (empty($group->location)) {
                return $this->respondWithError('VALIDATION_ERROR', 'Group has no location to geocode', null, 422);
            }

            $coords = $this->geocodingService->geocode($group->location);
            if (!$coords) {
                return $this->respondWithError('GEOCODING_FAILED', 'Failed to geocode location', null, 500);
            }

            DB::update("UPDATE `groups` SET latitude = ?, longitude = ? WHERE id = ? AND tenant_id = ?", [$coords['latitude'], $coords['longitude'], $id, $tenantId]);
            ActivityLog::log($adminId, 'admin_geocode_group', "Geocoded group #{$id}: {$group->name}");
            return $this->respondWithData(['latitude' => $coords['latitude'], 'longitude' => $coords['longitude']]);
        } catch (\Throwable $e) {
            return $this->respondWithError('GEOCODE_ERROR', 'Failed to geocode group: ' . $e->getMessage(), null, 500);
        }
    }

    /** POST /api/v2/admin/groups/batch-geocode */
    public function batchGeocode(): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            $groups = DB::select(
                "SELECT id, location FROM `groups` WHERE tenant_id = ? AND location IS NOT NULL AND location != '' AND (latitude IS NULL OR longitude IS NULL) LIMIT 50",
                [$tenantId]
            );

            $success = 0;
            $failed = 0;

            foreach ($groups as $group) {
                $coords = $this->geocodingService->geocode($group->location);
                if ($coords) {
                    DB::update("UPDATE `groups` SET latitude = ?, longitude = ? WHERE id = ?", [$coords['latitude'], $coords['longitude'], $group->id]);
                    $success++;
                } else {
                    $failed++;
                }
                usleep(100000);
            }

            ActivityLog::log($adminId, 'admin_batch_geocode_groups', "Batch geocoded {$success} groups, {$failed} failed");
            return $this->respondWithData(['processed' => count($groups), 'success' => $success, 'failed' => $failed]);
        } catch (\Throwable $e) {
            return $this->respondWithError('BATCH_GEOCODE_ERROR', 'Failed to batch geocode: ' . $e->getMessage(), null, 500);
        }
    }

    // =========================================================================
    // Recommendations & Featured
    // =========================================================================

    /** GET /api/v2/admin/groups/recommendations */
    public function getRecommendationData(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $limit = min(100, max(1, $this->queryInt('limit', 20)));
        $offset = $this->queryInt('offset', 0, 0);

        try {
            $recommendations = [];
            $stats = ['total' => 0, 'avg_score' => 0, 'joined_count' => 0];

            try {
                $recommendations = array_map(fn($r) => (array)$r, DB::select(
                    "SELECT gr.user_id, gr.group_id, gr.score, gr.created_at, g.tenant_id, t.name as tenant_name,
                        CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user_name,
                        g.name as group_name,
                        (SELECT COUNT(*) FROM group_members gm WHERE gm.user_id = gr.user_id AND gm.group_id = gr.group_id) > 0 as joined
                     FROM group_recommendations gr
                     JOIN users u ON gr.user_id = u.id
                     JOIN `groups` g ON gr.group_id = g.id
                     LEFT JOIN tenants t ON g.tenant_id = t.id
                     WHERE g.tenant_id = ?
                     ORDER BY gr.created_at DESC LIMIT ? OFFSET ?",
                    [$tenantId, $limit, $offset]
                ));

                $statsRow = DB::selectOne(
                    "SELECT COUNT(*) as total, AVG(score) as avg_score,
                        SUM(CASE WHEN EXISTS(SELECT 1 FROM group_members gm WHERE gm.user_id = gr.user_id AND gm.group_id = gr.group_id) THEN 1 ELSE 0 END) as joined_count
                     FROM group_recommendations gr JOIN `groups` g ON gr.group_id = g.id WHERE g.tenant_id = ?",
                    [$tenantId]
                );
                $stats = $statsRow ? (array)$statsRow : ['total' => 0, 'avg_score' => 0, 'joined_count' => 0];
            } catch (\Throwable $e) {
                // Table doesn't exist
            }

            $formatted = array_map(fn($row) => [
                'user_id' => (int) $row['user_id'], 'user_name' => trim($row['user_name']),
                'group_id' => (int) $row['group_id'], 'group_name' => $row['group_name'],
                'tenant_id' => (int) $row['tenant_id'], 'tenant_name' => $row['tenant_name'] ?? 'Unknown',
                'score' => (float) $row['score'], 'joined' => (bool) $row['joined'], 'created_at' => $row['created_at'],
            ], $recommendations);

            $joinRate = $stats['total'] > 0 ? round(($stats['joined_count'] / $stats['total']) * 100, 1) : 0;

            return $this->respondWithData([
                'recommendations' => $formatted,
                'stats' => ['total' => (int) $stats['total'], 'avg_score' => round((float) ($stats['avg_score'] ?? 0), 2), 'join_rate' => $joinRate],
            ]);
        } catch (\Throwable $e) {
            return $this->respondWithError('RECOMMENDATIONS_ERROR', 'Failed to load recommendations: ' . $e->getMessage(), null, 500);
        }
    }

    /** GET /api/v2/admin/groups/featured */
    public function getFeaturedGroups(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            $groups = $this->smartGroupRankingService->getFeaturedGroupsWithScores('local_hubs', $tenantId);
            return $this->respondWithData($groups);
        } catch (\Throwable $e) {
            return $this->respondWithError('FEATURED_GROUPS_ERROR', 'Failed to load featured groups: ' . $e->getMessage(), null, 500);
        }
    }

    /** POST /api/v2/admin/groups/featured/update */
    public function updateFeaturedGroups(): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            $result = $this->smartGroupRankingService->updateFeaturedLocalHubs($tenantId);
            ActivityLog::log($adminId, 'admin_update_featured_groups', "Updated featured groups: {$result['featured']} groups featured, {$result['cleared']} cleared");
            return $this->respondWithData($result);
        } catch (\Throwable $e) {
            return $this->respondWithError('UPDATE_FEATURED_ERROR', 'Failed to update featured groups: ' . $e->getMessage(), null, 500);
        }
    }

    /** PUT /api/v2/admin/groups/{id}/toggle-featured */
    public function toggleFeatured($id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            $group = DB::selectOne("SELECT id, name, is_featured, tenant_id FROM `groups` WHERE id = ? AND tenant_id = ?", [(int) $id, $tenantId]);
            if (!$group) {
                return $this->respondWithError('NOT_FOUND', 'Group not found', null, 404);
            }

            $newStatus = $group->is_featured ? 0 : 1;
            DB::update("UPDATE `groups` SET is_featured = ? WHERE id = ? AND tenant_id = ?", [$newStatus, (int) $id, $tenantId]);
            ActivityLog::log($adminId, 'admin_toggle_group_featured', ($newStatus ? 'Featured' : 'Unfeatured') . " group #{$id}: {$group->name}");

            return $this->respondWithData(['is_featured' => (bool) $newStatus]);
        } catch (\Exception $e) {
            return $this->respondWithError('TOGGLE_FEATURED_ERROR', 'Failed to toggle featured status: ' . $e->getMessage(), null, 500);
        }
    }

    // =========================================================================
    // Group Analytics (legacy controller delegation)
    // =========================================================================

    /** GET /api/v2/admin/groups/{id}/analytics */
    public function apiData($id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $id = (int) $id;

        try {
            $group = DB::selectOne("SELECT id, name FROM `groups` WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
            if (!$group) {
                return $this->respondWithError('NOT_FOUND', 'Group not found', null, 404);
            }

            $memberCount = (int) (DB::selectOne("SELECT COUNT(*) as cnt FROM group_members WHERE group_id = ?", [$id])->cnt ?? 0);
            $postsCount = 0;
            try { $postsCount = (int) (DB::selectOne("SELECT COUNT(*) as cnt FROM group_posts WHERE group_id = ?", [$id])->cnt ?? 0); } catch (\Throwable $e) {}
            $eventsCount = 0;
            try { $eventsCount = (int) (DB::selectOne("SELECT COUNT(*) as cnt FROM events WHERE group_id = ?", [$id])->cnt ?? 0); } catch (\Throwable $e) {}

            return $this->respondWithData([
                'group_id' => $id,
                'group_name' => $group->name,
                'member_count' => $memberCount,
                'posts_count' => $postsCount,
                'events_count' => $eventsCount,
            ]);
        } catch (\Throwable $e) {
            return $this->respondWithError('ANALYTICS_ERROR', 'Failed to load group analytics: ' . $e->getMessage(), null, 500);
        }
    }
}
