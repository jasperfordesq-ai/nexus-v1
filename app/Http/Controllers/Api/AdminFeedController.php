<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\ActivityLog;
use App\Models\Notification;

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
     * Queries feed_activity (the unified feed table) so admins see ALL feed
     * items — posts, listings, events, polls, goals, jobs, challenges, etc.
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
            $conditions[] = 'fa.tenant_id = ?';
            $params[] = $effectiveTenantId;
        }

        if ($type) {
            $conditions[] = 'fa.source_type = ?';
            $params[] = $type;
        }

        if ($userId) {
            $conditions[] = 'fa.user_id = ?';
            $params[] = $userId;
        }

        if ($isHidden !== null) {
            if (filter_var($isHidden, FILTER_VALIDATE_BOOLEAN)) {
                $conditions[] = 'fa.is_hidden = 1';
            } else {
                $conditions[] = 'fa.is_hidden = 0';
            }
        }

        if ($search) {
            $conditions[] = '(fa.content LIKE ? OR COALESCE(NULLIF(u.name, \'\'), CONCAT(u.first_name, \' \', u.last_name)) LIKE ?)';
            $searchPattern = '%' . $search . '%';
            $params[] = $searchPattern;
            $params[] = $searchPattern;
        }

        $where = !empty($conditions) ? implode(' AND ', $conditions) : '1=1';

        $total = (int) DB::selectOne(
            "SELECT COUNT(*) as total
             FROM feed_activity fa
             LEFT JOIN users u ON fa.user_id = u.id
             WHERE {$where}",
            $params
        )->total;

        // Use correlated subqueries instead of full-table GROUP BY derived tables
        // to avoid materializing the entire comments/likes tables on every request
        $rows = DB::select(
            "SELECT fa.id as activity_id, fa.source_type, fa.source_id, fa.user_id,
                    fa.tenant_id, fa.title, fa.content, fa.image_url,
                    fa.is_hidden, fa.is_visible, fa.created_at,
                    COALESCE(NULLIF(u.name, ''), CONCAT(u.first_name, ' ', u.last_name)) as user_name,
                    u.avatar_url as user_avatar,
                    t.name as tenant_name,
                    (SELECT COUNT(*) FROM comments c WHERE c.target_type = fa.source_type AND c.target_id = fa.source_id AND c.tenant_id = fa.tenant_id) as comments_count,
                    (SELECT COUNT(*) FROM likes l WHERE l.target_type = fa.source_type AND l.target_id = fa.source_id AND l.tenant_id = fa.tenant_id) as likes_count
             FROM feed_activity fa
             LEFT JOIN users u ON fa.user_id = u.id
             LEFT JOIN tenants t ON fa.tenant_id = t.id
             WHERE {$where}
             ORDER BY fa.created_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        );

        $formatted = array_map(function ($row) {
            return [
                'id' => (int) $row->source_id,
                'activity_id' => (int) $row->activity_id,
                'user_id' => (int) $row->user_id,
                'tenant_id' => (int) $row->tenant_id,
                'tenant_name' => $row->tenant_name ?? 'Unknown',
                'user_name' => $row->user_name ?? 'Unknown',
                'user_avatar' => $row->user_avatar,
                'type' => $row->source_type ?? 'post',
                'content' => $row->content,
                'image_url' => $row->image_url ?? null,
                'likes_count' => (int) ($row->likes_count ?? 0),
                'comments_count' => (int) ($row->comments_count ?? 0),
                'is_hidden' => (bool) ($row->is_hidden ?? false),
                'created_at' => $row->created_at,
            ];
        }, $rows);

        return $this->respondWithPaginatedCollection($formatted, $total, $page, $limit);
    }

    /**
     * GET /api/v2/admin/feed/{id}
     *
     * Accepts query param ?type=<source_type> to look up non-post items.
     * Defaults to 'post' for backwards compatibility.
     */
    public function show(int $id): JsonResponse
    {
        $this->requireAdmin();
        $superAdmin = $this->isSuperAdmin();
        $tenantId = $this->getTenantId();
        $sourceType = $this->query('type', 'post');

        $effectiveTenantId = $this->resolveEffectiveTenantId($superAdmin, $tenantId);
        $tenantWhere = $effectiveTenantId !== null ? 'fa.tenant_id = ?' : '1=1';
        $tenantParams = $effectiveTenantId !== null ? [$effectiveTenantId] : [];

        $row = DB::selectOne(
            "SELECT fa.id as activity_id, fa.source_type, fa.source_id, fa.user_id,
                    fa.tenant_id, fa.title, fa.content, fa.image_url,
                    fa.is_hidden, fa.is_visible, fa.created_at,
                    COALESCE(NULLIF(u.name, ''), CONCAT(u.first_name, ' ', u.last_name)) as user_name,
                    u.email as user_email, u.avatar_url as user_avatar,
                    t.name as tenant_name
             FROM feed_activity fa
             LEFT JOIN users u ON fa.user_id = u.id
             LEFT JOIN tenants t ON fa.tenant_id = t.id
             WHERE fa.source_id = ? AND fa.source_type = ? AND {$tenantWhere}",
            array_merge([$id, $sourceType], $tenantParams)
        );

        if (!$row) {
            return $this->respondWithError('NOT_FOUND', __('api.post_not_found'), null, 404);
        }

        $rowTenantId = (int) $row->tenant_id;
        $recentComments = DB::select(
            "SELECT c.*, u.name as user_name
             FROM comments c
             LEFT JOIN users u ON c.user_id = u.id
             WHERE c.target_type = ? AND c.target_id = ? AND c.tenant_id = ?
             ORDER BY c.created_at DESC
             LIMIT 10",
            [$row->source_type, $id, $rowTenantId]
        );

        return $this->respondWithData([
            'id' => (int) $row->source_id,
            'activity_id' => (int) $row->activity_id,
            'user_id' => (int) $row->user_id,
            'tenant_id' => (int) $row->tenant_id,
            'tenant_name' => $row->tenant_name ?? 'Unknown',
            'user_name' => $row->user_name ?? 'Unknown',
            'user_email' => $row->user_email,
            'user_avatar' => $row->user_avatar,
            'type' => $row->source_type ?? 'post',
            'content' => $row->content,
            'image_url' => $row->image_url ?? null,
            'is_hidden' => (bool) ($row->is_hidden ?? false),
            'created_at' => $row->created_at,
            'recent_comments' => $recentComments,
        ]);
    }

    /**
     * POST /api/v2/admin/feed/{id}/hide
     *
     * Accepts JSON body { "type": "listing" } to hide non-post items.
     * Defaults to 'post' for backwards compatibility.
     */
    public function hide(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $superAdmin = $this->isSuperAdmin();
        $tenantId = $this->getTenantId();
        $sourceType = $this->input('type') ?? $this->query('type', 'post');

        $effectiveTenantId = $this->resolveEffectiveTenantId($superAdmin, $tenantId);
        $tenantWhere = $effectiveTenantId !== null ? 'tenant_id = ?' : '1=1';
        $tenantParams = $effectiveTenantId !== null ? [$effectiveTenantId] : [];

        $row = DB::selectOne(
            "SELECT id, tenant_id FROM feed_activity WHERE source_type = ? AND source_id = ? AND {$tenantWhere}",
            array_merge([$sourceType, $id], $tenantParams)
        );

        if (!$row) {
            return $this->respondWithError('NOT_FOUND', __('api.feed_item_not_found'), null, 404);
        }

        $itemTenantId = (int) $row->tenant_id;

        // Hide from feed_activity (hides from main feed for all users)
        DB::update(
            "UPDATE feed_activity SET is_hidden = 1, is_visible = 0 WHERE source_type = ? AND source_id = ? AND tenant_id = ?",
            [$sourceType, $id, $itemTenantId]
        );

        // If the source is a post, also set is_hidden on the feed_posts row
        if ($sourceType === 'post') {
            DB::update(
                "UPDATE feed_posts SET is_hidden = 1 WHERE id = ? AND tenant_id = ?",
                [$id, $itemTenantId]
            );
        }

        ActivityLog::log(
            $adminId,
            'hide_feed_item',
            "Hidden feed {$sourceType} #{$id}" . ($superAdmin ? " (tenant {$itemTenantId})" : '')
        );

        // Notify the content creator that their post was hidden
        try {
            $feedItem = DB::selectOne(
                "SELECT user_id FROM feed_activity WHERE source_type = ? AND source_id = ? AND tenant_id = ? LIMIT 1",
                [$sourceType, $id, $itemTenantId]
            );
            if ($feedItem && $feedItem->user_id) {
                Notification::createNotification(
                    (int) $feedItem->user_id,
                    'Your post has been hidden by a moderator.',
                    null,
                    'moderation',
                    false,
                    $itemTenantId
                );
            }
        } catch (\Throwable $e) {
            Log::warning("AdminFeedController::hide notification failed for {$sourceType} #{$id}: " . $e->getMessage());
        }

        return $this->respondWithData(['success' => true, 'message' => 'Item hidden']);
    }

    /**
     * DELETE /api/v2/admin/feed/{id}
     *
     * Accepts query param ?type=<source_type> to delete non-post items.
     * Defaults to 'post' for backwards compatibility.
     */
    public function destroy(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $superAdmin = $this->isSuperAdmin();
        $tenantId = $this->getTenantId();
        $sourceType = $this->query('type', 'post');

        $effectiveTenantId = $this->resolveEffectiveTenantId($superAdmin, $tenantId);
        $tenantWhere = $effectiveTenantId !== null ? 'tenant_id = ?' : '1=1';
        $tenantParams = $effectiveTenantId !== null ? [$effectiveTenantId] : [];

        $row = DB::selectOne(
            "SELECT id, tenant_id FROM feed_activity WHERE source_type = ? AND source_id = ? AND {$tenantWhere}",
            array_merge([$sourceType, $id], $tenantParams)
        );

        if (!$row) {
            return $this->respondWithError('NOT_FOUND', __('api.feed_item_not_found'), null, 404);
        }

        $itemTenantId = (int) $row->tenant_id;

        // Capture creator user_id BEFORE deleting so we can notify them
        $creatorUserId = null;
        try {
            $feedItem = DB::selectOne(
                "SELECT user_id FROM feed_activity WHERE source_type = ? AND source_id = ? AND tenant_id = ? LIMIT 1",
                [$sourceType, $id, $itemTenantId]
            );
            $creatorUserId = $feedItem ? (int) $feedItem->user_id : null;
        } catch (\Throwable $e) {
            Log::warning("AdminFeedController::destroy failed to capture creator user_id for {$sourceType} #{$id}: " . $e->getMessage());
        }

        // Remove from feed_activity
        DB::delete("DELETE FROM feed_activity WHERE source_type = ? AND source_id = ? AND tenant_id = ?", [$sourceType, $id, $itemTenantId]);
        // Remove related engagement data
        DB::delete("DELETE FROM feed_hidden WHERE target_type = ? AND target_id = ? AND tenant_id = ?", [$sourceType, $id, $itemTenantId]);
        DB::delete("DELETE FROM likes WHERE target_type = ? AND target_id = ? AND tenant_id = ?", [$sourceType, $id, $itemTenantId]);
        DB::delete("DELETE FROM comments WHERE target_type = ? AND target_id = ? AND tenant_id = ?", [$sourceType, $id, $itemTenantId]);

        // If the source is a post, also delete the feed_posts row
        if ($sourceType === 'post') {
            DB::delete("DELETE FROM feed_posts WHERE id = ? AND tenant_id = ?", [$id, $itemTenantId]);
        }

        ActivityLog::log(
            $adminId,
            'delete_feed_item',
            "Deleted feed {$sourceType} #{$id}" . ($superAdmin ? " (tenant {$itemTenantId})" : '')
        );

        // Notify the content creator that their post was removed
        if ($creatorUserId) {
            try {
                Notification::createNotification(
                    $creatorUserId,
                    'Your post has been removed by a moderator.',
                    null,
                    'moderation',
                    false,
                    $itemTenantId
                );
            } catch (\Throwable $e) {
                Log::warning("AdminFeedController::destroy notification failed for {$sourceType} #{$id}: " . $e->getMessage());
            }
        }

        return $this->respondWithData(['success' => true, 'message' => 'Item deleted']);
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
                    SUM(fa.is_hidden) as hidden,
                    (SELECT COUNT(*) FROM comments WHERE tenant_id = ?) as total_comments
                 FROM feed_activity fa
                 WHERE fa.tenant_id = ?",
                [$effectiveTenantId, $effectiveTenantId]
            );
        } else {
            $stats = DB::selectOne(
                "SELECT
                    COUNT(*) as total,
                    SUM(fa.is_hidden) as hidden,
                    (SELECT COUNT(*) FROM comments) as total_comments
                 FROM feed_activity fa"
            );
        }

        return $this->respondWithData([
            'total' => (int) ($stats->total ?? 0),
            'hidden' => (int) ($stats->hidden ?? 0),
            'total_comments' => (int) ($stats->total_comments ?? 0),
        ]);
    }
}
