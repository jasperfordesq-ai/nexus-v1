<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\FeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * FeedController - Community feed (posts, likes, hide, mute, report).
 *
 * All endpoints migrated to native DB facade — no legacy delegation.
 *
 * Endpoints (v2):
 *   GET   /api/v2/feed            feed()
 *   POST  /api/v2/feed            createPost()
 *   POST  /api/v2/feed/like       like()
 *   POST  /api/v2/feed/hide       hidePost()
 *   POST  /api/v2/feed/mute       muteUser()
 *   POST  /api/v2/feed/report     reportPost()
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
     * POST /api/v2/feed/hide
     *
     * Hide a post from the current user's feed.
     * Body: { "post_id": 123 }
     * Response: { "success": true }
     */
    public function hidePost(): JsonResponse
    {
        $userId = $this->requireAuth();

        $postId = (int) ($this->input('post_id', 0));

        if ($postId <= 0) {
            return response()->json(['success' => false, 'error' => 'Invalid post ID']);
        }

        try {
            DB::table('user_hidden_posts')->insertOrIgnore([
                'user_id'    => $userId,
                'post_id'    => $postId,
                'created_at' => now(),
            ]);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'Database error']);
        }
    }

    /**
     * POST /api/v2/feed/mute
     *
     * Mute a user so their posts are hidden from the current user's feed.
     * Body: { "user_id": 456 }
     * Response: { "success": true }
     */
    public function muteUser(): JsonResponse
    {
        $userId = $this->requireAuth();

        $mutedUserId = (int) ($this->input('user_id', 0));

        if ($mutedUserId <= 0 || $mutedUserId === $userId) {
            return response()->json(['success' => false, 'error' => 'Invalid user']);
        }

        try {
            DB::table('user_muted_users')->insertOrIgnore([
                'user_id'       => $userId,
                'muted_user_id' => $mutedUserId,
                'created_at'    => now(),
            ]);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'Database error']);
        }
    }

    /**
     * POST /api/v2/feed/report
     *
     * Report a post for moderation.
     * Body: { "post_id": 123, "target_type"?: "post" }
     * Response: { "success": true }
     */
    public function reportPost(): JsonResponse
    {
        $userId = $this->requireAuth();

        $postId = (int) ($this->input('post_id', 0));
        $targetType = $this->input('target_type', 'post');

        if ($postId <= 0) {
            return response()->json(['success' => false, 'error' => 'Invalid post ID']);
        }

        try {
            DB::table('reports')->insert([
                'tenant_id'   => \App\Core\TenantContext::getId(),
                'reporter_id' => $userId,
                'target_type' => $targetType,
                'target_id'   => $postId,
                'reason'      => 'Reported via feed',
                'created_at'  => now(),
            ]);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'Database error']);
        }
    }
}
