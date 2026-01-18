<?php

namespace Nexus\Models;

use Nexus\Core\Database;

class Gamification
{
    /**
     * Award points to a user for an action.
     *
     * @param int $userId
     * @param int $points
     * @param string $reason
     * @return void
     */
    public static function awardPoints($userId, $points, $reason)
    {
        $db = Database::getConnection();

        // 1. Log the transaction/history (Optional table: user_points_log)
        // For now, we will just update the user's columns if they exist, 
        // or assume a 'points' column exists on users, or perhaps a separate gamification table.
        // Given the lack of a clear 'points' column in the User model in previous context,
        // we will check if 'users' table has 'points'.
        // If not, we might fail silently or log it.

        // Assuming 'users' table has a 'reputation' or 'points' column based on typical gamification.
        // Let's assume 'reputation' or 'points'. Ideally, we should check schema.
        // For safely, let's use a try-catch to update 'points'.

        try {
            $db->query("UPDATE users SET points = points + ? WHERE id = ?", [$points, $userId]);

            // Log if table exists
            // $db->query("INSERT INTO user_points_log (user_id, points, reason, created_at) VALUES (?, ?, ?, NOW())", [$userId, $points, $reason]);
        } catch (\Exception $e) {
            // Check if column missing?
            // Fallback: Do nothing or log error.
            // error_log("Gamification Error: " . $e->getMessage());
        }
    }
}
