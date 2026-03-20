<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * UserInsightsService — Personal transaction analytics for users.
 *
 * Shows trends, partner statistics, and activity summaries.
 * All queries are tenant-scoped.
 */
class UserInsightsService
{
    /**
     * Get complete user insights.
     */
    public function getInsights($userId, $months = null)
    {
        return [
            'summary' => $this->getSummary($userId),
            'monthly_trends' => $this->getMonthlyTrends($userId, 12),
            'partner_stats' => $this->getPartnerStats($userId),
        ];
    }

    /**
     * Get transaction summary for user.
     */
    public function getSummary($userId)
    {
        $tenantId = TenantContext::getId();

        $totalEarned = (float) DB::table('transactions')
            ->where('tenant_id', $tenantId)
            ->where('receiver_id', $userId)
            ->sum('amount');

        $totalSpent = $this->getTotalSpent($userId);

        $balance = (float) (DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->value('balance') ?? 0);

        $monthStats = DB::table('transactions')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)
                  ->orWhere('receiver_id', $userId);
            })
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->select([
                DB::raw("SUM(CASE WHEN receiver_id = {$userId} THEN amount ELSE 0 END) as earned_this_month"),
                DB::raw("SUM(CASE WHEN sender_id = {$userId} THEN amount ELSE 0 END) as spent_this_month"),
                DB::raw("COUNT(CASE WHEN receiver_id = {$userId} THEN 1 END) as received_count"),
                DB::raw("COUNT(CASE WHEN sender_id = {$userId} THEN 1 END) as sent_count"),
            ])
            ->first();

        $totalTransactions = (int) DB::table('transactions')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)
                  ->orWhere('receiver_id', $userId);
            })
            ->count();

        return [
            'total_earned' => $totalEarned,
            'total_spent' => $totalSpent,
            'current_balance' => $balance,
            'earned_this_month' => (float) ($monthStats->earned_this_month ?? 0),
            'spent_this_month' => (float) ($monthStats->spent_this_month ?? 0),
            'received_count_this_month' => (int) ($monthStats->received_count ?? 0),
            'sent_count_this_month' => (int) ($monthStats->sent_count ?? 0),
            'net_this_month' => (float) (($monthStats->earned_this_month ?? 0) - ($monthStats->spent_this_month ?? 0)),
            'total_transactions' => $totalTransactions,
        ];
    }

    /**
     * Get total spent by user.
     */
    public function getTotalSpent($userId)
    {
        $tenantId = TenantContext::getId();

        return (float) DB::table('transactions')
            ->where('tenant_id', $tenantId)
            ->where('sender_id', $userId)
            ->sum('amount');
    }

    /**
     * Get monthly transaction trends.
     */
    public function getMonthlyTrends($userId, $months = 12)
    {
        $tenantId = TenantContext::getId();
        $months = (int) $months;

        return DB::select(
            "SELECT
                DATE_FORMAT(created_at, '%Y-%m') as month,
                SUM(CASE WHEN receiver_id = ? THEN amount ELSE 0 END) as earned,
                SUM(CASE WHEN sender_id = ? THEN amount ELSE 0 END) as spent,
                COUNT(CASE WHEN receiver_id = ? THEN 1 END) as received_count,
                COUNT(CASE WHEN sender_id = ? THEN 1 END) as sent_count
             FROM transactions
             WHERE tenant_id = ?
             AND (sender_id = ? OR receiver_id = ?)
             AND created_at >= DATE_SUB(NOW(), INTERVAL {$months} MONTH)
             GROUP BY month
             ORDER BY month ASC",
            [$userId, $userId, $userId, $userId, $tenantId, $userId, $userId]
        );
    }

    /**
     * Get weekly transaction trends.
     */
    public function getWeeklyTrends($userId, $weeks = 12)
    {
        $tenantId = TenantContext::getId();
        $weeks = (int) $weeks;

        return DB::select(
            "SELECT
                YEARWEEK(created_at, 1) as week,
                MIN(DATE(created_at)) as week_start,
                SUM(CASE WHEN receiver_id = ? THEN amount ELSE 0 END) as earned,
                SUM(CASE WHEN sender_id = ? THEN amount ELSE 0 END) as spent
             FROM transactions
             WHERE tenant_id = ?
             AND (sender_id = ? OR receiver_id = ?)
             AND created_at >= DATE_SUB(NOW(), INTERVAL {$weeks} WEEK)
             GROUP BY week
             ORDER BY week ASC",
            [$userId, $userId, $tenantId, $userId, $userId]
        );
    }

    /**
     * Get partner diversity statistics.
     */
    public function getPartnerStats($userId, $months = null)
    {
        $tenantId = TenantContext::getId();

        $peoplePaid = (int) DB::table('transactions')
            ->where('tenant_id', $tenantId)
            ->where('sender_id', $userId)
            ->distinct()
            ->count('receiver_id');

        $peoplePaidBy = (int) DB::table('transactions')
            ->where('tenant_id', $tenantId)
            ->where('receiver_id', $userId)
            ->distinct()
            ->count('sender_id');

        $mutualConnections = (int) DB::table('transactions as sent')
            ->join('transactions as received', function ($join) {
                $join->on('sent.receiver_id', '=', 'received.sender_id')
                     ->on('sent.sender_id', '=', 'received.receiver_id');
            })
            ->where('sent.tenant_id', $tenantId)
            ->where('sent.sender_id', $userId)
            ->distinct()
            ->count('sent.receiver_id');

        return [
            'unique_people_paid' => $peoplePaid,
            'unique_people_received_from' => $peoplePaidBy,
            'mutual_connections' => $mutualConnections,
            'total_unique_partners' => $peoplePaid + $peoplePaidBy - $mutualConnections,
        ];
    }
}
