<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiAuth;
use Nexus\Services\CommentService;
use Nexus\Services\SocialNotificationService;
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
 * Endpoints:
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
 * @package Nexus\Modules\Social
 */
class SocialApiController
{
    use ApiAuth;

    // ============================================
    // DEBUG TEST ENDPOINT
    // ============================================

    /**
     * Test endpoint to verify API is working
     * GET /api/social/test
     * SECURITY: Restricted to admin users only
     */
    public function test()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // SECURITY: Require admin authentication for debug endpoints
        if (empty($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'super_admin'])) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'Access denied. Admin authentication required.']);
            exit;
        }

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

        header('Content-Type: application/json');
        echo json_encode($debug, JSON_PRETTY_PRINT);
        exit;
    }

    // ============================================
    // UTILITY METHODS
    // ============================================

    private $inputData = null;

    /**
     * Get input data from either JSON body or POST
     */
    private function getInput($key, $default = null)
    {
        // Parse JSON input once
        if ($this->inputData === null) {
            // Check multiple possible header keys for Content-Type
            $contentType = $_SERVER['CONTENT_TYPE']
                ?? $_SERVER['HTTP_CONTENT_TYPE']
                ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');

            // Also try to detect JSON by checking the raw body
            $rawBody = file_get_contents('php://input');

            if (strpos($contentType, 'application/json') !== false ||
                (strlen($rawBody) > 0 && $rawBody[0] === '{')) {
                $this->inputData = json_decode($rawBody, true) ?? [];
            } else {
                $this->inputData = $_POST;
            }
        }
        return $this->inputData[$key] ?? $default;
    }

    private function jsonResponse($data, $status = 200)
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit;
    }

    private function getUserId($required = true)
    {
        // Use unified auth supporting both session and Bearer token
        $userId = $this->getAuthenticatedUserId();
        if (!$userId) {
            if ($required) {
                $this->jsonResponse(['error' => 'Login required'], 401);
            }
            return 0;
        }
        return $userId;
    }

    private function getTenantId()
    {
        return TenantContext::get()['id'] ?? ($_SESSION['current_tenant_id'] ?? 1);
    }

    private function isSuperAdmin()
    {
        // Check both old and new session key names for compatibility
        return ($_SESSION['role'] ?? $_SESSION['user_role'] ?? '') === 'super_admin'
            || ($_SESSION['user_role'] ?? '') === 'admin'
            || ($_SESSION['is_admin'] ?? false)
            || ($_SESSION['is_super_admin'] ?? false);
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
    public function like()
    {
        $userId = $this->getUserId();
        $tenantId = $this->getTenantId();

        $targetType = $this->getInput('target_type', '');
        $targetId = (int)$this->getInput('target_id', 0);

        if (empty($targetType) || $targetId <= 0) {
            $this->jsonResponse(['error' => 'Invalid target'], 400);
        }

        try {
            // Check if already liked
            $existing = Database::query(
                "SELECT id FROM likes WHERE user_id = ? AND target_type = ? AND target_id = ?",
                [$userId, $targetType, $targetId]
            )->fetch();

            if ($existing) {
                // Unlike
                Database::query("DELETE FROM likes WHERE id = ?", [$existing['id']]);

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

            // Get updated count
            $countResult = Database::query(
                "SELECT COUNT(*) as cnt FROM likes WHERE target_type = ? AND target_id = ?",
                [$targetType, $targetId]
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
            $sql = "SELECT
                        u.id,
                        COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as name,
                        u.avatar_url,
                        l.created_at as liked_at
                    FROM likes l
                    JOIN users u ON l.user_id = u.id
                    WHERE l.target_type = ? AND l.target_id = ?
                    ORDER BY l.created_at DESC
                    LIMIT $limit OFFSET $offset";

            $likers = Database::query($sql, [$targetType, $targetId])->fetchAll(\PDO::FETCH_ASSOC);

            // Get total count
            $countResult = Database::query(
                "SELECT COUNT(*) as cnt FROM likes WHERE target_type = ? AND target_id = ?",
                [$targetType, $targetId]
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
                $this->submitComment($targetType, $targetId);
                break;
            default:
                $this->jsonResponse(['error' => 'Invalid action'], 400);
        }
    }

    private function fetchComments($targetType, $targetId)
    {
        try {
            $userId = $_SESSION['user_id'] ?? 0;

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

                $this->jsonResponse([
                    'success' => true,
                    'status' => 'success',
                    'comment' => [
                        'author_name' => $_SESSION['user_name'] ?? 'Me',
                        'author_avatar' => $_SESSION['user_avatar'] ?? '/assets/img/defaults/default_avatar.png',
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
                $comment = Database::query("SELECT user_id FROM comments WHERE id = ?", [$commentId])->fetch();
                if (!$comment) {
                    $this->jsonResponse(['error' => 'Comment not found'], 404);
                }
                if ($comment['user_id'] != $userId && !$this->isSuperAdmin()) {
                    $this->jsonResponse(['error' => 'Unauthorized'], 403);
                }
                Database::query("DELETE FROM comments WHERE id = ?", [$commentId]);
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
                    Database::query("DELETE FROM reactions WHERE id = ?", [$existing['id']]);
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
        $userId = $this->getUserId();
        $targetType = $this->getInput('target_type', '');
        $targetId = (int)$this->getInput('target_id', 0);

        if (empty($targetType) || $targetId <= 0) {
            $this->jsonResponse(['error' => 'Invalid target'], 400);
        }

        try {
            // Handle feed_posts deletion
            if ($targetType === 'post') {
                // Check ownership
                $post = Database::query("SELECT user_id FROM feed_posts WHERE id = ?", [$targetId])->fetch();

                if (!$post) {
                    $this->jsonResponse(['error' => 'Post not found'], 404);
                }

                if ($post['user_id'] != $userId && !$this->isSuperAdmin()) {
                    $this->jsonResponse(['error' => 'Unauthorized'], 403);
                }

                // Delete associated likes and comments
                Database::query("DELETE FROM likes WHERE target_type = 'post' AND target_id = ?", [$targetId]);
                Database::query("DELETE FROM comments WHERE target_type = 'post' AND target_id = ?", [$targetId]);

                // Delete the post
                Database::query("DELETE FROM feed_posts WHERE id = ?", [$targetId]);

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
                Database::query("DELETE FROM likes WHERE target_type = 'listing' AND target_id = ?", [$targetId]);
                Database::query("DELETE FROM comments WHERE target_type = 'listing' AND target_id = ?", [$targetId]);

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
                Database::query("DELETE FROM likes WHERE target_type = 'event' AND target_id = ?", [$targetId]);
                Database::query("DELETE FROM comments WHERE target_type = 'event' AND target_id = ?", [$targetId]);

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
                Database::query("DELETE FROM likes WHERE target_type = 'poll' AND target_id = ?", [$targetId]);
                Database::query("DELETE FROM comments WHERE target_type = 'poll' AND target_id = ?", [$targetId]);

                // Delete poll votes and options first
                Database::query("DELETE FROM poll_votes WHERE poll_id = ?", [$targetId]);
                Database::query("DELETE FROM poll_options WHERE poll_id = ?", [$targetId]);

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
                Database::query("DELETE FROM likes WHERE target_type = 'goal' AND target_id = ?", [$targetId]);
                Database::query("DELETE FROM comments WHERE target_type = 'goal' AND target_id = ?", [$targetId]);

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
                Database::query("DELETE FROM likes WHERE target_type = 'volunteering' AND target_id = ?", [$targetId]);
                Database::query("DELETE FROM comments WHERE target_type = 'volunteering' AND target_id = ?", [$targetId]);

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
        $currentUserId = $this->getUserId(false); // Not required
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
                       (SELECT COUNT(*) FROM comments WHERE target_type = 'post' AND target_id = p.id) as comments_count,
                       " . ($currentUserId ? "(SELECT COUNT(*) FROM likes WHERE user_id = $currentUserId AND target_type = 'post' AND target_id = p.id)" : "0") . " as is_liked
                FROM feed_posts p
                JOIN users u ON p.user_id = u.id
                WHERE p.group_id = ? AND p.tenant_id = ?
                ORDER BY p.created_at DESC
                LIMIT $limit OFFSET $offset";

        return Database::query($sql, [$groupId, $tenantId])->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function loadUserPosts($userId, $currentUserId, $tenantId, $limit, $offset)
    {
        $sql = "SELECT p.id, p.content, p.image_url, p.created_at, p.likes_count,
                       'post' as type,
                       COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name,
                       u.avatar_url as author_avatar,
                       p.user_id as author_id,
                       (SELECT COUNT(*) FROM comments WHERE target_type = 'post' AND target_id = p.id) as comments_count,
                       " . ($currentUserId ? "(SELECT COUNT(*) FROM likes WHERE user_id = $currentUserId AND target_type = 'post' AND target_id = p.id)" : "0") . " as is_liked
                FROM feed_posts p
                JOIN users u ON p.user_id = u.id
                WHERE p.user_id = ? AND p.tenant_id = ? AND p.visibility = 'public'
                ORDER BY p.created_at DESC
                LIMIT $limit OFFSET $offset";

        return Database::query($sql, [$userId, $tenantId])->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function loadAggregatedFeed($currentUserId, $tenantId, $filter, $limit, $offset)
    {
        $items = [];
        $likeSubquery = $currentUserId
            ? "(SELECT COUNT(*) FROM likes WHERE user_id = $currentUserId AND target_type = '%s' AND target_id = %s.id)"
            : "0";

        // Posts
        if ($filter === 'all' || $filter === 'posts') {
            $isLiked = sprintf($likeSubquery, 'post', 'p');
            $sql = "SELECT p.id, p.content, p.image_url, p.created_at, p.likes_count,
                           'post' as type,
                           COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name,
                           u.avatar_url as author_avatar,
                           p.user_id as author_id,
                           (SELECT COUNT(*) FROM comments WHERE target_type = 'post' AND target_id = p.id) as comments_count,
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
            $sql = "SELECT l.id, l.title, l.description as content, l.image_url, l.created_at,
                           'listing' as type,
                           COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name,
                           u.avatar_url as author_avatar,
                           l.user_id as author_id,
                           (SELECT COUNT(*) FROM likes WHERE target_type = 'listing' AND target_id = l.id) as likes_count,
                           (SELECT COUNT(*) FROM comments WHERE target_type = 'listing' AND target_id = l.id) as comments_count,
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
            $sql = "SELECT e.id, e.title, e.description as content, e.cover_image as image_url, e.created_at, e.start_time as start_date,
                           'event' as type,
                           COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name,
                           u.avatar_url as author_avatar,
                           e.user_id as author_id,
                           (SELECT COUNT(*) FROM likes WHERE target_type = 'event' AND target_id = e.id) as likes_count,
                           (SELECT COUNT(*) FROM comments WHERE target_type = 'event' AND target_id = e.id) as comments_count,
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
            $sql = "SELECT po.id, po.question as title, po.question as content, po.created_at,
                           'poll' as type,
                           COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name,
                           u.avatar_url as author_avatar,
                           po.user_id as author_id,
                           (SELECT COUNT(*) FROM likes WHERE target_type = 'poll' AND target_id = po.id) as likes_count,
                           (SELECT COUNT(*) FROM comments WHERE target_type = 'poll' AND target_id = po.id) as comments_count,
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
            $sql = "SELECT g.id, g.title, g.description as content, g.created_at,
                           'goal' as type,
                           COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name,
                           u.avatar_url as author_avatar,
                           g.user_id as author_id,
                           (SELECT COUNT(*) FROM likes WHERE target_type = 'goal' AND target_id = g.id) as likes_count,
                           (SELECT COUNT(*) FROM comments WHERE target_type = 'goal' AND target_id = g.id) as comments_count,
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
        $userId = $this->getUserId();
        $tenantId = $this->getTenantId();

        $content = trim($this->getInput('content', ''));
        $emoji = $this->getInput('emoji', null);
        $imageUrl = $this->getInput('image_url', null);
        $visibility = $this->getInput('visibility', 'public');
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
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (!in_array($file['type'], $allowedTypes)) {
            return null;
        }

        $uploadDir = dirname(__DIR__, 3) . '/httpdocs/uploads/posts/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('post_') . '.' . $ext;
        $filepath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return '/uploads/posts/' . $filename;
        }

        return null;
    }
}
