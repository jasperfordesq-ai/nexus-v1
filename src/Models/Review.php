<?php

namespace Nexus\Models;

use Nexus\Core\Database;

class Review
{
    /**
     * Ensure the reviews table has the group_id column
     */
    public static function ensureGroupColumn()
    {
        try {
            Database::query("SELECT group_id FROM reviews LIMIT 1");
        } catch (\Exception $e) {
            Database::query("ALTER TABLE reviews ADD COLUMN group_id INT NULL AFTER transaction_id");
            Database::query("ALTER TABLE reviews ADD INDEX idx_reviews_group (group_id)");
        }
    }

    /**
     * Create a review (with optional group context)
     */
    public static function create($reviewerId, $receiverId, $transactionId = null, $rating, $comment, $groupId = null)
    {
        self::ensureGroupColumn();

        $sql = "INSERT INTO reviews (reviewer_id, receiver_id, transaction_id, group_id, rating, comment)
                VALUES (?, ?, ?, ?, ?, ?)";
        Database::query($sql, [$reviewerId, $receiverId, $transactionId ?: null, $groupId ?: null, $rating, $comment]);

        ActivityLog::log($reviewerId, "left_review", "Rated user $rating/5");

        return Database::getInstance()->lastInsertId();
    }

    /**
     * Get all reviews for a user (global reputation)
     */
    public static function getForUser($userId)
    {
        self::ensureGroupColumn();

        $sql = "SELECT r.*, u.name as reviewer_name, u.avatar_url as reviewer_avatar,
                       g.name as group_name
                FROM reviews r
                JOIN users u ON r.reviewer_id = u.id
                LEFT JOIN `groups` g ON r.group_id = g.id
                WHERE r.receiver_id = ?
                ORDER BY r.created_at DESC";
        return Database::query($sql, [$userId])->fetchAll();
    }

    /**
     * Get reviews for a user within a specific group
     */
    public static function getForUserInGroup($userId, $groupId)
    {
        self::ensureGroupColumn();

        $sql = "SELECT r.*, u.name as reviewer_name, u.avatar_url as reviewer_avatar
                FROM reviews r
                JOIN users u ON r.reviewer_id = u.id
                WHERE r.receiver_id = ? AND r.group_id = ?
                ORDER BY r.created_at DESC";
        return Database::query($sql, [$userId, $groupId])->fetchAll();
    }

    /**
     * Get all reviews within a group (for the Reviews tab)
     */
    public static function getForGroup($groupId)
    {
        self::ensureGroupColumn();

        $sql = "SELECT r.*,
                       reviewer.name as reviewer_name, reviewer.avatar_url as reviewer_avatar,
                       receiver.name as receiver_name, receiver.avatar_url as receiver_avatar
                FROM reviews r
                JOIN users reviewer ON r.reviewer_id = reviewer.id
                JOIN users receiver ON r.receiver_id = receiver.id
                WHERE r.group_id = ?
                ORDER BY r.created_at DESC";
        return Database::query($sql, [$groupId])->fetchAll();
    }

    /**
     * Get average rating for a user (global)
     */
    public static function getAverageForUser($userId)
    {
        self::ensureGroupColumn();

        $sql = "SELECT AVG(rating) as avg_rating, COUNT(*) as total_count
                FROM reviews WHERE receiver_id = ?";
        return Database::query($sql, [$userId])->fetch();
    }

    /**
     * Get average rating for a user within a group
     */
    public static function getAverageForUserInGroup($userId, $groupId)
    {
        self::ensureGroupColumn();

        $sql = "SELECT AVG(rating) as avg_rating, COUNT(*) as total_count
                FROM reviews WHERE receiver_id = ? AND group_id = ?";
        return Database::query($sql, [$userId, $groupId])->fetch();
    }

    /**
     * Check if user already reviewed another user in a group
     */
    public static function hasReviewedInGroup($reviewerId, $receiverId, $groupId)
    {
        self::ensureGroupColumn();

        $sql = "SELECT id FROM reviews
                WHERE reviewer_id = ? AND receiver_id = ? AND group_id = ?";
        return (bool) Database::query($sql, [$reviewerId, $receiverId, $groupId])->fetch();
    }

    /**
     * Update an existing review
     */
    public static function update($reviewId, $rating, $comment)
    {
        $sql = "UPDATE reviews SET rating = ?, comment = ? WHERE id = ?";
        return Database::query($sql, [$rating, $comment, $reviewId]);
    }

    /**
     * Delete a review
     */
    public static function delete($reviewId, $tenantId = null)
    {
        if ($tenantId === null) {
            $tenantId = \Nexus\Core\TenantContext::getId();
        }
        $sql = "DELETE FROM reviews WHERE id = ? AND tenant_id = ?";
        return Database::query($sql, [$reviewId, $tenantId]);
    }

    /**
     * Get a single review by ID
     */
    public static function findById($reviewId)
    {
        $sql = "SELECT * FROM reviews WHERE id = ?";
        return Database::query($sql, [$reviewId])->fetch();
    }
}
