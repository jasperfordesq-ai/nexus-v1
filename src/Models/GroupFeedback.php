<?php

namespace Nexus\Models;

use Nexus\Core\Database;

class GroupFeedback
{
    /**
     * Create the feedback table if it doesn't exist
     */
    public static function ensureTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS group_feedback (
            id INT AUTO_INCREMENT PRIMARY KEY,
            group_id INT NOT NULL,
            user_id INT NOT NULL,
            rating TINYINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
            comment TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_group (group_id, user_id)
        )";
        Database::query($sql);
    }

    /**
     * Submit or update feedback for a group
     */
    public static function submit($groupId, $userId, $rating, $comment = null)
    {
        self::ensureTable();

        $sql = "INSERT INTO group_feedback (group_id, user_id, rating, comment)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), created_at = NOW()";
        return Database::query($sql, [$groupId, $userId, $rating, $comment]);
    }

    /**
     * Get all feedback for a group (for organisers)
     */
    public static function getForGroup($groupId)
    {
        self::ensureTable();

        $sql = "SELECT gf.*, u.name as user_name, u.avatar_url
                FROM group_feedback gf
                JOIN users u ON gf.user_id = u.id
                WHERE gf.group_id = ?
                ORDER BY gf.created_at DESC";
        return Database::query($sql, [$groupId])->fetchAll();
    }

    /**
     * Get average rating for a group
     */
    public static function getAverageRating($groupId)
    {
        self::ensureTable();

        $sql = "SELECT AVG(rating) as avg_rating, COUNT(*) as total_count
                FROM group_feedback
                WHERE group_id = ?";
        return Database::query($sql, [$groupId])->fetch();
    }

    /**
     * Get rating breakdown (count per star)
     */
    public static function getRatingBreakdown($groupId)
    {
        self::ensureTable();

        $sql = "SELECT rating, COUNT(*) as count
                FROM group_feedback
                WHERE group_id = ?
                GROUP BY rating
                ORDER BY rating DESC";
        $results = Database::query($sql, [$groupId])->fetchAll();

        // Build full breakdown with zeros for missing ratings
        $breakdown = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($results as $row) {
            $breakdown[$row['rating']] = (int)$row['count'];
        }
        return $breakdown;
    }

    /**
     * Check if user has already submitted feedback
     */
    public static function getUserFeedback($groupId, $userId)
    {
        self::ensureTable();

        $sql = "SELECT * FROM group_feedback WHERE group_id = ? AND user_id = ?";
        return Database::query($sql, [$groupId, $userId])->fetch();
    }

    /**
     * Delete feedback
     */
    public static function delete($id, $groupId)
    {
        $sql = "DELETE FROM group_feedback WHERE id = ? AND group_id = ?";
        return Database::query($sql, [$id, $groupId]);
    }
}
