<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * CommentService - Handles comments, replies, reactions, and mentions
 */
class CommentService
{
    private static $availableReactions = ['👍', '❤️', '😂', '😮', '😢', '🎉'];

    /**
     * Get available reaction emojis
     */
    public static function getAvailableReactions(): array
    {
        return self::$availableReactions;
    }

    /**
     * Fetch comments with nested replies for a target
     */
    public static function fetchComments(string $targetType, int $targetId, int $currentUserId = 0): array
    {
        $pdo = Database::getInstance();

        // Fetch all comments (including replies) for this target
        // Note: We don't filter by tenant_id here because comments are tied to a specific
        // target (post/listing) which already belongs to the correct tenant
        $sql = "SELECT c.id, c.user_id, c.content, c.parent_id, c.created_at, c.updated_at,
                       COALESCE(u.name, u.first_name, 'Unknown') as author_name,
                       COALESCE(u.avatar_url, '/assets/img/defaults/default_avatar.png') as author_avatar
                FROM comments c
                LEFT JOIN users u ON c.user_id = u.id
                WHERE c.target_type = ? AND c.target_id = ?
                ORDER BY c.created_at ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$targetType, $targetId]);
        $allComments = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Fetch reactions for all comments
        $commentIds = array_column($allComments, 'id');
        $reactionsByComment = [];
        $userReactionsByComment = [];

        if (!empty($commentIds)) {
            $placeholders = implode(',', array_fill(0, count($commentIds), '?'));

            // Get reaction counts grouped by emoji
            $sql = "SELECT target_id, emoji, COUNT(*) as count
                    FROM reactions
                    WHERE target_type = 'comment' AND target_id IN ($placeholders)
                    GROUP BY target_id, emoji";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($commentIds);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $reactionsByComment[$row['target_id']][$row['emoji']] = (int)$row['count'];
            }

            // Get current user's reactions
            if ($currentUserId) {
                $sql = "SELECT target_id, emoji FROM reactions
                        WHERE target_type = 'comment' AND target_id IN ($placeholders) AND user_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_merge($commentIds, [$currentUserId]));
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $userReactionsByComment[$row['target_id']][] = $row['emoji'];
                }
            }
        }

        // Build threaded structure
        $commentsById = [];
        $rootComments = [];

        foreach ($allComments as &$comment) {
            $comment['reactions'] = $reactionsByComment[$comment['id']] ?? [];
            $comment['user_reactions'] = $userReactionsByComment[$comment['id']] ?? [];
            $comment['is_owner'] = ($currentUserId && $comment['user_id'] == $currentUserId);
            $comment['is_edited'] = ($comment['updated_at'] !== $comment['created_at']);
            $comment['replies'] = [];
            $commentsById[$comment['id']] = &$comment;
        }
        unset($comment);

        // Organize into tree structure
        foreach ($allComments as &$comment) {
            if ($comment['parent_id'] && isset($commentsById[$comment['parent_id']])) {
                $commentsById[$comment['parent_id']]['replies'][] = &$commentsById[$comment['id']];
            } else {
                $rootComments[] = &$commentsById[$comment['id']];
            }
        }

        return $rootComments;
    }

    /**
     * Add a comment or reply
     */
    public static function addComment(
        int $userId,
        int $tenantId,
        string $targetType,
        int $targetId,
        string $content,
        ?int $parentId = null
    ): array {
        $pdo = Database::getInstance();
        $content = trim($content);

        if (empty($content)) {
            return ['success' => false, 'error' => 'Comment cannot be empty'];
        }

        // If replying, verify parent exists
        if ($parentId) {
            $stmt = $pdo->prepare("SELECT id, user_id FROM comments WHERE id = ?");
            $stmt->execute([$parentId]);
            $parent = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$parent) {
                return ['success' => false, 'error' => 'Parent comment not found'];
            }
        }

        // Insert comment
        $sql = "INSERT INTO comments (user_id, tenant_id, target_type, target_id, parent_id, content, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $tenantId, $targetType, $targetId, $parentId, $content]);
        $commentId = $pdo->lastInsertId();

        // Process @mentions
        $mentions = self::extractMentions($content);
        if (!empty($mentions)) {
            self::saveMentions($commentId, $mentions, $userId, $tenantId);
        }

        // Get the created comment with author info
        $stmt = $pdo->prepare("SELECT c.*, COALESCE(u.name, u.first_name, 'Unknown') as author_name,
                               COALESCE(u.avatar_url, '/assets/img/defaults/default_avatar.png') as author_avatar
                               FROM comments c LEFT JOIN users u ON c.user_id = u.id WHERE c.id = ?");
        $stmt->execute([$commentId]);
        $comment = $stmt->fetch(\PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'status' => 'success',
            'comment' => $comment,
            'is_reply' => $parentId !== null
        ];
    }

    /**
     * Edit a comment (owner only)
     */
    public static function editComment(int $commentId, int $userId, string $newContent): array
    {
        $pdo = Database::getInstance();
        $newContent = trim($newContent);

        if (empty($newContent)) {
            return ['success' => false, 'error' => 'Comment cannot be empty'];
        }

        // Verify ownership
        $stmt = $pdo->prepare("SELECT id, user_id, target_type, target_id FROM comments WHERE id = ?");
        $stmt->execute([$commentId]);
        $comment = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$comment) {
            return ['success' => false, 'error' => 'Comment not found'];
        }

        if ($comment['user_id'] != $userId) {
            return ['success' => false, 'error' => 'Unauthorized'];
        }

        // Update comment
        $stmt = $pdo->prepare("UPDATE comments SET content = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newContent, $commentId]);

        // Re-process mentions
        $mentions = self::extractMentions($newContent);
        // Clear old mentions and add new ones
        $stmt = $pdo->prepare("DELETE FROM mentions WHERE comment_id = ?");
        $stmt->execute([$commentId]);

        if (!empty($mentions)) {
            $tenantId = TenantContext::getId();
            self::saveMentions($commentId, $mentions, $userId, $tenantId);
        }

        return [
            'success' => true,
            'status' => 'success',
            'content' => $newContent,
            'is_edited' => true
        ];
    }

    /**
     * Delete a comment (owner only)
     */
    public static function deleteComment(int $commentId, int $userId, bool $isSuperAdmin = false): array
    {
        $pdo = Database::getInstance();
        $tenantId = TenantContext::getId();

        // Verify ownership (or super admin) — scoped by tenant
        $stmt = $pdo->prepare("SELECT id, user_id FROM comments WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$commentId, $tenantId]);
        $comment = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$comment) {
            return ['success' => false, 'error' => 'Comment not found'];
        }

        if ($comment['user_id'] != $userId && !$isSuperAdmin) {
            return ['success' => false, 'error' => 'Unauthorized'];
        }

        // Delete comment (CASCADE will handle replies and mentions) — scoped by tenant
        $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$commentId, $tenantId]);

        return ['success' => true, 'status' => 'success', 'message' => 'Comment deleted'];
    }

    /**
     * Toggle a reaction on a comment
     */
    public static function toggleReaction(int $userId, int $tenantId, int $commentId, string $emoji): array
    {
        $pdo = Database::getInstance();

        // Validate emoji
        if (!in_array($emoji, self::$availableReactions)) {
            return ['success' => false, 'error' => 'Invalid reaction'];
        }

        // Check if reaction exists — scoped by tenant
        $stmt = $pdo->prepare("SELECT id FROM reactions WHERE user_id = ? AND target_type = 'comment' AND target_id = ? AND emoji = ? AND tenant_id = ?");
        $stmt->execute([$userId, $commentId, $emoji, $tenantId]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Remove reaction — scoped by tenant
            $stmt = $pdo->prepare("DELETE FROM reactions WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$existing['id'], $tenantId]);
            $action = 'removed';
        } else {
            // Add reaction
            $stmt = $pdo->prepare("INSERT INTO reactions (user_id, tenant_id, target_type, target_id, emoji, created_at) VALUES (?, ?, 'comment', ?, ?, NOW())");
            $stmt->execute([$userId, $tenantId, $commentId, $emoji]);
            $action = 'added';

            // Notify comment owner
            $stmt = $pdo->prepare("SELECT user_id FROM comments WHERE id = ?");
            $stmt->execute([$commentId]);
            $comment = $stmt->fetch();
            if ($comment && $comment['user_id'] != $userId) {
                if (class_exists('\Nexus\Services\SocialNotificationService')) {
                    SocialNotificationService::notifyLike(
                        $comment['user_id'], $userId, 'comment', $commentId, "reacted $emoji to your comment"
                    );
                }
            }
        }

        // Get updated reaction counts
        $stmt = $pdo->prepare("SELECT emoji, COUNT(*) as count FROM reactions WHERE target_type = 'comment' AND target_id = ? GROUP BY emoji");
        $stmt->execute([$commentId]);
        $reactions = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $reactions[$row['emoji']] = (int)$row['count'];
        }

        return [
            'success' => true,
            'status' => 'success',
            'action' => $action,
            'reactions' => $reactions
        ];
    }

    /**
     * Extract @mentions from content
     */
    public static function extractMentions(string $content): array
    {
        preg_match_all('/@(\w+)/', $content, $matches);
        return array_unique($matches[1] ?? []);
    }

    /**
     * Save mentions to database and notify users
     */
    private static function saveMentions(int $commentId, array $usernames, int $mentioningUserId, int $tenantId): void
    {
        $pdo = Database::getInstance();

        foreach ($usernames as $username) {
            // Find user by username/name
            $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR name LIKE ? OR first_name = ?) AND tenant_id = ?");
            $stmt->execute([$username, "%$username%", $username, $tenantId]);
            $user = $stmt->fetch();

            if ($user && $user['id'] != $mentioningUserId) {
                // Insert mention
                try {
                    $stmt = $pdo->prepare("INSERT INTO mentions (comment_id, mentioned_user_id, mentioning_user_id, tenant_id, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$commentId, $user['id'], $mentioningUserId, $tenantId]);

                    // Send notification
                    if (class_exists('\Nexus\Services\SocialNotificationService')) {
                        SocialNotificationService::notifyComment(
                            $user['id'], $mentioningUserId, 'mention', $commentId, 'mentioned you in a comment'
                        );
                    }
                } catch (\PDOException $e) {
                    // Ignore duplicate mention errors
                }
            }
        }
    }

    /**
     * Get users for @mention autocomplete
     */
    public static function searchUsersForMention(string $query, int $tenantId, int $limit = 10): array
    {
        $pdo = Database::getInstance();

        $stmt = $pdo->prepare("SELECT id, name, first_name, avatar_url
                               FROM users
                               WHERE tenant_id = ? AND (name LIKE ? OR first_name LIKE ? OR username LIKE ?)
                               LIMIT ?");
        $searchTerm = "%$query%";
        $stmt->execute([$tenantId, $searchTerm, $searchTerm, $searchTerm, $limit]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get unread mentions count for a user
     */
    public static function getUnreadMentionsCount(int $userId): int
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM mentions WHERE mentioned_user_id = ? AND seen_at IS NULL");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Mark mentions as seen
     */
    public static function markMentionsAsSeen(int $userId): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("UPDATE mentions SET seen_at = NOW() WHERE mentioned_user_id = ? AND seen_at IS NULL");
        $stmt->execute([$userId]);
    }
}
