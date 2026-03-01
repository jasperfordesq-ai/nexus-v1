<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Services\PostSharingService;
use Nexus\Services\HashtagService;

/**
 * FeedSocialApiController - V2 API for post sharing and hashtags
 *
 * Post Sharing Endpoints:
 * - POST   /api/v2/feed/posts/{id}/share     - Share/repost a post
 * - DELETE /api/v2/feed/posts/{id}/share     - Unshare/remove repost
 * - GET    /api/v2/feed/posts/{id}/sharers   - Get users who shared a post
 *
 * Hashtag Endpoints:
 * - GET    /api/v2/feed/hashtags/trending     - Get trending hashtags
 * - GET    /api/v2/feed/hashtags/search       - Search/autocomplete hashtags
 * - GET    /api/v2/feed/hashtags/{tag}        - Get posts for a hashtag
 */
class FeedSocialApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    // =============================================
    // POST SHARING / REPOSTING
    // =============================================

    /**
     * POST /api/v2/feed/posts/{id}/share
     *
     * Request Body:
     * {
     *   "comment": "Check this out!" // optional
     * }
     */
    public function sharePost(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('feed_share', 20, 60);

        $data = $this->getAllInput();
        $comment = $data['comment'] ?? null;

        $result = PostSharingService::sharePost($userId, $id, 'post', $comment);

        if ($result === null) {
            $errors = PostSharingService::getErrors();
            $status = 422;
            if (!empty($errors)) {
                if ($errors[0]['code'] === 'ALREADY_SHARED') {
                    $status = 409;
                } elseif ($errors[0]['code'] === 'NOT_FOUND') {
                    $status = 404;
                }
            }
            $this->respondWithErrors($errors, $status);
        }

        $this->respondWithData($result, null, 201);
    }

    /**
     * DELETE /api/v2/feed/posts/{id}/share
     */
    public function unsharePost(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();

        $success = PostSharingService::unsharePost($userId, $id);

        if (!$success) {
            $this->respondWithErrors(PostSharingService::getErrors(), 404);
        }

        $this->respondWithData(['message' => 'Post unshared']);
    }

    /**
     * GET /api/v2/feed/posts/{id}/sharers
     */
    public function getSharers(int $id): void
    {
        $this->rateLimit('feed_sharers', 30, 60);

        $limit = $this->queryInt('limit', 20, 1, 100);
        $sharers = PostSharingService::getSharers($id, $limit);

        $shareCount = PostSharingService::getShareCount($id);
        $hasShared = false;

        $viewerId = $this->getOptionalUserId();
        if ($viewerId) {
            $hasShared = PostSharingService::hasShared($viewerId, $id);
        }

        $this->respondWithData([
            'sharers' => $sharers,
            'share_count' => $shareCount,
            'has_shared' => $hasShared,
        ]);
    }

    // =============================================
    // HASHTAGS
    // =============================================

    /**
     * GET /api/v2/feed/hashtags/trending
     */
    public function getTrendingHashtags(): void
    {
        $this->rateLimit('hashtags_trending', 30, 60);

        $limit = $this->queryInt('limit', 10, 1, 50);
        $days = $this->queryInt('days', 7, 1, 90);

        $trending = HashtagService::getTrending($limit, $days);
        $this->respondWithData($trending);
    }

    /**
     * GET /api/v2/feed/hashtags/search
     */
    public function searchHashtags(): void
    {
        $this->rateLimit('hashtags_search', 60, 60);

        $query = $this->query('q', '');
        if (strlen($query) < 1) {
            $this->respondWithData([]);
        }

        $limit = $this->queryInt('limit', 10, 1, 50);
        $results = HashtagService::search($query, $limit);

        $this->respondWithData($results);
    }

    /**
     * GET /api/v2/feed/hashtags/{tag}
     * Get posts with a specific hashtag
     */
    public function getHashtagPosts(string $tag): void
    {
        $this->rateLimit('hashtags_posts', 30, 60);

        $userId = $this->getOptionalUserId();
        $limit = $this->queryInt('limit', 20, 1, 100);
        $cursor = $this->query('cursor');

        $result = HashtagService::getPostsByHashtag($tag, $userId, $limit, $cursor);

        $this->respondWithData($result['items'], [
            'cursor' => $result['cursor'],
            'has_more' => $result['has_more'],
            'tag' => $result['tag'],
        ]);
    }
}
