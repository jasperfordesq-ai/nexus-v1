<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Models\Notification;
use App\Models\User;
use App\Services\FeedSocialService;
use App\Services\ShareService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;

/**
 * FeedSocialController — Social features for the feed (shares, hashtags).
 *
 * Native Eloquent implementation — no legacy delegation.
 */
class FeedSocialController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly FeedSocialService $socialService,
        private readonly ShareService $shareService,
    ) {}

    // =============================================
    // POST SHARING / REPOSTING
    // =============================================

    /**
     * POST /api/v2/feed/posts/{id}/share
     *
     * Legacy: share a native feed post. Delegates to ShareService with type='post'.
     * New polymorphic clients should call POST /api/v2/shares instead.
     */
    public function sharePost(int $id): JsonResponse
    {
        return $this->doShare('post', $id);
    }

    /**
     * DELETE /api/v2/feed/posts/{id}/share
     *
     * Legacy: remove a share on a native feed post.
     */
    public function unsharePost(int $id): JsonResponse
    {
        return $this->doUnshare('post', $id);
    }

    /**
     * POST /api/v2/shares
     * Body: { type: string, id: int, comment?: string }
     *
     * Polymorphic toggle-style share for ANY feed item type
     * (post, listing, event, poll, job, blog, discussion, goal, challenge, volunteer).
     * If the user already shared this item, this acts as an UNSHARE (toggles off).
     *
     * @return JsonResponse { data: { shared: bool, count: int, share_id: ?int } }
     */
    public function share(): JsonResponse
    {
        $type = (string) ($this->input('type') ?? '');
        $id = (int) ($this->input('id') ?? 0);
        $comment = $this->input('comment');
        $comment = $comment === null ? null : (string) $comment;

        if ($type === '' || $id <= 0) {
            return $this->respondWithError('INVALID_INPUT', __('api.invalid_input'), null, 422);
        }

        return $this->doShare($type, $id, $comment);
    }

    /**
     * DELETE /api/v2/shares
     * Body: { type: string, id: int }
     *
     * Explicit unshare endpoint (idempotent — returns 200 even if no row existed).
     */
    public function unshare(): JsonResponse
    {
        $type = (string) ($this->input('type') ?? '');
        $id = (int) ($this->input('id') ?? 0);

        if ($type === '' || $id <= 0) {
            return $this->respondWithError('INVALID_INPUT', __('api.invalid_input'), null, 422);
        }

        return $this->doUnshare($type, $id);
    }

    /**
     * Shared share/toggle path used by both the legacy post-specific endpoint and
     * the new polymorphic /v2/shares endpoint.
     */
    private function doShare(string $type, int $id, ?string $comment = null): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('feed_share', 20, 60);

        try {
            $result = $this->shareService->toggle($userId, $type, $id, $comment);
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('INVALID_INPUT', __('api.invalid_shareable_type'), null, 422);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('NOT_FOUND', __('api.post_not_found'), null, 404);
        } catch (\DomainException $e) {
            return $this->respondWithError('SELF_SHARE', __('api.cannot_share_own_post'), null, 422);
        }

        return $this->respondWithData([
            'shared'   => $result['shared'],
            'count'    => $result['count'],
            'share_id' => $result['share_id'],
            'type'     => $type,
            'id'       => $id,
        ], null, $result['shared'] ? 201 : 200);
    }

    /**
     * Shared unshare path. Idempotent: returns success even if no existing share.
     */
    private function doUnshare(string $type, int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        try {
            $this->shareService->validateType($type);
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('INVALID_INPUT', __('api.invalid_shareable_type'), null, 422);
        }

        // If a share exists, toggle() will remove it. Otherwise this is a no-op.
        if (!$this->shareService->isShared($userId, $type, $id)) {
            return $this->respondWithData([
                'shared' => false,
                'count'  => $this->shareService->getShareCount($type, $id),
                'type'   => $type,
                'id'     => $id,
            ]);
        }

        try {
            $result = $this->shareService->toggle($userId, $type, $id);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('NOT_FOUND', __('api.post_not_found'), null, 404);
        } catch (\DomainException $e) {
            // Can't happen on an unshare (self-share check only triggers on new shares).
            return $this->respondWithError('INVALID_INPUT', __('api.invalid_input'), null, 422);
        }

        return $this->respondWithData([
            'shared' => $result['shared'],
            'count'  => $result['count'],
            'type'   => $type,
            'id'     => $id,
        ]);
    }

    /**
     * GET /api/v2/feed/posts/{id}/sharers[?type=]
     *
     * Get users who shared an item. `type` defaults to 'post' for backwards
     * compatibility with the /feed/posts/{id}/sharers URL shape.
     */
    public function getSharers(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $this->rateLimit('feed_sharers', 30, 60);

        $type = (string) $this->query('type', 'post');
        if (!in_array($type, ShareService::VALID_TYPES, true)) {
            $type = 'post';
        }

        $limit = $this->queryInt('limit', 20, 1, 100);

        $sharers = DB::table('post_shares as ps')
            ->join('users as u', 'ps.user_id', '=', 'u.id')
            ->where('ps.original_type', $type)
            ->where('ps.original_post_id', $id)
            ->where('ps.tenant_id', $tenantId)
            ->orderByDesc('ps.created_at')
            ->limit($limit)
            ->select('u.id', 'u.first_name', 'u.last_name', 'u.avatar_url', 'ps.comment', 'ps.created_at')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $shareCount = $this->shareService->getShareCount($type, $id, $tenantId);

        $hasShared = false;
        $viewerId = $this->getOptionalUserId();
        if ($viewerId) {
            $hasShared = $this->shareService->isShared($viewerId, $type, $id, $tenantId);
        }

        return $this->respondWithData([
            'sharers'     => $sharers,
            'share_count' => $shareCount,
            'has_shared'  => $hasShared,
            'type'        => $type,
            'id'          => $id,
        ]);
    }

    // =============================================
    // HASHTAGS
    // =============================================

    /**
     * GET /api/v2/feed/hashtags/trending
     *
     * Get currently trending hashtags.
     */
    public function getTrendingHashtags(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $this->rateLimit('hashtags_trending', 30, 60);

        $limit = $this->queryInt('limit', 10, 1, 50);
        $days = $this->queryInt('days', 7, 1, 90);

        $since = now()->subDays($days);

        $trending = DB::table('hashtags')
            ->where('tenant_id', $tenantId)
            ->where('last_used_at', '>=', $since)
            ->where('post_count', '>', 0)
            ->orderByDesc('post_count')
            ->limit($limit)
            ->select('id', 'tag', 'post_count', 'last_used_at')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return $this->respondWithData($trending);
    }

    /**
     * GET /api/v2/feed/hashtags/search
     *
     * Search/autocomplete hashtags.
     */
    public function searchHashtags(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $this->rateLimit('hashtags_search', 60, 60);

        $query = $this->query('q', '');
        if (strlen($query) < 1) {
            return $this->respondWithData([]);
        }

        $limit = $this->queryInt('limit', 10, 1, 50);

        // M3: Strip LIKE wildcard characters to prevent pattern injection
        $escapedQuery = preg_replace('/[%_]/', '', $query);
        $likePattern = strtolower($escapedQuery) . '%';

        $results = DB::table('hashtags')
            ->where('tenant_id', $tenantId)
            ->where('tag', 'LIKE', $likePattern)
            ->orderByDesc('post_count')
            ->limit($limit)
            ->select('id', 'tag', 'post_count')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return $this->respondWithData($results);
    }

    /**
     * GET /api/v2/feed/hashtags/{tag}
     *
     * Get posts with a specific hashtag.
     */
    public function getHashtagPosts(string $tag): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $this->rateLimit('hashtags_posts', 30, 60);

        $limit = $this->queryInt('limit', 20, 1, 100);
        $cursor = $this->query('cursor');

        $normalizedTag = strtolower(ltrim($tag, '#'));

        // Find the hashtag
        $hashtag = DB::table('hashtags')
            ->where('tenant_id', $tenantId)
            ->where('tag', $normalizedTag)
            ->first();

        if (! $hashtag) {
            return $this->respondWithCollection([], null, $limit, false, ['total_items' => 0]);
        }

        // Count total posts for this hashtag (without cursor/limit)
        $totalCount = (int) DB::table('post_hashtags as ph')
            ->join('feed_posts as fp', 'ph.post_id', '=', 'fp.id')
            ->where('ph.hashtag_id', $hashtag->id)
            ->where('ph.tenant_id', $tenantId)
            ->count();

        $query = DB::table('post_hashtags as ph')
            ->join('feed_posts as fp', 'ph.post_id', '=', 'fp.id')
            ->leftJoin('users as u', 'fp.user_id', '=', 'u.id')
            ->where('ph.hashtag_id', $hashtag->id)
            ->where('ph.tenant_id', $tenantId)
            ->select('fp.*', 'u.first_name', 'u.last_name', 'u.avatar_url');

        if ($cursor !== null) {
            $decoded = base64_decode($cursor, true);
            if ($decoded !== false) {
                $query->where('fp.id', '<', (int) $decoded);
            }
        }

        $items = $query->orderByDesc('fp.id')->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items->pop();
        }

        $nextCursor = $hasMore && $items->isNotEmpty()
            ? base64_encode((string) $items->last()->id)
            : null;

        return $this->respondWithCollection(
            $items->map(fn ($i) => (array) $i)->values()->all(),
            $nextCursor,
            $limit,
            $hasMore,
            ['total_items' => $totalCount]
        );
    }

    /**
     * Alias for getTrendingHashtags (used by some routes).
     */
    public function trendingHashtags(): JsonResponse
    {
        return $this->getTrendingHashtags();
    }

    /**
     * Alias for getHashtagPosts (used by some routes).
     */
    public function hashtagPosts(string $tag): JsonResponse
    {
        return $this->getHashtagPosts($tag);
    }
}
