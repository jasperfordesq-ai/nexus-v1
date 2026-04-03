<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;

/**
 * GroupAnalyticsService — Comprehensive analytics and reporting for groups.
 *
 * Provides member growth, engagement metrics, top contributors,
 * content performance, retention/churn, and comparative analytics.
 */
class GroupAnalyticsService
{
    /**
     * Get comprehensive analytics dashboard for a group.
     */
    public static function getDashboard(int $groupId, int $days = 30): array
    {
        return [
            'overview' => self::getOverview($groupId),
            'member_growth' => self::getMemberGrowth($groupId, $days),
            'engagement' => self::getEngagementMetrics($groupId, $days),
            'top_contributors' => self::getTopContributors($groupId, $days),
            'content_performance' => self::getContentPerformance($groupId, $days),
            'activity_breakdown' => self::getActivityBreakdown($groupId, $days),
        ];
    }

    /**
     * Group overview stats.
     */
    public static function getOverview(int $groupId): array
    {
        $tenantId = TenantContext::getId();

        $group = DB::table('groups')
            ->where('id', $groupId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$group) {
            return [];
        }

        $totalMembers = DB::table('group_members')
            ->where('group_id', $groupId)
            ->where('status', 'active')
            ->count();

        $totalDiscussions = DB::table('group_discussions')
            ->where('group_id', $groupId)
            ->where('tenant_id', $tenantId)
            ->count();

        $totalPosts = DB::table('group_posts as gp')
            ->join('group_discussions as gd', 'gp.discussion_id', '=', 'gd.id')
            ->where('gd.group_id', $groupId)
            ->where('gp.tenant_id', $tenantId)
            ->count();

        $totalEvents = DB::table('events')
            ->where('group_id', $groupId)
            ->where('tenant_id', $tenantId)
            ->count();

        $totalFiles = DB::table('group_files')
            ->where('group_id', $groupId)
            ->where('tenant_id', $tenantId)
            ->count();

        $pendingRequests = DB::table('group_members')
            ->where('group_id', $groupId)
            ->where('status', 'pending')
            ->count();

        return [
            'total_members' => $totalMembers,
            'total_discussions' => $totalDiscussions,
            'total_posts' => $totalPosts,
            'total_events' => $totalEvents,
            'total_files' => $totalFiles,
            'pending_requests' => $pendingRequests,
            'created_at' => $group->created_at,
            'visibility' => $group->visibility,
        ];
    }

    /**
     * Member growth over time (daily counts).
     */
    public static function getMemberGrowth(int $groupId, int $days = 30): array
    {
        $since = now()->subDays($days)->startOfDay();

        $joins = DB::table('group_members')
            ->where('group_id', $groupId)
            ->where('status', 'active')
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Build complete date series
        $result = [];
        $current = $since->copy();
        $cumulative = DB::table('group_members')
            ->where('group_id', $groupId)
            ->where('status', 'active')
            ->where('created_at', '<', $since)
            ->count();

        while ($current->lte(now())) {
            $dateStr = $current->format('Y-m-d');
            $dayJoins = $joins->has($dateStr) ? (int) $joins[$dateStr]->count : 0;
            $cumulative += $dayJoins;

            $result[] = [
                'date' => $dateStr,
                'new_members' => $dayJoins,
                'total_members' => $cumulative,
            ];

            $current->addDay();
        }

        return $result;
    }

