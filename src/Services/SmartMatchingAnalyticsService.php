<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * SmartMatchingAnalyticsService - Analytics for the Smart Matching Engine
 *
 * Provides comprehensive analytics and reporting for administrators:
 * - Match generation statistics
 * - Conversion tracking (match → transaction)
 * - Score distribution analysis
 * - Category performance
 * - Geographic analysis
 */
class SmartMatchingAnalyticsService
{
    /**
     * Get dashboard summary with all key metrics
     */
    public static function getDashboardSummary(): array
    {
        return [
            'stats' => self::getOverallStats(),
            'score_distribution' => self::getScoreDistribution(),
            'conversion_funnel' => self::getConversionFunnel(),
            'top_categories' => self::getTopCategories(),
            'recent_activity' => self::getRecentActivity(),
            'geocoding_status' => GeocodingService::getStats(),
        ];
    }

    /**
     * Get overall matching statistics
     */
    public static function getOverallStats(): array
    {
        $tenantId = TenantContext::getId();

        $stats = [
            'total_matches_today' => 0,
            'total_matches_week' => 0,
            'total_matches_month' => 0,
            'hot_matches_count' => 0,
            'mutual_matches_count' => 0,
            'avg_match_score' => 0,
            'avg_distance_km' => 0,
            'cache_entries' => 0,
            'cache_hit_rate' => 0,
            'active_users_matching' => 0,
        ];

        try {
            // Today's matches (from cache)
            $stats['total_matches_today'] = (int) Database::query(
                "SELECT COUNT(*) FROM match_cache WHERE tenant_id = ? AND DATE(created_at) = CURDATE()",
                [$tenantId]
            )->fetchColumn();

            // This week's matches
            $stats['total_matches_week'] = (int) Database::query(
                "SELECT COUNT(*) FROM match_cache WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                [$tenantId]
            )->fetchColumn();

            // This month's matches
            $stats['total_matches_month'] = (int) Database::query(
                "SELECT COUNT(*) FROM match_cache WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                [$tenantId]
            )->fetchColumn();

            // Hot matches (score >= 85)
            $stats['hot_matches_count'] = (int) Database::query(
                "SELECT COUNT(*) FROM match_cache WHERE tenant_id = ? AND match_score >= 85",
                [$tenantId]
            )->fetchColumn();

            // Mutual matches
            $stats['mutual_matches_count'] = (int) Database::query(
                "SELECT COUNT(*) FROM match_cache WHERE tenant_id = ? AND match_type = 'mutual'",
                [$tenantId]
            )->fetchColumn();

            // Average match score
            $avgScore = Database::query(
                "SELECT AVG(match_score) FROM match_cache WHERE tenant_id = ?",
                [$tenantId]
            )->fetchColumn();
            $stats['avg_match_score'] = $avgScore ? round((float)$avgScore, 1) : 0;

            // Average distance
            $avgDist = Database::query(
                "SELECT AVG(distance_km) FROM match_cache WHERE tenant_id = ? AND distance_km IS NOT NULL",
                [$tenantId]
            )->fetchColumn();
            $stats['avg_distance_km'] = $avgDist ? round((float)$avgDist, 1) : 0;

            // Cache entries
            $stats['cache_entries'] = (int) Database::query(
                "SELECT COUNT(*) FROM match_cache WHERE tenant_id = ?",
                [$tenantId]
            )->fetchColumn();

            // Active users using matching
            $stats['active_users_matching'] = (int) Database::query(
                "SELECT COUNT(DISTINCT user_id) FROM match_cache WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                [$tenantId]
            )->fetchColumn();

        } catch (\Exception $e) {
            // Tables might not exist yet
        }

        return $stats;
    }

    /**
     * Get score distribution for histogram
     */
    public static function getScoreDistribution(): array
    {
        $tenantId = TenantContext::getId();

        $distribution = [
            '0-40' => 0,
            '40-60' => 0,
            '60-80' => 0,
            '80-100' => 0,
        ];

        try {
            $results = Database::query(
                "SELECT
                    SUM(CASE WHEN match_score < 40 THEN 1 ELSE 0 END) as low,
                    SUM(CASE WHEN match_score >= 40 AND match_score < 60 THEN 1 ELSE 0 END) as medium,
                    SUM(CASE WHEN match_score >= 60 AND match_score < 80 THEN 1 ELSE 0 END) as good,
                    SUM(CASE WHEN match_score >= 80 THEN 1 ELSE 0 END) as hot
                 FROM match_cache WHERE tenant_id = ?",
                [$tenantId]
            )->fetch();

            if ($results) {
                $distribution['0-40'] = (int) $results['low'];
                $distribution['40-60'] = (int) $results['medium'];
                $distribution['60-80'] = (int) $results['good'];
                $distribution['80-100'] = (int) $results['hot'];
            }
        } catch (\Exception $e) {}

        return $distribution;
    }

