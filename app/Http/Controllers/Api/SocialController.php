<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\FeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Nexus\Services\CommentService;
use Nexus\Services\SocialNotificationService;
use Nexus\Services\FeedRankingService;
use Nexus\Services\FeedActivityService;
use Nexus\Models\FeedPost;

/**
 * SocialController -- Social feed posts, likes, polls, comments, reactions.
 *
 * feedV2() and likeV2() are native Eloquent (no delegation).
 * createPostV2 and createPost delegate because they handle file uploads via $_FILES.
 * All other methods are now native — calling legacy static services directly.
 */
class SocialController extends BaseApiController
{
    protected bool $isV2Api = true;

    /** Valid target types for likes */
    private const VALID_LIKE_TARGETS = [
        'post', 'listing', 'event', 'poll', 'goal',
        'resource', 'volunteering', 'review', 'comment',
    ];

    public function __construct(
        private readonly FeedService $feedService,
    ) {}

    /**
     * GET /api/v2/feed
     *
     * Returns paginated feed items from the feed_activity table.
     * Native Eloquent — no delegation.
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

    /**
     * POST /api/v2/feed/like
     *
     * Toggle like on content. Native Eloquent — no delegation.
     *
     * Request Body: { "target_type": "post|listing|event|...", "target_id": int }
     */
    public function likeV2(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();
        $this->rateLimit('feed_like', 60, 60);

        $targetType = $this->input('target_type');
        $targetId = $this->inputInt('target_id');

        if (empty($targetType) || ! $targetId) {
            return $this->respondWithError('VALIDATION_ERROR', 'target_type and target_id are required', null, 400);
        }

        if (! in_array($targetType, self::VALID_LIKE_TARGETS, true)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Invalid target_type', 'target_type', 400);
        }

        // Check existing like (tenant-scoped)
        $existing = DB::table('likes')
            ->where('user_id', $userId)
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->where('tenant_id', $tenantId)
            ->first();

        if ($existing) {
            // Unlike
            DB::table('likes')
                ->where('id', $existing->id)
                ->where('tenant_id', $tenantId)
                ->delete();

            if ($targetType === 'post') {
                DB::table('feed_posts')
                    ->where('id', $targetId)
                    ->where('tenant_id', $tenantId)
                    ->update(['likes_count' => DB::raw('GREATEST(likes_count - 1, 0)')]);
            }

            $action = 'unliked';
        } else {
            // Like
            DB::table('likes')->insert([
                'user_id'     => $userId,
                'target_type' => $targetType,
                'target_id'   => $targetId,
                'tenant_id'   => $tenantId,
                'created_at'  => now(),
            ]);

            if ($targetType === 'post') {
                DB::table('feed_posts')
                    ->where('id', $targetId)
                    ->where('tenant_id', $tenantId)
                    ->increment('likes_count');
            }

            $action = 'liked';
        }

        // Get updated count (tenant-scoped)
        $count = (int) DB::table('likes')
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->where('tenant_id', $tenantId)
            ->count();

        return $this->respondWithData([
            'action'      => $action,
            'likes_count' => $count,
        ]);
    }

    // ========================================================================
    // Delegated endpoints — file uploads only
    // ========================================================================

    /** POST feed/posts — delegates due to $_FILES image upload handling */
    public function createPostV2(): JsonResponse
    {
        return $this->delegateToLegacy(\Nexus\Controllers\Api\SocialApiController::class, 'createPostV2');
    }

    /** POST /api/social/create-post — delegates due to $_FILES image upload handling */
    public function createPost(): JsonResponse
    {
        return $this->delegateToLegacy(\Nexus\Controllers\Api\SocialApiController::class, 'createPost');
    }

    // ========================================================================
    // V2 endpoints — native (no delegation)
    // ========================================================================

    /** POST /api/v2/feed/posts/{id}/hide */
    public function hidePostV2(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        DB::table('feed_hidden')->insertOrIgnore([
            'user_id'     => $userId,
            'tenant_id'   => $tenantId,
            'target_type' => 'post',
            'target_id'   => $id,
            'created_at'  => now(),
        ]);

        return $this->respondWithData(['hidden' => true, 'post_id' => $id]);
    }