    /**
     * Engagement metrics (posts, replies, active members over time).
     */
    public static function getEngagementMetrics(int $groupId, int $days = 30): array
    {
        $tenantId = TenantContext::getId();
        $since = now()->subDays($days)->startOfDay();

        // Discussion posts per day
        $postsPerDay = DB::table('group_posts as gp')
            ->join('group_discussions as gd', 'gp.discussion_id', '=', 'gd.id')
            ->where('gd.group_id', $groupId)
            ->where('gp.tenant_id', $tenantId)
            ->where('gp.created_at', '>=', $since)
            ->selectRaw('DATE(gp.created_at) as date, COUNT(*) as count')
            ->groupByRaw('DATE(gp.created_at)')
            ->get()
            ->keyBy('date');

        // Active members per day (posted or created discussion)
        $activeMembersPerDay = DB::table('group_posts as gp')
            ->join('group_discussions as gd', 'gp.discussion_id', '=', 'gd.id')
            ->where('gd.group_id', $groupId)
            ->where('gp.tenant_id', $tenantId)
            ->where('gp.created_at', '>=', $since)
            ->selectRaw('DATE(gp.created_at) as date, COUNT(DISTINCT gp.user_id) as count')
            ->groupByRaw('DATE(gp.created_at)')
            ->get()
            ->keyBy('date');

        // New discussions per day
        $discussionsPerDay = DB::table('group_discussions')
            ->where('group_id', $groupId)
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupByRaw('DATE(created_at)')
            ->get()
            ->keyBy('date');

        // Build timeline
        $result = [];
        $current = $since->copy();
        while ($current->lte(now())) {
            $dateStr = $current->format('Y-m-d');
            $result[] = [
                'date' => $dateStr,
                'posts' => $postsPerDay->has($dateStr) ? (int) $postsPerDay[$dateStr]->count : 0,
                'discussions' => $discussionsPerDay->has($dateStr) ? (int) $discussionsPerDay[$dateStr]->count : 0,
                'active_members' => $activeMembersPerDay->has($dateStr) ? (int) $activeMembersPerDay[$dateStr]->count : 0,
            ];
            $current->addDay();
        }

        // Summary
        $totalMembers = DB::table('group_members')
            ->where('group_id', $groupId)
            ->where('status', 'active')
            ->count();

        $uniqueActive = DB::table('group_posts as gp')
            ->join('group_discussions as gd', 'gp.discussion_id', '=', 'gd.id')
            ->where('gd.group_id', $groupId)
            ->where('gp.tenant_id', $tenantId)
            ->where('gp.created_at', '>=', $since)
            ->distinct('gp.user_id')
            ->count('gp.user_id');

        return [
            'timeline' => $result,
            'summary' => [
                'total_members' => $totalMembers,
                'active_members' => $uniqueActive,
                'participation_rate' => $totalMembers > 0 ? round(($uniqueActive / $totalMembers) * 100, 1) : 0,
                'avg_posts_per_day' => $days > 0 ? round(array_sum(array_column($result, 'posts')) / $days, 1) : 0,
            ],
        ];
    }

    /**
     * Top contributors (by posts, discussions, events).
     */
    public static function getTopContributors(int $groupId, int $days = 30, int $limit = 10): array
    {
        $tenantId = TenantContext::getId();
        $since = now()->subDays($days)->startOfDay();

        return DB::table('group_posts as gp')
            ->join('group_discussions as gd', 'gp.discussion_id', '=', 'gd.id')
            ->join('users as u', 'gp.user_id', '=', 'u.id')
            ->where('gd.group_id', $groupId)
            ->where('gp.tenant_id', $tenantId)
            ->where('gp.created_at', '>=', $since)
            ->selectRaw('gp.user_id, u.name, u.avatar_url, COUNT(*) as post_count')
            ->groupBy('gp.user_id', 'u.name', 'u.avatar_url')
            ->orderByDesc('post_count')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    /**
     * Content performance — most active discussions.
     */
    public static function getContentPerformance(int $groupId, int $days = 30, int $limit = 10): array
    {
        $tenantId = TenantContext::getId();
        $since = now()->subDays($days)->startOfDay();

        return DB::table('group_discussions as gd')
            ->leftJoin('group_posts as gp', 'gd.id', '=', 'gp.discussion_id')
            ->join('users as u', 'gd.user_id', '=', 'u.id')
            ->where('gd.group_id', $groupId)
            ->where('gd.tenant_id', $tenantId)
            ->where('gd.created_at', '>=', $since)
            ->selectRaw('gd.id, gd.title, gd.created_at, u.name as author_name, COUNT(gp.id) as reply_count, COUNT(DISTINCT gp.user_id) as unique_participants')
            ->groupBy('gd.id', 'gd.title', 'gd.created_at', 'u.name')
            ->orderByDesc('reply_count')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    /**
     * Activity breakdown by type.
     */
    public static function getActivityBreakdown(int $groupId, int $days = 30): array
    {
        $tenantId = TenantContext::getId();
        $since = now()->subDays($days)->startOfDay();

        $discussions = DB::table('group_discussions')
            ->where('group_id', $groupId)
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $since)
            ->count();

        $posts = DB::table('group_posts as gp')
            ->join('group_discussions as gd', 'gp.discussion_id', '=', 'gd.id')
            ->where('gd.group_id', $groupId)
            ->where('gp.tenant_id', $tenantId)
            ->where('gp.created_at', '>=', $since)
            ->count();

        $events = DB::table('events')
            ->where('group_id', $groupId)
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $since)
            ->count();

        $files = DB::table('group_files')
            ->where('group_id', $groupId)
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $since)
            ->count();

