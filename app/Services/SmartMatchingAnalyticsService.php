<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SmartMatchingAnalyticsService — Analytics and reporting for the smart matching engine.
 *
 * Provides dashboard summaries, score/distance distributions, conversion funnels,
 * and overall stats by querying the match_cache and match_approvals tables.
 */
class SmartMatchingAnalyticsService
{
    public function __construct()
    {
    }

    /**
     * Get a dashboard summary of matching metrics.
     */
    public function getDashboardSummary(): array
    {
        $tenantId = TenantContext::getId();

        try {
            $stats = $this->getOverallStats();
            $funnel = $this->getConversionFunnel();

            return [
                'overview' => $stats,
                'conversion' => $funnel,
                'period' => 'last_30_days',
            ];
        } catch (\Exception $e) {
            Log::error('[MatchingAnalytics] getDashboardSummary failed', ['error' => $e->getMessage()]);
            return [
                'overview' => [],
                'conversion' => [],
                'period' => 'last_30_days',
            ];
        }
    }

    /**
     * Get overall matching statistics.
     */
    public function getOverallStats(): array
    {
        $tenantId = TenantContext::getId();

        try {
            // Total matches in cache
            $totalCached = (int) (DB::selectOne(
                "SELECT COUNT(*) as cnt FROM match_cache WHERE tenant_id = ?",
                [$tenantId]
            )->cnt ?? 0);

            // Matches generated this month
            $totalThisMonth = (int) (DB::selectOne(
                "SELECT COUNT(*) as cnt FROM match_cache
                 WHERE tenant_id = ? AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')",
                [$tenantId]
            )->cnt ?? 0);

            // Average match score
            $avgScore = (float) (DB::selectOne(
                "SELECT AVG(match_score) as avg_score FROM match_cache WHERE tenant_id = ?",
                [$tenantId]
            )->avg_score ?? 0);

            // Average distance
            $avgDistance = (float) (DB::selectOne(
                "SELECT AVG(distance_km) as avg_dist FROM match_cache WHERE tenant_id = ? AND distance_km IS NOT NULL",
                [$tenantId]
            )->avg_dist ?? 0);

            // Match type breakdown
            $typeBreakdown = DB::select(
                "SELECT match_type, COUNT(*) as cnt FROM match_cache WHERE tenant_id = ? GROUP BY match_type",
                [$tenantId]
            );
            $types = [];
            foreach ($typeBreakdown as $row) {
                $types[$row->match_type] = (int) $row->cnt;
            }

            // Active users with matches
            $activeUsers = (int) (DB::selectOne(
                "SELECT COUNT(DISTINCT user_id) as cnt FROM match_cache WHERE tenant_id = ?",
                [$tenantId]
            )->cnt ?? 0);

            // Hot matches (score >= 80)
            $hotMatches = (int) (DB::selectOne(
                "SELECT COUNT(*) as cnt FROM match_cache WHERE tenant_id = ? AND match_score >= 80",
                [$tenantId]
            )->cnt ?? 0);

            return [
                'total_cached_matches' => $totalCached,
                'total_matches_month' => $totalThisMonth,
                'average_score' => round($avgScore, 1),
                'average_distance_km' => round($avgDistance, 1),
                'match_type_breakdown' => $types,
                'active_users_with_matches' => $activeUsers,
                'hot_matches' => $hotMatches,
            ];
        } catch (\Exception $e) {
            Log::error('[MatchingAnalytics] getOverallStats failed', ['error' => $e->getMessage()]);
            return [
                'total_cached_matches' => 0,
                'total_matches_month' => 0,
                'average_score' => 0,
                'average_distance_km' => 0,
                'match_type_breakdown' => [],
                'active_users_with_matches' => 0,
                'hot_matches' => 0,
            ];
        }
    }

