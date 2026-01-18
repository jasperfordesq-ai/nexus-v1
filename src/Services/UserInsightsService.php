<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\Transaction;

/**
 * UserInsightsService
 *
 * Provides personal transaction analytics for users.
 * Shows trends, partner statistics, and activity summaries.
 */
class UserInsightsService
{
    /**
     * Get complete user insights
     *
     * @param int $userId
     * @return array
     */
    public static function getInsights($userId)
    {
        return [
            'summary' => self::getSummary($userId),
            'monthly_trends' => self::getMonthlyTrends($userId, 12),
            'partner_stats' => self::getPartnerStats($userId),
            'top_partners' => self::getTopPartners($userId, 5),
            'category_breakdown' => self::getCategoryBreakdown($userId),
        ];
    }

    /**
     * Get transaction summary for user
     *
     * @param int $userId
     * @return array
     */
    public static function getSummary($userId)
    {
        $tenantId = TenantContext::getId();

        // Total earned (all time)
        $totalEarned = Transaction::getTotalEarned($userId);

        // Total spent (all time)
        $totalSpent = self::getTotalSpent($userId);

        // Current balance
        $balance = Database::query(
            "SELECT balance FROM users WHERE id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        )->fetchColumn() ?? 0;

        // This month stats
        $monthStats = Database::query(
            "SELECT
                SUM(CASE WHEN receiver_id = ? THEN amount ELSE 0 END) as earned_this_month,
                SUM(CASE WHEN sender_id = ? THEN amount ELSE 0 END) as spent_this_month,
                COUNT(CASE WHEN receiver_id = ? THEN 1 END) as received_count,
                COUNT(CASE WHEN sender_id = ? THEN 1 END) as sent_count
             FROM transactions
             WHERE tenant_id = ?
             AND (sender_id = ? OR receiver_id = ?)
             AND YEAR(created_at) = YEAR(CURDATE())
             AND MONTH(created_at) = MONTH(CURDATE())",
            [$userId, $userId, $userId, $userId, $tenantId, $userId, $userId]
        )->fetch();

        // Total transaction count
        $totalTransactions = Transaction::countForUser($userId);

        return [
            'total_earned' => (float) $totalEarned,
            'total_spent' => (float) $totalSpent,
            'current_balance' => (float) $balance,
            'earned_this_month' => (float) ($monthStats['earned_this_month'] ?? 0),
            'spent_this_month' => (float) ($monthStats['spent_this_month'] ?? 0),
            'received_count_this_month' => (int) ($monthStats['received_count'] ?? 0),
            'sent_count_this_month' => (int) ($monthStats['sent_count'] ?? 0),
            'net_this_month' => (float) (($monthStats['earned_this_month'] ?? 0) - ($monthStats['spent_this_month'] ?? 0)),
            'total_transactions' => (int) $totalTransactions,
        ];
    }

    /**
     * Get total spent by user
     *
     * @param int $userId
     * @return float
     */
    public static function getTotalSpent($userId)
    {
        $tenantId = TenantContext::getId();

        $result = Database::query(
            "SELECT COALESCE(SUM(amount), 0) as total FROM transactions
             WHERE tenant_id = ? AND sender_id = ?",
            [$tenantId, $userId]
        )->fetch();

        return (float) ($result['total'] ?? 0);
    }

    /**
     * Get monthly transaction trends
     *
     * @param int $userId
     * @param int $months Number of months to look back
     * @return array
     */
    public static function getMonthlyTrends($userId, $months = 12)
    {
        $tenantId = TenantContext::getId();
        $months = (int) $months;

        return Database::query(
            "SELECT
                DATE_FORMAT(created_at, '%Y-%m') as month,
                SUM(CASE WHEN receiver_id = ? THEN amount ELSE 0 END) as earned,
                SUM(CASE WHEN sender_id = ? THEN amount ELSE 0 END) as spent,
                COUNT(CASE WHEN receiver_id = ? THEN 1 END) as received_count,
                COUNT(CASE WHEN sender_id = ? THEN 1 END) as sent_count
             FROM transactions
             WHERE tenant_id = ?
             AND (sender_id = ? OR receiver_id = ?)
             AND created_at >= DATE_SUB(NOW(), INTERVAL $months MONTH)
             GROUP BY month
             ORDER BY month ASC",
            [$userId, $userId, $userId, $userId, $tenantId, $userId, $userId]
        )->fetchAll();
    }

    /**
     * Get weekly transaction trends
     *
     * @param int $userId
     * @param int $weeks Number of weeks to look back
     * @return array
     */
    public static function getWeeklyTrends($userId, $weeks = 12)
    {
        $tenantId = TenantContext::getId();
        $weeks = (int) $weeks;

        return Database::query(
            "SELECT
                YEARWEEK(created_at, 1) as week,
                MIN(DATE(created_at)) as week_start,
                SUM(CASE WHEN receiver_id = ? THEN amount ELSE 0 END) as earned,
                SUM(CASE WHEN sender_id = ? THEN amount ELSE 0 END) as spent
             FROM transactions
             WHERE tenant_id = ?
             AND (sender_id = ? OR receiver_id = ?)
             AND created_at >= DATE_SUB(NOW(), INTERVAL $weeks WEEK)
             GROUP BY week
             ORDER BY week ASC",
            [$userId, $userId, $tenantId, $userId, $userId]
        )->fetchAll();
    }

    /**
     * Get partner diversity statistics
     *
     * @param int $userId
     * @return array
     */
    public static function getPartnerStats($userId)
    {
        $tenantId = TenantContext::getId();

        // Unique people this user has helped (sent credits to)
        $peoplePaid = Database::query(
            "SELECT COUNT(DISTINCT receiver_id) FROM transactions
             WHERE tenant_id = ? AND sender_id = ?",
            [$tenantId, $userId]
        )->fetchColumn();

        // Unique people who have helped this user (received credits from)
        $peoplePaidBy = Database::query(
            "SELECT COUNT(DISTINCT sender_id) FROM transactions
             WHERE tenant_id = ? AND receiver_id = ?",
            [$tenantId, $userId]
        )->fetchColumn();

        // Mutual connections (both sent to and received from)
        $mutualConnections = Database::query(
            "SELECT COUNT(DISTINCT sent.receiver_id) as mutual
             FROM transactions sent
             JOIN transactions received ON sent.receiver_id = received.sender_id
                                       AND sent.sender_id = received.receiver_id
             WHERE sent.tenant_id = ? AND sent.sender_id = ?",
            [$tenantId, $userId]
        )->fetchColumn();

        return [
            'unique_people_paid' => (int) $peoplePaid,
            'unique_people_received_from' => (int) $peoplePaidBy,
            'mutual_connections' => (int) $mutualConnections,
            'total_unique_partners' => (int) $peoplePaid + (int) $peoplePaidBy - (int) $mutualConnections,
        ];
    }

    /**
     * Get top trading partners
     *
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public static function getTopPartners($userId, $limit = 10)
    {
        $tenantId = TenantContext::getId();
        $limit = (int) $limit;

        return Database::query(
            "SELECT
                partner_id,
                CONCAT(u.first_name, ' ', u.last_name) as partner_name,
                u.avatar_url,
                SUM(sent_amount) as total_sent,
                SUM(received_amount) as total_received,
                (SUM(sent_count) + SUM(received_count)) as total_transactions
             FROM (
                 SELECT receiver_id as partner_id, SUM(amount) as sent_amount, 0 as received_amount,
                        COUNT(*) as sent_count, 0 as received_count
                 FROM transactions
                 WHERE tenant_id = ? AND sender_id = ?
                 GROUP BY receiver_id
                 UNION ALL
                 SELECT sender_id as partner_id, 0 as sent_amount, SUM(amount) as received_amount,
                        0 as sent_count, COUNT(*) as received_count
                 FROM transactions
                 WHERE tenant_id = ? AND receiver_id = ?
                 GROUP BY sender_id
             ) as partners
             JOIN users u ON partners.partner_id = u.id
             GROUP BY partner_id
             ORDER BY total_transactions DESC
             LIMIT $limit",
            [$tenantId, $userId, $tenantId, $userId]
        )->fetchAll();
    }

    /**
     * Get category breakdown (based on transaction descriptions)
     * Note: This is a simple keyword-based categorization
     *
     * @param int $userId
     * @return array
     */
    public static function getCategoryBreakdown($userId)
    {
        $tenantId = TenantContext::getId();

        // Get all transaction descriptions for this user
        $transactions = Database::query(
            "SELECT description, amount,
                    CASE WHEN sender_id = ? THEN 'sent' ELSE 'received' END as type
             FROM transactions
             WHERE tenant_id = ? AND (sender_id = ? OR receiver_id = ?)",
            [$userId, $tenantId, $userId, $userId]
        )->fetchAll();

        // Simple keyword-based categorization
        $categories = [
            'gardening' => ['garden', 'plant', 'lawn', 'yard', 'weed'],
            'technology' => ['computer', 'tech', 'phone', 'website', 'it support'],
            'transport' => ['ride', 'drive', 'transport', 'car', 'lift'],
            'cooking' => ['cook', 'meal', 'food', 'baking', 'kitchen'],
            'tutoring' => ['tutor', 'teach', 'lesson', 'homework', 'study'],
            'childcare' => ['child', 'babysit', 'kids', 'nanny'],
            'petcare' => ['pet', 'dog', 'cat', 'walk', 'animal'],
            'repairs' => ['repair', 'fix', 'maintenance', 'plumbing', 'electrical'],
            'cleaning' => ['clean', 'tidy', 'housework', 'laundry'],
            'volunteering' => ['volunteer', 'hours', 'event', 'organization'],
        ];

        $breakdown = [];
        foreach ($categories as $category => $keywords) {
            $breakdown[$category] = ['sent' => 0, 'received' => 0, 'count' => 0];
        }
        $breakdown['other'] = ['sent' => 0, 'received' => 0, 'count' => 0];

        foreach ($transactions as $txn) {
            $desc = strtolower($txn['description'] ?? '');
            $matched = false;

            foreach ($categories as $category => $keywords) {
                foreach ($keywords as $keyword) {
                    if (strpos($desc, $keyword) !== false) {
                        $breakdown[$category][$txn['type']] += $txn['amount'];
                        $breakdown[$category]['count']++;
                        $matched = true;
                        break 2;
                    }
                }
            }

            if (!$matched) {
                $breakdown['other'][$txn['type']] += $txn['amount'];
                $breakdown['other']['count']++;
            }
        }

        // Filter out empty categories and sort by count
        $breakdown = array_filter($breakdown, function ($cat) {
            return $cat['count'] > 0;
        });

        uasort($breakdown, function ($a, $b) {
            return $b['count'] - $a['count'];
        });

        return $breakdown;
    }

    /**
     * Get recent activity with comparison to previous period
     *
     * @param int $userId
     * @param int $days Days to compare
     * @return array
     */
    public static function getActivityComparison($userId, $days = 30)
    {
        $tenantId = TenantContext::getId();
        $days = (int) $days;

        // Current period
        $current = Database::query(
            "SELECT
                SUM(CASE WHEN receiver_id = ? THEN amount ELSE 0 END) as earned,
                SUM(CASE WHEN sender_id = ? THEN amount ELSE 0 END) as spent,
                COUNT(*) as transactions
             FROM transactions
             WHERE tenant_id = ?
             AND (sender_id = ? OR receiver_id = ?)
             AND created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)",
            [$userId, $userId, $tenantId, $userId, $userId]
        )->fetch();

        // Previous period
        $previous = Database::query(
            "SELECT
                SUM(CASE WHEN receiver_id = ? THEN amount ELSE 0 END) as earned,
                SUM(CASE WHEN sender_id = ? THEN amount ELSE 0 END) as spent,
                COUNT(*) as transactions
             FROM transactions
             WHERE tenant_id = ?
             AND (sender_id = ? OR receiver_id = ?)
             AND created_at >= DATE_SUB(NOW(), INTERVAL " . ($days * 2) . " DAY)
             AND created_at < DATE_SUB(NOW(), INTERVAL $days DAY)",
            [$userId, $userId, $tenantId, $userId, $userId]
        )->fetch();

        $currentEarned = (float) ($current['earned'] ?? 0);
        $currentSpent = (float) ($current['spent'] ?? 0);
        $currentTxns = (int) ($current['transactions'] ?? 0);

        $prevEarned = (float) ($previous['earned'] ?? 0);
        $prevSpent = (float) ($previous['spent'] ?? 0);
        $prevTxns = (int) ($previous['transactions'] ?? 0);

        return [
            'period_days' => $days,
            'current' => [
                'earned' => $currentEarned,
                'spent' => $currentSpent,
                'transactions' => $currentTxns,
            ],
            'previous' => [
                'earned' => $prevEarned,
                'spent' => $prevSpent,
                'transactions' => $prevTxns,
            ],
            'change' => [
                'earned' => $prevEarned > 0 ? round((($currentEarned - $prevEarned) / $prevEarned) * 100, 1) : ($currentEarned > 0 ? 100 : 0),
                'spent' => $prevSpent > 0 ? round((($currentSpent - $prevSpent) / $prevSpent) * 100, 1) : ($currentSpent > 0 ? 100 : 0),
                'transactions' => $prevTxns > 0 ? round((($currentTxns - $prevTxns) / $prevTxns) * 100, 1) : ($currentTxns > 0 ? 100 : 0),
            ],
        ];
    }

