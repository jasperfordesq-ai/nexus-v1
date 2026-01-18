<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class LeaderboardService
{
    /**
     * Available leaderboard types
     */
    public const LEADERBOARD_TYPES = [
        'credits_earned' => 'Time Credits Earned',
        'credits_spent' => 'Time Credits Spent',
        'vol_hours' => 'Volunteer Hours',
        'badges' => 'Badges Earned',
        'xp' => 'Experience Points',
        'connections' => 'Connections Made',
        'reviews' => 'Reviews Given',
        'posts' => 'Posts Created',
        'streak' => 'Login Streak'
    ];

    /**
     * Available time periods
     */
    public const PERIODS = ['all_time', 'monthly', 'weekly'];

    /**
     * Get leaderboard data
     */
    public static function getLeaderboard($type, $period = 'all_time', $limit = 10, $includeCurrentUser = true)
    {
        if (!array_key_exists($type, self::LEADERBOARD_TYPES)) {
            return [];
        }

        try {
            $tenantId = TenantContext::getId();
            $currentUserId = $_SESSION['user_id'] ?? null;

            // Build query based on type
            $query = self::buildLeaderboardQuery($type, $period, $tenantId, $limit);

            if (!$query) {
                return [];
            }

            $results = Database::query($query['sql'], $query['params'])->fetchAll();

            // Add rank positions
            $rank = 1;
            foreach ($results as &$row) {
                $row['rank'] = $rank++;
                $row['is_current_user'] = ($currentUserId && $row['user_id'] == $currentUserId);
            }

            // If current user not in top results, fetch their rank
            if ($includeCurrentUser && $currentUserId) {
                $inResults = false;
                foreach ($results as $row) {
                    if ($row['user_id'] == $currentUserId) {
                        $inResults = true;
                        break;
                    }
                }

                if (!$inResults) {
                    $userRank = self::getUserRank($currentUserId, $type, $period);
                    if ($userRank) {
                        $userRank['is_current_user'] = true;
                        $results[] = $userRank;
                    }
                }
            }

            return $results;

        } catch (\Throwable $e) {
            error_log("Leaderboard Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Build the leaderboard query based on type
     */
    private static function buildLeaderboardQuery($type, $period, $tenantId, $limit)
    {
        $dateFilter = self::getDateFilter($period);
        $limit = (int)$limit;

        switch ($type) {
            case 'credits_earned':
                $dateFilterWithTable = str_replace('AND created_at', 'AND t.created_at', $dateFilter);
                return [
                    'sql' => "SELECT u.id as user_id, u.name, u.first_name, u.last_name, u.avatar_url,
                              COALESCE(SUM(t.amount), 0) as score
                              FROM users u
                              LEFT JOIN transactions t ON u.id = t.receiver_id AND t.deleted_for_receiver = 0 $dateFilterWithTable
                              WHERE u.tenant_id = ? AND u.is_approved = 1 AND COALESCE(u.show_on_leaderboard, 1) = 1
                              GROUP BY u.id
                              HAVING score > 0
                              ORDER BY score DESC
                              LIMIT $limit",
                    'params' => [$tenantId]
                ];

            case 'credits_spent':
                $dateFilterWithTable = str_replace('AND created_at', 'AND t.created_at', $dateFilter);
                return [
                    'sql' => "SELECT u.id as user_id, u.name, u.first_name, u.last_name, u.avatar_url,
                              COALESCE(SUM(t.amount), 0) as score
                              FROM users u
                              LEFT JOIN transactions t ON u.id = t.sender_id AND t.deleted_for_sender = 0 $dateFilterWithTable
                              WHERE u.tenant_id = ? AND u.is_approved = 1 AND COALESCE(u.show_on_leaderboard, 1) = 1
                              GROUP BY u.id
                              HAVING score > 0
                              ORDER BY score DESC
                              LIMIT $limit",
                    'params' => [$tenantId]
                ];

            case 'vol_hours':
                $dateFilterWithTable = str_replace('AND created_at', 'AND v.created_at', $dateFilter);
                return [
                    'sql' => "SELECT u.id as user_id, u.name, u.first_name, u.last_name, u.avatar_url,
                              COALESCE(SUM(v.hours), 0) as score
                              FROM users u
                              LEFT JOIN vol_logs v ON u.id = v.user_id AND v.status = 'approved' $dateFilterWithTable
                              WHERE u.tenant_id = ? AND u.is_approved = 1 AND COALESCE(u.show_on_leaderboard, 1) = 1
                              GROUP BY u.id
                              HAVING score > 0
                              ORDER BY score DESC
                              LIMIT $limit",
                    'params' => [$tenantId]
                ];

            case 'badges':
                $dateFilterWithTable = str_replace('AND created_at', 'AND ub.awarded_at', $dateFilter);
                return [
                    'sql' => "SELECT u.id as user_id, u.name, u.first_name, u.last_name, u.avatar_url,
                              COUNT(ub.id) as score
                              FROM users u
                              LEFT JOIN user_badges ub ON u.id = ub.user_id $dateFilterWithTable
                              WHERE u.tenant_id = ? AND u.is_approved = 1 AND COALESCE(u.show_on_leaderboard, 1) = 1
                              GROUP BY u.id
                              HAVING score > 0
                              ORDER BY score DESC
                              LIMIT $limit",
                    'params' => [$tenantId]
                ];

            case 'xp':
                return [
                    'sql' => "SELECT u.id as user_id, u.name, u.first_name, u.last_name, u.avatar_url,
                              COALESCE(u.xp, 0) as score, COALESCE(u.level, 1) as level
                              FROM users u
                              WHERE u.tenant_id = ? AND u.is_approved = 1 AND COALESCE(u.show_on_leaderboard, 1) = 1
                              AND COALESCE(u.xp, 0) > 0
                              ORDER BY score DESC
                              LIMIT $limit",
                    'params' => [$tenantId]
                ];

            case 'connections':
                $dateFilterWithTable = str_replace('AND created_at', 'AND c.created_at', $dateFilter);
                return [
                    'sql' => "SELECT u.id as user_id, u.name, u.first_name, u.last_name, u.avatar_url,
                              COUNT(DISTINCT c.id) as score
                              FROM users u
                              LEFT JOIN connections c ON (u.id = c.requester_id OR u.id = c.receiver_id) AND c.status = 'accepted' $dateFilterWithTable
                              WHERE u.tenant_id = ? AND u.is_approved = 1 AND COALESCE(u.show_on_leaderboard, 1) = 1
                              GROUP BY u.id
                              HAVING score > 0
                              ORDER BY score DESC
                              LIMIT $limit",
                    'params' => [$tenantId]
                ];

            case 'reviews':
                $dateFilterWithTable = str_replace('AND created_at', 'AND r.created_at', $dateFilter);
                return [
                    'sql' => "SELECT u.id as user_id, u.name, u.first_name, u.last_name, u.avatar_url,
                              COUNT(r.id) as score
                              FROM users u
                              LEFT JOIN reviews r ON u.id = r.reviewer_id $dateFilterWithTable
                              WHERE u.tenant_id = ? AND u.is_approved = 1 AND COALESCE(u.show_on_leaderboard, 1) = 1
                              GROUP BY u.id
                              HAVING score > 0
                              ORDER BY score DESC
                              LIMIT $limit",
                    'params' => [$tenantId]
                ];

            case 'posts':
                $dateFilterWithTable = str_replace('AND created_at', 'AND fp.created_at', $dateFilter);
                return [
                    'sql' => "SELECT u.id as user_id, u.name, u.first_name, u.last_name, u.avatar_url,
                              COUNT(fp.id) as score
                              FROM users u
                              LEFT JOIN feed_posts fp ON u.id = fp.user_id $dateFilterWithTable
                              WHERE u.tenant_id = ? AND u.is_approved = 1 AND COALESCE(u.show_on_leaderboard, 1) = 1
                              GROUP BY u.id
                              HAVING score > 0
                              ORDER BY score DESC
                              LIMIT $limit",
                    'params' => [$tenantId]
                ];

            case 'streak':
                return [
                    'sql' => "SELECT u.id as user_id, u.name, u.first_name, u.last_name, u.avatar_url,
                              COALESCE(us.current_streak, 0) as score, COALESCE(us.longest_streak, 0) as longest
                              FROM users u
                              LEFT JOIN user_streaks us ON u.id = us.user_id AND us.streak_type = 'login'
                              WHERE u.tenant_id = ? AND u.is_approved = 1 AND COALESCE(u.show_on_leaderboard, 1) = 1
                              AND COALESCE(us.current_streak, 0) > 0
                              ORDER BY score DESC, longest DESC
                              LIMIT $limit",
                    'params' => [$tenantId]
                ];

            default:
                return null;
        }
    }

    /**
     * Get date filter SQL for period
     */
    private static function getDateFilter($period)
    {
        switch ($period) {
            case 'weekly':
                return "AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            case 'monthly':
                return "AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            case 'all_time':
            default:
                return "";
        }
    }

    /**
     * Get a specific user's rank for a leaderboard type
     */
    public static function getUserRank($userId, $type, $period = 'all_time')
    {
        try {
            $tenantId = TenantContext::getId();

            // Get user's score
            $userScore = self::getUserScore($userId, $type, $period);
            if ($userScore === null) {
                return null;
            }

            // Count users with higher scores
            $rankQuery = self::buildRankQuery($type, $period, $tenantId, $userScore);
            if (!$rankQuery) {
                return null;
            }

            $result = Database::query($rankQuery['sql'], $rankQuery['params'])->fetch();
            $rank = $result ? ((int)$result['rank'] + 1) : 1;

            // Get user details
            $user = Database::query(
                "SELECT id as user_id, name, first_name, last_name, avatar_url FROM users WHERE id = ?",
                [$userId]
            )->fetch();

            if ($user) {
                $user['score'] = $userScore;
                $user['rank'] = $rank;
                return $user;
            }

            return null;

        } catch (\Throwable $e) {
            error_log("User Rank Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get user's score for a type
     */
    private static function getUserScore($userId, $type, $period)
    {
        $dateFilter = self::getDateFilter($period);

        try {
            switch ($type) {
                case 'credits_earned':
                    $result = Database::query(
                        "SELECT COALESCE(SUM(amount), 0) as score FROM transactions WHERE receiver_id = ? AND deleted_for_receiver = 0 " . str_replace('AND created_at', 'AND transactions.created_at', $dateFilter),
                        [$userId]
                    )->fetch();
                    break;

                case 'credits_spent':
                    $result = Database::query(
                        "SELECT COALESCE(SUM(amount), 0) as score FROM transactions WHERE sender_id = ? AND deleted_for_sender = 0 " . str_replace('AND created_at', 'AND transactions.created_at', $dateFilter),
                        [$userId]
                    )->fetch();
                    break;

                case 'vol_hours':
                    $result = Database::query(
                        "SELECT COALESCE(SUM(hours), 0) as score FROM vol_logs WHERE user_id = ? AND status = 'approved' " . str_replace('AND created_at', 'AND vol_logs.created_at', $dateFilter),
                        [$userId]
                    )->fetch();
                    break;

                case 'badges':
                    $result = Database::query(
                        "SELECT COUNT(*) as score FROM user_badges WHERE user_id = ? " . str_replace('AND created_at', 'AND user_badges.awarded_at', $dateFilter),
                        [$userId]
                    )->fetch();
                    break;

                case 'xp':
                    $result = Database::query(
                        "SELECT COALESCE(xp, 0) as score FROM users WHERE id = ?",
                        [$userId]
                    )->fetch();
                    break;

                case 'connections':
                    $result = Database::query(
                        "SELECT COUNT(DISTINCT id) as score FROM connections WHERE (requester_id = ? OR receiver_id = ?) AND status = 'accepted' " . str_replace('AND created_at', 'AND connections.created_at', $dateFilter),
                        [$userId, $userId]
                    )->fetch();
                    break;

                case 'reviews':
                    $result = Database::query(
                        "SELECT COUNT(*) as score FROM reviews WHERE reviewer_id = ? " . str_replace('AND created_at', 'AND reviews.created_at', $dateFilter),
                        [$userId]
                    )->fetch();
                    break;

                case 'posts':
                    $result = Database::query(
                        "SELECT COUNT(*) as score FROM feed_posts WHERE user_id = ? " . str_replace('AND created_at', 'AND feed_posts.created_at', $dateFilter),
                        [$userId]
                    )->fetch();
                    break;

                case 'streak':
                    $result = Database::query(
                        "SELECT COALESCE(current_streak, 0) as score FROM user_streaks WHERE user_id = ? AND streak_type = 'login'",
                        [$userId]
                    )->fetch();
                    break;

                default:
                    return null;
            }

            return $result ? (float)$result['score'] : 0;

        } catch (\Throwable $e) {
            error_log("User Score Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Build rank counting query
     */
    private static function buildRankQuery($type, $period, $tenantId, $userScore)
    {
        $dateFilter = self::getDateFilter($period);

        switch ($type) {
            case 'xp':
                return [
                    'sql' => "SELECT COUNT(*) as rank FROM users WHERE tenant_id = ? AND is_approved = 1 AND COALESCE(xp, 0) > ?",
                    'params' => [$tenantId, $userScore]
                ];

            case 'streak':
                return [
                    'sql' => "SELECT COUNT(DISTINCT us.user_id) as rank
                              FROM user_streaks us
                              JOIN users u ON us.user_id = u.id
                              WHERE u.tenant_id = ? AND us.streak_type = 'login' AND us.current_streak > ?",
                    'params' => [$tenantId, $userScore]
                ];

            default:
                // For aggregate types, this is approximate
                return [
                    'sql' => "SELECT 0 as rank",
                    'params' => []
                ];
        }
    }

    /**
     * Get all leaderboards summary (top 3 for each type)
     */
    public static function getAllLeaderboardsSummary($period = 'all_time')
    {
        $summary = [];
        foreach (array_keys(self::LEADERBOARD_TYPES) as $type) {
            $summary[$type] = [
                'title' => self::LEADERBOARD_TYPES[$type],
                'leaders' => self::getLeaderboard($type, $period, 3, false)
            ];
        }
        return $summary;
    }

    /**
     * Get medal icon for rank
     */
    public static function getMedalIcon($rank)
    {
        switch ($rank) {
            case 1: return '🥇';
            case 2: return '🥈';
            case 3: return '🥉';
            default: return '';
        }
    }

    /**
     * Format score for display
     */
    public static function formatScore($score, $type)
    {
        switch ($type) {
            case 'credits_earned':
            case 'credits_spent':
                return number_format($score) . ' credits';
            case 'vol_hours':
                return number_format($score, 1) . ' hours';
            case 'badges':
                return number_format($score) . ' badges';
            case 'xp':
                return number_format($score) . ' XP';
            case 'connections':
                return number_format($score) . ' connections';
            case 'reviews':
                return number_format($score) . ' reviews';
            case 'posts':
                return number_format($score) . ' posts';
            case 'streak':
                return number_format($score) . ' days';
            default:
                return number_format($score);
        }
    }

    /**
     * Update cached leaderboard (call via cron for performance)
     */
    public static function updateCache()
    {
        try {
            $tenantId = TenantContext::getId();

            foreach (array_keys(self::LEADERBOARD_TYPES) as $type) {
                foreach (self::PERIODS as $period) {
                    $leaders = self::getLeaderboard($type, $period, 100, false);

                    foreach ($leaders as $leader) {
                        Database::query(
                            "INSERT INTO leaderboard_cache (tenant_id, user_id, leaderboard_type, period, score, rank_position)
                             VALUES (?, ?, ?, ?, ?, ?)
                             ON DUPLICATE KEY UPDATE score = VALUES(score), rank_position = VALUES(rank_position), updated_at = NOW()",
                            [$tenantId, $leader['user_id'], $type, $period, $leader['score'], $leader['rank']]
                        );
                    }
                }
            }

            return true;

        } catch (\Throwable $e) {
            error_log("Leaderboard Cache Update Error: " . $e->getMessage());
            return false;
        }
    }
}
