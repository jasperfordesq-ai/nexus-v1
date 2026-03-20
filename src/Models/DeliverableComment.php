<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use PDO;

/**
 * DeliverableComment Model
 *
 * Manages comments and discussions on deliverables.
 */
class DeliverableComment
{
    /**
     * Create a new comment
     *
     * @param int $deliverableId Deliverable ID
     * @param int $userId User ID posting comment
     * @param string $commentText Comment content
     * @param array $options Additional options
     * @return array|false Created comment or false on failure
     */
    public static function create($deliverableId, $userId, $commentText, $options = [])
    {
        $tenantId = TenantContext::getId();

        $defaults = [
            'comment_type' => 'general',
            'parent_comment_id' => null,
            'mentioned_user_ids' => null,
        ];

        $data = array_merge($defaults, $options);

        // Extract @mentions from comment text
        $mentionedUserIds = self::extractMentions($commentText);
        if (!empty($mentionedUserIds)) {
            $data['mentioned_user_ids'] = json_encode($mentionedUserIds);
        }

        $sql = "INSERT INTO deliverable_comments (
            tenant_id, deliverable_id, user_id, comment_text,
            comment_type, parent_comment_id, mentioned_user_ids
        ) VALUES (?, ?, ?, ?, ?, ?, ?)";

        $params = [
            $tenantId,
            $deliverableId,
            $userId,
            $commentText,
            $data['comment_type'],
            $data['parent_comment_id'],
            $data['mentioned_user_ids']
        ];

        $result = Database::query($sql, $params);

        if ($result) {
            $commentId = Database::getInstance()->lastInsertId();

            // Log comment in deliverable history
            Deliverable::logHistory(
                $deliverableId,
                $userId,
                'commented',
                null,
                substr($commentText, 0, 100),
                null,
                'New comment added'
            );

            return self::findById($commentId);
        }

