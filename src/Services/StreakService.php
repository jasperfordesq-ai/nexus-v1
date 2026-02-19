<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class StreakService
{
    /**
     * Streak types available
     */
    public const STREAK_TYPES = ['login', 'activity', 'giving', 'volunteer'];

    /**
     * Record activity and update streak
     * Call this when user performs a relevant action
     */
    public static function recordActivity($userId, $streakType = 'activity')
    {
        if (!in_array($streakType, self::STREAK_TYPES)) {
            return false;
        }

        try {
            $tenantId = TenantContext::getId();
            $today = date('Y-m-d');

            // Get current streak data
            $streak = Database::query(
                "SELECT * FROM user_streaks WHERE tenant_id = ? AND user_id = ? AND streak_type = ?",
                [$tenantId, $userId, $streakType]
            )->fetch();

            if (!$streak) {
                // First activity - create streak record
                Database::query(
                    "INSERT INTO user_streaks (tenant_id, user_id, streak_type, current_streak, longest_streak, last_activity_date)
                     VALUES (?, ?, ?, 1, 1, ?)",
                    [$tenantId, $userId, $streakType, $today]
                );

                // Check for streak badges
                GamificationService::checkStreakBadges($userId, 1);
                return ['current' => 1, 'longest' => 1, 'is_new' => true];
            }

            $lastDate = $streak['last_activity_date'];
            $currentStreak = (int)$streak['current_streak'];
            $longestStreak = (int)$streak['longest_streak'];
            $freezesRemaining = (int)$streak['streak_freezes_remaining'];

            // Already recorded today
            if ($lastDate === $today) {
                return [
                    'current' => $currentStreak,
                    'longest' => $longestStreak,
                    'is_new' => false,
                    'message' => 'Already recorded today'
                ];
            }

            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $twoDaysAgo = date('Y-m-d', strtotime('-2 days'));

            if ($lastDate === $yesterday) {
                // Consecutive day - increment streak
                $currentStreak++;
                $longestStreak = max($longestStreak, $currentStreak);
            } elseif ($lastDate === $twoDaysAgo && $freezesRemaining > 0) {
                // Missed one day but have a freeze available
                $currentStreak++; // Continue streak
                $freezesRemaining--;
                $longestStreak = max($longestStreak, $currentStreak);
            } else {
                // Streak broken - reset to 1
                $currentStreak = 1;
            }

            // Update streak
            Database::query(
                "UPDATE user_streaks
                 SET current_streak = ?, longest_streak = ?, last_activity_date = ?, streak_freezes_remaining = ?
                 WHERE tenant_id = ? AND user_id = ? AND streak_type = ?",
                [$currentStreak, $longestStreak, $today, $freezesRemaining, $tenantId, $userId, $streakType]
            );

            // Check for streak badges
            GamificationService::checkStreakBadges($userId, $currentStreak);

            // Award XP for daily login streak
            if ($streakType === 'login') {
                GamificationService::awardXP($userId, GamificationService::XP_VALUES['daily_login'], 'daily_login', "Day $currentStreak streak");
            }

            return [
                'current' => $currentStreak,
                'longest' => $longestStreak,
                'is_new' => true,
                'freezes_remaining' => $freezesRemaining
            ];

        } catch (\Throwable $e) {
            error_log("Streak Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Record login streak specifically
     */
    public static function recordLogin($userId)
    {
        return self::recordActivity($userId, 'login');
    }

    /**
     * Record giving/transaction streak
     */
    public static function recordGiving($userId)
    {
        return self::recordActivity($userId, 'giving');
    }

    /**
     * Record volunteer activity streak
     */
    public static function recordVolunteer($userId)
    {
        return self::recordActivity($userId, 'volunteer');
    }

    /**
     * Get user's current streak for a type
     */
    public static function getStreak($userId, $streakType = 'activity')
    {
        try {
            $tenantId = TenantContext::getId();

            $streak = Database::query(
                "SELECT * FROM user_streaks WHERE tenant_id = ? AND user_id = ? AND streak_type = ?",
                [$tenantId, $userId, $streakType]
            )->fetch();

            if (!$streak) {
                return [
                    'current' => 0,
                    'longest' => 0,
                    'last_activity' => null,
                    'is_active' => false,
                    'freezes_remaining' => 1
                ];
            }

            // Check if streak is still active
            $lastDate = $streak['last_activity_date'];
            $today = date('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $twoDaysAgo = date('Y-m-d', strtotime('-2 days'));

            $isActive = ($lastDate === $today || $lastDate === $yesterday);
            $canFreeze = ($lastDate === $twoDaysAgo && $streak['streak_freezes_remaining'] > 0);

            return [
                'current' => (int)$streak['current_streak'],
                'longest' => (int)$streak['longest_streak'],
                'last_activity' => $streak['last_activity_date'],
                'is_active' => $isActive,
                'can_use_freeze' => $canFreeze,
                'freezes_remaining' => (int)$streak['streak_freezes_remaining']
            ];

        } catch (\Throwable $e) {
            error_log("Get Streak Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all streaks for a user
     */
    public static function getAllStreaks($userId)
    {
        $streaks = [];
        foreach (self::STREAK_TYPES as $type) {
            $streaks[$type] = self::getStreak($userId, $type);
        }
        return $streaks;
    }

    /**
     * Get streak leaderboard
     */
    public static function getLeaderboard($streakType = 'login', $limit = 10)
    {
        try {
            $tenantId = TenantContext::getId();

            return Database::query(
                "SELECT us.*, u.name, u.avatar_url, u.first_name, u.last_name
                 FROM user_streaks us
                 JOIN users u ON us.user_id = u.id
                 WHERE us.tenant_id = ? AND us.streak_type = ? AND us.current_streak > 0
                 ORDER BY us.current_streak DESC, us.longest_streak DESC
                 LIMIT " . (int)$limit,
                [$tenantId, $streakType]
            )->fetchAll();

        } catch (\Throwable $e) {
            error_log("Streak Leaderboard Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Reset weekly streak freezes (call via cron on Sundays)
     * Intentionally cross-tenant: resets all users' freeze allowance globally
     */
    public static function resetWeeklyFreezes()
    {
        try {
            Database::query(
                "UPDATE user_streaks SET streak_freezes_remaining = 1 WHERE streak_freezes_remaining != 1"
            );
            return true;
        } catch (\Throwable $e) {
            error_log("Reset Freezes Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check and break expired streaks (call via daily cron)
     * Intentionally cross-tenant: expires stale streaks for all users globally
     */
    public static function checkExpiredStreaks()
    {
        try {
            $twoDaysAgo = date('Y-m-d', strtotime('-2 days'));

            // Reset streaks that are more than 2 days old (even with freeze)
            Database::query(
                "UPDATE user_streaks SET current_streak = 0 WHERE last_activity_date < ? AND current_streak > 0",
                [$twoDaysAgo]
            );

            return true;
        } catch (\Throwable $e) {
            error_log("Check Expired Streaks Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get streak status message for display
     */
    public static function getStreakMessage($streak)
    {
        if (!$streak || $streak['current'] === 0) {
            return "Start your streak today!";
        }

        $current = $streak['current'];

        if ($current >= 365) {
            return "Incredible! $current day streak! You're a legend!";
        } elseif ($current >= 100) {
            return "Amazing! $current day streak! Keep it up!";
        } elseif ($current >= 30) {
            return "Fantastic! $current day streak!";
        } elseif ($current >= 7) {
            return "Great job! $current day streak!";
        } else {
            return "$current day streak - keep going!";
        }
    }

    /**
     * Get streak icon based on length
     */
    public static function getStreakIcon($streakLength)
    {
        if ($streakLength >= 365) return '🔥🏆';
        if ($streakLength >= 100) return '🔥💎';
        if ($streakLength >= 30) return '🔥⭐';
        if ($streakLength >= 7) return '🔥';
        if ($streakLength > 0) return '✨';
        return '💤';
    }
}
