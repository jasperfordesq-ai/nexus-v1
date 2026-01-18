<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\Notification;

class DailyRewardService
{
    /**
     * Daily login XP rewards (increases with streak)
     */
    public const DAILY_XP_BASE = 5;
    public const DAILY_XP_STREAK_BONUS = 2; // Extra XP per streak day (capped)
    public const DAILY_XP_MAX_BONUS = 20;   // Max bonus from streak

    /**
     * Weekly bonus rewards
     */
    public const WEEKLY_BONUS_XP = 50;      // Bonus for 7-day streak
    public const MONTHLY_BONUS_XP = 200;    // Bonus for 30-day streak

    /**
     * Check and award daily login reward
     * Returns reward data if awarded, null if already claimed today
     */
    public static function checkAndAwardDailyReward($userId)
    {
        $tenantId = TenantContext::getId();
        $today = date('Y-m-d');

        // Check if already claimed today
        $existing = Database::query(
            "SELECT id FROM daily_rewards WHERE tenant_id = ? AND user_id = ? AND reward_date = ?",
            [$tenantId, $userId, $today]
        )->fetch();

        if ($existing) {
            return null; // Already claimed
        }

        // Get current login streak
        $streak = StreakService::getStreak($userId, 'login');
        $currentStreak = $streak['current'] ?? 1;

        // Calculate XP reward
        $streakBonus = min(self::DAILY_XP_MAX_BONUS, ($currentStreak - 1) * self::DAILY_XP_STREAK_BONUS);
        $dailyXP = self::DAILY_XP_BASE + $streakBonus;

        // Check for milestone bonuses
        $milestoneBonus = 0;
        $milestoneName = null;

        if ($currentStreak === 7) {
            $milestoneBonus = self::WEEKLY_BONUS_XP;
            $milestoneName = '7-Day Streak';
        } elseif ($currentStreak === 30) {
            $milestoneBonus = self::MONTHLY_BONUS_XP;
            $milestoneName = '30-Day Streak';
        } elseif ($currentStreak === 100) {
            $milestoneBonus = 500;
            $milestoneName = '100-Day Streak';
        } elseif ($currentStreak === 365) {
            $milestoneBonus = 1000;
            $milestoneName = '365-Day Streak';
        }

        $totalXP = $dailyXP + $milestoneBonus;

        // Record the reward
        Database::query(
            "INSERT INTO daily_rewards (tenant_id, user_id, reward_date, xp_earned, streak_day, milestone_bonus)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$tenantId, $userId, $today, $totalXP, $currentStreak, $milestoneBonus]
        );

        // Award XP
        GamificationService::awardXP($userId, $dailyXP, 'daily_login', "Day $currentStreak login bonus");

        if ($milestoneBonus > 0) {
            GamificationService::awardXP($userId, $milestoneBonus, 'streak_milestone', $milestoneName);
        }

        return [
            'daily_xp' => $dailyXP,
            'streak_day' => $currentStreak,
            'streak_bonus' => $streakBonus,
            'milestone_bonus' => $milestoneBonus,
            'milestone_name' => $milestoneName,
            'total_xp' => $totalXP,
            'next_milestone' => self::getNextMilestone($currentStreak),
        ];
    }

    /**
     * Get today's reward status for a user
     */
    public static function getTodayStatus($userId)
    {
        $tenantId = TenantContext::getId();
        $today = date('Y-m-d');

        $reward = Database::query(
            "SELECT * FROM daily_rewards WHERE tenant_id = ? AND user_id = ? AND reward_date = ?",
            [$tenantId, $userId, $today]
        )->fetch();

        $streak = StreakService::getStreak($userId, 'login');

        return [
            'claimed' => (bool)$reward,
            'reward' => $reward,
            'current_streak' => $streak['current'] ?? 0,
            'longest_streak' => $streak['longest'] ?? 0,
        ];
    }

    /**
     * Get next milestone info
     */
    private static function getNextMilestone($currentStreak)
    {
        $milestones = [7, 30, 100, 365];

        foreach ($milestones as $milestone) {
            if ($currentStreak < $milestone) {
                return [
                    'days' => $milestone,
                    'remaining' => $milestone - $currentStreak,
                    'bonus_xp' => self::getMilestoneBonus($milestone),
                ];
            }
        }

        return null; // All milestones achieved
    }

    /**
     * Get milestone bonus amount
     */
    private static function getMilestoneBonus($days)
    {
        switch ($days) {
            case 7: return self::WEEKLY_BONUS_XP;
            case 30: return self::MONTHLY_BONUS_XP;
            case 100: return 500;
            case 365: return 1000;
            default: return 0;
        }
    }

    /**
     * Get reward history for a user
     */
    public static function getHistory($userId, $limit = 30)
    {
        $tenantId = TenantContext::getId();
        $limit = (int) $limit;

        return Database::query(
            "SELECT * FROM daily_rewards
             WHERE tenant_id = ? AND user_id = ?
             ORDER BY reward_date DESC
             LIMIT $limit",
            [$tenantId, $userId]
        )->fetchAll();
    }

    /**
     * Get total XP earned from daily rewards
     */
    public static function getTotalEarned($userId)
    {
        $tenantId = TenantContext::getId();

        $result = Database::query(
            "SELECT COALESCE(SUM(xp_earned), 0) as total FROM daily_rewards
             WHERE tenant_id = ? AND user_id = ?",
            [$tenantId, $userId]
        )->fetch();

        return (int)($result['total'] ?? 0);
    }
}
