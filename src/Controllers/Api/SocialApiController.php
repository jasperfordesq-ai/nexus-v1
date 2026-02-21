<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\CommentService;
use Nexus\Services\SocialNotificationService;
use Nexus\Services\FeedService;
use Nexus\Models\FeedPost;

/**
 * MASTER PLATFORM SOCIAL MEDIA MODULE - Unified API Controller
 * =============================================================
 *
 * Handles ALL social interactions for ANY layout:
 * - Modern Layout
 * - Nexus Social Layout
 * - CivicOne Layout
 * - Future layouts
 *
 * V2 Endpoints (new standardized API with cursor pagination):
 * - GET  /api/v2/feed              - Load feed items (cursor paginated)
 * - POST /api/v2/feed/posts        - Create new post
 * - POST /api/v2/feed/like         - Toggle like on content
 *
 * Legacy V1 Endpoints:
 * - POST /api/social/like          - Toggle like
 * - POST /api/social/comments      - Fetch/submit comments
 * - POST /api/social/share         - Repost/share content
 * - POST /api/social/delete        - Delete post (admin)
 * - POST /api/social/reaction      - Toggle emoji reaction
 * - POST /api/social/reply         - Reply to comment
 * - POST /api/social/edit-comment  - Edit comment
 * - POST /api/social/delete-comment - Delete comment
 * - POST /api/social/mention-search - Search users for @mention
 * - POST /api/social/feed          - Load feed items (pagination)
 *
 * Refactored 2026-01-30: Now extends BaseApiController to eliminate code duplication
 * Methods inherited from BaseApiController: jsonResponse, input, verifyCsrf, getUserId, getTenantId
 *
 * @package Nexus\Modules\Social
 */
class SocialApiController extends BaseApiController
{
    // ============================================
    // V2 ENDPOINTS (New standardized API)
    // ============================================

