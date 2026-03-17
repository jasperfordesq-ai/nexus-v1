<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Services\UserInsightsService;

/**
 * AdminDashboardController -- Admin analytics dashboard endpoints.
 *
 * Provides aggregated stats, trends, activity log, and user insights.
 * All endpoints require admin authentication.
 */
class AdminDashboardController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly UserInsightsService $userInsightsService,
    ) {}

    /**
     * GET /api/v2/admin/dashboard/stats
     *
     * Returns aggregate counts for the admin dashboard stat cards.
     */
    public function stats(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $totalUsers = (int) DB::selectOne(
            "SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ?",
            [$tenantId]
        )->cnt;

        $activeUsers = (int) DB::selectOne(
            "SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ? AND is_approved = 1",
            [$tenantId]
        )->cnt;

        $pendingUsers = (int) DB::selectOne(
            "SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ? AND is_approved = 0",
            [$tenantId]
        )->cnt;

        $totalListings = (int) DB::selectOne(
            "SELECT COUNT(*) as cnt FROM listings WHERE tenant_id = ?",
            [$tenantId]
        )->cnt;

        $activeListings = (int) DB::selectOne(
            "SELECT COUNT(*) as cnt FROM listings WHERE tenant_id = ? AND status = 'active'",
            [$tenantId]
        )->cnt;

        $pendingListings = (int) DB::selectOne(
            "SELECT COUNT(*) as cnt FROM listings WHERE tenant_id = ? AND status = 'pending'",
            [$tenantId]
        )->cnt;

        $totalTransactions = 0;
        $totalHoursExchanged = 0;
        try {
            $txRow = DB::selectOne(
                "SELECT COUNT(*) as cnt, COALESCE(SUM(amount), 0) as total_hours FROM transactions WHERE tenant_id = ?",
                [$tenantId]
            );
            $totalTransactions = (int) ($txRow->cnt ?? 0);
            $totalHoursExchanged = (float) ($txRow->total_hours ?? 0);
        } catch (\Throwable $e) {
            // transactions table may not exist in all envs
        }

        $newUsersThisMonth = (int) DB::selectOne(
            "SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ? AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')",
            [$tenantId]
        )->cnt;

        $newListingsThisMonth = (int) DB::selectOne(
            "SELECT COUNT(*) as cnt FROM listings WHERE tenant_id = ? AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')",
            [$tenantId]
        )->cnt;

        return $this->respondWithData([
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'pending_users' => $pendingUsers,
            'total_listings' => $totalListings,
            'active_listings' => $activeListings,
            'pending_listings' => $pendingListings,
            'total_transactions' => $totalTransactions,
            'total_hours_exchanged' => round($totalHoursExchanged, 1),
            'new_users_this_month' => $newUsersThisMonth,
            'new_listings_this_month' => $newListingsThisMonth,
        ]);
    }

    /**
     * GET /api/v2/admin/dashboard/trends?months=6
     *
     * Returns monthly registration and listing creation counts for charts.
     */
    public function trends(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $months = $this->queryInt('months', 6, 1, 24);

        // User registrations per month
        $userTrends = DB::select(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count
             FROM users
             WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
             GROUP BY month
             ORDER BY month ASC",
            [$tenantId, $months]
        );

        // Listing creations per month
        $listingTrends = DB::select(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count
             FROM listings
             WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
             GROUP BY month
             ORDER BY month ASC",
            [$tenantId, $months]
        );

        // Transaction volumes per month
        $txTrends = [];
        try {
            $txTrends = DB::select(
                "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count, COALESCE(SUM(amount), 0) as hours
                 FROM transactions
                 WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                 GROUP BY month
                 ORDER BY month ASC",
                [$tenantId, $months]
            );
        } catch (\Throwable $e) {
            // transactions table may not exist
        }

        // Build maps
        $userMap = [];
        foreach ($userTrends as $row) {
            $userMap[$row->month] = (int) $row->count;
        }

        $listingMap = [];
        foreach ($listingTrends as $row) {
            $listingMap[$row->month] = (int) $row->count;
        }

        $txCountMap = [];
        $txHoursMap = [];
        foreach ($txTrends as $row) {
            $txCountMap[$row->month] = (int) $row->count;
            $txHoursMap[$row->month] = round((float) $row->hours, 1);
        }

        $trends = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-{$i} months"));
            $trends[] = [
                'month' => $month,
                'users' => $userMap[$month] ?? 0,
                'listings' => $listingMap[$month] ?? 0,
                'transactions' => $txCountMap[$month] ?? 0,
                'hours' => $txHoursMap[$month] ?? 0,
            ];
        }

        return $this->respondWithData($trends);
    }

    /**
     * GET /api/v2/admin/dashboard/activity?page=1&limit=20
     *
     * Returns paginated activity log entries.
     */
    public function activity(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $page = $this->queryInt('page', 1, 1);
        $limit = $this->queryInt('limit', 20, 1, 100);
        $offset = ($page - 1) * $limit;

        $total = 0;
        $items = [];

        try {
            $total = (int) DB::selectOne(
                "SELECT COUNT(*) as cnt FROM activity_log al JOIN users u ON al.user_id = u.id WHERE u.tenant_id = ?",
                [$tenantId]
            )->cnt;

            $items = DB::select(
                "SELECT al.*, u.first_name, u.last_name, u.email, u.avatar_url
                 FROM activity_log al
                 JOIN users u ON al.user_id = u.id
                 WHERE u.tenant_id = ?
                 ORDER BY al.created_at DESC
                 LIMIT ? OFFSET ?",
                [$tenantId, $limit, $offset]
            );
        } catch (\Throwable $e) {
            // activity_log table may not exist
        }

        // Format entries
        $formatted = array_map(function ($row) {
            return [
                'id' => (int) $row->id,
                'user_id' => (int) ($row->user_id ?? 0),
                'user_name' => trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')),
                'user_email' => $row->email ?? '',
                'user_avatar' => $row->avatar_url ?? null,
                'action' => $row->action ?? '',
                'description' => $row->description ?? '',
                'ip_address' => $row->ip_address ?? null,
                'created_at' => $row->created_at ?? '',
            ];
        }, $items);

        return $this->respondWithPaginatedCollection($formatted, $total, $page, $limit);
    }

    /**
     * GET /insights
     *
     * Returns user insights data (transaction insights).
     */
    public function apiInsights(): JsonResponse
    {
        $userId = $this->requireAuth();
        $months = $this->queryInt('months', 6, 1, 24);

        return $this->respondWithData([
            'insights' => $this->userInsightsService->getInsights($userId, $months),
            'trends' => $this->userInsightsService->getMonthlyTrends($userId, $months),
            'partnerStats' => $this->userInsightsService->getPartnerStats($userId, $months),
        ]);
    }
}
