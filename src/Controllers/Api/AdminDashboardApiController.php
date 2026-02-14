<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * AdminDashboardApiController - V2 API for React admin dashboard
 *
 * Provides aggregated stats, trends, and activity log for the admin dashboard.
 * All endpoints require admin authentication.
 *
 * Endpoints:
 * - GET /api/v2/admin/dashboard/stats    - Aggregate stats (users, listings, transactions, etc.)
 * - GET /api/v2/admin/dashboard/trends   - Monthly trend data for charts
 * - GET /api/v2/admin/dashboard/activity - Recent activity log with pagination
 */
class AdminDashboardApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/admin/dashboard/stats
     *
     * Returns aggregate counts for the admin dashboard stat cards.
     */
    public function stats(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        // Total users
        $totalUsers = (int) Database::query(
            "SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ?",
            [$tenantId]
        )->fetch()['cnt'];

        // Active users (approved/active)
        $activeUsers = (int) Database::query(
            "SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ? AND is_approved = 1",
            [$tenantId]
        )->fetch()['cnt'];

        // Pending users
        $pendingUsers = (int) Database::query(
            "SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ? AND is_approved = 0",
            [$tenantId]
        )->fetch()['cnt'];

        // Total listings
        $totalListings = (int) Database::query(
            "SELECT COUNT(*) as cnt FROM listings WHERE tenant_id = ?",
            [$tenantId]
        )->fetch()['cnt'];

        // Active listings
        $activeListings = (int) Database::query(
            "SELECT COUNT(*) as cnt FROM listings WHERE tenant_id = ? AND status = 'active'",
            [$tenantId]
        )->fetch()['cnt'];

        // Total transactions
        $totalTransactions = 0;
        $totalHoursExchanged = 0;
        try {
            $txRow = Database::query(
                "SELECT COUNT(*) as cnt, COALESCE(SUM(amount), 0) as total_hours FROM transactions WHERE tenant_id = ?",
                [$tenantId]
            )->fetch();
            $totalTransactions = (int) ($txRow['cnt'] ?? 0);
            $totalHoursExchanged = (float) ($txRow['total_hours'] ?? 0);
        } catch (\Throwable $e) {
            // transactions table may not exist in all envs
        }

        // New users this month
        $newUsersThisMonth = (int) Database::query(
            "SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ? AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')",
            [$tenantId]
        )->fetch()['cnt'];

        // New listings this month
        $newListingsThisMonth = (int) Database::query(
            "SELECT COUNT(*) as cnt FROM listings WHERE tenant_id = ? AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')",
            [$tenantId]
        )->fetch()['cnt'];

        // Pending listings
        $pendingListings = (int) Database::query(
            "SELECT COUNT(*) as cnt FROM listings WHERE tenant_id = ? AND status = 'pending'",
            [$tenantId]
        )->fetch()['cnt'];

        $this->respondWithData([
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
    public function trends(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $months = min((int) ($_GET['months'] ?? 6), 24);
        if ($months < 1) $months = 6;

        // User registrations per month
        $userTrends = Database::query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count
             FROM users
             WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
             GROUP BY month
             ORDER BY month ASC",
            [$tenantId, $months]
        )->fetchAll();

        // Listing creations per month
        $listingTrends = Database::query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count
             FROM listings
             WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
             GROUP BY month
             ORDER BY month ASC",
            [$tenantId, $months]
        )->fetchAll();

        // Transaction volumes per month
        $txTrends = [];
        try {
            $txTrends = Database::query(
                "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count, COALESCE(SUM(amount), 0) as hours
                 FROM transactions
                 WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                 GROUP BY month
                 ORDER BY month ASC",
                [$tenantId, $months]
            )->fetchAll();
        } catch (\Throwable $e) {
            // transactions table may not exist
        }

        // Build unified month-by-month array
        $userMap = [];
        foreach ($userTrends as $row) {
            $userMap[$row['month']] = (int) $row['count'];
        }

        $listingMap = [];
        foreach ($listingTrends as $row) {
            $listingMap[$row['month']] = (int) $row['count'];
        }

        $txCountMap = [];
        $txHoursMap = [];
        foreach ($txTrends as $row) {
            $txCountMap[$row['month']] = (int) $row['count'];
            $txHoursMap[$row['month']] = round((float) $row['hours'], 1);
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

        $this->respondWithData($trends);
    }

    /**
     * GET /api/v2/admin/dashboard/activity?page=1&limit=20
     *
     * Returns paginated activity log entries.
     */
    public function activity(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $total = 0;
        $items = [];

        try {
            $total = (int) Database::query(
                "SELECT COUNT(*) as cnt FROM activity_log al JOIN users u ON al.user_id = u.id WHERE u.tenant_id = ?",
                [$tenantId]
            )->fetch()['cnt'];

            $items = Database::query(
                "SELECT al.*, u.first_name, u.last_name, u.email, u.avatar_url
                 FROM activity_log al
                 JOIN users u ON al.user_id = u.id
                 WHERE u.tenant_id = ?
                 ORDER BY al.created_at DESC
                 LIMIT ? OFFSET ?",
                [$tenantId, $limit, $offset]
            )->fetchAll();
        } catch (\Throwable $e) {
            // activity_log table may not exist
        }

        // Format entries
        $formatted = array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'user_id' => (int) ($row['user_id'] ?? 0),
                'user_name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                'user_email' => $row['email'] ?? '',
                'user_avatar' => $row['avatar_url'] ?? null,
                'action' => $row['action'] ?? '',
                'description' => $row['description'] ?? '',
                'ip_address' => $row['ip_address'] ?? null,
                'created_at' => $row['created_at'] ?? '',
            ];
        }, $items);

        $this->respondWithPaginatedCollection($formatted, $total, $page, $limit);
    }
}
