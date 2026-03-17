<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\FeedService;
use Illuminate\Http\JsonResponse;

/**
 * SocialController -- Social feed posts, likes, polls.
 *
 * feedV2() is native Eloquent (no delegation). Other endpoints delegate
 * to legacy controllers where the logic is complex (Pusher, Redis, etc.).
 */
class SocialController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly FeedService $feedService,
    ) {}

    /**
     * GET /api/v2/feed
     *
     * Returns paginated feed items from the feed_activity table.
     * Supports sort=recent (default for now), type filtering, user/group scoping.
     *
     * Response shape (matches legacy exactly):
     * {
     *   "data": [
     *     {
     *       "id": 123,
     *       "type": "post",
     *       "title": null,
     *       "content": "...",
     *       "content_truncated": false,
     *       "image_url": null,
     *       "author": { "id": 1, "name": "John Doe", "avatar_url": "..." },
     *       "likes_count": 5,
     *       "comments_count": 2,
     *       "is_liked": false,
     *       "created_at": "2026-03-01 12:00:00",
     *       "start_date": null, "location": null, "rating": null, "receiver": null,
     *       "job_type": null, "commitment": null, "submission_deadline": null,
     *       "ideas_count": null, "listing_type": null,
     *       "credits_offered": null, "organization": null,
     *       "poll_data": { ... }  // only for polls
     *     }
     *   ],
     *   "meta": { "cursor": "...", "per_page": 20, "has_more": true }
     * }
     */
    public function feedV2(): JsonResponse
    {
        $userId = $this->getOptionalUserId();

        $userLimit = $this->queryInt('per_page', 20, 1, 100);

        $filters = [
            'limit' => $userLimit,
        ];

        if ($this->query('type')) {
            $filters['type'] = $this->query('type');
        }
        if ($this->query('user_id')) {
            $filters['user_id'] = $this->queryInt('user_id');
        }
        if ($this->query('group_id')) {
            $filters['group_id'] = $this->queryInt('group_id');
        }
        if ($this->query('subtype')) {
            $filters['subtype'] = $this->query('subtype');
        }
        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = $this->feedService->getFeed($userId, $filters);

        // Strip internal cursor fields before sending response
        foreach ($result['items'] as &$item) {
            unset($item['_activity_id'], $item['_activity_created_at']);
        }
        unset($item);

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $userLimit,
            $result['has_more']
        );
    }

    // ========================================================================
    // Delegated endpoints — complex logic (Pusher, Redis, file uploads, etc.)
    // ========================================================================

    /** POST feed/posts */
    public function createPostV2(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'createPostV2');
    }

    /** POST feed/like */
    public function likeV2(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'likeV2');
    }

    /** POST feed/posts/{id}/hide */
    public function hidePostV2(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'hidePostV2', [$id]);
    }

    /** POST feed/posts/{id}/delete */
    public function deletePostV2(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'deletePostV2', [$id]);
    }

    /** POST feed/posts/{id}/impression */
    public function recordImpression(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'recordImpression', [$id]);
    }

    public function createPollV2(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'createPollV2');
    }

    public function getPollV2($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'getPollV2', [$id]);
    }

    public function votePollV2($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'votePollV2', [$id]);
    }

    public function reportPostV2($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'reportPostV2', [$id]);
    }

    public function muteUserV2($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'muteUserV2', [$id]);
    }

    public function recordClick($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'recordClick', [$id]);
    }

    public function test(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'test');
    }

    public function like(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'like');
    }

    public function likers(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'likers');
    }

    public function comments(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'comments');
    }

    public function share(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'share');
    }

    public function delete(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'delete');
    }

    public function reaction(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'reaction');
    }

    public function reply(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'reply');
    }

    public function editComment(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'editComment');
    }

    public function deleteComment(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'deleteComment');
    }

    public function mentionSearch(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'mentionSearch');
    }

    public function feed(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'feed');
    }

    public function createPost(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\SocialApiController::class, 'createPost');
    }

    /**
     * Delegate to legacy controller via output buffering.
     */
    private function delegate(string $legacyClass, string $method, array $params = []): JsonResponse
    {
        $controller = new $legacyClass();
        ob_start();
        try {
            $controller->$method(...$params);
        } catch (\Throwable $e) {
            ob_end_clean();
            return $this->respondWithError(
                'INTERNAL_ERROR', $e->getMessage(), null, 500
            );
        }
        $output = ob_get_clean();
        $status = http_response_code();
        return response()->json(json_decode($output, true) ?: $output, $status ?: 200);
    }
}
