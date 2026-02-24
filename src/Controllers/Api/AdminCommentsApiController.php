<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\ActivityLog;

/**
 * AdminCommentsApiController - V2 API for comments moderation
 *
 * Moderate comments across all content types.
 * All endpoints require admin authentication.
 * Super admins can operate across all tenants.
 *
 * Schema notes:
 * - Uses target_type/target_id (NOT content_type/content_id)
 * - NO is_hidden column - comments are either visible or deleted
 * - reports table uses target_type ENUM('listing','user','message')
 *
 * Endpoints:
 * - GET    /api/v2/admin/comments           - List comments
 * - GET    /api/v2/admin/comments/{id}      - Get comment detail
 * - POST   /api/v2/admin/comments/{id}/hide - Hide comment (via feed_hidden)
 * - DELETE /api/v2/admin/comments/{id}      - Delete comment
 */
class AdminCommentsApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/admin/comments
     *
     * Query params: page, limit, target_type, search, tenant_id (super admin only)
     */
    public function index(): void
    {
        $this->requireAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $targetType = $_GET['target_type'] ?? null;
        $search = $_GET['search'] ?? null;

        $conditions = [];
        $params = [];

        // Tenant scoping: defaults to current tenant, super admins can pass ?tenant_id=all
        $effectiveTenantId = $this->resolveAdminTenantFilter($isSuperAdmin, $tenantId);
        if ($effectiveTenantId !== null) {
            $conditions[] = 'c.tenant_id = ?';
            $params[] = $effectiveTenantId;
        }

        // Target type filter
        if ($targetType) {
            $conditions[] = 'c.target_type = ?';
            $params[] = $targetType;
        }

        // Search filter (content + user name)
        if ($search) {
            $conditions[] = '(c.content LIKE ? OR u.name LIKE ?)';
            $searchPattern = '%' . $search . '%';
            $params[] = $searchPattern;
            $params[] = $searchPattern;
        }

        $where = !empty($conditions) ? implode(' AND ', $conditions) : '1=1';

        // Get total count
        $countQuery = "SELECT COUNT(*) as total
                       FROM comments c
                       LEFT JOIN users u ON c.user_id = u.id
                       WHERE {$where}";
        $countStmt = Database::query($countQuery, $params);
        $total = (int) $countStmt->fetch()['total'];

        // Get paginated results — join tenants table for cross-tenant name display
        $query = "SELECT c.*,
                         u.name as user_name,
                         u.avatar_url as user_avatar,
                         t.name as tenant_name
                  FROM comments c
                  LEFT JOIN users u ON c.user_id = u.id
                  LEFT JOIN tenants t ON c.tenant_id = t.id
                  WHERE {$where}
                  ORDER BY c.created_at DESC
                  LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;
        $stmt = Database::query($query, $params);
        $comments = $stmt->fetchAll();

        // Format for frontend
        $formatted = array_map(function ($comment) {
            return [
                'id' => (int) $comment['id'],
                'user_id' => (int) $comment['user_id'],
                'tenant_id' => (int) $comment['tenant_id'],
                'tenant_name' => $comment['tenant_name'] ?? 'Unknown',
                'user_name' => $comment['user_name'] ?? 'Unknown',
                'user_avatar' => $comment['user_avatar'],
                'target_type' => $comment['target_type'],
                'target_id' => (int) $comment['target_id'],
                'parent_id' => $comment['parent_id'] ? (int) $comment['parent_id'] : null,
                'content' => $comment['content'],
                'created_at' => $comment['created_at'],
                'updated_at' => $comment['updated_at'] ?? $comment['created_at'],
            ];
        }, $comments);

        $this->respondWithPaginatedCollection($formatted, $total, $page, $limit);
    }

    /**
     * GET /api/v2/admin/comments/{id}
     */
    public function show(int $id): void
    {
        $this->requireAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        // Tenant scoping: defaults to current tenant, super admins can pass ?tenant_id=all
        $effectiveTenantId = $this->resolveAdminTenantFilter($isSuperAdmin, $tenantId);
        $tenantWhere = $effectiveTenantId !== null ? 'AND c.tenant_id = ?' : '';
        $tenantParams = $effectiveTenantId !== null ? [$effectiveTenantId] : [];

        $query = "SELECT c.*,
                         u.name as user_name,
                         u.email as user_email,
                         u.avatar_url as user_avatar,
                         t.name as tenant_name
                  FROM comments c
                  LEFT JOIN users u ON c.user_id = u.id
                  LEFT JOIN tenants t ON c.tenant_id = t.id
                  WHERE c.id = ? {$tenantWhere}";
        $stmt = Database::query($query, array_merge([$id], $tenantParams));
        $comment = $stmt->fetch();

        if (!$comment) {
            $this->respondWithError('NOT_FOUND', 'Comment not found', null, 404);
            return;
        }

        $formatted = [
            'id' => (int) $comment['id'],
            'user_id' => (int) $comment['user_id'],
            'tenant_id' => (int) $comment['tenant_id'],
            'tenant_name' => $comment['tenant_name'] ?? 'Unknown',
            'user_name' => $comment['user_name'] ?? 'Unknown',
            'user_email' => $comment['user_email'],
            'user_avatar' => $comment['user_avatar'],
            'target_type' => $comment['target_type'],
            'target_id' => (int) $comment['target_id'],
            'parent_id' => $comment['parent_id'] ? (int) $comment['parent_id'] : null,
            'content' => $comment['content'],
            'created_at' => $comment['created_at'],
            'updated_at' => $comment['updated_at'] ?? $comment['created_at'],
        ];

        $this->respondWithData($formatted);
    }

    /**
     * POST /api/v2/admin/comments/{id}/hide
     * Hides a comment using the feed_hidden table (target_type='comment')
     */
    public function hide(int $id): void
    {
        $this->requireAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();
        $adminId = $this->getAuthenticatedUserId();

        // Tenant scoping: defaults to current tenant, super admins can pass ?tenant_id=all
        $effectiveTenantId = $this->resolveAdminTenantFilter($isSuperAdmin, $tenantId);
        $tenantWhere = $effectiveTenantId !== null ? 'AND tenant_id = ?' : '';
        $tenantParams = $effectiveTenantId !== null ? [$effectiveTenantId] : [];

        $stmt = Database::query(
            "SELECT id, target_type, target_id, tenant_id FROM comments WHERE id = ? {$tenantWhere}",
            array_merge([$id], $tenantParams)
        );
        $comment = $stmt->fetch();

        if (!$comment) {
            $this->respondWithError('NOT_FOUND', 'Comment not found', null, 404);
            return;
        }

        // Use the comment's own tenant_id for the feed_hidden record
        $commentTenantId = (int) $comment['tenant_id'];

        // Insert into feed_hidden table with target_type='comment'
        Database::query(
            "INSERT IGNORE INTO feed_hidden (user_id, tenant_id, target_type, target_id, created_at)
             VALUES (?, ?, 'comment', ?, NOW())",
            [$adminId, $commentTenantId, $id]
        );

        // Log activity
        ActivityLog::log(
            $adminId,
            'hide_comment',
            "Hidden comment #{$id} on {$comment['target_type']} #{$comment['target_id']}" . ($isSuperAdmin ? " (tenant {$commentTenantId})" : '')
        );

        $this->respondWithData(['success' => true, 'message' => 'Comment hidden']);
    }

    /**
     * DELETE /api/v2/admin/comments/{id}
     */
    public function destroy(int $id): void
    {
        $this->requireAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();
        $adminId = $this->getAuthenticatedUserId();

        // Tenant scoping: defaults to current tenant, super admins can pass ?tenant_id=all
        $effectiveTenantId = $this->resolveAdminTenantFilter($isSuperAdmin, $tenantId);
        $tenantWhere = $effectiveTenantId !== null ? 'AND tenant_id = ?' : '';
        $tenantParams = $effectiveTenantId !== null ? [$effectiveTenantId] : [];

        $stmt = Database::query(
            "SELECT id, target_type, target_id, user_id, tenant_id FROM comments WHERE id = ? {$tenantWhere}",
            array_merge([$id], $tenantParams)
        );
        $comment = $stmt->fetch();

        if (!$comment) {
            $this->respondWithError('NOT_FOUND', 'Comment not found', null, 404);
            return;
        }

        $commentTenantId = (int) $comment['tenant_id'];

        // Delete comment (and child comments due to CASCADE) using the comment's own tenant_id for safety
        Database::query(
            "DELETE FROM comments WHERE id = ? AND tenant_id = ?",
            [$id, $commentTenantId]
        );

        // Also clean up any feed_hidden records for this comment
        Database::query(
            "DELETE FROM feed_hidden WHERE target_type = 'comment' AND target_id = ? AND tenant_id = ?",
            [$id, $commentTenantId]
        );

        // Log activity
        ActivityLog::log(
            $adminId,
            'delete_comment',
            "Deleted comment #{$id} on {$comment['target_type']} #{$comment['target_id']}" . ($isSuperAdmin ? " (tenant {$commentTenantId})" : '')
        );

        $this->respondWithData(['success' => true, 'message' => 'Comment deleted']);
    }
}