    /**
     * Get distance distribution
     */
    public static function getDistanceDistribution(): array
    {
        $tenantId = TenantContext::getId();

        $distribution = [
            'walking' => 0,    // 0-5km
            'local' => 0,      // 5-15km
            'city' => 0,       // 15-30km
            'regional' => 0,   // 30-50km
            'distant' => 0,    // 50+km
        ];

        try {
            $results = Database::query(
                "SELECT
                    SUM(CASE WHEN distance_km <= 5 THEN 1 ELSE 0 END) as walking,
                    SUM(CASE WHEN distance_km > 5 AND distance_km <= 15 THEN 1 ELSE 0 END) as local,
                    SUM(CASE WHEN distance_km > 15 AND distance_km <= 30 THEN 1 ELSE 0 END) as city,
                    SUM(CASE WHEN distance_km > 30 AND distance_km <= 50 THEN 1 ELSE 0 END) as regional,
                    SUM(CASE WHEN distance_km > 50 THEN 1 ELSE 0 END) as distant
                 FROM match_cache WHERE tenant_id = ? AND distance_km IS NOT NULL",
                [$tenantId]
            )->fetch();

            if ($results) {
                $distribution['walking'] = (int) $results['walking'];
                $distribution['local'] = (int) $results['local'];
                $distribution['city'] = (int) $results['city'];
                $distribution['regional'] = (int) $results['regional'];
                $distribution['distant'] = (int) $results['distant'];
            }
        } catch (\Exception $e) {}

        return $distribution;
    }

    /**
     * Get conversion funnel data
     */
    public static function getConversionFunnel(): array
    {
        $tenantId = TenantContext::getId();

        $funnel = [
            'matched' => 0,
            'viewed' => 0,
            'contacted' => 0,
            'completed' => 0,
            'conversion_rate' => 0,
        ];

        try {
            // Total matches generated
            $funnel['matched'] = (int) Database::query(
                "SELECT COUNT(*) FROM match_history WHERE tenant_id = ?",
                [$tenantId]
            )->fetchColumn();

            // Viewed matches
            $funnel['viewed'] = (int) Database::query(
                "SELECT COUNT(*) FROM match_history WHERE tenant_id = ? AND action = 'viewed'",
                [$tenantId]
            )->fetchColumn();

            // Contacted (user reached out)
            $funnel['contacted'] = (int) Database::query(
                "SELECT COUNT(*) FROM match_history WHERE tenant_id = ? AND action = 'contacted'",
                [$tenantId]
            )->fetchColumn();

            // Completed (resulted in transaction)
            $funnel['completed'] = (int) Database::query(
                "SELECT COUNT(*) FROM match_history WHERE tenant_id = ? AND resulted_in_transaction = 1",
                [$tenantId]
            )->fetchColumn();

            // Calculate conversion rate
            if ($funnel['matched'] > 0) {
                $funnel['conversion_rate'] = round(($funnel['completed'] / $funnel['matched']) * 100, 2);
            }

        } catch (\Exception $e) {}

        return $funnel;
    }

