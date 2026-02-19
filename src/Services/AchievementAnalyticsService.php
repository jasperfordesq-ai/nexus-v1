<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class AchievementAnalyticsService
{
    /**
     * Get overall gamification statistics
     */
    public static function getOverallStats()
    {
        $tenantId = TenantContext::getId();

        // Total XP earned across all users
        $xpStats = Database::query(
            "SELECT COALESCE(SUM(xp), 0) as total_xp, COALESCE(AVG(xp), 0) as avg_xp, MAX(xp) as max_xp
             FROM users WHERE tenant_id = ?",
            [$tenantId]
        )->fetch();

        // Total badges earned
        $badgeStats = Database::query(
            "SELECT COUNT(*) as total_badges, COUNT(DISTINCT ub.user_id) as users_with_badges
             FROM user_badges ub
             JOIN users u ON ub.user_id = u.id
             WHERE u.tenant_id = ?",
            [$tenantId]
        )->fetch();

        // User engagement
        $userStats = Database::query(
            "SELECT
                COUNT(*) as total_users,
                SUM(CASE WHEN xp > 0 THEN 1 ELSE 0 END) as engaged_users,
                SUM(CASE WHEN level >= 5 THEN 1 ELSE 0 END) as advanced_users
             FROM users WHERE tenant_id = ?",
            [$tenantId]
        )->fetch();

        // Level distribution
        $levelDist = Database::query(
            "SELECT level, COUNT(*) as count FROM users WHERE tenant_id = ? GROUP BY level ORDER BY level",
            [$tenantId]
        )->fetchAll();

        return [
            'total_xp' => (int)($xpStats['total_xp'] ?? 0),
            'avg_xp' => round((float)($xpStats['avg_xp'] ?? 0), 1),
            'max_xp' => (int)($xpStats['max_xp'] ?? 0),
            'total_badges' => (int)($badgeStats['total_badges'] ?? 0),
            'users_with_badges' => (int)($badgeStats['users_with_badges'] ?? 0),
            'total_users' => (int)($userStats['total_users'] ?? 0),
            'engaged_users' => (int)($userStats['engaged_users'] ?? 0),
            'advanced_users' => (int)($userStats['advanced_users'] ?? 0),
            'engagement_rate' => $userStats['total_users'] > 0
                ? round(($userStats['engaged_users'] / $userStats['total_users']) * 100, 1)
                : 0,
            'level_distribution' => $levelDist,
        ];
    }

    /**
     * Get badge earning trends over time
     */
    public static function getBadgeTrends($days = 30)
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT DATE(ub.awarded_at) as date, COUNT(*) as count
             FROM user_badges ub
             JOIN users u ON ub.user_id = u.id
             WHERE u.tenant_id = ? AND ub.awarded_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DATE(ub.awarded_at)
             ORDER BY date",
            [$tenantId, $days]
        )->fetchAll();
    }

    /**
     * Get most popular badges
     */
    public static function getPopularBadges($limit = 10)
    {
        $tenantId = TenantContext::getId();
        $limit = (int)$limit;

        $badges = Database::query(
            "SELECT ub.badge_key, COUNT(*) as award_count
             FROM user_badges ub
             JOIN users u ON ub.user_id = u.id
             WHERE u.tenant_id = ?
             GROUP BY ub.badge_key
             ORDER BY award_count DESC
             LIMIT {$limit}",
            [$tenantId]
        )->fetchAll();

        // Enrich with badge definitions
        foreach ($badges as &$badge) {
            $def = GamificationService::getBadgeByKey($badge['badge_key']);
            if ($def) {
                $badge['name'] = $def['name'] ?? $badge['badge_key'];
                $badge['icon'] = $def['icon'] ?? '🏆';
                $badge['xp'] = $def['xp'] ?? 0;
            } else {
                // Check custom badges
                $custom = Database::query(
                    "SELECT name, icon, xp FROM custom_badges WHERE id = ?",
                    [str_replace('custom_', '', $badge['badge_key'])]
                )->fetch();

                if ($custom) {
                    $badge['name'] = $custom['name'] ?? $badge['badge_key'];
                    $badge['icon'] = $custom['icon'] ?? '🏆';
                    $badge['xp'] = $custom['xp'] ?? 0;
                } else {
                    $badge['name'] = $badge['badge_key'];
                    $badge['icon'] = '🏆';
                    $badge['xp'] = 0;
                }
            }
        }

        return $badges;
    }

    /**
     * Get rarest badges (least earned)
     */
    public static function getRarestBadges($limit = 10)
    {
        $tenantId = TenantContext::getId();
        $limit = (int)$limit;
        $totalUsers = Database::query(
            "SELECT COUNT(*) as count FROM users WHERE tenant_id = ?",
            [$tenantId]
        )->fetch()['count'];

        $badges = Database::query(
            "SELECT ub.badge_key, COUNT(*) as award_count
             FROM user_badges ub
             JOIN users u ON ub.user_id = u.id
             WHERE u.tenant_id = ?
             GROUP BY ub.badge_key
             ORDER BY award_count ASC
             LIMIT {$limit}",
            [$tenantId]
        )->fetchAll();

        foreach ($badges as &$badge) {
            $def = GamificationService::getBadgeByKey($badge['badge_key']);
            if ($def) {
                $badge['name'] = $def['name'];
                $badge['icon'] = $def['icon'];
            } else {
                $badge['name'] = $badge['badge_key'];
                $badge['icon'] = '🏆';
            }
            $badge['rarity_percent'] = $totalUsers > 0
                ? round(($badge['award_count'] / $totalUsers) * 100, 1)
                : 0;
        }

        return $badges;
    }

    /**
     * Get top XP earners
     */
    public static function getTopEarners($limit = 10)
    {
        $tenantId = TenantContext::getId();
        $limit = (int)$limit;

        return Database::query(
            "SELECT u.id, u.first_name, u.last_name, u.avatar_url, u.xp, u.level,
                    (SELECT COUNT(*) FROM user_badges WHERE user_id = u.id) as badge_count
             FROM users u
             WHERE u.tenant_id = ?
             ORDER BY u.xp DESC
             LIMIT {$limit}",
            [$tenantId]
        )->fetchAll();
    }

    /**
     * Get recent badge activity
     */
    public static function getRecentActivity($limit = 20)
    {
        $tenantId = TenantContext::getId();
        $limit = (int)$limit;

        $activity = Database::query(
            "SELECT ub.*, u.first_name, u.last_name, u.avatar_url
             FROM user_badges ub
             JOIN users u ON ub.user_id = u.id
             WHERE u.tenant_id = ?
             ORDER BY ub.awarded_at DESC
             LIMIT {$limit}",
            [$tenantId]
        )->fetchAll();

        foreach ($activity as &$item) {
            $def = GamificationService::getBadgeByKey($item['badge_key']);
            if ($def) {
                $item['badge_name'] = $def['name'];
                $item['badge_icon'] = $def['icon'];
            } else {
                $item['badge_name'] = $item['badge_key'];
                $item['badge_icon'] = '🏆';
            }
        }

        return $activity;
    }

    /**
     * Get challenge completion stats
     */
    public static function getChallengeStats()
    {
        $tenantId = TenantContext::getId();

        $stats = Database::query(
            "SELECT
                c.id, c.title, c.type,
                COUNT(ucp.id) as participants,
                SUM(CASE WHEN ucp.completed = 1 THEN 1 ELSE 0 END) as completions
             FROM challenges c
             LEFT JOIN user_challenge_progress ucp ON c.id = ucp.challenge_id
             WHERE c.tenant_id = ? AND c.status = 'active'
             GROUP BY c.id
             ORDER BY participants DESC",
            [$tenantId]
        )->fetchAll();

        return $stats;
    }

    /**
     * Get daily rewards stats
     */
    public static function getDailyRewardStats($days = 30)
    {
        $tenantId = TenantContext::getId();

        $stats = Database::query(
            "SELECT
                DATE(claimed_at) as date,
                COUNT(*) as claims,
                SUM(xp_amount) as total_xp,
                AVG(streak_day) as avg_streak
             FROM daily_rewards
             WHERE tenant_id = ? AND claimed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DATE(claimed_at)
             ORDER BY date",
            [$tenantId, $days]
        )->fetchAll();

        return $stats;
    }

    /**
     * Get XP shop purchase stats
     */
    public static function getShopStats()
    {
        $tenantId = TenantContext::getId();

        $stats = Database::query(
            "SELECT
                xsi.name, xsi.icon, xsi.xp_cost,
                COUNT(uxp.id) as purchases,
                SUM(uxp.xp_spent) as total_xp_spent
             FROM xp_shop_items xsi
             LEFT JOIN user_xp_purchases uxp ON xsi.id = uxp.item_id
             WHERE xsi.tenant_id = ?
             GROUP BY xsi.id
             ORDER BY purchases DESC",
            [$tenantId]
        )->fetchAll();

        return $stats;
    }

    /**
     * Get referral stats
     */
    public static function getReferralStats()
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT
                COUNT(*) as total_referrals,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status IN ('active', 'engaged') THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'engaged' THEN 1 ELSE 0 END) as engaged
             FROM referral_tracking
             WHERE tenant_id = ?",
            [$tenantId]
        )->fetch();
    }

    /**
     * Get full analytics dashboard data
     */
    public static function getDashboardData()
    {
        return [
            'overview' => self::getOverallStats(),
            'badge_trends' => self::getBadgeTrends(30),
            'popular_badges' => self::getPopularBadges(5),
            'rarest_badges' => self::getRarestBadges(5),
            'top_earners' => self::getTopEarners(10),
            'recent_activity' => self::getRecentActivity(10),
        ];
    }
}
