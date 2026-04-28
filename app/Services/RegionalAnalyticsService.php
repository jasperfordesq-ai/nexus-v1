<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RegionalAnalyticsService — AG59 Regional Analytics Product
 *
 * Computes sellable analytics for municipalities and SME partners:
 * geographic heatmaps, demand/supply ratios, demographics, engagement
 * trends, volunteer breakdowns, and help-request analysis.
 *
 * All queries are tenant-scoped. Results are cached in regional_analytics_cache.
 * Schema::hasTable() guards are used on every table before querying.
 * Privacy rules: heatmap cells with fewer than 3 members are suppressed.
 */
class RegionalAnalyticsService
{
    // ──────────────────────────────────────────────────────────────────────────
    // Availability
    // ──────────────────────────────────────────────────────────────────────────

    public static function isAvailable(): bool
    {
        return Schema::hasTable('regional_analytics_cache');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Cache helpers
    // ──────────────────────────────────────────────────────────────────────────

    private static function getCached(int $tenantId, string $type, string $period): ?array
    {
        if (!self::isAvailable()) {
            return null;
        }

        try {
            $row = DB::table('regional_analytics_cache')
                ->where('tenant_id', $tenantId)
                ->where('report_type', $type)
                ->where('period', $period)
                ->where('expires_at', '>', now())
                ->first();

            if (!$row) {
                return null;
            }

            $decoded = json_decode($row->payload, true);
            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function putCache(int $tenantId, string $type, string $period, array $data, int $ttlHours = 6): void
    {
        if (!self::isAvailable()) {
            return;
        }

        try {
            DB::table('regional_analytics_cache')
                ->upsert(
                    [
                        'tenant_id'   => $tenantId,
                        'report_type' => $type,
                        'period'      => $period,
                        'payload'     => json_encode($data, JSON_UNESCAPED_UNICODE),
                        'computed_at' => now(),
                        'expires_at'  => now()->addHours($ttlHours),
                    ],
                    ['tenant_id', 'report_type', 'period'],
                    ['payload', 'computed_at', 'expires_at']
                );
        } catch (\Throwable) {
            // Cache write failure is non-fatal
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Period helper: returns a Carbon cutoff date or null for "all_time"
    // ──────────────────────────────────────────────────────────────────────────

    private static function periodCutoff(string $period): ?\Illuminate\Support\Carbon
    {
        return match ($period) {
            'last_30d'  => now()->subDays(30),
            'last_90d'  => now()->subDays(90),
            'last_12m'  => now()->subMonths(12),
            'all_time'  => null,
            default     => now()->subDays(90),
        };
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 1. Member Heatmap
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Returns geographic activity density bucketed to ~0.01° grid cells.
     * Privacy rule: cells with fewer than 3 members are suppressed.
     *
     * @return array{lat: float, lng: float, count: int}[]|array{error: string}
     */
    public static function getMemberHeatmap(int $tenantId, string $period = 'last_90d'): array
    {
        $cached = self::getCached($tenantId, 'member_heatmap', $period);
        if ($cached !== null) {
            return $cached;
        }

        try {
            if (!Schema::hasTable('users')) {
                return ['error' => 'data_unavailable'];
            }

            $query = DB::table('users')
                ->where('tenant_id', $tenantId)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->where('status', 'active');

            $cutoff = self::periodCutoff($period);
            if ($cutoff !== null) {
                $query->where('created_at', '>=', $cutoff);
            }

            // Bucket to 0.01° grid (~1 km)
            $rows = $query
                ->selectRaw('ROUND(latitude, 2) as lat_bucket, ROUND(longitude, 2) as lng_bucket, COUNT(*) as cnt')
                ->groupByRaw('lat_bucket, lng_bucket')
                ->having('cnt', '>=', 3)   // Privacy: suppress small cells
                ->orderByDesc('cnt')
                ->limit(500)
                ->get();

            $result = $rows->map(fn ($r) => [
                'lat'   => (float) $r->lat_bucket,
                'lng'   => (float) $r->lng_bucket,
                'count' => (int) $r->cnt,
            ])->values()->all();

            self::putCache($tenantId, 'member_heatmap', $period, $result);
            return $result;
        } catch (\Throwable) {
            return ['error' => 'data_unavailable'];
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2. Demand / Supply Ratio
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Per-category request vs offer counts, ratio, and trend vs prior period.
     *
     * @return array<int, array{category_id: int, category_name: string, request_count: int, offer_count: int, ratio: float, trend: string}>|array{error: string}
     */
    public static function getDemandSupplyRatio(int $tenantId, string $period = 'last_30d'): array
    {
        $cached = self::getCached($tenantId, 'demand_supply', $period);
        if ($cached !== null) {
            return $cached;
        }

        try {
            if (!Schema::hasTable('listings')) {
                return ['error' => 'data_unavailable'];
            }

            $cutoff = self::periodCutoff($period);

            $query = DB::table('listings')
                ->where('listings.tenant_id', $tenantId)
                ->whereIn('listings.status', ['active', 'completed']);

            if ($cutoff !== null) {
                $query->where('listings.created_at', '>=', $cutoff);
            }

            // Join categories if available
            if (Schema::hasTable('categories')) {
                $query->leftJoin('categories', function ($join) use ($tenantId) {
                    $join->on('listings.category_id', '=', 'categories.id')
                         ->where('categories.tenant_id', '=', $tenantId);
                })->select(
                    'listings.category_id',
                    DB::raw('COALESCE(categories.name, CONCAT("Category ", listings.category_id)) as category_name'),
                    DB::raw("SUM(CASE WHEN listings.type = 'request' THEN 1 ELSE 0 END) as request_count"),
                    DB::raw("SUM(CASE WHEN listings.type = 'offer' THEN 1 ELSE 0 END) as offer_count"),
                    DB::raw('COUNT(*) as total')
                );
            } else {
                $query->select(
                    'listings.category_id',
                    DB::raw('CONCAT("Category ", listings.category_id) as category_name'),
                    DB::raw("SUM(CASE WHEN listings.type = 'request' THEN 1 ELSE 0 END) as request_count"),
                    DB::raw("SUM(CASE WHEN listings.type = 'offer' THEN 1 ELSE 0 END) as offer_count"),
                    DB::raw('COUNT(*) as total')
                );
            }

            $rows = $query->groupBy('listings.category_id', 'category_name')
                ->orderByDesc('total')
                ->limit(50)
                ->get();

            // Compute prior period for trend comparison
            $priorWindow = self::priorWindow($period);

            $result = $rows->map(function ($r) use ($tenantId, $priorWindow) {
                $requests = (int) $r->request_count;
                $offers   = (int) $r->offer_count;
                $ratio    = $offers > 0 ? round($requests / $offers, 2) : ($requests > 0 ? 999.0 : 0.0);

                // Simple trend: compare ratio now vs prior period
                $trend = '→';
                if ($priorWindow !== null && Schema::hasTable('listings')) {
                    try {
                        $prior = DB::table('listings')
                            ->where('tenant_id', $tenantId)
                            ->where('category_id', $r->category_id)
                            ->whereIn('status', ['active', 'completed'])
                            ->whereBetween('created_at', [$priorWindow['from'], $priorWindow['to']])
                            ->selectRaw("SUM(CASE WHEN type='request' THEN 1 ELSE 0 END) as req, SUM(CASE WHEN type='offer' THEN 1 ELSE 0 END) as off")
                            ->first();

                        $priorReq = (int) ($prior->req ?? 0);
                        $priorOff = (int) ($prior->off ?? 0);
                        $priorRatio = $priorOff > 0 ? $priorReq / $priorOff : ($priorReq > 0 ? 999.0 : 0.0);

                        if ($ratio > $priorRatio * 1.05) {
                            $trend = '↑';
                        } elseif ($ratio < $priorRatio * 0.95) {
                            $trend = '↓';
                        }
                    } catch (\Throwable) {
                        // Trend unavailable — leave as '→'
                    }
                }

                return [
                    'category_id'    => (int) ($r->category_id ?? 0),
                    'category_name'  => (string) $r->category_name,
                    'request_count'  => $requests,
                    'offer_count'    => $offers,
                    'ratio'          => $ratio,
                    'trend'          => $trend,
                ];
            })->values()->all();

            self::putCache($tenantId, 'demand_supply', $period, $result);
            return $result;
        } catch (\Throwable) {
            return ['error' => 'data_unavailable'];
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3. Demographics
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Age groups, language breakdown, and monthly member growth curve.
     * Privacy: only bucket counts, never individual data.
     *
     * @return array{age_groups: array, languages: array, monthly_growth: array}|array{error: string}
     */
    public static function getDemographics(int $tenantId): array
    {
        $cached = self::getCached($tenantId, 'demographics', 'all_time');
        if ($cached !== null) {
            return $cached;
        }

        try {
            if (!Schema::hasTable('users')) {
                return ['error' => 'data_unavailable'];
            }

            // Age groups from birthdate
            $ageRows = DB::table('users')
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->selectRaw(
                    "CASE
                        WHEN birthdate IS NULL THEN 'unknown'
                        WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) < 25 THEN 'under_25'
                        WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) < 35 THEN '25_34'
                        WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) < 45 THEN '35_44'
                        WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) < 55 THEN '45_54'
                        WHEN TIMESTAMPDIFF(YEAR, birthdate, CURDATE()) < 65 THEN '55_64'
                        ELSE '65_plus'
                    END as age_group,
                    COUNT(*) as count"
                )
                ->groupBy('age_group')
                ->get();

            $ageGroups = [];
            foreach ($ageRows as $row) {
                $ageGroups[$row->age_group] = (int) $row->count;
            }

            // Language breakdown
            $langRows = DB::table('users')
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->selectRaw('COALESCE(preferred_language, \'en\') as lang, COUNT(*) as count')
                ->groupBy('lang')
                ->orderByDesc('count')
                ->limit(20)
                ->get();

            $languages = $langRows->map(fn ($r) => [
                'language' => (string) $r->lang,
                'count'    => (int) $r->count,
            ])->values()->all();

            // Monthly new member growth — last 12 months
            $monthlyGrowth = DB::table('users')
                ->where('tenant_id', $tenantId)
                ->where('created_at', '>=', now()->subMonths(12))
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as new_members")
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            // Build cumulative total
            $totalBefore = (int) DB::table('users')
                ->where('tenant_id', $tenantId)
                ->where('created_at', '<', now()->subMonths(12))
                ->count();

            $cumulativeTotal = $totalBefore;
            $growthWithCumulative = $monthlyGrowth->map(function ($r) use (&$cumulativeTotal) {
                $cumulativeTotal += (int) $r->new_members;
                return [
                    'month'       => $r->month,
                    'new_members' => (int) $r->new_members,
                    'cumulative'  => $cumulativeTotal,
                ];
            })->values()->all();

            $result = [
                'age_groups'     => $ageGroups,
                'languages'      => $languages,
                'monthly_growth' => $growthWithCumulative,
            ];

            self::putCache($tenantId, 'demographics', 'all_time', $result);
            return $result;
        } catch (\Throwable) {
            return ['error' => 'data_unavailable'];
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 4. Engagement Trends
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Monthly engagement metrics: active members, vol hours, new listings,
     * new events, help requests.
     *
     * @return array<int, array{month: string, active_members: int, vol_hours: float, new_listings: int, new_events: int, help_requests: int}>|array{error: string}
     */
    public static function getEngagementTrends(int $tenantId, string $period = 'last_12m'): array
    {
        $cached = self::getCached($tenantId, 'engagement_trend', $period);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $cutoff = self::periodCutoff($period) ?? now()->subYears(5);
            $months = [];

            // Collect all months in range
            $current = clone $cutoff;
            $current->startOfMonth();
            $end = now()->startOfMonth();
            while ($current->lte($end)) {
                $months[$current->format('Y-m')] = [
                    'month'          => $current->format('Y-m'),
                    'active_members' => 0,
                    'vol_hours'      => 0.0,
                    'new_listings'   => 0,
                    'new_events'     => 0,
                    'help_requests'  => 0,
                ];
                $current->addMonth();
            }

            // Active members: users with at least 1 vol_log entry per month
            if (Schema::hasTable('vol_logs')) {
                $volActive = DB::table('vol_logs')
                    ->where('tenant_id', $tenantId)
                    ->where('created_at', '>=', $cutoff)
                    ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(DISTINCT user_id) as active_members")
                    ->groupBy('month')
                    ->get();
                foreach ($volActive as $row) {
                    if (isset($months[$row->month])) {
                        $months[$row->month]['active_members'] = (int) $row->active_members;
                    }
                }
            }

            // Vol hours per month
            if (Schema::hasTable('vol_logs')) {
                $volHours = DB::table('vol_logs')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'approved')
                    ->where('created_at', '>=', $cutoff)
                    ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, SUM(hours) as total_hours")
                    ->groupBy('month')
                    ->get();
                foreach ($volHours as $row) {
                    if (isset($months[$row->month])) {
                        $months[$row->month]['vol_hours'] = round((float) $row->total_hours, 1);
                    }
                }
            }

            // New listings per month
            if (Schema::hasTable('listings')) {
                $newListings = DB::table('listings')
                    ->where('tenant_id', $tenantId)
                    ->where('created_at', '>=', $cutoff)
                    ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as cnt")
                    ->groupBy('month')
                    ->get();
                foreach ($newListings as $row) {
                    if (isset($months[$row->month])) {
                        $months[$row->month]['new_listings'] = (int) $row->cnt;
                    }
                }
            }

            // New events per month
            if (Schema::hasTable('events')) {
                $newEvents = DB::table('events')
                    ->where('tenant_id', $tenantId)
                    ->where('created_at', '>=', $cutoff)
                    ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as cnt")
                    ->groupBy('month')
                    ->get();
                foreach ($newEvents as $row) {
                    if (isset($months[$row->month])) {
                        $months[$row->month]['new_events'] = (int) $row->cnt;
                    }
                }
            }

            // Help requests per month
            if (Schema::hasTable('caring_help_requests')) {
                $helpReqs = DB::table('caring_help_requests')
                    ->where('tenant_id', $tenantId)
                    ->where('created_at', '>=', $cutoff)
                    ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as cnt")
                    ->groupBy('month')
                    ->get();
                foreach ($helpReqs as $row) {
                    if (isset($months[$row->month])) {
                        $months[$row->month]['help_requests'] = (int) $row->cnt;
                    }
                }
            }

            $result = array_values($months);
            self::putCache($tenantId, 'engagement_trend', $period, $result);
            return $result;
        } catch (\Throwable) {
            return ['error' => 'data_unavailable'];
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 5. Volunteer Breakdown
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Top 10 organisations by volunteer hours, avg hours/volunteer, reciprocity ratio.
     *
     * @return array{top_orgs: array, avg_hours_per_volunteer: float, total_hours: float, reciprocity_ratio: float}|array{error: string}
     */
    public static function getVolunteerBreakdown(int $tenantId, string $period = 'last_90d'): array
    {
        $cached = self::getCached($tenantId, 'volunteer_breakdown', $period);
        if ($cached !== null) {
            return $cached;
        }

        try {
            if (!Schema::hasTable('vol_logs')) {
                return ['error' => 'data_unavailable'];
            }

            $cutoff = self::periodCutoff($period);

            $query = DB::table('vol_logs')
                ->where('vol_logs.tenant_id', $tenantId)
                ->where('vol_logs.status', 'approved');

            if ($cutoff !== null) {
                $query->where('vol_logs.created_at', '>=', $cutoff);
            }

            // Total hours + unique volunteers in period
            $totals = (clone $query)
                ->selectRaw('SUM(hours) as total_hours, COUNT(DISTINCT user_id) as unique_volunteers')
                ->first();

            $totalHours      = round((float) ($totals->total_hours ?? 0), 1);
            $uniqueVolunteers = (int) ($totals->unique_volunteers ?? 0);
            $avgHoursPerVol  = $uniqueVolunteers > 0 ? round($totalHours / $uniqueVolunteers, 2) : 0.0;

            // Reciprocity ratio: volunteers who both give and receive services
            // Approximate: vol_logs participants who also appear as listing creators
            $reciprocityRatio = 0.0;
            if (Schema::hasTable('listings')) {
                try {
                    $volUserIds = DB::table('vol_logs')
                        ->where('tenant_id', $tenantId)
                        ->where('status', 'approved')
                        ->when($cutoff, fn ($q) => $q->where('created_at', '>=', $cutoff))
                        ->distinct()
                        ->pluck('user_id')
                        ->toArray();

                    if (!empty($volUserIds)) {
                        $placeholders = implode(',', array_fill(0, count($volUserIds), '?'));
                        $params = array_merge([$tenantId], $volUserIds);
                        $bothCount = DB::selectOne(
                            "SELECT COUNT(DISTINCT user_id) as cnt FROM listings WHERE tenant_id = ? AND user_id IN ({$placeholders})",
                            $params
                        );
                        $bothTotal = (int) ($bothCount->cnt ?? 0);
                        $reciprocityRatio = $uniqueVolunteers > 0
                            ? round($bothTotal / $uniqueVolunteers, 3)
                            : 0.0;
                    }
                } catch (\Throwable) {
                    // Reciprocity unavailable
                }
            }

            // Top 10 orgs by hours — join vol_organizations if available, else use org_id
            $topOrgs = [];
            $orgTable = Schema::hasTable('vol_organizations') ? 'vol_organizations' : null;

            if ($orgTable !== null) {
                $topOrgs = (clone $query)
                    ->whereNotNull('vol_logs.org_id')
                    ->join('vol_organizations as o', function ($join) use ($tenantId) {
                        $join->on('vol_logs.org_id', '=', 'o.id')
                             ->where('o.tenant_id', '=', $tenantId);
                    })
                    ->select(
                        'vol_logs.org_id',
                        'o.name as org_name',
                        DB::raw('SUM(vol_logs.hours) as total_hours'),
                        DB::raw('COUNT(DISTINCT vol_logs.user_id) as volunteers')
                    )
                    ->groupBy('vol_logs.org_id', 'o.name')
                    ->orderByDesc('total_hours')
                    ->limit(10)
                    ->get()
                    ->map(fn ($r) => [
                        'org_id'      => (int) $r->org_id,
                        'org_name'    => (string) $r->org_name,
                        'total_hours' => round((float) $r->total_hours, 1),
                        'volunteers'  => (int) $r->volunteers,
                    ])
                    ->values()
                    ->all();
            } else {
                // Fall back to org_id only
                $topOrgs = (clone $query)
                    ->whereNotNull('vol_logs.org_id')
                    ->select(
                        'vol_logs.org_id',
                        DB::raw('CONCAT("Org #", vol_logs.org_id) as org_name'),
                        DB::raw('SUM(vol_logs.hours) as total_hours'),
                        DB::raw('COUNT(DISTINCT vol_logs.user_id) as volunteers')
                    )
                    ->groupBy('vol_logs.org_id')
                    ->orderByDesc('total_hours')
                    ->limit(10)
                    ->get()
                    ->map(fn ($r) => [
                        'org_id'      => (int) $r->org_id,
                        'org_name'    => (string) $r->org_name,
                        'total_hours' => round((float) $r->total_hours, 1),
                        'volunteers'  => (int) $r->volunteers,
                    ])
                    ->values()
                    ->all();
            }

            $result = [
                'top_orgs'               => $topOrgs,
                'avg_hours_per_volunteer' => $avgHoursPerVol,
                'total_hours'            => $totalHours,
                'reciprocity_ratio'      => $reciprocityRatio,
            ];

            self::putCache($tenantId, 'volunteer_breakdown', $period, $result);
            return $result;
        } catch (\Throwable) {
            return ['error' => 'data_unavailable'];
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 6. Help Request Analysis
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Help request totals, resolution rates, and 6-month resolution trend.
     *
     * @return array{by_category: array, resolution_trend: array}|array{error: string}
     */
    public static function getHelpRequestAnalysis(int $tenantId, string $period = 'last_30d'): array
    {
        $cached = self::getCached($tenantId, 'help_requests', $period);
        if ($cached !== null) {
            return $cached;
        }

        if (!Schema::hasTable('caring_help_requests')) {
            return ['error' => 'data_unavailable'];
        }

        try {
            $cutoff = self::periodCutoff($period);

            $query = DB::table('caring_help_requests')
                ->where('tenant_id', $tenantId);

            if ($cutoff !== null) {
                $query->where('created_at', '>=', $cutoff);
            }

            // By category
            $byCategory = (clone $query)
                ->select('category')
                ->selectRaw('COUNT(*) as total')
                ->selectRaw("SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_count")
                ->selectRaw(
                    "AVG(CASE WHEN status = 'resolved' AND updated_at IS NOT NULL
                        THEN TIMESTAMPDIFF(DAY, created_at, updated_at) END) as avg_resolution_days"
                )
                ->groupBy('category')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($r) => [
                    'category'            => (string) ($r->category ?? 'unknown'),
                    'total'               => (int) $r->total,
                    'resolved_count'      => (int) $r->resolved_count,
                    'resolution_rate'     => $r->total > 0
                        ? round($r->resolved_count / $r->total * 100, 1)
                        : 0.0,
                    'avg_resolution_days' => $r->avg_resolution_days !== null
                        ? round((float) $r->avg_resolution_days, 1)
                        : null,
                ])
                ->values()
                ->all();

            // Resolution rate trend — last 6 months
            $resolutionTrend = DB::table('caring_help_requests')
                ->where('tenant_id', $tenantId)
                ->where('created_at', '>=', now()->subMonths(6))
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month")
                ->selectRaw('COUNT(*) as total')
                ->selectRaw("SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved")
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->map(fn ($r) => [
                    'month'           => $r->month,
                    'total'           => (int) $r->total,
                    'resolved'        => (int) $r->resolved,
                    'resolution_rate' => $r->total > 0
                        ? round($r->resolved / $r->total * 100, 1)
                        : 0.0,
                ])
                ->values()
                ->all();

            $result = [
                'by_category'      => $byCategory,
                'resolution_trend' => $resolutionTrend,
            ];

            self::putCache($tenantId, 'help_requests', $period, $result);
            return $result;
        } catch (\Throwable) {
            return ['error' => 'data_unavailable'];
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 7. Overview Summary (hero metrics, 1h TTL)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Headline metrics for dashboard hero section.
     * Aggressively cached (1 hour) to meet the <200ms target after warm.
     *
     * @return array{active_members: int, vol_hours_this_month: float, help_requests_this_month: int, most_needed_category: string}|array{error: string}
     */
    public static function getOverviewSummary(int $tenantId): array
    {
        $cached = self::getCached($tenantId, 'overview', 'last_30d');
        if ($cached !== null) {
            return $cached;
        }

        try {
            $thirtyDaysAgo = now()->subDays(30);
            $monthStart    = now()->startOfMonth();

            // Active members: users with at least 1 vol_log in last 30d
            $activeMembers = 0;
            if (Schema::hasTable('vol_logs')) {
                $activeMembers = (int) DB::table('vol_logs')
                    ->where('tenant_id', $tenantId)
                    ->where('created_at', '>=', $thirtyDaysAgo)
                    ->distinct('user_id')
                    ->count('user_id');
            }

            // Vol hours this calendar month
            $volHoursThisMonth = 0.0;
            if (Schema::hasTable('vol_logs')) {
                $volHoursThisMonth = round((float) DB::table('vol_logs')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'approved')
                    ->where('created_at', '>=', $monthStart)
                    ->sum('hours'), 1);
            }

            // Help requests this month
            $helpRequestsThisMonth = 0;
            if (Schema::hasTable('caring_help_requests')) {
                $helpRequestsThisMonth = (int) DB::table('caring_help_requests')
                    ->where('tenant_id', $tenantId)
                    ->where('created_at', '>=', $monthStart)
                    ->count();
            }

            // Most needed category (highest request_count in last 30d)
            $mostNeededCategory = 'N/A';
            if (Schema::hasTable('listings') && Schema::hasTable('categories')) {
                $row = DB::table('listings')
                    ->where('listings.tenant_id', $tenantId)
                    ->where('listings.type', 'request')
                    ->where('listings.created_at', '>=', $thirtyDaysAgo)
                    ->leftJoin('categories', function ($join) use ($tenantId) {
                        $join->on('listings.category_id', '=', 'categories.id')
                             ->where('categories.tenant_id', '=', $tenantId);
                    })
                    ->select(DB::raw('COALESCE(categories.name, CONCAT("Category ", listings.category_id)) as cat_name'))
                    ->selectRaw('COUNT(*) as cnt')
                    ->groupBy('listings.category_id', 'cat_name')
                    ->orderByDesc('cnt')
                    ->first();

                if ($row) {
                    $mostNeededCategory = (string) $row->cat_name;
                }
            } elseif (Schema::hasTable('listings')) {
                $row = DB::table('listings')
                    ->where('tenant_id', $tenantId)
                    ->where('type', 'request')
                    ->where('created_at', '>=', $thirtyDaysAgo)
                    ->select('category_id')
                    ->selectRaw('COUNT(*) as cnt')
                    ->groupBy('category_id')
                    ->orderByDesc('cnt')
                    ->first();

                if ($row) {
                    $mostNeededCategory = 'Category ' . $row->category_id;
                }
            }

            $result = [
                'active_members'           => $activeMembers,
                'vol_hours_this_month'     => $volHoursThisMonth,
                'help_requests_this_month' => $helpRequestsThisMonth,
                'most_needed_category'     => $mostNeededCategory,
            ];

            self::putCache($tenantId, 'overview', 'last_30d', $result, 1);  // 1-hour TTL
            return $result;
        } catch (\Throwable) {
            return ['error' => 'data_unavailable'];
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 8. Full Report Export
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Assembles all metrics into one exportable payload.
     *
     * @return array{tenant_id: int, report_generated_at: string, period: string, overview: array, heatmap: array, demand_supply: array, demographics: array, engagement_trends: array, volunteer_breakdown: array, help_requests: array}
     */
    public static function exportReportJson(int $tenantId, string $period = 'last_30d'): array
    {
        // Resolve tenant name if possible
        $tenantName = 'Unknown';
        if (Schema::hasTable('tenants')) {
            try {
                $tenant = DB::table('tenants')->where('id', $tenantId)->first();
                $tenantName = (string) ($tenant->name ?? $tenantName);
            } catch (\Throwable) {
                // Name unavailable
            }
        }

        return [
            'tenant_id'           => $tenantId,
            'tenant_name'         => $tenantName,
            'report_generated_at' => now()->toIso8601String(),
            'period'              => $period,
            'overview'            => self::getOverviewSummary($tenantId),
            'heatmap'             => self::getMemberHeatmap($tenantId, $period),
            'demand_supply'       => self::getDemandSupplyRatio($tenantId, $period),
            'demographics'        => self::getDemographics($tenantId),
            'engagement_trends'   => self::getEngagementTrends($tenantId, 'last_12m'),
            'volunteer_breakdown' => self::getVolunteerBreakdown($tenantId, $period),
            'help_requests'       => self::getHelpRequestAnalysis($tenantId, $period),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 9. Cache Invalidation
    // ──────────────────────────────────────────────────────────────────────────

    public static function invalidateCache(int $tenantId): void
    {
        if (!self::isAvailable()) {
            return;
        }

        try {
            DB::table('regional_analytics_cache')
                ->where('tenant_id', $tenantId)
                ->delete();
        } catch (\Throwable) {
            // Non-fatal
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Private: prior period window for trend comparison
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Returns ['from' => Carbon, 'to' => Carbon] for the period immediately
     * preceding the given period, or null for 'all_time'.
     */
    private static function priorWindow(string $period): ?array
    {
        return match ($period) {
            'last_30d' => ['from' => now()->subDays(60), 'to' => now()->subDays(30)],
            'last_90d' => ['from' => now()->subDays(180), 'to' => now()->subDays(90)],
            'last_12m' => ['from' => now()->subMonths(24), 'to' => now()->subMonths(12)],
            default    => null,
        };
    }
}