    /**
     * Get top performing categories
     */
    public static function getTopCategories(int $limit = 10): array
    {
        $tenantId = TenantContext::getId();

        try {
            // Cast limit to int for SQL - safe since it's type-hinted
            $limitInt = (int) $limit;
            $sql = "SELECT
                    c.id, c.name, c.color,
                    COUNT(mc.id) as match_count,
                    AVG(mc.match_score) as avg_score,
                    SUM(CASE WHEN mh.resulted_in_transaction = 1 THEN 1 ELSE 0 END) as conversions
                 FROM categories c
                 LEFT JOIN listings l ON l.category_id = c.id AND l.tenant_id = ?
                 LEFT JOIN match_cache mc ON mc.listing_id = l.id
                 LEFT JOIN match_history mh ON mh.listing_id = l.id AND mh.tenant_id = ?
                 WHERE c.tenant_id = ?
                 GROUP BY c.id, c.name, c.color
                 HAVING match_count > 0
                 ORDER BY match_count DESC
                 LIMIT " . $limitInt;
            return Database::query($sql, [$tenantId, $tenantId, $tenantId])->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get recent matching activity
     */
    public static function getRecentActivity(int $limit = 20): array
    {
        $tenantId = TenantContext::getId();

        try {
            // Cast limit to int for SQL - safe since it's type-hinted
            $limitInt = (int) $limit;
            $sql = "SELECT
                    mh.id, mh.action, mh.match_score, mh.distance_km, mh.created_at,
                    mh.resulted_in_transaction,
                    u.first_name, u.last_name, u.avatar_url,
                    l.title as listing_title
                 FROM match_history mh
                 JOIN users u ON mh.user_id = u.id
                 LEFT JOIN listings l ON mh.listing_id = l.id
                 WHERE mh.tenant_id = ?
                 ORDER BY mh.created_at DESC
                 LIMIT " . $limitInt;
            return Database::query($sql, [$tenantId])->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get weekly trends for the past 12 weeks
     */
    public static function getWeeklyTrends(int $weeks = 12): array
    {
        $tenantId = TenantContext::getId();

        try {
            return Database::query(
                "SELECT
                    YEARWEEK(created_at, 1) as week,
                    MIN(DATE(created_at)) as week_start,
                    COUNT(*) as match_count,
                    AVG(match_score) as avg_score,
                    SUM(CASE WHEN match_score >= 85 THEN 1 ELSE 0 END) as hot_count
                 FROM match_cache
                 WHERE tenant_id = ?
                 AND created_at >= DATE_SUB(NOW(), INTERVAL ? WEEK)
                 GROUP BY YEARWEEK(created_at, 1)
                 ORDER BY week ASC",
                [$tenantId, $weeks]
            )->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get daily trends for the past 30 days
     */
    public static function getDailyTrends(int $days = 30): array
    {
        $tenantId = TenantContext::getId();

        try {
            return Database::query(
                "SELECT
                    DATE(created_at) as date,
                    COUNT(*) as match_count,
                    AVG(match_score) as avg_score,
                    COUNT(DISTINCT user_id) as unique_users
                 FROM match_cache
                 WHERE tenant_id = ?
                 AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                 GROUP BY DATE(created_at)
                 ORDER BY date ASC",
                [$tenantId, $days]
            )->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get conversion metrics
     */
    public static function getConversionMetrics(): array
    {
        $tenantId = TenantContext::getId();

        $metrics = [
            'total_conversions' => 0,
            'conversion_rate' => 0,
            'avg_time_to_conversion_hours' => 0,
            'top_converting_score_range' => 'N/A',
            'avg_conversion_score' => 0,
            'avg_conversion_distance' => 0,
        ];

        try {
            // Total conversions
            $metrics['total_conversions'] = (int) Database::query(
                "SELECT COUNT(*) FROM match_history WHERE tenant_id = ? AND resulted_in_transaction = 1",
                [$tenantId]
            )->fetchColumn();

            // Total matches for rate calculation
            $totalMatches = (int) Database::query(
                "SELECT COUNT(DISTINCT CONCAT(user_id, '-', listing_id)) FROM match_history WHERE tenant_id = ?",
                [$tenantId]
            )->fetchColumn();

            if ($totalMatches > 0) {
                $metrics['conversion_rate'] = round(($metrics['total_conversions'] / $totalMatches) * 100, 2);
            }

            // Average score for conversions
            $avgConvScore = Database::query(
                "SELECT AVG(match_score) FROM match_history WHERE tenant_id = ? AND resulted_in_transaction = 1",
                [$tenantId]
            )->fetchColumn();
            $metrics['avg_conversion_score'] = $avgConvScore ? round((float)$avgConvScore, 1) : 0;

            // Average distance for conversions
            $avgConvDist = Database::query(
                "SELECT AVG(distance_km) FROM match_history WHERE tenant_id = ? AND resulted_in_transaction = 1 AND distance_km IS NOT NULL",
                [$tenantId]
            )->fetchColumn();
            $metrics['avg_conversion_distance'] = $avgConvDist ? round((float)$avgConvDist, 1) : 0;

            // Average time to conversion
            $avgTime = Database::query(
                "SELECT AVG(TIMESTAMPDIFF(HOUR, mh.created_at, mh.conversion_time))
                 FROM match_history mh
                 WHERE mh.tenant_id = ? AND mh.resulted_in_transaction = 1 AND mh.conversion_time IS NOT NULL",
                [$tenantId]
            )->fetchColumn();
            $metrics['avg_time_to_conversion_hours'] = $avgTime ? round((float)$avgTime, 1) : 0;

        } catch (\Exception $e) {}

        return $metrics;
    }

    /**
     * Get user engagement with matching
     */
    public static function getUserEngagement(): array
    {
        $tenantId = TenantContext::getId();

        $engagement = [
            'users_with_preferences' => 0,
            'users_with_hot_notifications' => 0,
            'users_with_mutual_notifications' => 0,
            'avg_max_distance' => 0,
            'avg_min_score' => 0,
        ];

        try {
            $engagement['users_with_preferences'] = (int) Database::query(
                "SELECT COUNT(*) FROM match_preferences WHERE tenant_id = ?",
                [$tenantId]
            )->fetchColumn();

            $engagement['users_with_hot_notifications'] = (int) Database::query(
                "SELECT COUNT(*) FROM match_preferences WHERE tenant_id = ? AND notify_hot_matches = 1",
                [$tenantId]
            )->fetchColumn();

            $engagement['users_with_mutual_notifications'] = (int) Database::query(
                "SELECT COUNT(*) FROM match_preferences WHERE tenant_id = ? AND notify_mutual_matches = 1",
                [$tenantId]
            )->fetchColumn();

            $avgDist = Database::query(
                "SELECT AVG(max_distance_km) FROM match_preferences WHERE tenant_id = ?",
                [$tenantId]
            )->fetchColumn();
            $engagement['avg_max_distance'] = $avgDist ? round((float)$avgDist, 1) : 25;

            $avgScore = Database::query(
                "SELECT AVG(min_match_score) FROM match_preferences WHERE tenant_id = ?",
                [$tenantId]
            )->fetchColumn();
            $engagement['avg_min_score'] = $avgScore ? round((float)$avgScore, 1) : 50;

        } catch (\Exception $e) {}

        return $engagement;
    }
}
