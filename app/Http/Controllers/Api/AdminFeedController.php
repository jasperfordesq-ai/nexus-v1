<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Models\ActivityLog;

/**
 * AdminFeedController -- Admin feed post moderation.
 *
 * Moderate feed posts (hide/delete).
 * All endpoints require admin authentication.
 */
class AdminFeedController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * Check if the current user is a super admin.
     */
    private function isSuperAdmin(): bool
    {
        $userId = $this->getUserId();
        $user = DB::selectOne(
            "SELECT is_super_admin, is_tenant_super_admin FROM users WHERE id = ?",
            [$userId]
        );
        return $user && (!empty($user->is_super_admin) || !empty($user->is_tenant_super_admin));
    }

    /**
     * Resolve effective tenant ID for admin filtering.
     */
    private function resolveEffectiveTenantId(bool $isSuperAdmin, int $tenantId): ?int
    {
        $filterRaw = $this->query('tenant_id');

        if ($isSuperAdmin) {
            if ($filterRaw === 'all') {
                return null;
            }
            if ($filterRaw !== null && is_numeric($filterRaw)) {
                return (int) $filterRaw;
            }
            return $tenantId;
        }

        return $tenantId;
    }

    /**
     * GET /api/v2/admin/feed
     *
     * Query params: page, limit, type, user_id, search, is_hidden
     */
    public function index(): JsonResponse
    {
        $this->requireAdmin();
        $superAdmin = $this->isSuperAdmin();
        $tenantId = $this->getTenantId();

        $page = $this->queryInt('page', 1, 1);
        $limit = $this->queryInt('limit', 20, 1, 100);
        $offset = ($page - 1) * $limit;
        $type = $this->query('type');
        $userId = $this->queryInt('user_id');
        $search = $this->query('search');
        $isHidden = $this->query('is_hidden');

        $conditions = [];
        $params = [];

        $effectiveTenantId = $this->resolveEffectiveTenantId($superAdmin, $tenantId);
        if ($effectiveTenantId !== null) {
            $conditions[] = 'fp.tenant_id = ?';
            $params[] = $effectiveTenantId;
        }

        if ($type) {
            $conditions[] = 'fp.type = ?';
            $params[] = $type;
        }

        if ($userId) {
            $conditions[] = 'fp.user_id = ?';
            $params[] = $userId;
        }

        if ($isHidden !== null) {
            if (filter_var($isHidden, FILTER_VALIDATE_BOOLEAN)) {
                $conditions[] = 'fp.is_hidden = 1';
            } else {
                $conditions[] = 'fp.is_hidden = 0';
            }
        }

        if ($search) {
            $conditions[] = '(fp.content LIKE ? OR u.name LIKE ?)';
            $searchPattern = '%' . $search . '%';
            $params[] = $searchPattern;
            $params[] = $searchPattern;
        }

        $where = !empty($conditions) ? implode(' AND ', $conditions) : '1=1';

        $total = (int) DB::selectOne(
            "SELECT COUNT(*) as total
             FROM feed_posts fp
             LEFT JOIN users u ON fp.user_id = u.id
             WHERE {$where}",
            $params
        )->total;

        $posts = DB::select(
            "SELECT fp.*,
                    u.name as user_name, u.avatar_url as user_avatar,
                    t.name as tenant_name,
                    (SELECT COUNT(*) FROM comments WHERE target_type = 'post' AND target_id = fp.id) as comments_count
             FROM feed_posts fp
             LEFT JOIN users u ON fp.user_id = u.id
             LEFT JOIN tenants t ON fp.tenant_id = t.id
             WHERE {$where}
             ORDER BY fp.created_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        );

        $formatted = array_map(function ($post) {
            return [
                'id' => (int) $post->id,
                'user_id' => (int) $post->user_id,
                'tenant_id' => (int) $post->tenant_id,
                'tenant_name' => $post->tenant_name ?? 'Unknown',
                'user_name' => $post->user_name ?? 'Unknown',
                'user_avatar' => $post->user_avatar,
                'type' => $post->type ?? 'post',
                'content' => $post->content,
                'image_url' => $post->image ?? null,
                'likes_count' => (int) ($post->likes_count ?? 0),
                'comments_count' => (int) ($post->comments_count ?? 0),
                'visibility' => $post->visibility ?? 'public',
                'is_hidden' => (bool) ($post->is_hidden ?? false),
                'created_at' => $post->created_at,
            ];
        }, $posts);

        return $this->respondWithPaginatedCollection($formatted, $total, $page, $limit);
    }

    /**
     * GET /api/v2/admin/feed/{id}
     */
    public function show(int $id): JsonResponse
    {
        $this->requireAdmin();
        $superAdmin = $this->isSuperAdmin();
        $tenantId = $this->getTenantId();

        $effectiveTenantId = $this->resolveEffectiveTenantId($superAdmin, $tenantId);
        $tenantWhere = $effectiveTenantId !== null ? 'fp.tenant_id = ?' : '1=1';
        $tenantParams = $effectiveTenantId !== null ? [$effectiveTenantId] : [];

        $post = DB::selectOne(
            "SELECT fp.*,
                    u.name as user_name, u.email as user_email, u.avatar_url as user_avatar,
                    t.name as tenant_name
             FROM feed_posts fp
             LEFT JOIN users u ON fp.user_id = u.id
             LEFT JOIN tenants t ON fp.tenant_id = t.id
             WHERE fp.id = ? AND {$tenantWhere}",
            array_merge([$id], $tenantParams)
        );

        if (!$post) {
            return $this->respondWithError('NOT_FOUND', 'Post not found', null, 404);
        }

        $postTenantId = (int) $post->tenant_id;
        $recentComments = DB::select(
            "SELECT c.*, u.name as user_name
             FROM comments c
             LEFT JOIN users u ON c.user_id = u.id
             WHERE c.target_type = 'post' AND c.target_id = ? AND c.tenant_id = ?
             ORDER BY c.created_at DESC
             LIMIT 10",
            [$id, $postTenantId]
        );

        return $this->respondWithData([
            'id' => (int) $post->id,
            'user_id' => (int) $post->user_id,
            'tenant_id' => (int) $post->tenant_id,
            'tenant_name' => $post->tenant_name ?? 'Unknown',
            'user_name' => $post->user_name ?? 'Unknown',
            'user_email' => $post->user_email,
            'user_avatar' => $post->user_avatar,
            'type' => $post->type ?? 'post',
            'content' => $post->content,
            'image_url' => $post->image ?? null,
            'likes_count' => (int) ($post->likes_count ?? 0),
            'visibility' => $post->visibility ?? 'public',
            'is_hidden' => (bool) ($post->is_hidden ?? false),
            'created_at' => $post->created_at,
            'recent_comments' => $recentComments,
        ]);
    }

    /**
     * POST /api/v2/admin/feed/{id}/hide
     */
    public function hide(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $superAdmin = $this->isSuperAdmin();
        $tenantId = $this->getTenantId();

        $effectiveTenantId = $this->resolveEffectiveTenantId($superAdmin, $tenantId);
        $tenantWhere = $effectiveTenantId !== null ? 'tenant_id = ?' : '1=1';
        $tenantParams = $effectiveTenantId !== null ? [$effectiveTenantId] : [];

        $post = DB::selectOne(
            "SELECT id, user_id, tenant_id FROM feed_posts WHERE id = ? AND {$tenantWhere}",
            array_merge([$id], $tenantParams)
        );

        if (!$post) {
            return $this->respondWithError('NOT_FOUND', 'Post not found', null, 404);
        }

        $postTenantId = (int) $post->tenant_id;

        // Global admin hide — set is_hidden on the post itself (hides from all users)
        DB::update(
            "UPDATE feed_posts SET is_hidden = 1 WHERE id = ? AND tenant_id = ?",
            [$id, $postTenantId]
        );

        // Also hide from feed_activity so it doesn't appear in the main feed
        DB::update(
            "UPDATE feed_activity SET is_hidden = 1, is_visible = 0 WHERE source_type = 'post' AND source_id = ? AND tenant_id = ?",
            [$id, $postTenantId]
        );

        ActivityLog::log(
            $adminId,
            'hide_feed_post',
            "Hidden feed post #{$id}" . ($superAdmin ? " (tenant {$postTenantId})" : '')
        );

        return $this->respondWithData(['success' => true, 'message' => 'Post hidden']);
    }

    /**
     * DELETE /api/v2/admin/feed/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $superAdmin = $this->isSuperAdmin();
        $tenantId = $this->getTenantId();

        $effectiveTenantId = $this->resolveEffectiveTenantId($superAdmin, $tenantId);
        $tenantWhere = $effectiveTenantId !== null ? 'tenant_id = ?' : '1=1';
        $tenantParams = $effectiveTenantId !== null ? [$effectiveTenantId] : [];

        $post = DB::selectOne(
            "SELECT id, tenant_id FROM feed_posts WHERE id = ? AND {$tenantWhere}",
            array_merge([$id], $tenantParams)
        );

        if (!$post) {
            return $this->respondWithError('NOT_FOUND', 'Post not found', null, 404);
        }

        $postTenantId = (int) $post->tenant_id;

        DB::delete("DELETE FROM feed_posts WHERE id = ? AND tenant_id = ?", [$id, $postTenantId]);
        DB::delete("DELETE FROM feed_hidden WHERE target_type = 'post' AND target_id = ? AND tenant_id = ?", [$id, $postTenantId]);
        DB::delete("DELETE FROM feed_activity WHERE source_type = 'post' AND source_id = ? AND tenant_id = ?", [$id, $postTenantId]);
        DB::delete("DELETE FROM likes WHERE target_type = 'post' AND target_id = ? AND tenant_id = ?", [$id, $postTenantId]);
        DB::delete("DELETE FROM comments WHERE target_type = 'post' AND target_id = ? AND tenant_id = ?", [$id, $postTenantId]);

        ActivityLog::log(
            $adminId,
            'delete_feed_post',
            "Deleted feed post #{$id}" . ($superAdmin ? " (tenant {$postTenantId})" : '')
        );

        return $this->respondWithData(['success' => true, 'message' => 'Post deleted']);
    }

    /**
     * GET /api/v2/admin/feed/stats
     */
    public function stats(): JsonResponse
    {
        $this->requireAdmin();
        $superAdmin = $this->isSuperAdmin();
        $tenantId = $this->getTenantId();

        $effectiveTenantId = $this->resolveEffectiveTenantId($superAdmin, $tenantId);

        if ($effectiveTenantId !== null) {
            $stats = DB::selectOne(
                "SELECT
                    COUNT(*) as total,
                    SUM(fp.is_hidden) as hidden,
                    (SELECT COUNT(*) FROM comments WHERE target_type = 'post' AND tenant_id = ?) as total_comments
                 FROM feed_posts fp
                 WHERE fp.tenant_id = ?",
                [$effectiveTenantId, $effectiveTenantId]
            );
        } else {
            $stats = DB::selectOne(
                "SELECT
                    COUNT(*) as total,
                    SUM(fp.is_hidden) as hidden,
                    (SELECT COUNT(*) FROM comments WHERE target_type = 'post') as total_comments
                 FROM feed_posts fp"
            );
        }

        return $this->respondWithData([
            'total' => (int) ($stats->total ?? 0),
            'hidden' => (int) ($stats->hidden ?? 0),
            'total_comments' => (int) ($stats->total_comments ?? 0),
        ]);
    }
}
