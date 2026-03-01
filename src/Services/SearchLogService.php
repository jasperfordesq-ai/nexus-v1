<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * SearchLogService - Search analytics and trending searches
 *
 * Logs every search query and provides:
 * - Trending/popular search terms
 * - Search analytics for admins
 * - Zero-result query tracking (to improve content)
 */
class SearchLogService
{
    /**
     * Log a search query.
     *
     * @param string $query Search query
     * @param string $searchType Search type (all, listings, users, events, groups)
     * @param int $resultCount Number of results returned
     * @param int|null $userId User who searched (null for anonymous)
     * @param array|null $filters Applied filters
     */
    public static function log(
        string $query,
        string $searchType = 'all',
        int $resultCount = 0,
        ?int $userId = null,
        ?array $filters = null
    ): void {
        $tenantId = TenantContext::getId();

        try {
            Database::query(
                "INSERT INTO search_logs (tenant_id, user_id, query, search_type, result_count, filters, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())",
                [
                    $tenantId,
                    $userId,
                    substr($query, 0, 500), // Truncate to column limit
                    $searchType,
                    $resultCount,
                    $filters ? json_encode($filters) : null,
                ]
            );
        } catch (\Exception $e) {
            // Non-critical — never block search for analytics failure
            error_log("[SearchLogService] log error: " . $e->getMessage());
        }
    }

    /**
     * Get trending search terms for the tenant.
     *
     * Aggregates the most popular search queries over a configurable period.
     *
     * @param int $days Number of days to look back
     * @param int $limit Max terms to return
     * @return array [['query' => string, 'search_count' => int, 'avg_results' => float], ...]
     */
    public static function getTrendingSearches(int $days = 7, int $limit = 20): array
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT
                LOWER(TRIM(query)) as query,
                COUNT(*) as search_count,
                ROUND(AVG(result_count), 1) as avg_results,
                MAX(created_at) as last_searched
             FROM search_logs
             WHERE tenant_id = ?
               AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY LOWER(TRIM(query))
             ORDER BY search_count DESC
             LIMIT ?",
            [$tenantId, $days, $limit]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get zero-result searches (queries that returned no results).
     *
     * Useful for admins to identify content gaps.
     *
     * @param int $days Number of days to look back
     * @param int $limit Max terms to return
     * @return array [['query' => string, 'search_count' => int], ...]
     */
    public static function getZeroResultSearches(int $days = 30, int $limit = 20): array
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT
                LOWER(TRIM(query)) as query,
                COUNT(*) as search_count,
                MAX(created_at) as last_searched
             FROM search_logs
             WHERE tenant_id = ?
               AND result_count = 0
               AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY LOWER(TRIM(query))
             ORDER BY search_count DESC
             LIMIT ?",
            [$tenantId, $days, $limit]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get search analytics summary for admins.
     *
     * @param int $days Number of days to look back
     * @return array Analytics summary
     */
    public static function getAnalyticsSummary(int $days = 30): array
    {
        $tenantId = TenantContext::getId();

        $summary = Database::query(
            "SELECT
                COUNT(*) as total_searches,
                COUNT(DISTINCT user_id) as unique_searchers,
                COUNT(DISTINCT LOWER(TRIM(query))) as unique_queries,
                ROUND(AVG(result_count), 1) as avg_results,
                SUM(CASE WHEN result_count = 0 THEN 1 ELSE 0 END) as zero_result_count
             FROM search_logs
             WHERE tenant_id = ?
               AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$tenantId, $days]
        )->fetch(\PDO::FETCH_ASSOC);

        // Searches per day
        $perDay = Database::query(
            "SELECT DATE(created_at) as date, COUNT(*) as count
             FROM search_logs
             WHERE tenant_id = ?
               AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DATE(created_at)
             ORDER BY date ASC",
            [$tenantId, $days]
        )->fetchAll(\PDO::FETCH_ASSOC);

        // Most popular search types
        $byType = Database::query(
            "SELECT search_type, COUNT(*) as count
             FROM search_logs
             WHERE tenant_id = ?
               AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY search_type
             ORDER BY count DESC",
            [$tenantId, $days]
        )->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'period_days' => $days,
            'total_searches' => (int)($summary['total_searches'] ?? 0),
            'unique_searchers' => (int)($summary['unique_searchers'] ?? 0),
            'unique_queries' => (int)($summary['unique_queries'] ?? 0),
            'avg_results' => (float)($summary['avg_results'] ?? 0),
            'zero_result_count' => (int)($summary['zero_result_count'] ?? 0),
            'zero_result_rate' => (int)($summary['total_searches'] ?? 0) > 0
                ? round(((int)($summary['zero_result_count'] ?? 0) / (int)$summary['total_searches']) * 100, 1)
                : 0,
            'searches_per_day' => $perDay,
            'searches_by_type' => $byType,
        ];
    }

    /**
     * Clean up old search logs (older than 90 days).
     *
     * @return int Number of records deleted
     */
    public static function cleanupOldLogs(): int
    {
        try {
            $result = Database::query(
                "DELETE FROM search_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
            );
            return $result->rowCount();
        } catch (\Exception $e) {
            error_log("[SearchLogService] Cleanup error: " . $e->getMessage());
            return 0;
        }
    }
}
