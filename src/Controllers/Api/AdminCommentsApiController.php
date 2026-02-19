<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\ActivityLog;

/**
 * AdminCommentsApiController - V2 API for comments moderation
 *
 * Moderate comments across all content types.
 * All endpoints require admin authentication.
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
     * Query params: page, limit, target_type, search
     */
    public function index(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $targetType = $_GET['target_type'] ?? null;
        $search = $_GET['search'] ?? null;

        $conditions = ['c.tenant_id = ?'];
        $params = [$tenantId];

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

        $where = implode(' AND ', $conditions);

        // Get total count
        $countQuery = "SELECT COUNT(*) as total
                       FROM comments c
                       LEFT JOIN users u ON c.user_id = u.id
                       WHERE {$where}";
        $countStmt = Database::query($countQuery, $params);
        $total = (int) $countStmt->fetch()['total'];

        // Get paginated results
        $query = "SELECT c.*,
                         u.name as user_name,
                         u.avatar_url as user_avatar
                  FROM comments c
                  LEFT JOIN users u ON c.user_id = u.id
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
        $tenantId = TenantContext::getId();

        $query = "SELECT c.*,
                         u.name as user_name,
                         u.email as user_email,
                         u.avatar_url as user_avatar
                  FROM comments c
                  LEFT JOIN users u ON c.user_id = u.id
                  WHERE c.id = ? AND c.tenant_id = ?";

        $stmt = Database::query($query, [$id, $tenantId]);
        $comment = $stmt->fetch();

        if (!$comment) {
            $this->respondWithError('NOT_FOUND', 'Comment not found', null, 404);
            return;
        }

        $formatted = [
            'id' => (int) $comment['id'],
            'user_id' => (int) $comment['user_id'],
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
        $tenantId = TenantContext::getId();
        $adminId = $this->getAuthenticatedUserId();

        // Verify comment exists
        $stmt = Database::query(
            "SELECT id, target_type, target_id FROM comments WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );
        $comment = $stmt->fetch();

        if (!$comment) {
            $this->respondWithError('NOT_FOUND', 'Comment not found', null, 404);
            return;
        }

        // Insert into feed_hidden table with target_type='comment'
        Database::query(
            "INSERT IGNORE INTO feed_hidden (user_id, tenant_id, target_type, target_id, created_at)
             VALUES (?, ?, 'comment', ?, NOW())",
            [$adminId, $tenantId, $id]
        );

        // Log activity
        ActivityLog::log(
            $adminId,
            'hide_comment',
            "Hidden comment #{$id} on {$comment['target_type']} #{$comment['target_id']}"
        );

        $this->respondWithData(['success' => true, 'message' => 'Comment hidden']);
    }

    /**
     * DELETE /api/v2/admin/comments/{id}
     */
    public function destroy(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $adminId = $this->getAuthenticatedUserId();

        // Verify comment exists
        $stmt = Database::query(
            "SELECT id, target_type, user_id FROM comments WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );
        $comment = $stmt->fetch();

        if (!$comment) {
            $this->respondWithError('NOT_FOUND', 'Comment not found', null, 404);
            return;
        }

        // Delete comment (and child comments due to CASCADE)
        Database::query(
            "DELETE FROM comments WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        // Log activity
        ActivityLog::log(
            $adminId,
            'delete_comment',
            "Deleted comment #{$id} on {$comment['target_type']} #{$comment['target_id']}"
        );

        $this->respondWithData(['success' => true, 'message' => 'Comment deleted']);
    }
}