    /** POST /api/v2/feed/posts/{id}/delete */
    public function deletePostV2(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        $post = DB::table('feed_posts')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $post) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'Post not found', null, 404);
        }

        if ((int) $post->user_id !== $userId) {
            return $this->respondWithError('FORBIDDEN', 'You can only delete your own posts', null, 403);
        }

        DB::table('feed_posts')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->delete();

        try {
            FeedActivityService::removeActivity('post', $id);
        } catch (\Exception $e) {
            error_log("SocialController::deletePostV2 feed_activity remove failed: " . $e->getMessage());
        }

        return $this->respondWithData(['deleted' => true, 'id' => $id]);
    }

    /** POST /api/v2/feed/posts/{id}/impression */
    public function recordImpression(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        if ($userId && $id > 0) {
            FeedRankingService::recordImpression($id, $userId);
        }
        return $this->respondWithData(['recorded' => true]);
    }

    /** POST /api/v2/feed/polls */
    public function createPollV2(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('feed_create_poll', 10, 60);

        $data = $this->getAllInput();
        $question = trim($data['question'] ?? '');
        $options = $data['options'] ?? [];

        if (empty($question)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Question is required', 'question', 400);
        }

        if (! is_array($options) || count($options) < 2) {
            return $this->respondWithError('VALIDATION_ERROR', 'At least 2 options are required', 'options', 400);
        }

        $pollData = [
            'question'   => $question,
            'options'    => $options,
            'expires_at' => $data['expires_at'] ?? null,
            'visibility' => $data['visibility'] ?? 'public',
        ];

        $pollId = \Nexus\Services\PollService::create($userId, $pollData);

        if ($pollId === null) {
            $errors = \Nexus\Services\PollService::getErrors();
            return $this->respondWithErrors($errors, 422);
        }

        $poll = \Nexus\Services\PollService::getById($pollId, $userId);
        return $this->respondWithData($poll, null, 201);
    }

    /** GET /api/v2/feed/polls/{id} */
    public function getPollV2($id): JsonResponse
    {
        $userId = $this->getOptionalUserId();
        $this->rateLimit('feed_poll_get', 60, 60);

        $poll = \Nexus\Services\PollService::getById((int) $id, $userId);

        if (! $poll) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'Poll not found', null, 404);
        }

        return $this->respondWithData($poll);
    }

    /** POST /api/v2/feed/polls/{id}/vote */
    public function votePollV2($id): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('feed_poll_vote', 30, 60);

        $optionId = $this->inputInt('option_id');

        if (! $optionId) {
            return $this->respondWithError('VALIDATION_ERROR', 'option_id is required', 'option_id', 400);
        }

        $success = \Nexus\Services\PollService::vote((int) $id, $optionId, $userId);

        if (! $success) {
            $errors = \Nexus\Services\PollService::getErrors();
            return $this->respondWithErrors($errors, 400);
        }

        $poll = \Nexus\Services\PollService::getById((int) $id, $userId);
        return $this->respondWithData($poll);
    }

    /** POST /api/v2/feed/posts/{id}/report */
    public function reportPostV2($id): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();
        $reason = trim($this->input('reason', ''));

        if (mb_strlen($reason) > 1000) {
            $reason = mb_substr($reason, 0, 1000);
        }

        DB::table('reports')->insert([
            'user_id'     => $userId,
            'tenant_id'   => $tenantId,
            'target_type' => 'feed_post',
            'target_id'   => (int) $id,
            'reason'      => $reason,
            'status'      => 'pending',
            'created_at'  => now(),
        ]);

        return $this->respondWithData(['reported' => true, 'post_id' => (int) $id]);
    }

    /** POST /api/v2/feed/users/{id}/mute */
    public function muteUserV2($id): JsonResponse
    {
        $currentUserId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        DB::table('feed_muted_users')->insertOrIgnore([
            'user_id'       => $currentUserId,
            'tenant_id'     => $tenantId,
            'muted_user_id' => (int) $id,
            'created_at'    => now(),
        ]);

        return $this->respondWithData(['muted' => true, 'user_id' => (int) $id]);
    }

    /** POST /api/v2/feed/posts/{id}/click */
    public function recordClick($id): JsonResponse
    {
        $userId = $this->requireAuth();
        if ($userId && (int) $id > 0) {
            FeedRankingService::recordClick((int) $id, $userId);
        }
        return $this->respondWithData(['recorded' => true]);
    }

    /** GET /api/social/test — admin debug endpoint */
    public function test(): JsonResponse
    {
        $this->requireAdmin();

        $debug = [
            'api_working'        => true,
            'request_method'     => request()->method(),
            'likes_table_exists' => false,
        ];

        try {
            DB::table('likes')->count();
            $debug['likes_table_exists'] = true;
        } catch (\Exception) {
            $debug['likes_error'] = 'Database error';
        }

        return response()->json($debug);
    }

    // ========================================================================
    // Legacy V1 social endpoints — native (no delegation)
    // ========================================================================

    /** POST /api/social/like */
    public function like(): JsonResponse
    {
        $this->rateLimit('social_like', 60, 60);
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        $targetType = $this->input('target_type', '');
        $targetId = $this->inputInt('target_id');

        if (empty($targetType) || ! $targetId || $targetId <= 0) {
            return response()->json(['error' => 'Invalid target'], 400);
        }

        if (! in_array($targetType, self::VALID_LIKE_TARGETS, true)) {
            return response()->json(['error' => 'Invalid target_type'], 400);
        }

        try {
            $existing = DB::table('likes')
                ->where('user_id', $userId)
                ->where('target_type', $targetType)
                ->where('target_id', $targetId)
                ->where('tenant_id', $tenantId)
                ->first();

            if ($existing) {
                DB::table('likes')->where('id', $existing->id)->where('tenant_id', $tenantId)->delete();

                if ($targetType === 'post') {
                    DB::table('feed_posts')
                        ->where('id', $targetId)
                        ->where('tenant_id', $tenantId)
                        ->update(['likes_count' => DB::raw('GREATEST(likes_count - 1, 0)')]);
                }

                $action = 'unliked';
            } else {
                DB::table('likes')->insert([
                    'user_id'     => $userId,
                    'target_type' => $targetType,
                    'target_id'   => $targetId,
                    'tenant_id'   => $tenantId,
                    'created_at'  => now(),
                ]);

                if ($targetType === 'post') {
                    DB::table('feed_posts')
                        ->where('id', $targetId)
                        ->where('tenant_id', $tenantId)
                        ->increment('likes_count');
                }

                $action = 'liked';

                // Send notification to content owner
                try {
                    $contentOwnerId = SocialNotificationService::getContentOwnerId($targetType, $targetId);
                    if ($contentOwnerId && $contentOwnerId != $userId) {
                        SocialNotificationService::notifyLike($contentOwnerId, $userId, $targetType, $targetId, '');
                    }
                } catch (\Throwable $e) {
                    error_log("notifyLike error (non-critical): " . $e->getMessage());
                }
            }

            $count = (int) DB::table('likes')
                ->where('target_type', $targetType)
                ->where('target_id', $targetId)
                ->where('tenant_id', $tenantId)
                ->count();

            return response()->json([
                'status'      => $action,
                'likes_count' => $count,
            ]);
        } catch (\Exception $e) {
            error_log("Social Like Error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to process like'], 500);
        }
    }

    /** POST /api/social/likers */
    public function likers(): JsonResponse
    {
        $targetType = $this->input('target_type', '');
        $targetId = $this->inputInt('target_id');
        $page = max(1, $this->inputInt('page', 1));
        $limit = min(50, max(5, $this->inputInt('limit', 20)));
        $offset = ($page - 1) * $limit;
        $tenantId = $this->getTenantId();

        if (empty($targetType) || ! $targetId || $targetId <= 0) {
            return response()->json(['error' => 'Invalid target'], 400);
        }

        try {
            $likers = DB::select(
                "SELECT u.id, COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as name,
                        u.avatar_url, l.created_at as liked_at
                 FROM likes l JOIN users u ON l.user_id = u.id
                 WHERE l.target_type = ? AND l.target_id = ? AND l.tenant_id = ?
                 ORDER BY l.created_at DESC LIMIT ? OFFSET ?",
                [$targetType, $targetId, $tenantId, $limit, $offset]
            );

            $likers = array_map(function ($l) {
                $l = (array) $l;
                if (empty($l['avatar_url'])) {
                    $l['avatar_url'] = '/assets/img/defaults/default_avatar.png';
                }
                $l['liked_at_formatted'] = date('M j, Y', strtotime($l['liked_at']));
                return $l;
            }, $likers);

            $totalCount = (int) DB::table('likes')
                ->where('target_type', $targetType)
                ->where('target_id', $targetId)
                ->where('tenant_id', $tenantId)
                ->count();

            return response()->json([
                'success'     => true,
                'status'      => 'success',
                'likers'      => $likers,
                'total_count' => $totalCount,
                'page'        => $page,
                'has_more'    => ($offset + count($likers)) < $totalCount,
            ]);
        } catch (\Exception $e) {
            error_log("Get Likers Error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch likers'], 500);
        }
    }

    /** POST /api/social/comments */
    public function comments(): JsonResponse
    {
        $this->rateLimit('social_comments', 60, 60);
        $action = $this->input('action', '');
        $targetType = $this->input('target_type', '');
        $targetId = $this->inputInt('target_id');

        if (empty($targetType) || ! $targetId || $targetId <= 0) {
            return response()->json(['error' => 'Invalid target'], 400);
        }

        switch ($action) {
            case 'fetch':
            case 'fetch_comments':
                return $this->fetchComments($targetType, $targetId);
            case 'submit':
            case 'submit_comment':
                return $this->submitComment($targetType, $targetId);
            default:
                return response()->json(['error' => 'Invalid action'], 400);
        }
    }

    private function fetchComments(string $targetType, int $targetId): JsonResponse
    {
        try {
            $userId = $this->getOptionalUserId() ?? 0;

            if (class_exists(CommentService::class)) {
                $comments = CommentService::fetchComments($targetType, $targetId, $userId);
                return response()->json([
                    'success'             => true,
                    'status'              => 'success',
                    'comments'            => $comments,
                    'available_reactions' => CommentService::getAvailableReactions(),
                ]);
            }

            $comments = DB::select(
                "SELECT c.id, c.content, c.created_at, c.user_id,
                    COALESCE(u.name, u.first_name, 'Unknown') as author_name,
                    COALESCE(u.avatar_url, '/assets/img/defaults/default_avatar.png') as author_avatar
                 FROM comments c LEFT JOIN users u ON c.user_id = u.id
                 WHERE c.target_type = ? AND c.target_id = ? ORDER BY c.created_at ASC",
                [$targetType, $targetId]
            );

            $comments = array_map(function ($c) use ($userId) {
                $c = (array) $c;
                $c['is_owner'] = ($userId && $c['user_id'] == $userId);
                $c['reactions'] = [];
                $c['replies'] = [];
                return $c;
            }, $comments);

            return response()->json(['success' => true, 'status' => 'success', 'comments' => $comments]);
        } catch (\Exception $e) {
            error_log("Fetch Comments Error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch comments'], 500);
        }
    }

    private function submitComment(string $targetType, int $targetId): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();
        $content = trim($this->input('content', ''));

        if (empty($content)) {
            return response()->json(['error' => 'Comment cannot be empty'], 400);
        }

        try {
            if (class_exists(CommentService::class)) {
                $result = CommentService::addComment($userId, $tenantId, $targetType, $targetId, $content);

                if (($result['status'] ?? '') === 'success') {
                    $this->notifyComment($userId, $targetType, $targetId, $content);
                }

                return response()->json($result);
            }

            DB::table('comments')->insert([
                'user_id'     => $userId,
                'tenant_id'   => $tenantId,
                'target_type' => $targetType,
                'target_id'   => $targetId,
                'content'     => $content,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            $this->notifyComment($userId, $targetType, $targetId, $content);

            $user = DB::selectOne(
                "SELECT COALESCE(name, CONCAT(first_name, ' ', last_name)) as name, avatar_url FROM users WHERE id = ?",
                [$userId]
            );

            return response()->json([
                'success' => true,
                'status'  => 'success',
                'comment' => [
                    'author_name'   => $user->name ?? 'Me',
                    'author_avatar' => $user->avatar_url ?? '/assets/img/defaults/default_avatar.png',
                    'content'       => $content,
                ],
            ]);
        } catch (\Exception $e) {
            error_log("Submit Comment Error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to post comment'], 500);
        }
    }

    private function notifyComment(int $userId, string $targetType, int $targetId, string $content): void
    {
        try {
            $contentOwnerId = SocialNotificationService::getContentOwnerId($targetType, $targetId);
            if ($contentOwnerId && $contentOwnerId != $userId) {
                SocialNotificationService::notifyComment($contentOwnerId, $userId, $targetType, $targetId, $content);
            }
        } catch (\Throwable $e) {
            error_log("notifyComment error (non-critical): " . $e->getMessage());
        }
    }

    /** POST /api/social/share */
    public function share(): JsonResponse
    {
        $this->rateLimit('social_share', 20, 60);
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        $parentType = $this->input('parent_type', '');
        $parentId = $this->inputInt('parent_id');

        if (empty($parentType) || ! $parentId || $parentId <= 0) {
            return response()->json(['error' => 'Invalid content to share'], 400);
        }

        $content = trim($this->input('content', ''));

        try {
            if (class_exists(FeedPost::class)) {
                FeedPost::create($userId, $content, null, null, $parentId, $parentType);
            } else {
                DB::table('feed_posts')->insert([
                    'user_id'     => $userId,
                    'tenant_id'   => $tenantId,
                    'content'     => $content,
                    'likes_count' => 0,
                    'visibility'  => 'public',
                    'created_at'  => now(),
                    'parent_id'   => $parentId,
                    'parent_type' => $parentType,
                ]);
            }

            try {
                $contentOwnerId = SocialNotificationService::getContentOwnerId($parentType, $parentId);
                if ($contentOwnerId && $contentOwnerId != $userId) {
                    SocialNotificationService::notifyShare($contentOwnerId, $userId, $parentType, $parentId);
                }
            } catch (\Throwable $e) {
                // Non-critical
            }

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            error_log("Share Error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to share'], 500);
        }
    }

    /** POST /api/social/delete */
    public function delete(): JsonResponse
    {
        $this->rateLimit('social_delete', 20, 60);
        $userId = $this->requireAuth();
        $targetType = $this->input('target_type', '');
        $targetId = $this->inputInt('target_id');
        $tenantId = $this->getTenantId();

        if (empty($targetType) || ! $targetId || $targetId <= 0) {
            return response()->json(['error' => 'Invalid target'], 400);
        }

        try {
            $tableMap = [
                'post'         => ['table' => 'feed_posts', 'owner_col' => 'user_id'],
                'listing'      => ['table' => 'listings', 'owner_col' => 'user_id'],
                'event'        => ['table' => 'events', 'owner_col' => 'user_id'],
                'poll'         => ['table' => 'polls', 'owner_col' => 'user_id'],
                'goal'         => ['table' => 'goals', 'owner_col' => 'user_id'],
                'volunteering' => ['table' => 'vol_opportunities', 'owner_col' => 'created_by'],
            ];

            if (! isset($tableMap[$targetType])) {
                return response()->json(['error' => 'Unsupported target type for deletion'], 400);
            }

            $config = $tableMap[$targetType];
            $record = DB::table($config['table'])
                ->where('id', $targetId)
                ->where('tenant_id', $tenantId)
                ->first();

            if (! $record) {
                return response()->json(['error' => ucfirst($targetType) . ' not found'], 404);
            }

            $ownerId = $record->{$config['owner_col']};
            $isAdmin = $this->isUserAdmin();

            if ($ownerId != $userId && ! $isAdmin) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            // Delete associated likes and comments
            DB::table('likes')->where('target_type', $targetType)->where('target_id', $targetId)->where('tenant_id', $tenantId)->delete();
            DB::table('comments')->where('target_type', $targetType)->where('target_id', $targetId)->where('tenant_id', $tenantId)->delete();

            if ($targetType === 'listing') {
                DB::table($config['table'])->where('id', $targetId)->where('tenant_id', $tenantId)->update(['status' => 'deleted']);
            } elseif ($targetType === 'poll') {
                DB::table('poll_votes')->where('poll_id', $targetId)->whereIn('poll_id', function ($q) use ($tenantId) {
                    $q->select('id')->from('polls')->where('tenant_id', $tenantId);
                })->delete();
                DB::table('poll_options')->where('poll_id', $targetId)->whereIn('poll_id', function ($q) use ($tenantId) {
                    $q->select('id')->from('polls')->where('tenant_id', $tenantId);
                })->delete();
                DB::table($config['table'])->where('id', $targetId)->where('tenant_id', $tenantId)->delete();
            } elseif ($targetType === 'volunteering') {
                DB::table('vol_applications')->where('opportunity_id', $targetId)->where('tenant_id', $tenantId)->delete();
                DB::table('vol_shifts')->where('opportunity_id', $targetId)->where('tenant_id', $tenantId)->delete();
                DB::table($config['table'])->where('id', $targetId)->where('tenant_id', $tenantId)->delete();
            } else {
                DB::table($config['table'])->where('id', $targetId)->where('tenant_id', $tenantId)->delete();
            }

            if ($targetType === 'post') {
                try {
                    FeedActivityService::removeActivity('post', $targetId);
                } catch (\Exception $e) {
                    error_log("SocialController::delete feed_activity remove failed: " . $e->getMessage());
                }
            }

            return response()->json(['success' => true, 'status' => 'deleted']);
        } catch (\Exception $e) {
            error_log("Delete Post Error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to delete'], 500);
        }
    }

    /** POST /api/social/reaction */
    public function reaction(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        $commentId = $this->inputInt('comment_id');
        $targetType = $this->input('target_type', 'comment');
        $targetId = $this->inputInt('target_id');
        $emoji = $this->input('emoji', '');

        if ($targetId && $targetId > 0) {
            $commentId = $targetId;
        }

        if (! $commentId || $commentId <= 0 || empty($emoji)) {
            return response()->json(['error' => 'Invalid reaction data'], 400);
        }

        try {
            if (class_exists(CommentService::class)) {
                $result = CommentService::toggleReaction($userId, $tenantId, $commentId, $emoji);
                return response()->json($result);
            }

            $existing = DB::table('reactions')
                ->where('user_id', $userId)
                ->where('target_type', 'comment')
                ->where('target_id', $commentId)
                ->where('emoji', $emoji)
                ->first();

            if ($existing) {
                DB::table('reactions')->where('id', $existing->id)->where('tenant_id', $tenantId)->delete();
                $action = 'removed';
            } else {
                DB::table('reactions')->insert([
                    'user_id'     => $userId,
                    'tenant_id'   => $tenantId,
                    'target_type' => 'comment',
                    'target_id'   => $commentId,
                    'emoji'       => $emoji,
                    'created_at'  => now(),
                ]);
                $action = 'added';
            }

            return response()->json(['success' => true, 'status' => 'success', 'action' => $action]);
        } catch (\Exception $e) {
            error_log("Reaction Error: " . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to toggle reaction'], 500);
        }
    }

    /** POST /api/social/reply */
    public function reply(): JsonResponse
    {
        $this->rateLimit('social_reply', 30, 60);
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        $parentId = $this->inputInt('parent_id');
        $targetType = $this->input('target_type', '');
        $targetId = $this->inputInt('target_id');
        $content = trim($this->input('content', ''));

        if (! $parentId || $parentId <= 0 || empty($content)) {
            return response()->json(['error' => 'Invalid reply data'], 400);
        }

        try {
            if (class_exists(CommentService::class)) {
                $result = CommentService::addComment($userId, $tenantId, $targetType, $targetId, $content, $parentId);
                return response()->json($result);
            }

            DB::table('comments')->insert([
                'user_id'     => $userId,
                'tenant_id'   => $tenantId,
                'target_type' => $targetType,
                'target_id'   => $targetId,
                'parent_id'   => $parentId,
                'content'     => $content,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            return response()->json(['success' => true, 'status' => 'success']);
        } catch (\Exception $e) {
            error_log("Reply Comment Error: " . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Failed to post reply'], 500);
        }
    }

    /** POST /api/social/edit-comment */
    public function editComment(): JsonResponse
    {
        $this->rateLimit('social_edit_comment', 30, 60);
        $userId = $this->requireAuth();
        $commentId = $this->inputInt('comment_id');
        $content = trim($this->input('content', ''));
        $tenantId = $this->getTenantId();

        if (! $commentId || $commentId <= 0 || empty($content)) {
            return response()->json(['error' => 'Invalid edit data'], 400);
        }

        try {
            if (class_exists(CommentService::class)) {
                $result = CommentService::editComment($commentId, $userId, $content);
                return response()->json($result);
            }

            $comment = DB::table('comments')->where('id', $commentId)->where('tenant_id', $tenantId)->first();
            if (! $comment || $comment->user_id != $userId) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            DB::table('comments')
                ->where('id', $commentId)
                ->where('tenant_id', $tenantId)
                ->update(['content' => $content, 'updated_at' => now()]);

            return response()->json(['success' => true, 'status' => 'success', 'is_edited' => true]);
        } catch (\Exception $e) {
            error_log("Edit Comment Error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to edit comment'], 500);
        }
    }

    /** POST /api/social/delete-comment */
    public function deleteComment(): JsonResponse
    {
        $this->rateLimit('social_delete_comment', 20, 60);
        $userId = $this->requireAuth();
        $commentId = $this->inputInt('comment_id');
        $tenantId = $this->getTenantId();

        if (! $commentId || $commentId <= 0) {
            return response()->json(['error' => 'Invalid comment ID'], 400);
        }

        try {
            if (class_exists(CommentService::class)) {
                $result = CommentService::deleteComment($commentId, $userId, $this->isUserAdmin());
                return response()->json($result);
            }

            $comment = DB::table('comments')->where('id', $commentId)->where('tenant_id', $tenantId)->first();
            if (! $comment) {
                return response()->json(['error' => 'Comment not found'], 404);
            }
            if ($comment->user_id != $userId && ! $this->isUserAdmin()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            DB::table('comments')->where('id', $commentId)->where('tenant_id', $tenantId)->delete();

            return response()->json(['success' => true, 'status' => 'success']);
        } catch (\Exception $e) {
            error_log("Delete Comment Error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to delete comment'], 500);
        }
    }

    /** POST /api/social/mention-search */
    public function mentionSearch(): JsonResponse
    {
        $this->rateLimit('social_mention_search', 30, 60);
        $this->requireAuth();
        $tenantId = $this->getTenantId();

        $query = trim($this->input('query', ''));

        if (strlen($query) < 1) {
            return response()->json(['status' => 'success', 'users' => []]);
        }

        try {
            if (class_exists(CommentService::class)) {
                $users = CommentService::searchUsersForMention($query, $tenantId, 10);
            } else {
                $searchTerm = "%{$query}%";
                $users = DB::select(
                    "SELECT id, COALESCE(name, first_name) as name, avatar_url
                     FROM users WHERE tenant_id = ? AND (name LIKE ? OR first_name LIKE ? OR username LIKE ?) LIMIT 10",
                    [$tenantId, $searchTerm, $searchTerm, $searchTerm]
                );
                $users = array_map(fn ($u) => (array) $u, $users);
            }

            return response()->json(['status' => 'success', 'users' => $users]);
        } catch (\Exception $e) {
            error_log("Mention Search Error: " . $e->getMessage());
            return response()->json(['error' => 'Search failed'], 500);
        }
    }

    /** POST /api/social/feed — delegates to legacy for complex aggregated SQL */
    public function feed(): JsonResponse
    {
        return $this->delegateToLegacy(\Nexus\Controllers\Api\SocialApiController::class, 'feed');
    }

    // ========================================================================
    // Private helpers
    // ========================================================================

    /**
     * Check if current user is an admin
     */
    private function isUserAdmin(): bool
    {
        try {
            $userId = $this->getOptionalUserId();
            if (! $userId) {
                return false;
            }
            $user = DB::table('users')->where('id', $userId)->first(['role', 'is_super_admin', 'is_tenant_super_admin']);
            if (! $user) {
                return false;
            }
            return in_array($user->role, ['admin', 'super_admin', 'tenant_admin', 'god'])
                || ($user->is_super_admin ?? false)
                || ($user->is_tenant_super_admin ?? false);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Delegate to legacy controller via output buffering.
     * Only used for file upload methods.
     */
    private function delegateToLegacy(string $legacyClass, string $method, array $params = []): JsonResponse
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
