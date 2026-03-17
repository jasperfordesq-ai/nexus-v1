<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * MemberActivityService — Laravel DI-based service for member activity data.
 *
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\MemberActivityService.
 * Provides aggregated dashboard data, activity timelines, and hours summaries.
 */
class MemberActivityService
{
    /**
     * Get comprehensive dashboard data for a user.
     */
    public function getDashboard(int $userId): array
    {
        return [
            'timeline'    => $this->getTimeline($userId, 20),
            'hours'       => $this->getHours($userId),
            'connections' => (int) DB::table('connections')
                ->where(fn ($q) => $q->where('user_id', $userId)->orWhere('connected_user_id', $userId))
                ->where('status', 'accepted')
                ->count(),
            'posts_count' => (int) DB::table('feed_posts')->where('user_id', $userId)->count(),
        ];
    }

    /**
     * Get recent activity timeline for a user.
     */
    public function getTimeline(int $userId, int $limit = 30): array
    {
        $items = collect();

        // Posts
        $posts = DB::table('feed_posts')
            ->where('user_id', $userId)
            ->select('id', DB::raw("'post' as activity_type"), 'content as description', 'created_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
        $items = $items->merge($posts);

        // Transactions
        $txns = DB::table('transactions as t')
            ->where(fn ($q) => $q->where('t.sender_id', $userId)->orWhere('t.receiver_id', $userId))
            ->where('t.status', 'completed')
            ->select(
                't.id',
                DB::raw("CASE WHEN t.sender_id = {$userId} THEN 'gave_hours' ELSE 'received_hours' END as activity_type"),
                DB::raw("CONCAT(t.amount, ' hour(s)') as description"),
                't.created_at'
            )
            ->orderByDesc('t.created_at')
            ->limit($limit)
            ->get();
        $items = $items->merge($txns);

        return $items
            ->sortByDesc('created_at')
            ->take($limit)
            ->values()
            ->map(fn ($i) => (array) $i)
            ->all();
    }

    /**
     * Get hours given/received summary.
     *
     * @return array{given: float, received: float, balance: float}
     */
    public function getHours(int $userId): array
    {
        $given = (float) DB::table('transactions')
            ->where('sender_id', $userId)
            ->where('status', 'completed')
            ->sum('amount');

        $received = (float) DB::table('transactions')
            ->where('receiver_id', $userId)
            ->where('status', 'completed')
            ->sum('amount');

        return [
            'given'    => $given,
            'received' => $received,
            'balance'  => $received - $given,
        ];
    }

    /**
     * Delegates to legacy MemberActivityService::getDashboardData().
     */
    public function getDashboardData(int $userId): array
    {
        return \Nexus\Services\MemberActivityService::getDashboardData($userId);
    }

    /**
     * Delegates to legacy MemberActivityService::getRecentTimeline().
     */
    public function getRecentTimeline(int $userId, ?int $tenantId = null, int $limit = 30): array
    {
        return \Nexus\Services\MemberActivityService::getRecentTimeline($userId, $tenantId, $limit);
    }

    /**
     * Delegates to legacy MemberActivityService::getHoursSummary().
     */
    public function getHoursSummary(int $userId, ?int $tenantId = null): array
    {
        return \Nexus\Services\MemberActivityService::getHoursSummary($userId, $tenantId);
    }

    /**
     * Delegates to legacy MemberActivityService::getMonthlyHours().
     */
    public function getMonthlyHours(int $userId, ?int $tenantId = null): array
    {
        return \Nexus\Services\MemberActivityService::getMonthlyHours($userId, $tenantId);
    }
}