    /**
     * Get score distribution (bucketed into ranges).
     */
    public function getScoreDistribution(): array
    {
        $tenantId = TenantContext::getId();

        try {
            $buckets = [
                ['label' => '0-20', 'min' => 0, 'max' => 20],
                ['label' => '21-40', 'min' => 21, 'max' => 40],
                ['label' => '41-60', 'min' => 41, 'max' => 60],
                ['label' => '61-80', 'min' => 61, 'max' => 80],
                ['label' => '81-100', 'min' => 81, 'max' => 100],
            ];

            $distribution = [];
            foreach ($buckets as $bucket) {
                $count = (int) (DB::selectOne(
                    "SELECT COUNT(*) as cnt FROM match_cache
                     WHERE tenant_id = ? AND match_score >= ? AND match_score <= ?",
                    [$tenantId, $bucket['min'], $bucket['max']]
                )->cnt ?? 0);

                $distribution[] = [
                    'range' => $bucket['label'],
                    'count' => $count,
                ];
            }

            return $distribution;
        } catch (\Exception $e) {
            Log::error('[MatchingAnalytics] getScoreDistribution failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get distance distribution (bucketed into ranges).
     */
    public function getDistanceDistribution(): array
    {
        $tenantId = TenantContext::getId();

        try {
            $buckets = [
                ['label' => '0-5km', 'min' => 0, 'max' => 5],
                ['label' => '5-15km', 'min' => 5, 'max' => 15],
                ['label' => '15-30km', 'min' => 15, 'max' => 30],
                ['label' => '30-50km', 'min' => 30, 'max' => 50],
                ['label' => '50+km', 'min' => 50, 'max' => 99999],
            ];

            $distribution = [];
            foreach ($buckets as $bucket) {
                $count = (int) (DB::selectOne(
                    "SELECT COUNT(*) as cnt FROM match_cache
                     WHERE tenant_id = ? AND distance_km IS NOT NULL AND distance_km >= ? AND distance_km < ?",
                    [$tenantId, $bucket['min'], $bucket['max']]
                )->cnt ?? 0);

                $distribution[] = [
                    'range' => $bucket['label'],
                    'count' => $count,
                ];
            }

            // Count nulls (no distance data)
            $nullCount = (int) (DB::selectOne(
                "SELECT COUNT(*) as cnt FROM match_cache WHERE tenant_id = ? AND distance_km IS NULL",
                [$tenantId]
            )->cnt ?? 0);

            if ($nullCount > 0) {
                $distribution[] = [
                    'range' => 'Unknown',
                    'count' => $nullCount,
                ];
            }

            return $distribution;
        } catch (\Exception $e) {
            Log::error('[MatchingAnalytics] getDistanceDistribution failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get the match conversion funnel (generated -> viewed -> contacted -> completed).
     */
    public function getConversionFunnel(): array
    {
        $tenantId = TenantContext::getId();

        try {
            $total = (int) (DB::selectOne(
                "SELECT COUNT(*) as cnt FROM match_cache WHERE tenant_id = ?",
                [$tenantId]
            )->cnt ?? 0);

            $viewed = (int) (DB::selectOne(
                "SELECT COUNT(*) as cnt FROM match_cache WHERE tenant_id = ? AND status IN ('viewed', 'contacted', 'saved')",
                [$tenantId]
            )->cnt ?? 0);

            $contacted = (int) (DB::selectOne(
                "SELECT COUNT(*) as cnt FROM match_cache WHERE tenant_id = ? AND status = 'contacted'",
                [$tenantId]
            )->cnt ?? 0);

            $saved = (int) (DB::selectOne(
                "SELECT COUNT(*) as cnt FROM match_cache WHERE tenant_id = ? AND status = 'saved'",
                [$tenantId]
            )->cnt ?? 0);

            $dismissed = (int) (DB::selectOne(
                "SELECT COUNT(*) as cnt FROM match_cache WHERE tenant_id = ? AND status = 'dismissed'",
                [$tenantId]
            )->cnt ?? 0);

            $conversionRate = $total > 0 ? round(($contacted / $total) * 100, 1) : 0;

            return [
                'total_generated' => $total,
                'viewed' => $viewed,
                'contacted' => $contacted,
                'saved' => $saved,
                'dismissed' => $dismissed,
                'conversion_rate' => $conversionRate,
            ];
        } catch (\Exception $e) {
            Log::error('[MatchingAnalytics] getConversionFunnel failed', ['error' => $e->getMessage()]);
            return [
                'total_generated' => 0,
                'viewed' => 0,
                'contacted' => 0,
                'saved' => 0,
                'dismissed' => 0,
                'conversion_rate' => 0,
            ];
        }
    }
}