    /**
     * Get streak information (consecutive days of activity)
     *
     * @param int $userId
     * @return array
     */
    public static function getStreak($userId)
    {
        $tenantId = TenantContext::getId();

        // Get dates with transactions (ordered desc)
        $activityDates = Database::query(
            "SELECT DISTINCT DATE(created_at) as activity_date
             FROM transactions
             WHERE tenant_id = ? AND (sender_id = ? OR receiver_id = ?)
             ORDER BY activity_date DESC",
            [$tenantId, $userId, $userId]
        )->fetchAll(\PDO::FETCH_COLUMN);

        if (empty($activityDates)) {
            return ['current_streak' => 0, 'longest_streak' => 0, 'last_active' => null];
        }

        $currentStreak = 0;
        $longestStreak = 0;
        $streak = 0;
        $lastDate = null;

        foreach ($activityDates as $dateStr) {
            $date = new \DateTime($dateStr);

            if ($lastDate === null) {
                $streak = 1;
                // Check if this is today or yesterday for current streak
                $today = new \DateTime('today');
                $yesterday = new \DateTime('yesterday');
                if ($date == $today || $date == $yesterday) {
                    $currentStreak = 1;
                }
            } else {
                $diff = $lastDate->diff($date)->days;
                if ($diff == 1) {
                    $streak++;
                    if ($currentStreak > 0) {
                        $currentStreak++;
                    }
                } else {
                    $longestStreak = max($longestStreak, $streak);
                    $streak = 1;
                    $currentStreak = 0;
                }
            }

            $lastDate = $date;
        }

        $longestStreak = max($longestStreak, $streak);

        return [
            'current_streak' => $currentStreak,
            'longest_streak' => $longestStreak,
            'last_active' => $activityDates[0] ?? null,
        ];
    }
}
