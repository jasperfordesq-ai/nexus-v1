<?php

namespace Nexus\Models;

use Nexus\Core\Database;

class VolReview
{
    public static function create($reviewerId, $targetType, $targetId, $rating, $comment)
    {
        $sql = "INSERT INTO vol_reviews (reviewer_id, target_type, target_id, rating, comment) VALUES (?, ?, ?, ?, ?)";
        Database::query($sql, [$reviewerId, $targetType, $targetId, $rating, $comment]);
    }

    public static function getForTarget($type, $id)
    {
        $sql = "SELECT r.*, u.first_name, u.last_name, u.avatar 
                FROM vol_reviews r
                JOIN users u ON r.reviewer_id = u.id
                WHERE r.target_type = ? AND r.target_id = ?
                ORDER BY r.created_at DESC";
        return Database::query($sql, [$type, $id])->fetchAll();
    }
}