    /**
     * GET /api/v2/feed
     * Load feed with cursor-based pagination
     *
     * Query Parameters:
     * - type: 'all' (default), 'posts', 'listings', 'events', 'polls', 'goals'
     * - user_id: int (filter by specific user's content)
     * - group_id: int (filter by group)
     * - cursor: string (pagination cursor)
     * - per_page: int (default 20, max 100)
     */
    public function feedV2(): void
    {
        $userId = $this->getOptionalUserId();
        $this->rateLimit('feed_list', 60, 60);

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
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

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = FeedService::getFeed($userId, $filters);

        $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /**
     * POST /api/v2/feed/posts
     * Create a new feed post
     *
     * Request Body (JSON):
     * {
     *   "content": "string",
     *   "image_url": "string (optional)",
     *   "visibility": "public|private (default: public)",
     *   "group_id": "int (optional)"
     * }
     */
    public function createPostV2(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('feed_create', 20, 60);

        $data = $this->getAllInput();

        $postId = FeedService::createPost($userId, $data);

        if ($postId === null) {
            $errors = FeedService::getErrors();
            $status = 422;

            foreach ($errors as $error) {
                if ($error['code'] === 'FORBIDDEN') {
                    $status = 403;
                    break;
                }
            }

            $this->respondWithErrors($errors, $status);
        }

        // Get the created post
        $post = FeedService::getItem('post', $postId, $userId);

        $this->respondWithData($post, null, 201);
    }

    /**
     * POST /api/v2/feed/like
     * Toggle like on content
     *
     * Request Body (JSON):
     * {
     *   "target_type": "post|listing|event|poll|goal",
     *   "target_id": int
     * }
     */
    public function likeV2(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('feed_like', 60, 60);

        $targetType = $this->input('target_type');
        $targetId = $this->inputInt('target_id');

        if (empty($targetType) || !$targetId) {
            $this->respondWithError('VALIDATION_ERROR', 'target_type and target_id are required', null, 400);
            return;
        }

        if (!in_array($targetType, self::VALID_LIKE_TARGETS, true)) {
            $this->respondWithError('VALIDATION_ERROR', 'Invalid target_type', 'target_type', 400);
            return;
        }

        $result = FeedService::toggleLike($userId, $targetType, $targetId);

        $this->respondWithData($result);
    }

    /**
     * POST /api/v2/feed/polls
     * Create a new poll
     */
    public function createPollV2(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('feed_create_poll', 10, 60);

        $data = $this->getAllInput();
        $question = trim($data['question'] ?? '');
        $options = $data['options'] ?? [];

        if (empty($question)) {
            $this->respondWithError('VALIDATION_ERROR', 'Question is required', 'question', 400);
            return;
        }

        if (!is_array($options) || count($options) < 2) {
            $this->respondWithError('VALIDATION_ERROR', 'At least 2 options are required', 'options', 400);
            return;
        }

        $pollData = [
            'question' => $question,
            'options' => $options,
            'expires_at' => $data['expires_at'] ?? null,
            'visibility' => $data['visibility'] ?? 'public',
        ];

        $pollId = \Nexus\Services\PollService::create($userId, $pollData);

        if ($pollId === null) {
            $errors = \Nexus\Services\PollService::getErrors();
            $this->respondWithErrors($errors, 422);
            return;
        }

        $poll = \Nexus\Services\PollService::getById($pollId, $userId);
        $this->respondWithData($poll, null, 201);
    }

    /**
     * POST /api/v2/feed/polls/{id}/vote
     * Vote on a poll option
     */
    public function votePollV2(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('feed_poll_vote', 30, 60);

        $optionId = (int) ($this->input('option_id') ?? 0);

        if (!$optionId) {
            $this->respondWithError('VALIDATION_ERROR', 'option_id is required', 'option_id', 400);
            return;
        }

        $success = \Nexus\Services\PollService::vote($id, $optionId, $userId);

        if (!$success) {
            $errors = \Nexus\Services\PollService::getErrors();
            $this->respondWithErrors($errors, 400);
            return;
        }

        $poll = \Nexus\Services\PollService::getById($id, $userId);
        $this->respondWithData($poll);
    }

    /**
     * GET /api/v2/feed/polls/{id}
     * Get poll details
     */
    public function getPollV2(int $id): void
    {
        $userId = $this->getOptionalUserId();
        $this->rateLimit('feed_poll_get', 60, 60);

        $poll = \Nexus\Services\PollService::getById($id, $userId);

        if (!$poll) {
            $this->respondWithError('RESOURCE_NOT_FOUND', 'Poll not found', null, 404);
            return;
        }

        $this->respondWithData($poll);
    }

    /**
     * POST /api/v2/feed/posts/{id}/hide
     * Hide a post from user's feed
     */
    public function hidePostV2(int $id): void
    {
        $userId = $this->getUserId();
        $tenantId = TenantContext::getId();

        Database::query(
            "INSERT IGNORE INTO feed_hidden (user_id, tenant_id, target_type, target_id, created_at) VALUES (?, ?, 'post', ?, NOW())",
            [$userId, $tenantId, $id]
        );

        $this->respondWithData(['hidden' => true, 'post_id' => $id]);
    }

    /**
     * POST /api/v2/feed/users/{id}/mute
     * Mute a user in the feed
     */
    public function muteUserV2(int $userId): void
    {
        $currentUserId = $this->getUserId();
        $tenantId = TenantContext::getId();

        Database::query(
            "INSERT IGNORE INTO feed_muted_users (user_id, tenant_id, muted_user_id, created_at) VALUES (?, ?, ?, NOW())",
            [$currentUserId, $tenantId, $userId]
        );

        $this->respondWithData(['muted' => true, 'user_id' => $userId]);
    }

    /**
     * POST /api/v2/feed/posts/{id}/report
     * Report a feed post
     */
    public function reportPostV2(int $id): void
    {
        $userId = $this->getUserId();
        $tenantId = TenantContext::getId();
        $reason = trim($this->input('reason') ?? '');

        // Validate reason length (1-1000 characters)
        if (mb_strlen($reason) > 1000) {
            $reason = mb_substr($reason, 0, 1000);
        }

        Database::query(
            "INSERT INTO reports (user_id, tenant_id, target_type, target_id, reason, status, created_at)
             VALUES (?, ?, 'feed_post', ?, ?, 'pending', NOW())",
            [$userId, $tenantId, $id, $reason]
        );

        $this->respondWithData(['reported' => true, 'post_id' => $id]);
    }

    /**
     * POST /api/v2/feed/posts/{id}/delete
     * Delete a feed post (owner only)
     */
    public function deletePostV2(int $id): void
    {
        $userId = $this->getUserId();
        $tenantId = $this->getTenantId();
        $this->verifyCsrf();

        $post = Database::query(
            "SELECT id, user_id FROM feed_posts WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if (!$post) {
            $this->respondWithError('RESOURCE_NOT_FOUND', 'Post not found', null, 404);
            return;
        }

        if ((int) $post['user_id'] !== $userId) {
            $this->respondWithError('FORBIDDEN', 'You can only delete your own posts', null, 403);
            return;
        }

        Database::query("DELETE FROM feed_posts WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);

        $this->respondWithData(['deleted' => true, 'id' => $id]);
    }

    // ============================================
    // DEBUG TEST ENDPOINT (LEGACY)
    // ============================================

    /**
     * Test endpoint to verify API is working
     * GET /api/social/test
     * SECURITY: Restricted to admin users only
     */
    public function test()
    {
        // SECURITY: Require admin authentication for debug endpoints
        $this->requireAdmin();

        $debug = [
            'api_working' => true,
            'request_method' => $_SERVER['REQUEST_METHOD'],
            'likes_table_exists' => false
        ];

        try {
            $result = Database::query("SELECT COUNT(*) as cnt FROM likes")->fetch();
            $debug['likes_table_exists'] = true;
        } catch (\Exception $e) {
            $debug['likes_error'] = 'Database error';
        }

        $this->jsonResponse($debug);
    }

    // ============================================
    // UTILITY METHODS (local helpers)
    // ============================================

    /**
     * Get input value - wrapper for BaseApiController::input()
     * Kept for backward compatibility with existing code
     */
    private function getInput($key, $default = null)
    {
        return $this->input($key, $default);
    }

    /**
     * Check if current user is super admin
     * Uses ApiAuth trait method for proper Bearer/session support
     */
    private function isSuperAdmin(): bool
    {
        $role = $this->getAuthenticatedUserRole() ?? '';
        return in_array($role, ['admin', 'super_admin', 'tenant_admin', 'god']);
    }

    // ============================================
    // LIKE FUNCTIONALITY
    // ============================================

    /**
     * Toggle like on any content type
     * POST /api/social/like
     *
     * @param string target_type - post, listing, event, poll, goal, resource, volunteering
     * @param int target_id
     */
    /** Valid target types for likes */
    private const VALID_LIKE_TARGETS = ['post', 'listing', 'event', 'poll', 'goal', 'resource', 'volunteering', 'review', 'comment'];

    /** Valid visibility values */
    private const VALID_VISIBILITY = ['public', 'private', 'friends'];

    public function like()
    {
        $this->verifyCsrf();
        $this->rateLimit('social_like', 60, 60);
        $userId = $this->getUserId();
        $tenantId = $this->getTenantId();

        $targetType = $this->getInput('target_type', '');
        $targetId = (int)$this->getInput('target_id', 0);

        if (empty($targetType) || $targetId <= 0) {
            $this->jsonResponse(['error' => 'Invalid target'], 400);
        }

        if (!in_array($targetType, self::VALID_LIKE_TARGETS, true)) {
            $this->jsonResponse(['error' => 'Invalid target_type'], 400);
        }

        try {
            // Check if already liked
            $existing = Database::query(
                "SELECT id FROM likes WHERE user_id = ? AND target_type = ? AND target_id = ? AND tenant_id = ?",
                [$userId, $targetType, $targetId, $tenantId]
            )->fetch();

            if ($existing) {
                // Unlike
                Database::query("DELETE FROM likes WHERE id = ? AND tenant_id = ?", [$existing['id'], $tenantId]);

                // Decrement likes_count on feed_posts if applicable
                if ($targetType === 'post') {
                    Database::query("UPDATE feed_posts SET likes_count = GREATEST(likes_count - 1, 0) WHERE id = ?", [$targetId]);
                }

                $action = 'unliked';
            } else {
                // Like
                Database::query(
                    "INSERT INTO likes (user_id, target_type, target_id, tenant_id, created_at) VALUES (?, ?, ?, ?, NOW())",
                    [$userId, $targetType, $targetId, $tenantId]
                );

                // Increment likes_count on feed_posts if applicable
                if ($targetType === 'post') {
                    Database::query("UPDATE feed_posts SET likes_count = likes_count + 1 WHERE id = ?", [$targetId]);
                }

                $action = 'liked';

                // Send notification to content owner
                $this->notifyLike($userId, $targetType, $targetId);
            }

            // Get updated count (tenant-scoped)
            $countResult = Database::query(
                "SELECT COUNT(*) as cnt FROM likes WHERE target_type = ? AND target_id = ? AND tenant_id = ?",
                [$targetType, $targetId, $tenantId]
            )->fetch();

            $this->jsonResponse([
                'status' => $action,
                'likes_count' => (int)($countResult['cnt'] ?? 0)
            ]);

        } catch (\Exception $e) {
            error_log("Social Like Error: " . $e->getMessage());
            $this->jsonResponse(['error' => 'Failed to process like'], 500);
        }
    }

    private function notifyLike($userId, $targetType, $targetId)
    {
        try {
            if (class_exists('\Nexus\Services\SocialNotificationService')) {
                $contentOwnerId = SocialNotificationService::getContentOwnerId($targetType, $targetId);
                if ($contentOwnerId && $contentOwnerId != $userId) {
                    SocialNotificationService::notifyLike($contentOwnerId, $userId, $targetType, $targetId, '');
                }
            }
        } catch (\Throwable $e) {
            // Don't let notification failures break the like action
            error_log("notifyLike error (non-critical): " . $e->getMessage());
        }
    }

    // ============================================
    // VIEW LIKERS (Who liked this content)
    // ============================================

    /**
     * Get list of users who liked content
     * POST /api/social/likers
     *
     * @param string target_type - post, listing, event, poll, goal, volunteering
     * @param int target_id
     * @param int page (optional, default 1)
     * @param int limit (optional, default 20)
     */
    public function likers()
    {
        $targetType = $this->getInput('target_type', '');
        $targetId = (int)$this->getInput('target_id', 0);
        $page = max(1, (int)$this->getInput('page', 1));
        $limit = min(50, max(5, (int)$this->getInput('limit', 20)));
        $offset = ($page - 1) * $limit;

        if (empty($targetType) || $targetId <= 0) {
            $this->jsonResponse(['error' => 'Invalid target'], 400);
        }

        try {
            // Get users who liked this content
            $tenantId = $this->getTenantId();

            $sql = "SELECT
                        u.id,
                        COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as name,
                        u.avatar_url,
                        l.created_at as liked_at
                    FROM likes l
                    JOIN users u ON l.user_id = u.id
                    WHERE l.target_type = ? AND l.target_id = ? AND l.tenant_id = ?
                    ORDER BY l.created_at DESC
                    LIMIT $limit OFFSET $offset";

            $likers = Database::query($sql, [$targetType, $targetId, $tenantId])->fetchAll(\PDO::FETCH_ASSOC);

            // Get total count (tenant-scoped)
            $countResult = Database::query(
                "SELECT COUNT(*) as cnt FROM likes WHERE target_type = ? AND target_id = ? AND tenant_id = ?",
                [$targetType, $targetId, $tenantId]
            )->fetch();
            $totalCount = (int)($countResult['cnt'] ?? 0);

            // Format avatar URLs
            foreach ($likers as &$liker) {
                if (empty($liker['avatar_url'])) {
                    $liker['avatar_url'] = '/assets/img/defaults/default_avatar.png';
                }
                // Format the liked_at timestamp for display
                $liker['liked_at_formatted'] = date('M j, Y', strtotime($liker['liked_at']));
            }

            $this->jsonResponse([
                'success' => true,
                'status' => 'success',
                'likers' => $likers,
                'total_count' => $totalCount,
                'page' => $page,
                'has_more' => ($offset + count($likers)) < $totalCount
            ]);

        } catch (\Exception $e) {
            error_log("Get Likers Error: " . $e->getMessage());
            $this->jsonResponse(['error' => 'Failed to fetch likers'], 500);
        }
    }

    // ============================================
    // COMMENT FUNCTIONALITY
    // ============================================

    /**
     * Handle comments - fetch or submit
     * POST /api/social/comments
     *
     * @param string action - fetch_comments, submit_comment
     * @param string target_type
     * @param int target_id
     * @param string content (for submit)
     */
    public function comments()
    {
        $this->rateLimit('social_comments', 60, 60);
        $action = $this->getInput('action', '');
        $targetType = $this->getInput('target_type', '');
        $targetId = (int)$this->getInput('target_id', 0);

        if (empty($targetType) || $targetId <= 0) {
            $this->jsonResponse(['error' => 'Invalid target'], 400);
        }

        switch ($action) {
            case 'fetch':
            case 'fetch_comments':
                $this->fetchComments($targetType, $targetId);
                break;
            case 'submit':
            case 'submit_comment':
                $this->verifyCsrf(); // CSRF protection for state-changing actions
                $this->submitComment($targetType, $targetId);
                break;
            default:
                $this->jsonResponse(['error' => 'Invalid action'], 400);
        }
    }

    private function fetchComments($targetType, $targetId)
    {
        try {
            $userId = $this->getOptionalUserId() ?? 0;

            if (class_exists('\Nexus\Services\CommentService')) {
                $comments = CommentService::fetchComments($targetType, $targetId, $userId);

                $this->jsonResponse([
                    'success' => true,
                    'status' => 'success',
                    'comments' => $comments,
                    'available_reactions' => CommentService::getAvailableReactions()
                ]);
            } else {
                // Fallback query
                $sql = "SELECT c.id, c.content, c.created_at, c.user_id,
                    COALESCE(u.name, u.first_name, 'Unknown') as author_name,
                    COALESCE(u.avatar_url, '/assets/img/defaults/default_avatar.png') as author_avatar
                    FROM comments c LEFT JOIN users u ON c.user_id = u.id
                    WHERE c.target_type = ? AND c.target_id = ? ORDER BY c.created_at ASC";
                $comments = Database::query($sql, [$targetType, $targetId])->fetchAll(\PDO::FETCH_ASSOC);

                // Add is_owner flag
                foreach ($comments as &$c) {
                    $c['is_owner'] = ($userId && $c['user_id'] == $userId);
                    $c['reactions'] = [];
                    $c['replies'] = [];
                }

                $this->jsonResponse(['success' => true, 'status' => 'success', 'comments' => $comments]);
            }
        } catch (\Exception $e) {
            error_log("Fetch Comments Error: " . $e->getMessage());
            $this->jsonResponse(['error' => 'Failed to fetch comments'], 500);
        }
    }

    private function submitComment($targetType, $targetId)
    {
        $userId = $this->getUserId();
        $tenantId = $this->getTenantId();
        $content = trim($this->getInput('content', ''));

        if (empty($content)) {
            $this->jsonResponse(['error' => 'Comment cannot be empty'], 400);
        }

        try {
            if (class_exists('\Nexus\Services\CommentService')) {
                $result = CommentService::addComment($userId, $tenantId, $targetType, $targetId, $content);

                // Send notification
                if ($result['status'] === 'success') {
                    $this->notifyComment($userId, $targetType, $targetId, $content);
                }

                $this->jsonResponse($result);
            } else {
                // Fallback insert
                Database::query(
                    "INSERT INTO comments (user_id, tenant_id, target_type, target_id, content, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
                    [$userId, $tenantId, $targetType, $targetId, $content]
                );

                $this->notifyComment($userId, $targetType, $targetId, $content);

                // Fetch user info for response (avoid session dependency)
                $user = Database::query(
                    "SELECT COALESCE(name, CONCAT(first_name, ' ', last_name)) as name, avatar_url FROM users WHERE id = ?",
                    [$userId]
                )->fetch();

                $this->jsonResponse([
                    'success' => true,
                    'status' => 'success',
                    'comment' => [
                        'author_name' => $user['name'] ?? 'Me',
                        'author_avatar' => $user['avatar_url'] ?? '/assets/img/defaults/default_avatar.png',
                        'content' => $content
                    ]
                ]);
            }
        } catch (\Exception $e) {
            error_log("Submit Comment Error: " . $e->getMessage());
            $this->jsonResponse(['error' => 'Failed to post comment'], 500);
        }
    }

    private function notifyComment($userId, $targetType, $targetId, $content)
    {
        try {
            if (class_exists('\Nexus\Services\SocialNotificationService')) {
                $contentOwnerId = SocialNotificationService::getContentOwnerId($targetType, $targetId);
                if ($contentOwnerId && $contentOwnerId != $userId) {
                    SocialNotificationService::notifyComment($contentOwnerId, $userId, $targetType, $targetId, $content);
                }
            }
        } catch (\Throwable $e) {
            // Don't let notification failures break the comment action
            error_log("notifyComment error (non-critical): " . $e->getMessage());
        }
    }

    // ============================================
    // REPLY TO COMMENT
    // ============================================

    /**
     * Reply to a comment (nested comments)
     * POST /api/social/reply
     *
     * @param int parent_id - Parent comment ID
     * @param string target_type
     * @param int target_id
     * @param string content
     */
    public function reply()
    {
        $this->verifyCsrf();
        $this->rateLimit('social_reply', 30, 60);
        $userId = $this->getUserId();
        $tenantId = $this->getTenantId();

        $parentId = (int)$this->getInput('parent_id', 0);
        $targetType = $this->getInput('target_type', '');
        $targetId = (int)$this->getInput('target_id', 0);
        $content = trim($this->getInput('content', ''));

        if ($parentId <= 0 || empty($content)) {
            $this->jsonResponse(['error' => 'Invalid reply data'], 400);
        }

        try {
            if (class_exists('\Nexus\Services\CommentService')) {
                $result = CommentService::addComment($userId, $tenantId, $targetType, $targetId, $content, $parentId);
                $this->jsonResponse($result);
            } else {
                // Fallback
                Database::query(
                    "INSERT INTO comments (user_id, tenant_id, target_type, target_id, parent_id, content, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())",
                    [$userId, $tenantId, $targetType, $targetId, $parentId, $content]
                );
                $this->jsonResponse(['success' => true, 'status' => 'success']);
            }
        } catch (\Exception $e) {
            error_log("Reply Comment Error: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Failed to post reply'], 500);
        }
    }

    // ============================================
    // EDIT COMMENT
    // ============================================

    /**
     * Edit a comment (owner only)
     * POST /api/social/edit-comment
     *
     * @param int comment_id
     * @param string content
     */
    public function editComment()
    {
        $this->verifyCsrf();
        $this->rateLimit('social_edit_comment', 30, 60);
        $userId = $this->getUserId();
        $commentId = (int)$this->getInput('comment_id', 0);
        $content = trim($this->getInput('content', ''));

        if ($commentId <= 0 || empty($content)) {
            $this->jsonResponse(['error' => 'Invalid edit data'], 400);
        }

        try {
            if (class_exists('\Nexus\Services\CommentService')) {
                $result = CommentService::editComment($commentId, $userId, $content);
                $this->jsonResponse($result);
            } else {
                // Fallback - verify ownership
                $comment = Database::query("SELECT user_id FROM comments WHERE id = ?", [$commentId])->fetch();
                if (!$comment || $comment['user_id'] != $userId) {
                    $this->jsonResponse(['error' => 'Unauthorized'], 403);
                }
                Database::query("UPDATE comments SET content = ?, updated_at = NOW() WHERE id = ?", [$content, $commentId]);
                $this->jsonResponse(['success' => true, 'status' => 'success', 'is_edited' => true]);
            }
        } catch (\Exception $e) {
            error_log("Edit Comment Error: " . $e->getMessage());
            $this->jsonResponse(['error' => 'Failed to edit comment'], 500);
        }
    }

    // ============================================
    // DELETE COMMENT
    // ============================================

    /**
     * Delete a comment (owner or admin)
     * POST /api/social/delete-comment
     *
     * @param int comment_id
     */
    public function deleteComment()
    {
        $this->verifyCsrf();
        $this->rateLimit('social_delete_comment', 20, 60);
        $userId = $this->getUserId();
        $commentId = (int)$this->getInput('comment_id', 0);

        if ($commentId <= 0) {
            $this->jsonResponse(['error' => 'Invalid comment ID'], 400);
        }

        try {
            if (class_exists('\Nexus\Services\CommentService')) {
                $result = CommentService::deleteComment($commentId, $userId, $this->isSuperAdmin());
                $this->jsonResponse($result);
            } else {
                // Fallback - verify ownership
                $tenantId = $this->getTenantId();
                $comment = Database::query("SELECT user_id FROM comments WHERE id = ? AND tenant_id = ?", [$commentId, $tenantId])->fetch();
                if (!$comment) {
                    $this->jsonResponse(['error' => 'Comment not found'], 404);
                }
                if ($comment['user_id'] != $userId && !$this->isSuperAdmin()) {
                    $this->jsonResponse(['error' => 'Unauthorized'], 403);
                }
                Database::query("DELETE FROM comments WHERE id = ? AND tenant_id = ?", [$commentId, $tenantId]);
                $this->jsonResponse(['success' => true, 'status' => 'success']);
            }
        } catch (\Exception $e) {
            error_log("Delete Comment Error: " . $e->getMessage());
            $this->jsonResponse(['error' => 'Failed to delete comment'], 500);
        }
    }

    // ============================================
    // EMOJI REACTIONS
    // ============================================

    /**
     * Toggle emoji reaction on a comment
     * POST /api/social/reaction
     *
     * @param int comment_id
     * @param string emoji
     */
    public function reaction()
    {
        $this->verifyCsrf();
        $userId = $this->getUserId();
        $tenantId = $this->getTenantId();

        // Support both comment_id and target_id/target_type for flexibility
        $commentId = (int)$this->getInput('comment_id', 0);
        $targetType = $this->getInput('target_type', 'comment');
        $targetId = (int)$this->getInput('target_id', 0);
        $emoji = $this->getInput('emoji', '');

        // If target_id is provided, use it; otherwise fall back to comment_id
        if ($targetId > 0) {
            $commentId = $targetId;
        }

        if ($commentId <= 0 || empty($emoji)) {
            $this->jsonResponse(['error' => 'Invalid reaction data'], 400);
        }

        try {
            if (class_exists('\Nexus\Services\CommentService')) {
                $result = CommentService::toggleReaction($userId, $tenantId, $commentId, $emoji);
                $this->jsonResponse($result);
            } else {
                // Fallback
                $existing = Database::query(
                    "SELECT id FROM reactions WHERE user_id = ? AND target_type = 'comment' AND target_id = ? AND emoji = ?",
                    [$userId, $commentId, $emoji]
                )->fetch();

                if ($existing) {
                    Database::query("DELETE FROM reactions WHERE id = ? AND tenant_id = ?", [$existing['id'], $tenantId]);
                    $action = 'removed';
                } else {
                    Database::query(
                        "INSERT INTO reactions (user_id, tenant_id, target_type, target_id, emoji, created_at) VALUES (?, ?, 'comment', ?, ?, NOW())",
                        [$userId, $tenantId, $commentId, $emoji]
                    );
                    $action = 'added';
                }

                $this->jsonResponse(['success' => true, 'status' => 'success', 'action' => $action]);
            }
        } catch (\Exception $e) {
            error_log("Reaction Error: " . $e->getMessage());
            $this->jsonResponse(['success' => false, 'error' => 'Failed to toggle reaction'], 500);
        }
    }

    // ============================================
    // SHARE/REPOST
    // ============================================

    /**
     * Share content to feed (repost)
     * POST /api/social/share
     *
     * @param string parent_type - post, listing, event, etc.
     * @param int parent_id
     * @param string content (optional comment)
     */
    public function share()
    {
        $this->verifyCsrf();
        $this->rateLimit('social_share', 20, 60);
        $userId = $this->getUserId();
        $tenantId = $this->getTenantId();

        $parentType = $this->getInput('parent_type', '');
        $parentId = (int)$this->getInput('parent_id', 0);
        $content = trim($this->getInput('content', ''));

        if (empty($parentType) || $parentId <= 0) {
            $this->jsonResponse(['error' => 'Invalid content to share'], 400);
        }

        try {
            if (class_exists('\Nexus\Models\FeedPost')) {
                FeedPost::create($userId, $content, null, null, $parentId, $parentType);
            } else {
                Database::query(
                    "INSERT INTO feed_posts (user_id, tenant_id, content, likes_count, visibility, created_at, parent_id, parent_type) VALUES (?, ?, ?, 0, 'public', NOW(), ?, ?)",
                    [$userId, $tenantId, $content, $parentId, $parentType]
                );
            }

            // Notify original content owner
            if (class_exists('\Nexus\Services\SocialNotificationService')) {
                $contentOwnerId = SocialNotificationService::getContentOwnerId($parentType, $parentId);
                if ($contentOwnerId && $contentOwnerId != $userId) {
                    SocialNotificationService::notifyShare($contentOwnerId, $userId, $parentType, $parentId);
                }
            }

            $this->jsonResponse(['status' => 'success']);

        } catch (\Exception $e) {
            error_log("Share Error: " . $e->getMessage());
            $this->jsonResponse(['error' => 'Failed to share'], 500);
        }
    }

    // ============================================
    // DELETE POST
    // ============================================

    /**
     * Delete a post (owner or admin)
     * POST /api/social/delete
     *
     * @param string target_type
     * @param int target_id
     */
    public function delete()
    {
        $this->verifyCsrf();
        $this->rateLimit('social_delete', 20, 60);
        $userId = $this->getUserId();
        $targetType = $this->getInput('target_type', '');
        $targetId = (int)$this->getInput('target_id', 0);

        if (empty($targetType) || $targetId <= 0) {
            $this->jsonResponse(['error' => 'Invalid target'], 400);
        }

        try {
            // Handle feed_posts deletion
            if ($targetType === 'post') {
                $tenantId = $this->getTenantId();

                // Check ownership
                $post = Database::query("SELECT user_id FROM feed_posts WHERE id = ? AND tenant_id = ?", [$targetId, $tenantId])->fetch();

                if (!$post) {
                    $this->jsonResponse(['error' => 'Post not found'], 404);
                }

                if ($post['user_id'] != $userId && !$this->isSuperAdmin()) {
                    $this->jsonResponse(['error' => 'Unauthorized'], 403);
                }

                // Delete associated likes and comments
                Database::query("DELETE FROM likes WHERE target_type = 'post' AND target_id = ? AND tenant_id = ?", [$targetId, $tenantId]);
                Database::query("DELETE FROM comments WHERE target_type = 'post' AND target_id = ? AND tenant_id = ?", [$targetId, $tenantId]);

                // Delete the post
                Database::query("DELETE FROM feed_posts WHERE id = ? AND tenant_id = ?", [$targetId, $tenantId]);

                $this->jsonResponse(['success' => true, 'status' => 'deleted']);
            }
            // Handle listing deletion
            elseif ($targetType === 'listing') {
                $tenantId = $this->getTenantId();

                // Check ownership
                $listing = Database::query("SELECT user_id FROM listings WHERE id = ? AND tenant_id = ?", [$targetId, $tenantId])->fetch();

                if (!$listing) {
                    $this->jsonResponse(['error' => 'Listing not found'], 404);
                }

                if ($listing['user_id'] != $userId && !$this->isSuperAdmin()) {
                    $this->jsonResponse(['error' => 'Unauthorized'], 403);
                }

                // Delete associated likes and comments
                Database::query("DELETE FROM likes WHERE target_type = 'listing' AND target_id = ? AND tenant_id = ?", [$targetId, $tenantId]);
                Database::query("DELETE FROM comments WHERE target_type = 'listing' AND target_id = ? AND tenant_id = ?", [$targetId, $tenantId]);

                // Soft delete the listing (using status = 'deleted' like the Listing model does)
                Database::query("UPDATE listings SET status = 'deleted' WHERE id = ? AND tenant_id = ?", [$targetId, $tenantId]);

                $this->jsonResponse(['success' => true, 'status' => 'deleted']);
            }
            // Handle event deletion
            elseif ($targetType === 'event') {
                $tenantId = $this->getTenantId();

                // Check ownership
                $event = Database::query("SELECT user_id FROM events WHERE id = ? AND tenant_id = ?", [$targetId, $tenantId])->fetch();

                if (!$event) {
                    $this->jsonResponse(['error' => 'Event not found'], 404);
                }

                if ($event['user_id'] != $userId && !$this->isSuperAdmin()) {
                    $this->jsonResponse(['error' => 'Unauthorized'], 403);
                }

                // Delete associated likes and comments
                Database::query("DELETE FROM likes WHERE target_type = 'event' AND target_id = ? AND tenant_id = ?", [$targetId, $tenantId]);
                Database::query("DELETE FROM comments WHERE target_type = 'event' AND target_id = ? AND tenant_id = ?", [$targetId, $tenantId]);

                // Delete event (hard delete)
                Database::query("DELETE FROM events WHERE id = ? AND tenant_id = ?", [$targetId, $tenantId]);

                $this->jsonResponse(['success' => true, 'status' => 'deleted']);
            }
            // Handle poll deletion
            elseif ($targetType === 'poll') {
                $tenantId = $this->getTenantId();

                // Check ownership
                $poll = Database::query("SELECT user_id FROM polls WHERE id = ? AND tenant_id = ?", [$targetId, $tenantId])->fetch();

                if (!$poll) {
                    $this->jsonResponse(['error' => 'Poll not found'], 404);
                }

                if ($poll['user_id'] != $userId && !$this->isSuperAdmin()) {
                    $this->jsonResponse(['error' => 'Unauthorized'], 403);
                }

                // Delete associated likes and comments
                Database::query("DELETE FROM likes WHERE target_type = 'poll' AND target_id = ? AND tenant_id = ?", [$targetId, $tenantId]);
                Database::query("DELETE FROM comments WHERE target_type = 'poll' AND target_id = ? AND tenant_id = ?", [$targetId, $tenantId]);

                // Delete poll votes and options first (scoped via poll ownership already verified above)
                Database::query("DELETE FROM poll_votes WHERE poll_id = ? AND poll_id IN (SELECT id FROM polls WHERE tenant_id = ?)", [$targetId, $tenantId]);
                Database::query("DELETE FROM poll_options WHERE poll_id = ? AND poll_id IN (SELECT id FROM polls WHERE tenant_id = ?)", [$targetId, $tenantId]);

                // Delete poll
                Database::query("DELETE FROM polls WHERE id = ? AND tenant_id = ?", [$targetId, $tenantId]);

                $this->jsonResponse(['success' => true, 'status' => 'deleted']);
            }
            // Handle goal deletion
            elseif ($targetType === 'goal') {
                $tenantId = $this->getTenantId();

                // Check ownership
                $goal = Database::query("SELECT user_id FROM goals WHERE id = ? AND tenant_id = ?", [$targetId, $tenantId])->fetch();

                if (!$goal) {
                    $this->jsonResponse(['error' => 'Goal not found'], 404);
                }

                if ($goal['user_id'] != $userId && !$this->isSuperAdmin()) {
                    $this->jsonResponse(['error' => 'Unauthorized'], 403);
                }

                // Delete associated likes and comments
                Database::query("DELETE FROM likes WHERE target_type = 'goal' AND target_id = ? AND tenant_id = ?", [$targetId, $tenantId]);
                Database::query("DELETE FROM comments WHERE target_type = 'goal' AND target_id = ? AND tenant_id = ?", [$targetId, $tenantId]);

                // Delete goal
                Database::query("DELETE FROM goals WHERE id = ? AND tenant_id = ?", [$targetId, $tenantId]);

                $this->jsonResponse(['success' => true, 'status' => 'deleted']);
            }
            // Handle volunteering opportunity deletion
            elseif ($targetType === 'volunteering') {
                $tenantId = $this->getTenantId();

                // Check ownership (created_by is the user who created it)
                $opp = Database::query("SELECT created_by FROM vol_opportunities WHERE id = ? AND tenant_id = ?", [$targetId, $tenantId])->fetch();

                if (!$opp) {
                    $this->jsonResponse(['error' => 'Opportunity not found'], 404);
                }

                if ($opp['created_by'] != $userId && !$this->isSuperAdmin()) {
                    $this->jsonResponse(['error' => 'Unauthorized'], 403);
                }

                // Delete associated likes and comments
                Database::query("DELETE FROM likes WHERE target_type = 'volunteering' AND target_id = ? AND tenant_id = ?", [$targetId, $tenantId]);
                Database::query("DELETE FROM comments WHERE target_type = 'volunteering' AND target_id = ? AND tenant_id = ?", [$targetId, $tenantId]);

                // Delete applications for this opportunity
                Database::query("DELETE FROM vol_applications WHERE opportunity_id = ?", [$targetId]);

                // Delete shifts for this opportunity
                Database::query("DELETE FROM vol_shifts WHERE opportunity_id = ?", [$targetId]);

                // Delete the opportunity
                Database::query("DELETE FROM vol_opportunities WHERE id = ? AND tenant_id = ?", [$targetId, $tenantId]);

                $this->jsonResponse(['success' => true, 'status' => 'deleted']);
            }
            else {
                $this->jsonResponse(['error' => 'Unsupported target type for deletion'], 400);
            }

        } catch (\Exception $e) {
            error_log("Delete Post Error: " . $e->getMessage());
            $this->jsonResponse(['error' => 'Failed to delete'], 500);
        }
    }

    // ============================================
    // @MENTION USER SEARCH
    // ============================================

    /**
     * Search users for @mention autocomplete
     * POST /api/social/mention-search
     *
     * @param string query
     */
    public function mentionSearch()
    {
        $this->rateLimit('social_mention_search', 30, 60);
        $this->getUserId(); // Must be logged in
        $tenantId = $this->getTenantId();

        $query = trim($this->getInput('query', ''));

        if (strlen($query) < 1) {
            $this->jsonResponse(['status' => 'success', 'users' => []]);
        }

        try {
            if (class_exists('\Nexus\Services\CommentService')) {
                $users = CommentService::searchUsersForMention($query, $tenantId, 10);
            } else {
                $searchTerm = "%$query%";
                $users = Database::query(
                    "SELECT id, COALESCE(name, first_name) as name, avatar_url
                     FROM users WHERE tenant_id = ? AND (name LIKE ? OR first_name LIKE ? OR username LIKE ?) LIMIT 10",
                    [$tenantId, $searchTerm, $searchTerm, $searchTerm]
                )->fetchAll(\PDO::FETCH_ASSOC);
            }

            $this->jsonResponse(['status' => 'success', 'users' => $users]);

        } catch (\Exception $e) {
            error_log("Mention Search Error: " . $e->getMessage());
            $this->jsonResponse(['error' => 'Search failed'], 500);
        }
    }

    // ============================================
    // FEED LOADING (Pagination / Infinite Scroll)
    // ============================================

    /**
     * Load feed items with pagination
     * POST /api/social/feed
     *
     * @param int page - Page number (default 1)
     * @param int limit - Items per page (default 20)
     * @param string filter - Optional filter (all, posts, listings, events, etc.)
     * @param int user_id - Optional: load specific user's posts (for profile)
     */
    public function feed()
    {
        $this->rateLimit('social_feed', 60, 60);
        $currentUserId = $this->getOptionalUserId(); // Not required
        $tenantId = $this->getTenantId();

        $page = max(1, (int)$this->getInput('page', 1));
        $limit = min(50, max(5, (int)$this->getInput('limit', 20)));
        $filter = $this->getInput('filter', 'all');
        $profileUserId = (int)$this->getInput('user_id', 0);
        $groupId = (int)$this->getInput('group_id', 0);

        // Support offset-based pagination as well as page-based
        $offset = (int)$this->getInput('offset', 0);
        if ($offset === 0) {
            $offset = ($page - 1) * $limit;
        }

        try {
            $items = [];

            if ($groupId > 0) {
                // Group feed - posts for a specific group
                $items = $this->loadGroupFeed($groupId, $currentUserId, $tenantId, $limit, $offset);
            } elseif ($profileUserId > 0) {
                // Profile feed - single user's posts
                $items = $this->loadUserPosts($profileUserId, $currentUserId, $tenantId, $limit, $offset);
            } else {
                // Main feed - aggregated content
                $items = $this->loadAggregatedFeed($currentUserId, $tenantId, $filter, $limit, $offset);
            }

            $this->jsonResponse([
                'success' => true,
                'status' => 'success',
                'items' => $items,
                'page' => $page,
                'has_more' => count($items) >= $limit
            ]);

        } catch (\Exception $e) {
            error_log("Feed Load Error: " . $e->getMessage());
            $this->jsonResponse(['error' => 'Failed to load feed'], 500);
        }
    }

    private function loadGroupFeed($groupId, $currentUserId, $tenantId, $limit, $offset)
    {
        // Security: Cast all numeric values to integers to prevent SQL injection
        $currentUserId = (int) $currentUserId;
        $limit = (int) $limit;
        $offset = (int) $offset;

        // Check if group_id column exists
        try {
            $columns = Database::query("SHOW COLUMNS FROM feed_posts LIKE 'group_id'")->fetch();
            if (empty($columns)) {
                // Column doesn't exist - return empty array
                return [];
            }
        } catch (\Exception $e) {
            return [];
        }

        $sql = "SELECT p.id, p.content, p.image_url, p.created_at, p.likes_count, p.user_id,
                       'post' as type,
                       COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name,
                       u.avatar_url as author_avatar,
                       p.user_id as author_id,
                       (SELECT COUNT(*) FROM comments cm WHERE cm.target_type = 'post' AND cm.target_id = p.id AND cm.tenant_id = $tenantId) as comments_count,
                       " . ($currentUserId ? "(SELECT COUNT(*) FROM likes lk WHERE lk.user_id = $currentUserId AND lk.target_type = 'post' AND lk.target_id = p.id AND lk.tenant_id = $tenantId)" : "0") . " as is_liked
                FROM feed_posts p
                JOIN users u ON p.user_id = u.id
                WHERE p.group_id = ? AND p.tenant_id = ?
                ORDER BY p.created_at DESC
                LIMIT $limit OFFSET $offset";

        return Database::query($sql, [$groupId, $tenantId])->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function loadUserPosts($userId, $currentUserId, $tenantId, $limit, $offset)
    {
        // Security: Cast all numeric values to integers to prevent SQL injection
        $currentUserId = (int) $currentUserId;
        $tenantId = (int) $tenantId;
        $limit = (int) $limit;
        $offset = (int) $offset;

        $sql = "SELECT p.id, p.content, p.image_url, p.created_at, p.likes_count,
                       'post' as type,
                       COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name,
                       u.avatar_url as author_avatar,
                       p.user_id as author_id,
                       (SELECT COUNT(*) FROM comments cm WHERE cm.target_type = 'post' AND cm.target_id = p.id AND cm.tenant_id = $tenantId) as comments_count,
                       " . ($currentUserId ? "(SELECT COUNT(*) FROM likes lk WHERE lk.user_id = $currentUserId AND lk.target_type = 'post' AND lk.target_id = p.id AND lk.tenant_id = $tenantId)" : "0") . " as is_liked
                FROM feed_posts p
                JOIN users u ON p.user_id = u.id
                WHERE p.user_id = ? AND p.tenant_id = ? AND p.visibility = 'public'
                ORDER BY p.created_at DESC
                LIMIT $limit OFFSET $offset";

        return Database::query($sql, [$userId, $tenantId])->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function loadAggregatedFeed($currentUserId, $tenantId, $filter, $limit, $offset)
    {
        // Security: Cast all numeric values to integers to prevent SQL injection
        $currentUserId = (int) $currentUserId;
        $limit = (int) $limit;
        $offset = (int) $offset;

        $items = [];
        // Tenant-scoped like subquery (int-cast above prevents SQL injection)
        $likeSubquery = $currentUserId
            ? "(SELECT COUNT(*) FROM likes WHERE user_id = $currentUserId AND target_type = '%s' AND target_id = %s.id AND tenant_id = $tenantId)"
            : "0";

        // Tenant-scoped count helpers
        $likesCountSub = "(SELECT COUNT(*) FROM likes lk WHERE lk.target_type = '%s' AND lk.target_id = %s.id AND lk.tenant_id = $tenantId)";
        $commentsCountSub = "(SELECT COUNT(*) FROM comments cm WHERE cm.target_type = '%s' AND cm.target_id = %s.id AND cm.tenant_id = $tenantId)";

        // Posts
        if ($filter === 'all' || $filter === 'posts') {
            $isLiked = sprintf($likeSubquery, 'post', 'p');
            $commentsSub = sprintf($commentsCountSub, 'post', 'p');
            $sql = "SELECT p.id, p.content, p.image_url, p.created_at, p.likes_count,
                           'post' as type,
                           COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name,
                           u.avatar_url as author_avatar,
                           p.user_id as author_id,
                           $commentsSub as comments_count,
                           $isLiked as is_liked
                    FROM feed_posts p
                    JOIN users u ON p.user_id = u.id
                    WHERE p.tenant_id = ? AND p.visibility = 'public'
                    ORDER BY p.created_at DESC
                    LIMIT " . ($filter === 'posts' ? $limit : 30);
            $items = array_merge($items, Database::query($sql, [$tenantId])->fetchAll(\PDO::FETCH_ASSOC));
        }

        // Listings
        if ($filter === 'all' || $filter === 'listings') {
            $isLiked = sprintf($likeSubquery, 'listing', 'l');
            $likesSub = sprintf($likesCountSub, 'listing', 'l');
            $commentsSub = sprintf($commentsCountSub, 'listing', 'l');
            $sql = "SELECT l.id, l.title, l.description as content, l.image_url, l.created_at,
                           'listing' as type,
                           COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name,
                           u.avatar_url as author_avatar,
                           l.user_id as author_id,
                           $likesSub as likes_count,
                           $commentsSub as comments_count,
                           $isLiked as is_liked
                    FROM listings l
                    JOIN users u ON l.user_id = u.id
                    WHERE l.tenant_id = ? AND l.status = 'active'
                    ORDER BY l.created_at DESC
                    LIMIT " . ($filter === 'listings' ? $limit : 15);
            $items = array_merge($items, Database::query($sql, [$tenantId])->fetchAll(\PDO::FETCH_ASSOC));
        }

        // Events
        if ($filter === 'all' || $filter === 'events') {
            $isLiked = sprintf($likeSubquery, 'event', 'e');
            $likesSub = sprintf($likesCountSub, 'event', 'e');
            $commentsSub = sprintf($commentsCountSub, 'event', 'e');
            $sql = "SELECT e.id, e.title, e.description as content, e.cover_image as image_url, e.created_at, e.start_time as start_date,
                           'event' as type,
                           COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name,
                           u.avatar_url as author_avatar,
                           e.user_id as author_id,
                           $likesSub as likes_count,
                           $commentsSub as comments_count,
                           $isLiked as is_liked
                    FROM events e
                    JOIN users u ON e.user_id = u.id
                    WHERE e.tenant_id = ?
                    ORDER BY e.created_at DESC
                    LIMIT " . ($filter === 'events' ? $limit : 10);
            $items = array_merge($items, Database::query($sql, [$tenantId])->fetchAll(\PDO::FETCH_ASSOC));
        }

        // Polls
        if ($filter === 'all' || $filter === 'polls') {
            $isLiked = sprintf($likeSubquery, 'poll', 'po');
            $likesSub = sprintf($likesCountSub, 'poll', 'po');
            $commentsSub = sprintf($commentsCountSub, 'poll', 'po');
            $sql = "SELECT po.id, po.question as title, po.question as content, po.created_at,
                           'poll' as type,
                           COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name,
                           u.avatar_url as author_avatar,
                           po.user_id as author_id,
                           $likesSub as likes_count,
                           $commentsSub as comments_count,
                           $isLiked as is_liked
                    FROM polls po
                    JOIN users u ON po.user_id = u.id
                    WHERE po.tenant_id = ? AND po.is_active = 1
                    ORDER BY po.created_at DESC
                    LIMIT " . ($filter === 'polls' ? $limit : 10);
            $items = array_merge($items, Database::query($sql, [$tenantId])->fetchAll(\PDO::FETCH_ASSOC));
        }

        // Goals
        if ($filter === 'all' || $filter === 'goals') {
            $isLiked = sprintf($likeSubquery, 'goal', 'g');
            $likesSub = sprintf($likesCountSub, 'goal', 'g');
            $commentsSub = sprintf($commentsCountSub, 'goal', 'g');
            $sql = "SELECT g.id, g.title, g.description as content, g.created_at,
                           'goal' as type,
                           COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name,
                           u.avatar_url as author_avatar,
                           g.user_id as author_id,
                           $likesSub as likes_count,
                           $commentsSub as comments_count,
                           $isLiked as is_liked
                    FROM goals g
                    JOIN users u ON g.user_id = u.id
                    WHERE g.tenant_id = ?
                    ORDER BY g.created_at DESC
                    LIMIT " . ($filter === 'goals' ? $limit : 10);
            $items = array_merge($items, Database::query($sql, [$tenantId])->fetchAll(\PDO::FETCH_ASSOC));
        }

        // Sort by created_at descending and apply pagination
        usort($items, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return array_slice($items, $offset, $limit);
    }

    // ============================================
    // CREATE POST
    // ============================================

    /**
     * Create a new feed post
     * POST /api/social/create-post
     *
     * @param string content
     * @param string emoji (optional)
     * @param file image (optional)
     */
    public function createPost()
    {
        $this->verifyCsrf();
        $this->rateLimit('social_create_post', 20, 60);
        $userId = $this->getUserId();
        $tenantId = $this->getTenantId();

        $content = trim($this->getInput('content', ''));
        $emoji = $this->getInput('emoji', null);
        $imageUrl = $this->getInput('image_url', null);
        $visibility = $this->getInput('visibility', 'public');

        // Validate visibility
        if (!in_array($visibility, self::VALID_VISIBILITY, true)) {
            $visibility = 'public';
        }
        $groupId = (int)$this->getInput('group_id', 0);

        if (empty($content) && empty($imageUrl)) {
            $this->jsonResponse(['error' => 'Post content or image is required'], 400);
        }

        // If posting to a group, verify membership
        if ($groupId > 0) {
            $membership = Database::query(
                "SELECT id FROM group_members WHERE group_id = ? AND user_id = ? AND status = 'active'",
                [$groupId, $userId]
            )->fetch();

            if (!$membership) {
                $this->jsonResponse(['error' => 'You must be a member of this group to post'], 403);
            }
        }

        try {
            // Handle image upload if present (file upload takes precedence over URL)
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $imageUrl = $this->handleImageUpload($_FILES['image']);
            }

            // Check if group_id column exists (backward compatibility)
            $hasGroupColumn = false;
            try {
                $columns = Database::query("SHOW COLUMNS FROM feed_posts LIKE 'group_id'")->fetch();
                $hasGroupColumn = !empty($columns);
            } catch (\Exception $e) {
                // Column doesn't exist
            }

            // Insert with or without group_id support
            if ($hasGroupColumn && $groupId > 0) {
                Database::query(
                    "INSERT INTO feed_posts (user_id, tenant_id, content, emoji, image_url, likes_count, visibility, group_id, created_at) VALUES (?, ?, ?, ?, ?, 0, ?, ?, NOW())",
                    [$userId, $tenantId, $content, $emoji, $imageUrl, $visibility, $groupId]
                );
            } else {
                Database::query(
                    "INSERT INTO feed_posts (user_id, tenant_id, content, emoji, image_url, likes_count, visibility, created_at) VALUES (?, ?, ?, ?, ?, 0, ?, NOW())",
                    [$userId, $tenantId, $content, $emoji, $imageUrl, $visibility]
                );
            }
            $postId = Database::lastInsertId();

            $this->jsonResponse([
                'success' => true,
                'status' => 'success',
                'post_id' => $postId
            ]);

        } catch (\Exception $e) {
            error_log("Create Post Error: " . $e->getMessage());
            $this->jsonResponse(['error' => 'Failed to create post: ' . $e->getMessage()], 500);
        }
    }

    private function handleImageUpload($file)
    {
        // SECURITY: Enforce server-side file size limit (5MB)
        $maxSize = 5 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            return null;
        }

        // SECURITY: Validate actual MIME type using finfo, not user-supplied type
        $allowedTypes = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/gif' => ['gif'],
            'image/webp' => ['webp']
        ];

        // Check actual file MIME type (not user-supplied header)
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $actualMime = $finfo->file($file['tmp_name']);

        if (!isset($allowedTypes[$actualMime])) {
            return null;
        }

        // Validate extension matches MIME type
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedTypes[$actualMime])) {
            // Use first valid extension for detected MIME type
            $ext = $allowedTypes[$actualMime][0];
        }

        // Also verify it's a valid image using getimagesize
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return null;
        }

        $uploadDir = dirname(__DIR__, 3) . '/httpdocs/uploads/posts/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // SECURITY: Use cryptographically secure random filename
        $filename = 'post_' . bin2hex(random_bytes(16)) . '.' . $ext;
        $filepath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return '/uploads/posts/' . $filename;
        }

        return null;
    }
}
