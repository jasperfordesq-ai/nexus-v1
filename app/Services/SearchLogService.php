<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\SearchLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Native Laravel implementation for search log analytics.
 *
 * All queries are automatically tenant-scoped via the SearchLog model's
 * HasTenantScope trait.
 */
class SearchLogService
{
    /**
     * Log a search query.
     */
    public function log(
        string $query,
        string $searchType = 'all',
        int $resultCount = 0,
        ?int $userId = null,
        ?array $filters = null,
    ): void {
        try {
            SearchLog::create([
                'query' => mb_substr($query, 0, 500),
                'search_type' => $searchType,
                'result_count' => $resultCount,
                'user_id' => $userId,
                'filters' => $filters,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to log search query', [
                'error' => $e->getMessage(),
                'query' => $query,
            ]);
        }
    }

    /**
     * Get trending search terms within the given time period.
     *
     * @return array<int, array{query: string, count: int}>
     */
    public function getTrendingSearches(int $days = 7, int $limit = 20): array
    {
        try {
            return SearchLog::query()
                ->select('query', DB::raw('COUNT(*) as count'))
                ->where('created_at', '>=', now()->subDays($days))
                ->where('result_count', '>', 0)
                ->groupBy('query')
                ->orderByDesc('count')
                ->limit($limit)
                ->get()
                ->map(fn ($row) => [
                    'query' => $row->query,
                    'count' => (int) $row->count,
                ])
                ->toArray();
        } catch (\Throwable $e) {
            Log::warning('Failed to get trending searches', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get searches that returned zero results.
     *
     * @return array<int, array{query: string, count: int, last_searched: string}>
     */
    public function getZeroResultSearches(int $days = 30, int $limit = 20): array
    {
        try {
            return SearchLog::query()
                ->select(
                    'query',
                    DB::raw('COUNT(*) as count'),
                    DB::raw('MAX(created_at) as last_searched'),
                )
                ->where('created_at', '>=', now()->subDays($days))
                ->where('result_count', '=', 0)
                ->groupBy('query')
                ->orderByDesc('count')
                ->limit($limit)
                ->get()
                ->map(fn ($row) => [
                    'query' => $row->query,
                    'count' => (int) $row->count,
                    'last_searched' => $row->last_searched,
                ])
                ->toArray();
        } catch (\Throwable $e) {
            Log::warning('Failed to get zero-result searches', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get a summary of search analytics for the admin dashboard.
     *
     * @return array{total_searches: int, unique_queries: int, zero_result_rate: float, avg_results: float, searches_by_type: array, daily_volume: array}
     */
    public function getAnalyticsSummary(int $days = 30): array
    {
        try {
            $since = now()->subDays($days);

            $base = SearchLog::query()->where('created_at', '>=', $since);

            $totalSearches = (clone $base)->count();
            $uniqueQueries = (clone $base)->distinct('query')->count('query');
            $zeroResultCount = (clone $base)->where('result_count', 0)->count();
            $avgResults = (clone $base)->avg('result_count') ?? 0;

            $searchesByType = (clone $base)
                ->select('search_type', DB::raw('COUNT(*) as count'))
                ->groupBy('search_type')
                ->orderByDesc('count')
                ->get()
                ->map(fn ($row) => [
                    'type' => $row->search_type,
                    'count' => (int) $row->count,
                ])
                ->toArray();

            $dailyVolume = (clone $base)
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('date')
                ->get()
                ->map(fn ($row) => [
                    'date' => $row->date,
                    'count' => (int) $row->count,
                ])
                ->toArray();

            return [
                'total_searches' => $totalSearches,
                'unique_queries' => $uniqueQueries,
                'zero_result_rate' => $totalSearches > 0
                    ? round($zeroResultCount / $totalSearches * 100, 1)
                    : 0.0,
                'avg_results' => round((float) $avgResults, 1),
                'searches_by_type' => $searchesByType,
                'daily_volume' => $dailyVolume,
            ];
        } catch (\Throwable $e) {
            Log::warning('Failed to get analytics summary', ['error' => $e->getMessage()]);
            return [
                'total_searches' => 0,
                'unique_queries' => 0,
                'zero_result_rate' => 0.0,
                'avg_results' => 0.0,
                'searches_by_type' => [],
                'daily_volume' => [],
            ];
        }
    }

    /**
     * Delete search logs older than 90 days.
     *
     * @return int Number of deleted rows
     */
    public function cleanupOldLogs(): int
    {
        try {
            return SearchLog::query()
                ->where('created_at', '<', now()->subDays(90))
                ->delete();
        } catch (\Throwable $e) {
            Log::warning('Failed to cleanup old search logs', ['error' => $e->getMessage()]);
            return 0;
        }
    }
}
