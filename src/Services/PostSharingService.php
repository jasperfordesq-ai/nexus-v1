<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\FeedPost;
use Nexus\Models\Notification;

/**
 * PostSharingService - Post sharing/reposting for social feed
 *
 * Provides:
 * - Share a post (creates a repost linking to original)
 * - Track share count
 * - Show "Shared by X" attribution
 * - Get share details for a post
 */
class PostSharingService
{
    private static array $errors = [];

    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * Share/repost a post
     *
     * @param int $userId User doing the sharing
     * @param int $originalPostId ID of the post being shared
     * @param string $originalType Type of content (post, listing, event)
     * @param string|null $comment Optional comment when sharing
     * @return array|null The new share data
     */
    public static function sharePost(int $userId, int $originalPostId, string $originalType = 'post', ?string $comment = null): ?array
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        // Validate original post exists
        $original = Database::query(
            "SELECT id, user_id, content, tenant_id FROM feed_posts WHERE id = ? AND tenant_id = ?",
            [$originalPostId, $tenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$original) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Post not found'];
            return null;
        }

        // Cannot share your own post
        if ((int)$original['user_id'] === $userId) {
            self::$errors[] = ['code' => 'SELF_SHARE', 'message' => 'You cannot share your own post'];
            return null;
        }

        // Check if already shared
        $existing = Database::query(
            "SELECT id FROM post_shares WHERE user_id = ? AND original_post_id = ? AND tenant_id = ?",
            [$userId, $originalPostId, $tenantId]
        )->fetch();

        if ($existing) {
            self::$errors[] = ['code' => 'ALREADY_SHARED', 'message' => 'You have already shared this post'];
            return null;
        }

        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            // Create a new feed post that is a repost
            $shareContent = $comment ?: '';
            $newPostId = FeedPost::create($userId, $shareContent, null, null, $originalPostId, $originalType);

            // Mark the new post as a repost
            Database::query(
                "UPDATE feed_posts SET is_repost = 1, original_post_id = ? WHERE id = ? AND tenant_id = ?",
                [$originalPostId, $newPostId, $tenantId]
            );

            // Record in post_shares
            Database::query(
                "INSERT INTO post_shares (user_id, tenant_id, original_post_id, original_type, shared_post_id, comment)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$userId, $tenantId, $originalPostId, $originalType, $newPostId, $comment]
            );

            $shareId = (int)Database::lastInsertId();

            // Increment share count on original post
            Database::query(
                "UPDATE feed_posts SET share_count = share_count + 1 WHERE id = ? AND tenant_id = ?",
                [$originalPostId, $tenantId]
            );

            $db->commit();

            // Notify original post author
            try {
                $sharerName = Database::query(
                    "SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = ? AND tenant_id = ?",
                    [$userId, TenantContext::getId()]
                )->fetchColumn();

                Notification::create(
                    (int)$original['user_id'],
                    "{$sharerName} shared your post",
                    '/feed',
                    'share'
                );
            } catch (\Exception $e) {
                // Non-critical
            }

            return [
                'share_id' => $shareId,
                'shared_post_id' => $newPostId,
                'original_post_id' => $originalPostId,
            ];
        } catch (\Exception $e) {
            $db->rollBack();
            error_log("PostSharingService::sharePost error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to share post'];
            return null;
        }
    }

    /**
     * Unshare (remove a repost)
     */
    public static function unsharePost(int $userId, int $originalPostId): bool
    {
        self::$errors = [];
        $tenantId = TenantContext::getId();

        $share = Database::query(
            "SELECT id, shared_post_id FROM post_shares WHERE user_id = ? AND original_post_id = ? AND tenant_id = ?",
            [$userId, $originalPostId, $tenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$share) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Share not found'];
            return false;
        }

        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            // Delete the shared post
            if ($share['shared_post_id']) {
                Database::query(
                    "DELETE FROM feed_posts WHERE id = ? AND user_id = ? AND tenant_id = ?",
                    [$share['shared_post_id'], $userId, $tenantId]
                );
            }

            // Delete share record
            Database::query(
                "DELETE FROM post_shares WHERE id = ? AND tenant_id = ?",
                [$share['id'], $tenantId]
            );

            // Decrement share count
            Database::query(
                "UPDATE feed_posts SET share_count = GREATEST(share_count - 1, 0) WHERE id = ? AND tenant_id = ?",
                [$originalPostId, $tenantId]
            );

            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            return false;
        }
    }

    /**
     * Get share count for a post
     */
    public static function getShareCount(int $postId): int
    {
        $tenantId = TenantContext::getId();

        return (int)Database::query(
            "SELECT share_count FROM feed_posts WHERE id = ? AND tenant_id = ?",
            [$postId, $tenantId]
        )->fetchColumn();
    }

    /**
     * Get users who shared a post
     */
    public static function getSharers(int $postId, int $limit = 20): array
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT ps.id, ps.comment, ps.created_at,
                    u.id as user_id,
                    CONCAT(u.first_name, ' ', u.last_name) as name,
                    u.avatar_url
             FROM post_shares ps
             JOIN users u ON ps.user_id = u.id
             WHERE ps.original_post_id = ? AND ps.tenant_id = ?
             ORDER BY ps.created_at DESC
             LIMIT ?",
            [$postId, $tenantId, $limit]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Check if a user has shared a specific post
     */
    public static function hasShared(int $userId, int $postId): bool
    {
        $tenantId = TenantContext::getId();

        $row = Database::query(
            "SELECT 1 FROM post_shares WHERE user_id = ? AND original_post_id = ? AND tenant_id = ? LIMIT 1",
            [$userId, $postId, $tenantId]
        )->fetch();

        return (bool)$row;
    }

    /**
     * Get original post data for a repost (for "Shared by X" display)
     */
    public static function getOriginalPost(int $repostId): ?array
    {
        $tenantId = TenantContext::getId();

        $repost = Database::query(
            "SELECT original_post_id FROM feed_posts WHERE id = ? AND tenant_id = ? AND is_repost = 1",
            [$repostId, $tenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$repost || !$repost['original_post_id']) {
            return null;
        }

        return Database::query(
            "SELECT p.id, p.content, p.image_url, p.created_at, p.likes_count, p.share_count,
                    CONCAT(u.first_name, ' ', u.last_name) as author_name,
                    u.avatar_url as author_avatar, u.id as author_id
             FROM feed_posts p
             JOIN users u ON p.user_id = u.id
             WHERE p.id = ? AND p.tenant_id = ?",
            [$repost['original_post_id'], $tenantId]
        )->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
}
