<?php
// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * AdminAnalyticsService — Laravel DI-based service for admin dashboard analytics.
 *
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\AdminAnalyticsService.
 * All queries are tenant-scoped via explicit tenant_id parameter.
 */
class AdminAnalyticsService
{
    public function __construct(
        private readonly User $user,
        private readonly Transaction $transaction,
    ) {}

    /**
     * Get overall dashboard metrics for a tenant.
     */
    public function getDashboard(int $tenantId): array
    {
        $thirtyDaysAgo = now()->subDays(30);

        $totalCredits = (float) $this->user->newQuery()
            ->where('tenant_id', $tenantId)
            ->sum('balance');

        $txnVolume30d = (float) $this->transaction->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->sum('amount');

        $txnCount30d = (int) $this->transaction->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();

        $newUsers30d = (int) $this->user->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->count();

        $avgTxnSize = (float) $this->transaction->newQuery()
            ->where('tenant_id', $tenantId)
            ->avg('amount');

        return [
            'total_credits_circulation' => $totalCredits,
            'transaction_volume_30d'    => $txnVolume30d,
            'transaction_count_30d'     => $txnCount30d,
            'new_users_30d'             => $newUsers30d,
            'avg_transaction_size'      => round($avgTxnSize, 2),
        ];
    }

    /**
     * Get user statistics broken down by status.
     */
    public function getUserStats(int $tenantId): array
    {
        $statuses = $this->user->newQuery()
            ->where('tenant_id', $tenantId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $total = array_sum($statuses);

        $activeLastWeek = (int) $this->user->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('last_active_at', '>=', now()->subDays(7))
            ->count();

        return [
            'total'            => $total,
            'by_status'        => $statuses,
            'active_last_week' => $activeLastWeek,
        ];
    }

    // =========================================================================
    // Legacy delegation methods — used by AdminCommunityAnalyticsController
    // =========================================================================

    /**
     * Delegates to legacy AdminAnalyticsService::getOverallStats().
     */
    public function getOverallStats(): array
    {
        return \Nexus\Services\AdminAnalyticsService::getOverallStats();
    }

    /**
     * Delegates to legacy AdminAnalyticsService::getMonthlyTrends().
     */
    public function getMonthlyTrends(int $months = 12): array
    {
        return \Nexus\Services\AdminAnalyticsService::getMonthlyTrends($months);
    }

    /**
     * Delegates to legacy AdminAnalyticsService::getWeeklyTrends().
     */
    public function getWeeklyTrends(int $weeks = 12): array
    {
        return \Nexus\Services\AdminAnalyticsService::getWeeklyTrends($weeks);
    }

    /**
     * Delegates to legacy AdminAnalyticsService::getTopEarners().
     */
    public function getTopEarners(int $days = 30, int $limit = 10): array
    {
        return \Nexus\Services\AdminAnalyticsService::getTopEarners($days, $limit);
    }

    /**
     * Delegates to legacy AdminAnalyticsService::getTopSpenders().
     */
    public function getTopSpenders(int $days = 30, int $limit = 10): array
    {
        return \Nexus\Services\AdminAnalyticsService::getTopSpenders($days, $limit);
    }
}
