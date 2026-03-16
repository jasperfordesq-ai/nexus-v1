<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\FeedSocialService;
use Illuminate\Http\JsonResponse;

/**
 * FeedSocialController — Social features for the feed (shares, hashtags).
 */
class FeedSocialController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly FeedSocialService $socialService,
    ) {}

    /**
     * POST /api/v2/feed/posts/{id}/share
     *
     * Share a feed post (creates a share record, optional comment).
     * Body: comment (optional).
     */
    public function sharePost(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();
        $this->rateLimit('feed_share', 20, 60);

        $comment = $this->input('comment');

        $result = $this->socialService->sharePost($id, $userId, $tenantId, $comment);

        if ($result === null) {
            return $this->respondWithError('NOT_FOUND', 'Post not found', null, 404);
        }

        return $this->respondWithData($result, null, 201);
    }

    /**
     * GET /api/v2/feed/hashtags/trending
     *
     * Get currently trending hashtags.
     * Query params: limit (default 10).
     */
    public function trendingHashtags(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $limit = $this->queryInt('limit', 10, 1, 50);

        $hashtags = $this->socialService->getTrendingHashtags($tenantId, $limit);

        return $this->respondWithData($hashtags);
    }

    /**
     * GET /api/v2/feed/hashtags/{tag}
     *
     * Get posts for a specific hashtag.
     * Query params: cursor, per_page.
     */
    public function hashtagPosts(string $tag): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $cursor = $this->query('cursor');
        $perPage = $this->queryInt('per_page', 20, 1, 100);

        $result = $this->socialService->getPostsByHashtag($tag, $tenantId, $cursor, $perPage);

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'] ?? null,
            $perPage,
            $result['has_more'] ?? false
        );
    }
}
