<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\AdminAnalyticsService;
use Nexus\Services\AchievementAnalyticsService;
use Nexus\Services\SmartMatchingAnalyticsService;

/**
 * AdminCommunityAnalyticsApiController - V2 API for Community Analytics Dashboard
 *
 * Aggregates data from multiple analytics services to power the React
 * Community Analytics page with charts, trends, and top-performer tables.
 *
 * Endpoints:
 * - GET  /api/v2/admin/community-analytics             - Full analytics payload
 * - GET  /api/v2/admin/community-analytics/export       - CSV export of monthly trends
 * - GET  /api/v2/admin/community-analytics/geography    - Member geographic distribution
 */
class AdminCommunityAnalyticsApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/admin/community-analytics
     *
     * Aggregates overview stats, monthly/weekly trends, top earners/spenders,
     * gamification stats, matching stats, category demand, and engagement rate.
     */
    public function index(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        // Core analytics from AdminAnalyticsService
        $overview = AdminAnalyticsService::getOverallStats();
        $monthlyTrends = AdminAnalyticsService::getMonthlyTrends(12);
        $weeklyTrends = AdminAnalyticsService::getWeeklyTrends(12);
        $topEarners = AdminAnalyticsService::getTopEarners(30, 10);
        $topSpenders = AdminAnalyticsService::getTopSpenders(30, 10);

        // Enrich monthly trends with new_users count per month
        $monthlyTrends = $this->enrichMonthlyTrendsWithNewUsers($monthlyTrends, $tenantId);

        // Format top earners/spenders for frontend consumption
        $topEarners = array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                'total' => round((float) ($row['total_earned'] ?? 0), 1),
            ];
        }, $topEarners);

        $topSpenders = array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                'total' => round((float) ($row['total_spent'] ?? 0), 1),
            ];
        }, $topSpenders);

        // Gamification stats (may not be available for all tenants)
        $gamification = null;
        try {
            if (TenantContext::hasFeature('gamification')) {
                $gamStats = AchievementAnalyticsService::getOverallStats();
                $gamification = [
                    'total_xp' => $gamStats['total_xp'] ?? 0,
                    'total_badges' => $gamStats['total_badges'] ?? 0,
                    'engagement_rate' => $gamStats['engagement_rate'] ?? 0,
                ];
            }
        } catch (\Throwable $e) {
            // Gamification tables may not exist
        }

        // Matching stats (may not be available for all tenants)
        $matching = null;
        try {
            $matchStats = SmartMatchingAnalyticsService::getOverallStats();
            $conversionFunnel = SmartMatchingAnalyticsService::getConversionFunnel();
            $matching = [
                'total_matches' => ($matchStats['total_matches_month'] ?? 0),
                'conversion_rate' => ($conversionFunnel['conversion_rate'] ?? 0),
            ];
        } catch (\Throwable $e) {
            // Matching tables may not exist
        }

        // Category demand — listing distribution by category
        $categoryDemand = [];
        try {
            $categoryDemand = Database::query(
                "SELECT c.name, c.id, COUNT(l.id) as listing_count,
                        SUM(CASE WHEN l.status = 'active' THEN 1 ELSE 0 END) as active_count
                 FROM categories c
                 LEFT JOIN listings l ON l.category_id = c.id AND l.tenant_id = ?
                 WHERE c.tenant_id = ?
                 GROUP BY c.id, c.name
                 ORDER BY listing_count DESC
                 LIMIT 20",
                [$tenantId, $tenantId]
            )->fetchAll();

            $categoryDemand = array_map(function ($row) {
                return [
                    'name' => $row['name'] ?? 'Unknown',
                    'listing_count' => (int) ($row['listing_count'] ?? 0),
                    'active_count' => (int) ($row['active_count'] ?? 0),
                ];
            }, $categoryDemand);
        } catch (\Throwable $e) {
            // Categories/listings tables issue
        }

        // Engagement rate: active traders (30d) / total approved users
        $engagementRate = 0;
        try {
            $totalApproved = (int) Database::query(
                "SELECT COUNT(*) FROM users WHERE tenant_id = ? AND is_approved = 1",
                [$tenantId]
            )->fetchColumn();

            if ($totalApproved > 0) {
                $engagementRate = round(($overview['active_traders_30d'] / $totalApproved), 4);
            }
        } catch (\Throwable $e) {
            // Fallback
        }

        // Format monthly trends for frontend
        $formattedMonthly = array_map(function ($row) {
            return [
                'month' => $row['month'] ?? '',
                'transaction_count' => (int) ($row['transaction_count'] ?? 0),
                'total_volume' => round((float) ($row['total_volume'] ?? 0), 1),
                'new_users' => (int) ($row['new_users'] ?? 0),
            ];
        }, $monthlyTrends);

        // Format weekly trends for frontend
        $formattedWeekly = array_map(function ($row) {
            return [
                'week' => $row['week_start'] ?? $row['week'] ?? '',
                'transaction_count' => (int) ($row['transaction_count'] ?? 0),
                'total_volume' => round((float) ($row['total_volume'] ?? 0), 1),
            ];
        }, $weeklyTrends);

        $this->respondWithData([
            'overview' => $overview,
            'monthly_trends' => $formattedMonthly,
            'weekly_trends' => $formattedWeekly,
            'top_earners' => $topEarners,
            'top_spenders' => $topSpenders,
            'gamification' => $gamification,
            'matching' => $matching,
            'category_demand' => $categoryDemand,
            'engagement_rate' => $engagementRate,
        ]);
    }

    /**
     * GET /api/v2/admin/community-analytics/export
     *
     * Exports monthly trends as a CSV file.
     * Columns: Month, New Users, Active Traders, Transactions, Hours Exchanged
     */
    public function export(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $monthlyTrends = AdminAnalyticsService::getMonthlyTrends(12);
        $monthlyTrends = $this->enrichMonthlyTrendsWithNewUsers($monthlyTrends, $tenantId);

        // Build active traders per month
        $activeTradersByMonth = $this->getActiveTradersByMonth($tenantId, 12);

        // CSV headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=community-analytics.csv');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Month', 'New Users', 'Active Traders', 'Transactions', 'Hours Exchanged']);

        foreach ($monthlyTrends as $row) {
            $month = $row['month'] ?? '';
            fputcsv($output, [
                $month,
                (int) ($row['new_users'] ?? 0),
                (int) ($activeTradersByMonth[$month] ?? 0),
                (int) ($row['transaction_count'] ?? 0),
                round((float) ($row['total_volume'] ?? 0), 1),
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * GET /api/v2/admin/community-analytics/geography
     *
     * Returns member geographic distribution: clustered locations, coverage stats,
     * and top areas. Used by the Community Analytics dashboard map section.
     */
    public function geography(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        // Get member locations grouped by rounded coordinates (2 decimal places ~ 1km clusters)
        $clusters = [];
        try {
            $stmt = Database::query(
                "SELECT
                    ROUND(latitude, 2) as lat,
                    ROUND(longitude, 2) as lng,
                    COUNT(*) as count,
                    MIN(location) as area
                FROM users
                WHERE tenant_id = ?
                    AND latitude IS NOT NULL
                    AND longitude IS NOT NULL
                    AND status = 'active'
                GROUP BY ROUND(latitude, 2), ROUND(longitude, 2)
                ORDER BY count DESC",
                [$tenantId]
            );
            $clusters = $stmt->fetchAll();
        } catch (\Throwable $e) {
            // latitude/longitude columns may not exist
        }

        // Total stats
        $total = 0;
        $withLocation = 0;
        try {
            $statsStmt = Database::query(
                "SELECT
                    COUNT(CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL THEN 1 END) as with_location,
                    COUNT(*) as total
                FROM users
                WHERE tenant_id = ? AND status = 'active'",
                [$tenantId]
            );
            $stats = $statsStmt->fetch();
            $total = (int) ($stats['total'] ?? 0);
            $withLocation = (int) ($stats['with_location'] ?? 0);
        } catch (\Throwable $e) {
            // Fallback
        }

        // Top areas (by location text, grouped)
        $topAreas = [];
        try {
            $areasStmt = Database::query(
                "SELECT location as area, COUNT(*) as count
                FROM users
                WHERE tenant_id = ? AND location IS NOT NULL AND location != '' AND status = 'active'
                GROUP BY location
                ORDER BY count DESC
                LIMIT 10",
                [$tenantId]
            );
            $topAreas = $areasStmt->fetchAll();
        } catch (\Throwable $e) {
            // location column may not exist
        }

        $this->respondWithData([
            'member_locations' => array_map(fn($c) => [
                'lat' => (float) $c['lat'],
                'lng' => (float) $c['lng'],
                'count' => (int) $c['count'],
                'area' => $c['area'] ?? 'Unknown',
            ], $clusters),
            'total_with_location' => $withLocation,
            'total_members' => $total,
            'coverage_percentage' => $total > 0 ? round(($withLocation / $total) * 100, 1) : 0,
            'top_areas' => array_map(fn($a) => [
                'area' => $a['area'],
                'count' => (int) $a['count'],
                'percentage' => $total > 0 ? round(((int) $a['count'] / $total) * 100, 1) : 0,
            ], $topAreas),
        ]);
    }

    /**
     * Enrich monthly trends data with new user registration counts.
     *
     * AdminAnalyticsService::getMonthlyTrends() returns transaction data but
     * does not include new user counts. This method queries user registrations
     * per month and merges them into the trends array.
     *
     * @param array $monthlyTrends Existing monthly trends from AdminAnalyticsService
     * @param int $tenantId Current tenant ID
     * @return array Enriched monthly trends with 'new_users' key
     */
    private function enrichMonthlyTrendsWithNewUsers(array $monthlyTrends, int $tenantId): array
    {
        try {
            $usersByMonth = Database::query(
                "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as new_users
                 FROM users
                 WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                 GROUP BY month
                 ORDER BY month ASC",
                [$tenantId]
            )->fetchAll();

            $userMap = [];
            foreach ($usersByMonth as $row) {
                $userMap[$row['month']] = (int) $row['new_users'];
            }

            // If monthlyTrends is empty (no transactions), build from user data
            if (empty($monthlyTrends)) {
                $result = [];
                for ($i = 11; $i >= 0; $i--) {
                    $month = date('Y-m', strtotime("-{$i} months"));
                    $result[] = [
                        'month' => $month,
                        'transaction_count' => 0,
                        'total_volume' => 0,
                        'new_users' => $userMap[$month] ?? 0,
                    ];
                }
                return $result;
            }

            foreach ($monthlyTrends as &$row) {
                $row['new_users'] = $userMap[$row['month']] ?? 0;
            }
        } catch (\Throwable $e) {
            // If user query fails, leave new_users as 0
            foreach ($monthlyTrends as &$row) {
                $row['new_users'] = $row['new_users'] ?? 0;
            }
        }

        return $monthlyTrends;
    }

    /**
     * Get active traders count per month for the CSV export.
     *
     * @param int $tenantId Current tenant ID
     * @param int $months Number of months to look back
     * @return array Associative array of month => active trader count
     */
    private function getActiveTradersByMonth(int $tenantId, int $months): array
    {
        $result = [];
        try {
            $rows = Database::query(
                "SELECT month, COUNT(DISTINCT user_id) as active_traders FROM (
                    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, sender_id as user_id
                    FROM transactions
                    WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                    UNION ALL
                    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, receiver_id as user_id
                    FROM transactions
                    WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                ) as traders
                GROUP BY month
                ORDER BY month ASC",
                [$tenantId, $months, $tenantId, $months]
            )->fetchAll();

            foreach ($rows as $row) {
                $result[$row['month']] = (int) $row['active_traders'];
            }
        } catch (\Throwable $e) {
            // transactions table may not exist
        }

        return $result;
    }
}
