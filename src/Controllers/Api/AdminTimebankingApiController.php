<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\AbuseDetectionService;

/**
 * AdminTimebankingApiController - V2 API for React admin timebanking module
 *
 * Provides transaction analytics, fraud alert management, balance adjustments,
 * user financial reports, and organization wallet oversight.
 *
 * Endpoints:
 * - GET  /api/v2/admin/timebanking/stats          - Aggregate transaction stats
 * - GET  /api/v2/admin/timebanking/alerts          - List fraud alerts (paginated)
 * - PUT  /api/v2/admin/timebanking/alerts/{id}     - Update alert status
 * - POST /api/v2/admin/timebanking/adjust-balance  - Admin balance adjustment
 * - GET  /api/v2/admin/timebanking/org-wallets     - Organization wallets list
 * - GET  /api/v2/admin/timebanking/user-report     - User financial overview
 */
class AdminTimebankingApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/admin/timebanking/stats
     *
     * Aggregate transaction statistics including top earners/spenders
     * and active fraud alert count.
     */
    public function stats(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        // Aggregate transaction stats
        $txRow = Database::query(
            "SELECT COUNT(*) as total_transactions,
                    COALESCE(SUM(amount), 0) as total_volume,
                    COALESCE(AVG(amount), 0) as avg_transaction
             FROM transactions
             WHERE tenant_id = ? AND status = 'completed'",
            [$tenantId]
        )->fetch();

        $totalTransactions = (int) ($txRow['total_transactions'] ?? 0);
        $totalVolume = round((float) ($txRow['total_volume'] ?? 0), 1);
        $avgTransaction = round((float) ($txRow['avg_transaction'] ?? 0), 2);

        // Top earners (by amount received)
        $topEarners = Database::query(
            "SELECT u.id as user_id,
                    CONCAT(u.first_name, ' ', u.last_name) as user_name,
                    COALESCE(SUM(t.amount), 0) as amount
             FROM transactions t
             JOIN users u ON t.recipient_id = u.id
             WHERE t.tenant_id = ? AND t.status = 'completed'
             GROUP BY u.id
             ORDER BY amount DESC
             LIMIT 5",
            [$tenantId]
        )->fetchAll();

        // Top spenders (by amount sent)
        $topSpenders = Database::query(
            "SELECT u.id as user_id,
                    CONCAT(u.first_name, ' ', u.last_name) as user_name,
                    COALESCE(SUM(t.amount), 0) as amount
             FROM transactions t
             JOIN users u ON t.sender_id = u.id
             WHERE t.tenant_id = ? AND t.status = 'completed'
             GROUP BY u.id
             ORDER BY amount DESC
             LIMIT 5",
            [$tenantId]
        )->fetchAll();

        // Active fraud alerts count
        $activeAlerts = 0;
        try {
            $activeAlerts = (int) Database::query(
                "SELECT COUNT(*) as cnt FROM abuse_alerts
                 WHERE tenant_id = ? AND status IN ('new', 'reviewing')",
                [$tenantId]
            )->fetch()['cnt'];
        } catch (\Throwable $e) {
            // abuse_alerts table may not exist
        }

        // Format numeric values
        $formattedEarners = array_map(function ($row) {
            return [
                'user_id' => (int) $row['user_id'],
                'user_name' => $row['user_name'],
                'amount' => round((float) $row['amount'], 1),
            ];
        }, $topEarners);

        $formattedSpenders = array_map(function ($row) {
            return [
                'user_id' => (int) $row['user_id'],
                'user_name' => $row['user_name'],
                'amount' => round((float) $row['amount'], 1),
            ];
        }, $topSpenders);

        $this->respondWithData([
            'total_transactions' => $totalTransactions,
            'total_volume' => $totalVolume,
            'avg_transaction' => $avgTransaction,
            'active_alerts' => $activeAlerts,
            'top_earners' => $formattedEarners,
            'top_spenders' => $formattedSpenders,
        ]);
    }

    /**
     * GET /api/v2/admin/timebanking/alerts?status=open&page=1
     *
     * List fraud alerts with optional status filter and pagination.
     */
    public function alerts(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $status = $this->query('status');
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $offset = ($page - 1) * $perPage;

        // Count total
        $countSql = "SELECT COUNT(*) as cnt FROM abuse_alerts WHERE tenant_id = ?";
        $countParams = [$tenantId];

        if ($status && $status !== 'all') {
            $countSql .= " AND status = ?";
            $countParams[] = $status;
        }

        $total = 0;
        $items = [];

        try {
            $total = (int) Database::query($countSql, $countParams)->fetch()['cnt'];

            $sql = "SELECT a.id, a.user_id, a.alert_type, a.severity, a.status,
                           a.details, a.created_at, a.resolved_at, a.resolution_notes,
                           CONCAT(u.first_name, ' ', u.last_name) as user_name
                    FROM abuse_alerts a
                    LEFT JOIN users u ON a.user_id = u.id
                    WHERE a.tenant_id = ?";
            $params = [$tenantId];

            if ($status && $status !== 'all') {
                $sql .= " AND a.status = ?";
                $params[] = $status;
            }

            $sql .= " ORDER BY
                        CASE a.severity
                            WHEN 'critical' THEN 1
                            WHEN 'high' THEN 2
                            WHEN 'medium' THEN 3
                            WHEN 'low' THEN 4
                        END,
                        a.created_at DESC
                      LIMIT $perPage OFFSET $offset";

            $items = Database::query($sql, $params)->fetchAll();
        } catch (\Throwable $e) {
            // abuse_alerts table may not exist
        }

        // Format items
        $formatted = array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'user_id' => (int) ($row['user_id'] ?? 0),
                'user_name' => $row['user_name'] ?? 'Unknown',
                'alert_type' => $row['alert_type'] ?? '',
                'severity' => $row['severity'] ?? 'low',
                'status' => $row['status'] ?? 'new',
                'description' => $row['details'] ?? '',
                'created_at' => $row['created_at'] ?? '',
                'resolved_at' => $row['resolved_at'] ?? null,
                'resolution_notes' => $row['resolution_notes'] ?? null,
            ];
        }, $items);

        $this->respondWithPaginatedCollection($formatted, $total, $page, $perPage);
    }

    /**
     * PUT /api/v2/admin/timebanking/alerts/{id}
     *
     * Update a fraud alert's status.
     * Valid statuses: new, reviewing, resolved, dismissed
     */
    public function updateAlert(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $status = $this->input('status', '');

        $validStatuses = ['new', 'reviewing', 'resolved', 'dismissed'];
        if (!in_array($status, $validStatuses)) {
            $this->respondWithError(
                'INVALID_STATUS',
                'Status must be one of: ' . implode(', ', $validStatuses),
                'status',
                400
            );
            return;
        }

        // Verify alert exists and belongs to tenant
        try {
            $alert = Database::query(
                "SELECT id FROM abuse_alerts WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            )->fetch();

            if (!$alert) {
                $this->respondWithError('NOT_FOUND', 'Alert not found', null, 404);
                return;
            }

            $resolvedBy = in_array($status, ['resolved', 'dismissed']) ? $this->getUserId() : null;
            $notes = $this->input('notes', '');

            AbuseDetectionService::updateAlertStatus($id, $status, $resolvedBy, $notes);
        } catch (\Throwable $e) {
            $this->respondWithError('UPDATE_FAILED', 'Failed to update alert status', null, 500);
            return;
        }

        $this->respondWithData(['id' => $id, 'status' => $status]);
    }

    /**
     * POST /api/v2/admin/timebanking/adjust-balance
     *
     * Admin balance adjustment for a user.
     * Logs the adjustment in the activity_log table.
     */
    public function adjustBalance(): void
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $userId = $this->inputInt('user_id');
        $amount = (float) $this->input('amount', 0);
        $reason = trim($this->input('reason', ''));

        if (!$userId) {
            $this->respondWithError('VALIDATION_ERROR', 'user_id is required', 'user_id', 400);
            return;
        }

        if ($amount == 0) {
            $this->respondWithError('VALIDATION_ERROR', 'amount must be non-zero', 'amount', 400);
            return;
        }

        if (empty($reason)) {
            $this->respondWithError('VALIDATION_ERROR', 'reason is required', 'reason', 400);
            return;
        }

        // Verify user exists
        $user = Database::query(
            "SELECT id, first_name, last_name, balance FROM users WHERE id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        )->fetch();

        if (!$user) {
            $this->respondWithError('NOT_FOUND', 'User not found', null, 404);
            return;
        }

        $currentBalance = (float) ($user['balance'] ?? 0);
        $newBalance = $currentBalance + $amount;

        // Prevent negative balance
        if ($newBalance < 0) {
            $this->respondWithError(
                'INSUFFICIENT_BALANCE',
                'Adjustment would result in negative balance',
                null,
                400
            );
            return;
        }

        // Update user balance
        Database::query(
            "UPDATE users SET balance = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?",
            [$newBalance, $userId, $tenantId]
        );

        // Log as a transaction
        $absAmount = abs($amount);
        if ($amount > 0) {
            Database::query(
                "INSERT INTO transactions (tenant_id, sender_id, recipient_id, amount, description, status, created_at)
                 VALUES (?, ?, ?, ?, ?, 'completed', NOW())",
                [$tenantId, $adminId, $userId, $absAmount, '[Admin Adjustment] ' . $reason]
            );
        } else {
            Database::query(
                "INSERT INTO transactions (tenant_id, sender_id, recipient_id, amount, description, status, created_at)
                 VALUES (?, ?, ?, ?, ?, 'completed', NOW())",
                [$tenantId, $userId, $adminId, $absAmount, '[Admin Adjustment] ' . $reason]
            );
        }

        // Log in activity_log
        try {
            Database::query(
                "INSERT INTO activity_log (user_id, action, description, ip_address, created_at)
                 VALUES (?, 'admin_balance_adjustment', ?, ?, NOW())",
                [
                    $adminId,
                    "Adjusted balance for user #{$userId} by {$amount}h: {$reason}",
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]
            );
        } catch (\Throwable $e) {
            // activity_log may not exist — non-critical
        }

        $userName = trim($user['first_name'] . ' ' . $user['last_name']);
        $this->respondWithData([
            'user_id' => $userId,
            'user_name' => $userName,
            'previous_balance' => $currentBalance,
            'adjustment' => $amount,
            'new_balance' => $newBalance,
        ]);
    }

    /**
     * GET /api/v2/admin/timebanking/org-wallets
     *
     * List all organization wallets with member counts.
     */
    public function orgWallets(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $wallets = [];

        try {
            $rows = Database::query(
                "SELECT ow.id, ow.organization_id as org_id, ow.balance,
                        ow.created_at, vo.name as org_name,
                        COUNT(om.id) as member_count
                 FROM org_wallets ow
                 JOIN vol_organizations vo ON ow.organization_id = vo.id
                 LEFT JOIN org_members om ON om.organization_id = ow.organization_id AND om.status = 'active'
                 WHERE ow.tenant_id = ?
                 GROUP BY ow.id
                 ORDER BY ow.balance DESC",
                [$tenantId]
            )->fetchAll();

            // Calculate total_in and total_out for each org wallet
            foreach ($rows as $row) {
                $orgId = (int) $row['org_id'];

                $totalIn = 0;
                $totalOut = 0;
                try {
                    $inRow = Database::query(
                        "SELECT COALESCE(SUM(amount), 0) as total
                         FROM org_transactions
                         WHERE tenant_id = ? AND organization_id = ? AND type = 'credit'",
                        [$tenantId, $orgId]
                    )->fetch();
                    $totalIn = round((float) ($inRow['total'] ?? 0), 1);
                } catch (\Throwable $e) {
                    // org_transactions may not exist
                }

                try {
                    $outRow = Database::query(
                        "SELECT COALESCE(SUM(amount), 0) as total
                         FROM org_transactions
                         WHERE tenant_id = ? AND organization_id = ? AND type = 'debit'",
                        [$tenantId, $orgId]
                    )->fetch();
                    $totalOut = round((float) ($outRow['total'] ?? 0), 1);
                } catch (\Throwable $e) {
                    // org_transactions may not exist
                }

                $wallets[] = [
                    'id' => (int) $row['id'],
                    'org_id' => $orgId,
                    'org_name' => $row['org_name'] ?? 'Unknown',
                    'balance' => round((float) ($row['balance'] ?? 0), 1),
                    'total_in' => $totalIn,
                    'total_out' => $totalOut,
                    'member_count' => (int) ($row['member_count'] ?? 0),
                    'created_at' => $row['created_at'] ?? '',
                ];
            }
        } catch (\Throwable $e) {
            // org_wallets table may not exist
        }

        $this->respondWithData($wallets);
    }

    /**
     * GET /api/v2/admin/timebanking/user-report?page=1&search=john
     *
     * User financial overview listing all users with balance and transaction data.
     */
    public function userReport(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $search = trim($this->query('search', ''));
        $offset = ($page - 1) * $perPage;

        // Build WHERE clause
        $where = "u.tenant_id = ?";
        $params = [$tenantId];

        if ($search !== '') {
            $where .= " AND (CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ?)";
            $searchTerm = '%' . $search . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Total count
        $total = (int) Database::query(
            "SELECT COUNT(*) as cnt FROM users u WHERE {$where}",
            $params
        )->fetch()['cnt'];

        // Fetch users with earned/spent sums
        $users = Database::query(
            "SELECT u.id, u.first_name, u.last_name, u.email, u.avatar_url, u.balance,
                    COALESCE(earned.total, 0) as total_earned,
                    COALESCE(spent.total, 0) as total_spent,
                    COALESCE(earned.cnt, 0) + COALESCE(spent.cnt, 0) as transaction_count
             FROM users u
             LEFT JOIN (
                 SELECT recipient_id, SUM(amount) as total, COUNT(*) as cnt
                 FROM transactions
                 WHERE tenant_id = ? AND status = 'completed'
                 GROUP BY recipient_id
             ) earned ON earned.recipient_id = u.id
             LEFT JOIN (
                 SELECT sender_id, SUM(amount) as total, COUNT(*) as cnt
                 FROM transactions
                 WHERE tenant_id = ? AND status = 'completed'
                 GROUP BY sender_id
             ) spent ON spent.sender_id = u.id
             WHERE {$where}
             ORDER BY u.balance DESC
             LIMIT {$perPage} OFFSET {$offset}",
            array_merge([$tenantId, $tenantId], $params)
        )->fetchAll();

        // Format
        $formatted = array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'name' => trim($row['first_name'] . ' ' . $row['last_name']),
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'email' => $row['email'],
                'avatar_url' => $row['avatar_url'] ?? null,
                'balance' => round((float) ($row['balance'] ?? 0), 1),
                'total_earned' => round((float) ($row['total_earned'] ?? 0), 1),
                'total_spent' => round((float) ($row['total_spent'] ?? 0), 1),
                'transaction_count' => (int) ($row['transaction_count'] ?? 0),
            ];
        }, $users);

        $this->respondWithPaginatedCollection($formatted, $total, $page, $perPage);
    }
}
