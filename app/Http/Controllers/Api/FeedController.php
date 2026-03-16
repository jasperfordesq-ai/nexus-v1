<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\FeedService;
use Illuminate\Http\JsonResponse;

/**
 * FeedController - Community feed (posts, likes).
 *
 * Endpoints (v2):
 *   GET   /api/v2/feed       feed()
 *   POST  /api/v2/feed       createPost()
 *   POST  /api/v2/feed/like  like()
 */
class FeedController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly FeedService $feedService,
    ) {}

    /**
     * Get the community feed with EdgeRank-sorted items.
     *
     * Query params: type, cursor, per_page.
     */
    public function feed(): JsonResponse
    {
        $userId = $this->getOptionalUserId();

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];

        if ($this->query('type')) {
            $filters['type'] = $this->query('type');
        }
        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }
        if ($userId !== null) {
            $filters['current_user_id'] = $userId;
        }

        $result = $this->feedService->getFeed($filters);

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'] ?? null,
            $filters['limit'],
            $result['has_more'] ?? false
        );
    }

    /**
     * Create a new feed post. Requires authentication.
     */
    public function createPost(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('feed_post', 10, 60);

        $post = $this->feedService->createPost($userId, $this->getAllInput());

        return $this->respondWithData($post, null, 201);
    }

    /**
     * Like or unlike a feed item. Requires authentication.
     */
    public function like(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('feed_like', 60, 60);

        $result = $this->feedService->toggleLike($userId, $this->getAllInput());

        return $this->respondWithData($result);
    }

    /**
     * Delegate to legacy controller via output buffering.
     */
    private function delegate(string $legacyClass, string $method, array $params = []): JsonResponse
    {
        $controller = new $legacyClass();
        ob_start();
        $controller->$method(...$params);
        $output = ob_get_clean();
        $status = http_response_code();
        return response()->json(json_decode($output, true) ?: $output, $status ?: 200);
    }


    public function hidePost(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\FeedController::class, 'hidePost');
    }


    public function muteUser(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\FeedController::class, 'muteUser');
    }


    public function reportPost(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\FeedController::class, 'reportPost');
    }

}
