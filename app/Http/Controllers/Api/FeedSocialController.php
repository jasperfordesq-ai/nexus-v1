<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Models\Notification;
use App\Models\User;
use App\Services\FeedSocialService;
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
    ) {}

    // =============================================
    // POST SHARING / REPOSTING
    // =============================================

    /**
     * POST /api/v2/feed/posts/{id}/share
     *
     * Share a feed post (creates a share record, optional comment).
     */
    public function sharePost(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();
        $this->rateLimit('feed_share', 20, 60);

        $comment = $this->input('comment');

        // Validate original post exists
        $original = DB::table('feed_posts')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $original) {
            return $this->respondWithError('NOT_FOUND', 'Post not found', null, 404);
        }

        // Cannot share own post
        if ((int) $original->user_id === $userId) {
            return $this->respondWithError('SELF_SHARE', 'You cannot share your own post', null, 422);
        }

        // Check if already shared
        $existing = DB::table('post_shares')
            ->where('user_id', $userId)
            ->where('original_post_id', $id)
            ->where('tenant_id', $tenantId)
            ->exists();

        if ($existing) {
            return $this->respondWithError('ALREADY_SHARED', 'You have already shared this post', null, 409);
        }

        // Create share record
        $shareId = DB::table('post_shares')->insertGetId([
            'user_id'          => $userId,
            'original_post_id' => $id,
            'tenant_id'        => $tenantId,
            'comment'          => $comment,
            'created_at'       => now(),
        ]);

        // Increment share count on the original post
        DB::table('feed_posts')->where('id', $id)->where('tenant_id', $tenantId)->increment('share_count');

        // Notify the original post author (sharer !== author already guaranteed above)
        try {
            $sharer = DB::table('users')
                ->where('id', $userId)
                ->where('tenant_id', $tenantId)
                ->select('first_name', 'last_name')
                ->first();

            if ($sharer) {
                $sharerName = trim($sharer->first_name . ' ' . $sharer->last_name);
                Notification::createNotification(
                    (int) $original->user_id,
                    "{$sharerName} shared your post",
                    "/feed/post/{$id}",
                    'post_shared'
                );
            }
        } catch (\Throwable $e) {
            // Non-critical — don't fail the share if notification fails
        }

        return $this->respondWithData([
            'id'               => $shareId,
            'original_post_id' => $id,
            'user_id'          => $userId,
            'comment'          => $comment,
        ], null, 201);
    }

    /**
     * DELETE /api/v2/feed/posts/{id}/share
     *
     * Remove a share/repost.
     */
    public function unsharePost(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        $deleted = DB::table('post_shares')
            ->where('user_id', $userId)
            ->where('original_post_id', $id)
            ->where('tenant_id', $tenantId)
            ->delete();

        if (! $deleted) {
            return $this->respondWithError('NOT_FOUND', 'Share not found', null, 404);
        }

        // Decrement share count
        DB::table('feed_posts')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->update(['share_count' => DB::raw('GREATEST(share_count - 1, 0)')]);

        return $this->respondWithData(['message' => 'Post unshared']);
    }

    /**
     * GET /api/v2/feed/posts/{id}/sharers
     *
     * Get users who shared a post, with share count and viewer status.
     */
    public function getSharers(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $this->rateLimit('feed_sharers', 30, 60);

        $limit = $this->queryInt('limit', 20, 1, 100);

        $sharers = DB::table('post_shares as ps')
            ->join('users as u', 'ps.user_id', '=', 'u.id')
            ->where('ps.original_post_id', $id)
            ->where('ps.tenant_id', $tenantId)
            ->orderByDesc('ps.created_at')
            ->limit($limit)
            ->select('u.id', 'u.first_name', 'u.last_name', 'u.avatar_url', 'ps.comment', 'ps.created_at')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $shareCount = (int) DB::table('post_shares')
            ->where('original_post_id', $id)
            ->where('tenant_id', $tenantId)
            ->count();

        $hasShared = false;
        $viewerId = $this->getOptionalUserId();
        if ($viewerId) {
            $hasShared = DB::table('post_shares')
                ->where('user_id', $viewerId)
                ->where('original_post_id', $id)
                ->where('tenant_id', $tenantId)
                ->exists();
        }

        return $this->respondWithData([
            'sharers'     => $sharers,
            'share_count' => $shareCount,
            'has_shared'  => $hasShared,
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

        $results = DB::table('hashtags')
            ->where('tenant_id', $tenantId)
            ->where('tag', 'LIKE', strtolower($query) . '%')
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
