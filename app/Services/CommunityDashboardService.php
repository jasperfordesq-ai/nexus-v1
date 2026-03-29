<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\Connection;
use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CommunityDashboardService — replaces competitive leaderboard as the default view.
 *
 * Provides aggregate community impact stats, personal journey tracking,
 * and member spotlight rotation. All data is tenant-scoped.
 */
class CommunityDashboardService
{
    /**
     * Get aggregate community impact stats for the current tenant.
     *
     * Returns collective metrics (not individual rankings) to reflect
     * timebanking's cooperative philosophy.
     */
    public static function getCommunityImpact(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        $thisMonthStart = now()->startOfMonth();
        $lastMonthStart = now()->subMonth()->startOfMonth();
        $lastMonthEnd = now()->subMonth()->endOfMonth();

        try {
            // Current month stats
            $currentMonth = self::getMonthStats($tenantId, $thisMonthStart, now());
            $lastMonth = self::getMonthStats($tenantId, $lastMonthStart, $lastMonthEnd);

            // All-time stats
            $totalHoursExchanged = (float) Transaction::where('tenant_id', $tenantId)
                ->where('status', 'completed')
                ->sum('amount');

            $totalMembers = (int) User::where('tenant_id', $tenantId)
                ->where('is_approved', true)
                ->count();

            $totalSkillsOffered = (int) Listing::where('tenant_id', $tenantId)
                ->where('type', 'offer')
                ->where('status', 'active')
                ->distinct()
                ->count('category_id');

            $totalExchanges = (int) Transaction::where('tenant_id', $tenantId)
                ->where('status', 'completed')
                ->count();

            return [
                'total_hours_exchanged' => round($totalHoursExchanged, 1),
                'total_members' => $totalMembers,
                'total_exchanges' => $totalExchanges,
                'total_skills_offered' => $totalSkillsOffered,
                'this_month' => $currentMonth,
                'last_month' => $lastMonth,
                'trends' => self::calculateTrends($currentMonth, $lastMonth),
            ];
        } catch (\Throwable $e) {
            Log::error('CommunityDashboardService::getCommunityImpact failed: ' . $e->getMessage());
            return [
                'total_hours_exchanged' => 0,
                'total_members' => 0,
                'total_exchanges' => 0,
                'total_skills_offered' => 0,
                'this_month' => [],
                'last_month' => [],
                'trends' => [],
            ];
        }
    }

    /**
     * Get personal journey data for a specific user — their own growth over time.
     *
     * Shows monthly activity timeline (12 months), badge progression,
     * skills growth, and personal milestones.
     */
    public static function getPersonalJourney(?int $tenantId = null, ?int $userId = null): array
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        if (! $userId) {
            return [];
        }