        return false;
    }

    /**
     * Find comment by ID
     *
     * @param int $id Comment ID
     * @return array|false Comment data or false if not found
     */
    public static function findById($id)
    {
        $tenantId = TenantContext::getId();

        $sql = "SELECT c.*, u.first_name, u.last_name, u.profile_pic
                FROM deliverable_comments c
                LEFT JOIN users u ON c.user_id = u.id
                WHERE c.id = ? AND c.tenant_id = ? AND c.is_deleted = 0";

        $result = Database::query($sql, [$id, $tenantId])->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $result = self::decodeJsonFields($result);
        }

        return $result;
    }

    /**
     * Update comment
     *
     * @param int $id Comment ID
     * @param string $commentText New comment text
     * @param int $userId User making the update (for ownership check)
     * @return bool Success status
     */
    public static function update($id, $commentText, $userId)
    {
        $tenantId = TenantContext::getId();

        // Verify ownership
        $comment = self::findById($id);
        if (!$comment || $comment['user_id'] != $userId) {
            return false;
        }

        // Extract new mentions
        $mentionedUserIds = self::extractMentions($commentText);
        $mentionsJson = !empty($mentionedUserIds) ? json_encode($mentionedUserIds) : null;

        $sql = "UPDATE deliverable_comments
                SET comment_text = ?, is_edited = 1, edited_at = NOW(),
                    mentioned_user_ids = ?
                WHERE id = ? AND tenant_id = ?";

        return Database::query($sql, [$commentText, $mentionsJson, $id, $tenantId]) !== false;
    }

    /**
     * Soft delete comment
     *
     * @param int $id Comment ID
     * @param int $userId User performing deletion
     * @return bool Success status
     */
    public static function delete($id, $userId)
    {
        $tenantId = TenantContext::getId();

        // Verify ownership
        $comment = self::findById($id);
        if (!$comment || $comment['user_id'] != $userId) {
            return false;
        }

        $sql = "UPDATE deliverable_comments
                SET is_deleted = 1, deleted_at = NOW()
                WHERE id = ? AND tenant_id = ?";

        return Database::query($sql, [$id, $tenantId]) !== false;
    }

    /**
     * Get all comments for a deliverable
     *
     * @param int $deliverableId Deliverable ID
     * @param bool $includeThreads Include threaded replies
     * @return array List of comments
     */
    public static function getByDeliverable($deliverableId, $includeThreads = true)
    {
        $tenantId = TenantContext::getId();

        // Get top-level comments
        $sql = "SELECT c.*, u.first_name, u.last_name, u.profile_pic
                FROM deliverable_comments c
                LEFT JOIN users u ON c.user_id = u.id
                WHERE c.deliverable_id = ? AND c.tenant_id = ?
                  AND c.is_deleted = 0
                  AND c.parent_comment_id IS NULL
                ORDER BY c.is_pinned DESC, c.created_at DESC";

        $comments = Database::query($sql, [$deliverableId, $tenantId])
            ->fetchAll(PDO::FETCH_ASSOC);

        // Decode JSON fields
        $comments = array_map([self::class, 'decodeJsonFields'], $comments);

        // Load threaded replies if requested
        if ($includeThreads) {
            foreach ($comments as &$comment) {
                $comment['replies'] = self::getReplies($comment['id']);
            }
        }

        return $comments;
    }

    /**
     * Get replies to a comment
     *
     * @param int $parentCommentId Parent comment ID
     * @return array List of reply comments
     */
    public static function getReplies($parentCommentId)
    {
        $tenantId = TenantContext::getId();

        $sql = "SELECT c.*, u.first_name, u.last_name, u.profile_pic
                FROM deliverable_comments c
                LEFT JOIN users u ON c.user_id = u.id
                WHERE c.parent_comment_id = ? AND c.tenant_id = ?
                  AND c.is_deleted = 0
                ORDER BY c.created_at ASC";

        $replies = Database::query($sql, [$parentCommentId, $tenantId])
            ->fetchAll(PDO::FETCH_ASSOC);

        return array_map([self::class, 'decodeJsonFields'], $replies);
    }

    /**
     * Add reaction to comment
     *
     * @param int $commentId Comment ID
     * @param int $userId User adding reaction
     * @param string $emoji Reaction emoji
     * @return bool Success status
     */
    public static function addReaction($commentId, $userId, $emoji)
    {
        $tenantId = TenantContext::getId();

        $comment = self::findById($commentId);
        if (!$comment) {
            return false;
        }

        $reactions = $comment['reactions'] ?? [];
        $reactions[$userId] = $emoji;

        $reactionsJson = json_encode($reactions);

        $sql = "UPDATE deliverable_comments SET reactions = ?
                WHERE id = ? AND tenant_id = ?";

        return Database::query($sql, [$reactionsJson, $commentId, $tenantId]) !== false;
    }

    /**
     * Remove reaction from comment
     *
     * @param int $commentId Comment ID
     * @param int $userId User removing reaction
     * @return bool Success status
     */
    public static function removeReaction($commentId, $userId)
    {
        $tenantId = TenantContext::getId();

        $comment = self::findById($commentId);
        if (!$comment) {
            return false;
        }

        $reactions = $comment['reactions'] ?? [];
        unset($reactions[$userId]);

        $reactionsJson = json_encode($reactions);

        $sql = "UPDATE deliverable_comments SET reactions = ?
                WHERE id = ? AND tenant_id = ?";

        return Database::query($sql, [$reactionsJson, $commentId, $tenantId]) !== false;
    }

    /**
     * Pin/unpin comment
     *
     * @param int $commentId Comment ID
     * @param bool $pinned Pin status
     * @return bool Success status
     */
    public static function setPinned($commentId, $pinned)
    {
        $tenantId = TenantContext::getId();

        $sql = "UPDATE deliverable_comments SET is_pinned = ?
                WHERE id = ? AND tenant_id = ?";

        return Database::query($sql, [$pinned ? 1 : 0, $commentId, $tenantId]) !== false;
    }

    /**
     * Get comment count for deliverable
     *
     * @param int $deliverableId Deliverable ID
     * @return int Comment count
     */
    public static function getCount($deliverableId)
    {
        $tenantId = TenantContext::getId();

        $sql = "SELECT COUNT(*) as count FROM deliverable_comments
                WHERE deliverable_id = ? AND tenant_id = ? AND is_deleted = 0";

        $result = Database::query($sql, [$deliverableId, $tenantId])->fetch(PDO::FETCH_ASSOC);

        return (int) $result['count'];
    }

    /**
     * Extract @mentioned user IDs from comment text
     *
     * @param string $commentText Comment text
     * @return array Array of user IDs
     */
    private static function extractMentions($commentText)
    {
        // Match @userID patterns (e.g., @123, @456)
        preg_match_all('/@(\d+)/', $commentText, $matches);

        return !empty($matches[1]) ? array_map('intval', $matches[1]) : [];
    }

    /**
     * Decode JSON fields in comment data
     *
     * @param array $comment Comment data
     * @return array Comment with decoded JSON fields
     */
    private static function decodeJsonFields($comment)
    {
        $jsonFields = ['reactions', 'mentioned_user_ids'];

        foreach ($jsonFields as $field) {
            if (isset($comment[$field]) && is_string($comment[$field])) {
                $comment[$field] = json_decode($comment[$field], true);
            }
        }

        return $comment;
    }
}