        $memberJoins = DB::table('group_members')
            ->where('group_id', $groupId)
            ->where('status', 'active')
            ->where('created_at', '>=', $since)
            ->count();

        return [
            'discussions' => $discussions,
            'posts' => $posts,
            'events' => $events,
            'files' => $files,
            'member_joins' => $memberJoins,
            'total' => $discussions + $posts + $events + $files + $memberJoins,
        ];
    }

    /**
     * Retention metrics — cohort analysis.
     */
    public static function getRetentionMetrics(int $groupId, int $months = 6): array
    {
        $tenantId = TenantContext::getId();

        $cohorts = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $monthStart = now()->subMonths($i)->startOfMonth();
            $monthEnd = now()->subMonths($i)->endOfMonth();
            $monthLabel = $monthStart->format('Y-m');

            // Members who joined this month
            $joinedIds = DB::table('group_members')
                ->where('group_id', $groupId)
                ->where('status', 'active')
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->pluck('user_id')
                ->toArray();

            $joined = count($joinedIds);

            // Of those who joined, how many are still active (posted in last 30 days)?
            $stillActive = 0;
            if ($joined > 0 && $i > 0) {
                $stillActive = DB::table('group_posts as gp')
                    ->join('group_discussions as gd', 'gp.discussion_id', '=', 'gd.id')
                    ->where('gd.group_id', $groupId)
                    ->where('gp.tenant_id', $tenantId)
                    ->where('gp.created_at', '>=', now()->subDays(30))
                    ->whereIn('gp.user_id', $joinedIds)
                    ->distinct('gp.user_id')
                    ->count('gp.user_id');
            }

            $cohorts[] = [
                'month' => $monthLabel,
                'joined' => $joined,
                'still_active' => $stillActive,
                'retention_rate' => $joined > 0 ? round(($stillActive / $joined) * 100, 1) : 0,
            ];
        }

        return $cohorts;
    }

    /**
     * Comparative analytics — rank group against others in tenant.
     */
    public static function getComparativeAnalytics(int $groupId): array
    {
        $tenantId = TenantContext::getId();

        $group = DB::table('groups')
            ->where('id', $groupId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$group) {
            return [];
        }

        $allGroups = DB::table('groups')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->select('id', 'cached_member_count')
            ->get();

        $totalGroups = $allGroups->count();
        $memberCounts = $allGroups->pluck('cached_member_count')->sort()->values()->toArray();

        // Calculate percentile
        $groupMembers = (int) $group->cached_member_count;
        $belowCount = count(array_filter($memberCounts, fn ($c) => $c < $groupMembers));
        $percentile = $totalGroups > 0 ? round(($belowCount / $totalGroups) * 100) : 0;

        // Average stats
        $avgMembers = $totalGroups > 0 ? round(array_sum($memberCounts) / $totalGroups) : 0;

        return [
            'group_members' => $groupMembers,
            'avg_members' => $avgMembers,
            'percentile' => $percentile,
            'total_groups' => $totalGroups,
            'rank' => $totalGroups - $belowCount,
        ];
    }

    /**
     * Export group data as associative arrays for CSV generation.
     */
    public static function exportMembers(int $groupId): array
    {
        $tenantId = TenantContext::getId();

        return DB::table('group_members as gm')
            ->join('users as u', 'gm.user_id', '=', 'u.id')
            ->join('groups as g', 'gm.group_id', '=', 'g.id')
            ->where('gm.group_id', $groupId)
            ->where('g.tenant_id', $tenantId)
            ->select('u.name', 'u.email', 'gm.role', 'gm.status', 'gm.created_at as joined_at')
            ->orderBy('gm.created_at')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    /**
     * Export activity data for CSV.
     */
    public static function exportActivity(int $groupId, int $days = 30): array
    {
        $tenantId = TenantContext::getId();
        $since = now()->subDays($days);

        return DB::table('group_posts as gp')
            ->join('group_discussions as gd', 'gp.discussion_id', '=', 'gd.id')
            ->join('users as u', 'gp.user_id', '=', 'u.id')
            ->where('gd.group_id', $groupId)
            ->where('gp.tenant_id', $tenantId)
            ->where('gp.created_at', '>=', $since)
            ->select('gd.title as discussion_title', 'u.name as author', 'gp.content', 'gp.created_at')
            ->orderByDesc('gp.created_at')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }
}