        try {
            return [
                'monthly_activity' => self::getMonthlyActivityTimeline($tenantId, $userId),
                'badge_progression' => self::getBadgeProgression($userId),
                'skills_growth' => self::getSkillsGrowth($tenantId, $userId),
                'milestones' => self::getPersonalMilestones($tenantId, $userId),
                'summary' => self::getPersonalSummary($tenantId, $userId),
            ];
        } catch (\Throwable $e) {
            Log::error('CommunityDashboardService::getPersonalJourney failed: ' . $e->getMessage());
            return [
                'monthly_activity' => [],
                'badge_progression' => [],
                'skills_growth' => [],
                'milestones' => [],
                'summary' => [],
            ];
        }
    }

    /**
     * Get randomly spotlighted active members — daily rotation.
     *
     * Uses a date-based seed so the same members are shown all day,
     * then different ones tomorrow. Avoids always surfacing "top earners".
     */
    public static function getMemberSpotlight(?int $tenantId = null, int $limit = 3): array
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        try {
            // Find members active in the last 30 days (SQL-level distinct)
            $activeUserIds = collect(DB::select("
                SELECT DISTINCT user_id FROM (
                    SELECT sender_id AS user_id FROM transactions
                    WHERE tenant_id = ? AND status = 'completed' AND created_at >= ?
                    UNION
                    SELECT receiver_id AS user_id FROM transactions
                    WHERE tenant_id = ? AND status = 'completed' AND created_at >= ?
                ) AS active_users
            ", [$tenantId, now()->subDays(30), $tenantId, now()->subDays(30)]))->pluck('user_id')->toArray();

            if (empty($activeUserIds)) {
                return [];
            }

            // Use date-based seed for consistent daily rotation
            $dateSeed = (int) now()->format('Ymd');
            srand($dateSeed);
            shuffle($activeUserIds);
            srand(); // Reset to random seed

            $spotlightIds = array_slice($activeUserIds, 0, $limit);

            $users = User::whereIn('id', $spotlightIds)
                ->where('is_approved', true)
                ->get(['id', 'first_name', 'last_name', 'avatar_url', 'bio', 'created_at']);

            return $users->map(function ($user) use ($tenantId) {
                $recentActivity = self::getRecentActivitySummary($tenantId, $user->id);

                return [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'avatar_url' => $user->avatar_url,
                    'bio' => $user->bio ? mb_substr($user->bio, 0, 120) . '...' : null,
                    'member_since' => $user->created_at?->format('M Y'),
                    'recent_activity' => $recentActivity,
                ];
            })->values()->toArray();
        } catch (\Throwable $e) {
            Log::error('CommunityDashboardService::getMemberSpotlight failed: ' . $e->getMessage());
            return [];
        }
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Get stats for a specific date range within a tenant.
     */
    private static function getMonthStats(int $tenantId, $start, $end): array
    {
        $hoursExchanged = (float) Transaction::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount');

        $activeMembers = (int) DB::selectOne("
            SELECT COUNT(DISTINCT user_id) AS cnt FROM (
                SELECT sender_id AS user_id FROM transactions
                WHERE tenant_id = ? AND status = 'completed' AND created_at BETWEEN ? AND ?
                UNION
                SELECT receiver_id AS user_id FROM transactions
                WHERE tenant_id = ? AND status = 'completed' AND created_at BETWEEN ? AND ?
            ) AS active_users
        ", [$tenantId, $start, $end, $tenantId, $start, $end])->cnt ?? 0;

        $newConnections = (int) Connection::where('tenant_id', $tenantId)
            ->where('status', 'accepted')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $newExchanges = (int) Transaction::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $newMembers = (int) User::where('tenant_id', $tenantId)
            ->where('is_approved', true)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        return [
            'hours_exchanged' => round($hoursExchanged, 1),
            'active_members' => $activeMembers,
            'new_connections' => $newConnections,
            'new_exchanges' => $newExchanges,
            'new_members' => $newMembers,
        ];
    }

    /**
     * Calculate percentage trends between two months.
     */
    private static function calculateTrends(array $current, array $last): array
    {
        $trends = [];

        foreach ($current as $key => $value) {
            $prev = $last[$key] ?? 0;
            if ($prev > 0) {
                $trends[$key] = round((($value - $prev) / $prev) * 100, 1);
            } else {
                $trends[$key] = $value > 0 ? 100.0 : 0.0;
            }
        }

        return $trends;
    }

    /**
     * Get monthly activity timeline for the last 12 months (single query).
     */
    private static function getMonthlyActivityTimeline(int $tenantId, int $userId): array
    {
        $startDate = now()->subMonths(11)->startOfMonth();

        // Single query for exchanges + hours per month
        $txData = collect(DB::select("
            SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym,
                   COUNT(*) AS exchanges,
                   COALESCE(SUM(CASE WHEN receiver_id = ? THEN amount ELSE 0 END), 0) AS hours_earned
            FROM transactions
            WHERE tenant_id = ? AND status = 'completed'
              AND (sender_id = ? OR receiver_id = ?)
              AND created_at >= ?
            GROUP BY ym ORDER BY ym
        ", [$userId, $tenantId, $userId, $userId, $startDate]))->keyBy('ym');

        // Single query for connections per month
        $connData = collect(DB::select("
            SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS connections
            FROM connections
            WHERE tenant_id = ? AND status = 'accepted'
              AND (requester_id = ? OR receiver_id = ?)
              AND created_at >= ?
            GROUP BY ym ORDER BY ym
        ", [$tenantId, $userId, $userId, $startDate]))->keyBy('ym');

        $timeline = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i)->startOfMonth();
            $ym = $date->format('Y-m');
            $label = $date->format('M Y');

            $tx = $txData->get($ym);
            $conn = $connData->get($ym);

            $timeline[] = [
                'month' => $label,
                'exchanges' => (int) ($tx->exchanges ?? 0),
                'hours_earned' => round((float) ($tx->hours_earned ?? 0), 1),
                'connections' => (int) ($conn->connections ?? 0),
            ];
        }

        return $timeline;
    }

    /**
     * Get badge progression — when each badge was earned.
     */
    private static function getBadgeProgression(int $userId): array
    {
        return UserBadge::where('user_id', $userId)
            ->orderBy('awarded_at')
            ->get(['badge_key', 'name', 'icon', 'awarded_at'])
            ->map(fn ($b) => [
                'badge_key' => $b->badge_key,
                'name' => $b->name,
                'icon' => $b->icon,
                'earned_at' => $b->awarded_at?->format('Y-m-d'),
            ])
            ->toArray();
    }

    /**
     * Get skill category growth over time — how many categories they've been active in.
     */
    private static function getSkillsGrowth(int $tenantId, int $userId): array
    {
        try {
            $results = DB::table('transactions')
                ->join('listings', 'transactions.listing_id', '=', 'listings.id')
                ->where('transactions.tenant_id', $tenantId)
                ->where(function ($q) use ($userId) {
                    $q->where('transactions.sender_id', $userId)
                      ->orWhere('transactions.receiver_id', $userId);
                })
                ->where('transactions.status', 'completed')
                ->selectRaw('DATE_FORMAT(transactions.created_at, "%Y-%m") as month')
                ->selectRaw('COUNT(DISTINCT listings.category_id) as categories')
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            return $results->map(fn ($r) => [
                'month' => $r->month,
                'categories' => (int) $r->categories,
            ])->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get personal milestones — key moments in the user's journey.
     */
    private static function getPersonalMilestones(int $tenantId, int $userId): array
    {
        $milestones = [];

        // First transaction
        $firstTx = Transaction::where('tenant_id', $tenantId)
            ->where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)->orWhere('receiver_id', $userId);
            })
            ->where('status', 'completed')
            ->orderBy('created_at')
            ->first(['created_at']);

        if ($firstTx) {
            $milestones[] = [
                'type' => 'first_exchange',
                'label' => 'First exchange',
                'date' => $firstTx->created_at->format('M d, Y'),
            ];
        }

        // First badge
        $firstBadge = UserBadge::where('user_id', $userId)
            ->orderBy('awarded_at')
            ->first(['name', 'icon', 'awarded_at']);

        if ($firstBadge) {
            $milestones[] = [
                'type' => 'first_badge',
                'label' => "Earned \"{$firstBadge->name}\" {$firstBadge->icon}",
                'date' => $firstBadge->awarded_at?->format('M d, Y'),
            ];
        }

        // Total exchanges milestone
        $totalTx = (int) Transaction::where('tenant_id', $tenantId)
            ->where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)->orWhere('receiver_id', $userId);
            })
            ->where('status', 'completed')
            ->count();

        $txMilestones = [10, 25, 50, 100, 250, 500];
        foreach ($txMilestones as $milestone) {
            if ($totalTx >= $milestone) {
                $milestones[] = [
                    'type' => 'exchange_milestone',
                    'label' => "{$milestone} exchanges completed",
                    'date' => null,
                ];
            }
        }

        return $milestones;
    }

    /**
     * Get a quick personal summary (total hours, exchanges, badges, connections).
     */
    private static function getPersonalSummary(int $tenantId, int $userId): array
    {
        return [
            'total_hours_earned' => round((float) Transaction::where('tenant_id', $tenantId)
                ->where('receiver_id', $userId)->where('status', 'completed')->sum('amount'), 1),
            'total_hours_given' => round((float) Transaction::where('tenant_id', $tenantId)
                ->where('sender_id', $userId)->where('status', 'completed')->sum('amount'), 1),
            'total_exchanges' => (int) Transaction::where('tenant_id', $tenantId)
                ->where(function ($q) use ($userId) {
                    $q->where('sender_id', $userId)->orWhere('receiver_id', $userId);
                })->where('status', 'completed')->count(),
            'total_badges' => (int) UserBadge::where('user_id', $userId)->count(),
            'total_connections' => (int) Connection::where('tenant_id', $tenantId)
                ->where('status', 'accepted')
                ->where(function ($q) use ($userId) {
                    $q->where('requester_id', $userId)->orWhere('receiver_id', $userId);
                })->count(),
            'member_since' => User::find($userId)?->created_at?->format('M Y'),
        ];
    }

    /**
     * Get recent activity summary for a spotlight member.
     */
    private static function getRecentActivitySummary(int $tenantId, int $userId): string
    {
        $exchanges = (int) Transaction::where('tenant_id', $tenantId)
            ->where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)->orWhere('receiver_id', $userId);
            })
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $people = (int) Transaction::where('tenant_id', $tenantId)
            ->where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)->orWhere('receiver_id', $userId);
            })
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('COUNT(DISTINCT CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END) as cnt', [$userId])
            ->value('cnt');

        if ($exchanges > 0 && $people > 0) {
            return "Helped {$people} " . ($people === 1 ? 'person' : 'people') . " this month";
        }

        if ($exchanges > 0) {
            return "{$exchanges} " . ($exchanges === 1 ? 'exchange' : 'exchanges') . " this month";
        }

        return 'Active community member';
    }
}
