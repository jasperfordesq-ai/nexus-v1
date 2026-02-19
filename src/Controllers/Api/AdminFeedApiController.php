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
 * AdminFeedApiController - V2 API for feed posts moderation
 *
 * Moderate feed posts (hide/delete).
 * All endpoints require admin authentication.
 *
 * Schema notes:
 * - feed_posts has NO is_hidden/is_flagged columns
 * - Hiding is tracked via feed_hidden table (user_id, tenant_id, target_type, target_id)
 * - reports.target_type ENUM('listing','user','message') - does NOT include 'post'
 *
 * Endpoints:
 * - GET    /api/v2/admin/feed/posts         - List feed posts
 * - GET    /api/v2/admin/feed/posts/{id}    - Get post detail
 * - POST   /api/v2/admin/feed/posts/{id}/hide  - Hide post
 * - DELETE /api/v2/admin/feed/posts/{id}    - Delete post
 * - GET    /api/v2/admin/feed/stats          - Get moderation stats
 */
class AdminFeedApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/admin/feed/posts
     *
     * Query params: page, limit, type, user_id, search, is_hidden
     */
    public function index(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $type = $_GET['type'] ?? null;
        $userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : null;
        $search = $_GET['search'] ?? null;
        $isHidden = isset($_GET['is_hidden']) ? (bool) $_GET['is_hidden'] : null;

        $conditions = ['fp.tenant_id = ?'];
        $params = [$tenantId];

        // Type filter
        if ($type) {
            $conditions[] = 'fp.type = ?';
            $params[] = $type;
        }

        // User filter
        if ($userId) {
            $conditions[] = 'fp.user_id = ?';
            $params[] = $userId;
        }

        // Hidden filter
        if ($isHidden !== null) {
            if ($isHidden) {
                $conditions[] = 'fh.id IS NOT NULL';
            } else {
                $conditions[] = 'fh.id IS NULL';
            }
        }

        // Search filter
        if ($search) {
            $conditions[] = '(fp.content LIKE ? OR u.name LIKE ?)';
            $searchPattern = '%' . $search . '%';
            $params[] = $searchPattern;
            $params[] = $searchPattern;
        }

        $where = implode(' AND ', $conditions);

        // Get total count
        $countQuery = "SELECT COUNT(*) as total
                       FROM feed_posts fp
                       LEFT JOIN users u ON fp.user_id = u.id
                       LEFT JOIN feed_hidden fh ON fh.target_type = 'post' AND fh.target_id = fp.id AND fh.tenant_id = fp.tenant_id
                       WHERE {$where}";
        $countStmt = Database::query($countQuery, $params);
        $total = (int) $countStmt->fetch()['total'];

        // Get paginated results
        $query = "SELECT fp.*,
                         u.name as user_name,
                         u.avatar_url as user_avatar,
                         (fh.id IS NOT NULL) as is_hidden,
                         (SELECT COUNT(*) FROM comments WHERE target_type = 'post' AND target_id = fp.id) as comments_count
                  FROM feed_posts fp
                  LEFT JOIN users u ON fp.user_id = u.id
                  LEFT JOIN feed_hidden fh ON fh.target_type = 'post' AND fh.target_id = fp.id AND fh.tenant_id = fp.tenant_id
                  WHERE {$where}
                  ORDER BY fp.created_at DESC
                  LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;
        $stmt = Database::query($query, $params);
        $posts = $stmt->fetchAll();

        // Format for frontend
        $formatted = array_map(function ($post) {
            return [
                'id' => (int) $post['id'],
                'user_id' => (int) $post['user_id'],
                'user_name' => $post['user_name'] ?? 'Unknown',
                'user_avatar' => $post['user_avatar'],
                'type' => $post['type'],
                'content' => $post['content'],
                'image_url' => $post['image_url'],
                'video_url' => $post['video_url'],
                'likes_count' => (int) $post['likes_count'],
                'comments_count' => (int) ($post['comments_count'] ?? 0),
                'visibility' => $post['visibility'],
                'is_hidden' => (bool) ($post['is_hidden'] ?? false),
                'created_at' => $post['created_at'],
            ];
        }, $posts);

        $this->respondWithPaginatedCollection($formatted, $total, $page, $limit);
    }

    /**
     * GET /api/v2/admin/feed/posts/{id}
     */
    public function show(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $query = "SELECT fp.*,
                         u.name as user_name,
                         u.email as user_email,
                         u.avatar_url as user_avatar,
                         (fh.id IS NOT NULL) as is_hidden
                  FROM feed_posts fp
                  LEFT JOIN users u ON fp.user_id = u.id
                  LEFT JOIN feed_hidden fh ON fh.target_type = 'post' AND fh.target_id = fp.id AND fh.tenant_id = fp.tenant_id
                  WHERE fp.id = ? AND fp.tenant_id = ?";

        $stmt = Database::query($query, [$id, $tenantId]);
        $post = $stmt->fetch();

        if (!$post) {
            $this->respondWithError('NOT_FOUND', 'Post not found', null, 404);
            return;
        }

        // Get comments
        $commentsStmt = Database::query(
            "SELECT c.*, u.name as user_name
             FROM comments c
             LEFT JOIN users u ON c.user_id = u.id
             WHERE c.target_type = 'post' AND c.target_id = ? AND c.tenant_id = ?
             ORDER BY c.created_at DESC
             LIMIT 10",
            [$id, $tenantId]
        );
        $post['recent_comments'] = $commentsStmt->fetchAll();

        $formatted = [
            'id' => (int) $post['id'],
            'user_id' => (int) $post['user_id'],
            'user_name' => $post['user_name'] ?? 'Unknown',
            'user_email' => $post['user_email'],
            'user_avatar' => $post['user_avatar'],
            'type' => $post['type'],
            'content' => $post['content'],
            'image_url' => $post['image_url'],
            'video_url' => $post['video_url'],
            'likes_count' => (int) $post['likes_count'],
            'visibility' => $post['visibility'],
            'is_hidden' => (bool) ($post['is_hidden'] ?? false),
            'created_at' => $post['created_at'],
            'recent_comments' => $post['recent_comments'],
        ];

        $this->respondWithData($formatted);
    }

    /**
     * POST /api/v2/admin/feed/posts/{id}/hide
     */
    public function hide(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $adminId = $this->getAuthenticatedUserId();

        // Verify post exists
        $stmt = Database::query(
            "SELECT id, user_id FROM feed_posts WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );
        $post = $stmt->fetch();

        if (!$post) {
            $this->respondWithError('NOT_FOUND', 'Post not found', null, 404);
            return;
        }

        // Insert into feed_hidden table
        Database::query(
            "INSERT IGNORE INTO feed_hidden (user_id, tenant_id, target_type, target_id, created_at)
             VALUES (?, ?, 'post', ?, NOW())",
            [$adminId, $tenantId, $id]
        );

        // Log activity
        ActivityLog::log(
            $adminId,
            'hide_feed_post',
            "Hidden feed post #{$id}"
        );

        $this->respondWithData(['success' => true, 'message' => 'Post hidden']);
    }

    /**
     * DELETE /api/v2/admin/feed/posts/{id}
     */
    public function destroy(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $adminId = $this->getAuthenticatedUserId();

        // Verify post exists
        $stmt = Database::query(
            "SELECT id FROM feed_posts WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );
        $post = $stmt->fetch();

        if (!$post) {
            $this->respondWithError('NOT_FOUND', 'Post not found', null, 404);
            return;
        }

        // Delete post
        Database::query(
            "DELETE FROM feed_posts WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        // Log activity
        ActivityLog::log(
            $adminId,
            'delete_feed_post',
            "Deleted feed post #{$id}"
        );

        $this->respondWithData(['success' => true, 'message' => 'Post deleted']);
    }

    /**
     * GET /api/v2/admin/feed/stats
     */
    public function stats(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $query = "SELECT
                    COUNT(DISTINCT fp.id) as total,
                    (SELECT COUNT(DISTINCT fh.target_id) FROM feed_hidden fh
                     WHERE fh.target_type = 'post' AND fh.tenant_id = ?) as hidden,
                    (SELECT COUNT(*) FROM comments WHERE target_type = 'post' AND tenant_id = ?) as total_comments
                  FROM feed_posts fp
                  WHERE fp.tenant_id = ?";

        $stmt = Database::query($query, [$tenantId, $tenantId, $tenantId]);
        $stats = $stmt->fetch();

        $formatted = [
            'total' => (int) ($stats['total'] ?? 0),
            'hidden' => (int) ($stats['hidden'] ?? 0),
            'total_comments' => (int) ($stats['total_comments'] ?? 0),
        ];

        $this->respondWithData($formatted);
    }
}
