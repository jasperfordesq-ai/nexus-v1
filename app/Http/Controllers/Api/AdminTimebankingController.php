<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;
use App\Services\AbuseDetectionService;

/**
 * AdminTimebankingController -- Admin timebanking stats, alerts, balance adjustments, org wallets, user reports.
 *
 * All methods require admin authentication.
 * CSV export (userStatement with format=csv) delegates to legacy for direct output.
 */
class AdminTimebankingController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly AbuseDetectionService $abuseDetectionService,
    ) {}

    /** GET /api/v2/admin/timebanking/stats */
    public function stats(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $txRow = DB::selectOne(
            "SELECT COUNT(*) as total_transactions, COALESCE(SUM(amount), 0) as total_volume, COALESCE(AVG(amount), 0) as avg_transaction
             FROM transactions WHERE tenant_id = ? AND status = 'completed'",
            [$tenantId]
        );

        $topEarners = DB::select(
            "SELECT u.id as user_id, CONCAT(u.first_name, ' ', u.last_name) as user_name, COALESCE(SUM(t.amount), 0) as amount
             FROM transactions t JOIN users u ON t.receiver_id = u.id
             WHERE t.tenant_id = ? AND t.status = 'completed' GROUP BY u.id ORDER BY amount DESC LIMIT 5",
            [$tenantId]
        );

        $topSpenders = DB::select(
            "SELECT u.id as user_id, CONCAT(u.first_name, ' ', u.last_name) as user_name, COALESCE(SUM(t.amount), 0) as amount
             FROM transactions t JOIN users u ON t.sender_id = u.id
             WHERE t.tenant_id = ? AND t.status = 'completed' GROUP BY u.id ORDER BY amount DESC LIMIT 5",
            [$tenantId]
        );

        $activeAlerts = 0;
        try {
            $activeAlerts = (int) DB::selectOne(
                "SELECT COUNT(*) as cnt FROM abuse_alerts WHERE tenant_id = ? AND status IN ('new', 'reviewing')",
                [$tenantId]
            )->cnt;
        } catch (\Throwable $e) {}

        return $this->respondWithData([
            'total_transactions' => (int) ($txRow->total_transactions ?? 0),
            'total_volume' => round((float) ($txRow->total_volume ?? 0), 1),
            'avg_transaction' => round((float) ($txRow->avg_transaction ?? 0), 2),
            'active_alerts' => $activeAlerts,
            'top_earners' => array_map(fn($r) => ['user_id' => (int) $r->user_id, 'user_name' => $r->user_name, 'amount' => round((float) $r->amount, 1)], $topEarners),
            'top_spenders' => array_map(fn($r) => ['user_id' => (int) $r->user_id, 'user_name' => $r->user_name, 'amount' => round((float) $r->amount, 1)], $topSpenders),
        ]);
    }

    /** GET /api/v2/admin/timebanking/alerts */
    public function alerts(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $status = $this->query('status');
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $offset = ($page - 1) * $perPage;

        $total = 0;
        $items = [];

        try {
            $countSql = "SELECT COUNT(*) as cnt FROM abuse_alerts WHERE tenant_id = ?";
            $countParams = [$tenantId];
            if ($status && $status !== 'all') {
                $countSql .= " AND status = ?";
                $countParams[] = $status;
            }
            $total = (int) DB::selectOne($countSql, $countParams)->cnt;

            $sql = "SELECT a.id, a.user_id, a.alert_type, a.severity, a.status, a.details, a.created_at, a.resolved_at, a.resolution_notes,
                           CONCAT(u.first_name, ' ', u.last_name) as user_name
                    FROM abuse_alerts a LEFT JOIN users u ON a.user_id = u.id WHERE a.tenant_id = ?";
            $params = [$tenantId];
            if ($status && $status !== 'all') {
                $sql .= " AND a.status = ?";
                $params[] = $status;
            }
            $sql .= " ORDER BY CASE a.severity WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 END, a.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $perPage;
            $params[] = $offset;

            $items = DB::select($sql, $params);
        } catch (\Throwable $e) {}

        $formatted = array_map(fn($r) => [
            'id' => (int) $r->id,
            'user_id' => (int) ($r->user_id ?? 0),
            'user_name' => $r->user_name ?? 'Unknown',
            'alert_type' => $r->alert_type ?? '',
            'severity' => $r->severity ?? 'low',
            'status' => $r->status ?? 'new',
            'description' => $r->details ?? '',
            'created_at' => $r->created_at ?? '',
            'resolved_at' => $r->resolved_at ?? null,
            'resolution_notes' => $r->resolution_notes ?? null,
        ], $items);

        return $this->respondWithPaginatedCollection($formatted, $total, $page, $perPage);
    }

    /** PUT /api/v2/admin/timebanking/alerts/{id} */
    public function updateAlert(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $status = $this->input('status', '');
        $validStatuses = ['new', 'reviewing', 'resolved', 'dismissed'];
        if (!in_array($status, $validStatuses)) {
            return $this->respondWithError('INVALID_STATUS', 'Status must be one of: ' . implode(', ', $validStatuses), 'status', 400);
        }

        try {
            $alert = DB::selectOne("SELECT id FROM abuse_alerts WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
            if (!$alert) {
                return $this->respondWithError('NOT_FOUND', 'Alert not found', null, 404);
            }

            $resolvedBy = in_array($status, ['resolved', 'dismissed']) ? $this->getUserId() : null;
            $notes = $this->input('notes', '');
            $this->abuseDetectionService->updateAlertStatus($id, $status, $resolvedBy, $notes);
        } catch (\Throwable $e) {
            return $this->respondWithError('UPDATE_FAILED', 'Failed to update alert status', null, 500);
        }

        return $this->respondWithData(['id' => $id, 'status' => $status]);
    }

    /** POST /api/v2/admin/timebanking/adjust-balance */
    public function adjustBalance(): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $userId = $this->inputInt('user_id');
        $amount = (float) $this->input('amount', 0);
        $reason = trim($this->input('reason', ''));

        if (!$userId) return $this->respondWithError('VALIDATION_ERROR', 'user_id is required', 'user_id', 400);
        if ($amount == 0) return $this->respondWithError('VALIDATION_ERROR', 'amount must be non-zero', 'amount', 400);
        if (empty($reason)) return $this->respondWithError('VALIDATION_ERROR', 'reason is required', 'reason', 400);

        // Verify user exists (non-locking read for early 404)
        $user = DB::selectOne("SELECT id, first_name, last_name, balance FROM users WHERE id = ? AND tenant_id = ?", [$userId, $tenantId]);
        if (!$user) return $this->respondWithError('NOT_FOUND', 'User not found', null, 404);

        try {
            $result = DB::transaction(function () use ($userId, $tenantId, $adminId, $amount, $reason) {
                // Lock the user row to prevent concurrent balance modifications (race condition fix)
                $lockedUser = DB::selectOne(
                    "SELECT id, first_name, last_name, balance FROM users WHERE id = ? AND tenant_id = ? FOR UPDATE",
                    [$userId, $tenantId]
                );

                if (!$lockedUser) {
                    throw new \RuntimeException('User not found');
                }

                $currentBalance = (float) ($lockedUser->balance ?? 0);
                $newBalance = $currentBalance + $amount;

                if ($newBalance < 0) {
                    throw new \DomainException('Adjustment would result in negative balance');
                }

                // Atomic balance update using relative arithmetic
                DB::update(
                    "UPDATE users SET balance = balance + ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?",
                    [$amount, $userId, $tenantId]
                );

                $absAmount = abs($amount);
                if ($amount > 0) {
                    DB::insert("INSERT INTO transactions (tenant_id, sender_id, receiver_id, amount, description, status, created_at) VALUES (?, ?, ?, ?, ?, 'completed', NOW())",
                        [$tenantId, $adminId, $userId, $absAmount, '[Admin Adjustment] ' . $reason]);
                } else {
                    DB::insert("INSERT INTO transactions (tenant_id, sender_id, receiver_id, amount, description, status, created_at) VALUES (?, ?, ?, ?, ?, 'completed', NOW())",
                        [$tenantId, $userId, $adminId, $absAmount, '[Admin Adjustment] ' . $reason]);
                }

                return [
                    'previous_balance' => $currentBalance,
                    'new_balance' => $newBalance,
                    'user_name' => trim($lockedUser->first_name . ' ' . $lockedUser->last_name),
                ];
            });
        } catch (\DomainException $e) {
            return $this->respondWithError('INSUFFICIENT_BALANCE', $e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Balance adjustment failed', ['user' => $userId, 'error' => $e->getMessage()]);
            return $this->respondWithError('SERVER_ERROR', 'Failed to adjust balance', null, 500);
        }

        // Notify user of balance adjustment
        try {
            $absAmount = abs($amount);
            $hourLabel = $absAmount == 1 ? 'hour' : 'hours';
            $msg = $amount > 0
                ? "Your balance was adjusted: +{$absAmount} {$hourLabel} added by coordinator"
                : "Your balance was adjusted: -{$absAmount} {$hourLabel} removed by coordinator";
            \App\Models\Notification::createNotification(
                $userId,
                $msg,
                '/wallet',
                'transaction'
            );
        } catch (\Throwable $e) {
            \Log::warning('Balance adjustment notification failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
        }

        return $this->respondWithData([
            'user_id' => $userId,
            'user_name' => $result['user_name'],
            'previous_balance' => $result['previous_balance'],
            'adjustment' => $amount,
            'new_balance' => $result['new_balance'],
        ]);
    }

    /** GET /api/v2/admin/timebanking/org-wallets */
    public function orgWallets(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $wallets = [];

        try {
            // Single query with LEFT JOINed aggregates — eliminates N+1 per-org queries
            $rowResults = DB::select(
                "SELECT ow.id, ow.organization_id as org_id, ow.balance,
                        ow.created_at, vo.name as org_name,
                        COUNT(DISTINCT om.id) as member_count,
                        COALESCE(ot_in.total_in, 0) as total_in,
                        COALESCE(ot_out.total_out, 0) as total_out
                 FROM org_wallets ow
                 JOIN vol_organizations vo ON ow.organization_id = vo.id
                 LEFT JOIN org_members om ON om.organization_id = ow.organization_id AND om.status = 'active'
                 LEFT JOIN (
                     SELECT organization_id, ROUND(SUM(amount), 1) as total_in
                     FROM org_transactions
                     WHERE tenant_id = ? AND receiver_type = 'organization'
                     GROUP BY organization_id
                 ) ot_in ON ot_in.organization_id = ow.organization_id
                 LEFT JOIN (
                     SELECT organization_id, ROUND(SUM(amount), 1) as total_out
                     FROM org_transactions
                     WHERE tenant_id = ? AND sender_type = 'organization'
                     GROUP BY organization_id
                 ) ot_out ON ot_out.organization_id = ow.organization_id
                 WHERE ow.tenant_id = ?
                 GROUP BY ow.id, ow.organization_id, ow.balance, ow.created_at, vo.name, ot_in.total_in, ot_out.total_out
                 ORDER BY ow.balance DESC",
                [$tenantId, $tenantId, $tenantId]
            );

            foreach ($rowResults as $row) {
                $wallets[] = [
                    'id' => (int) $row->id,
                    'org_id' => (int) $row->org_id,
                    'org_name' => $row->org_name ?? 'Unknown',
                    'balance' => round((float) ($row->balance ?? 0), 1),
                    'total_in' => round((float) ($row->total_in ?? 0), 1),
                    'total_out' => round((float) ($row->total_out ?? 0), 1),
                    'member_count' => (int) ($row->member_count ?? 0),
                    'created_at' => $row->created_at ?? '',
                ];
            }
        } catch (\Throwable $e) {}

        return $this->respondWithData($wallets);
    }

    /** GET /api/v2/admin/timebanking/user-report */
    public function userReport(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $search = trim($this->query('search', ''));
        $offset = ($page - 1) * $perPage;

        $where = "u.tenant_id = ?";
        $params = [$tenantId];

        if ($search !== '') {
            $where .= " AND (CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ?)";
            $searchTerm = '%' . $search . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $total = (int) DB::selectOne(
            "SELECT COUNT(*) as cnt FROM users u WHERE {$where}",
            $params
        )->cnt;

        $userResults = DB::select(
            "SELECT u.id, u.first_name, u.last_name, u.email, u.avatar_url, u.balance,
                    COALESCE(earned.total, 0) as total_earned,
                    COALESCE(spent.total, 0) as total_spent,
                    COALESCE(earned.cnt, 0) + COALESCE(spent.cnt, 0) as transaction_count
             FROM users u
             LEFT JOIN (
                 SELECT receiver_id, SUM(amount) as total, COUNT(*) as cnt
                 FROM transactions WHERE tenant_id = ? AND status = 'completed' GROUP BY receiver_id
             ) earned ON earned.receiver_id = u.id
             LEFT JOIN (
                 SELECT sender_id, SUM(amount) as total, COUNT(*) as cnt
                 FROM transactions WHERE tenant_id = ? AND status = 'completed' GROUP BY sender_id
             ) spent ON spent.sender_id = u.id
             WHERE {$where}
             ORDER BY u.balance DESC
             LIMIT ? OFFSET ?",
            array_merge([$tenantId, $tenantId], $params, [$perPage, $offset])
        );
        $users = array_map(fn($r) => (array)$r, $userResults);

        $formatted = array_map(fn($row) => [
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
        ], $users);

        return $this->respondWithPaginatedCollection($formatted, $total, $page, $perPage);
    }

    /** GET /api/v2/admin/timebanking/user-statement */
    public function userStatement(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $userId = $this->queryInt('user_id');
        if (!$userId) {
            return $this->respondWithError('VALIDATION_ERROR', 'user_id is required', 'user_id', 400);
        }

        $startDate = $this->query('start_date', date('Y-m-01', strtotime('-12 months')));
        $endDate = $this->query('end_date', date('Y-m-d'));
        $format = $this->query('format', 'json');

        $userRow = DB::selectOne(
            "SELECT id, first_name, last_name, email, balance FROM users WHERE id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        );
        $user = $userRow ? (array)$userRow : null;

        if (!$user) {
            return $this->respondWithError('NOT_FOUND', 'User not found', null, 404);
        }

        $txResults = DB::select(
            "SELECT t.*,
                    sender.first_name as sender_first_name, sender.last_name as sender_last_name,
                    receiver.first_name as receiver_first_name, receiver.last_name as receiver_last_name,
                    l.title as listing_title
             FROM transactions t
             LEFT JOIN users sender ON sender.id = t.sender_id
             LEFT JOIN users receiver ON receiver.id = t.receiver_id
             LEFT JOIN listings l ON l.id = t.listing_id
             WHERE t.tenant_id = ?
               AND (t.sender_id = ? OR t.receiver_id = ?)
               AND t.created_at BETWEEN ? AND ?
             ORDER BY t.created_at DESC",
            [$tenantId, $userId, $userId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']
        );
        $transactions = array_map(fn($r) => (array)$r, $txResults);

        $earned = 0;
        $spent = 0;
        foreach ($transactions as $t) {
            if ((int) $t['receiver_id'] === $userId) $earned += (float) $t['amount'];
            if ((int) $t['sender_id'] === $userId) $spent += (float) $t['amount'];
        }

        $statement = [
            'user' => [
                'id' => (int) $user['id'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email' => $user['email'],
                'balance' => (float) ($user['balance'] ?? 0),
            ],
            'period' => ['start' => $startDate, 'end' => $endDate],
            'summary' => [
                'total_transactions' => count($transactions),
                'hours_earned' => round($earned, 2),
                'hours_spent' => round($spent, 2),
                'net_change' => round($earned - $spent, 2),
                'current_balance' => (float) ($user['balance'] ?? 0),
            ],
            'transactions' => $transactions,
        ];

        if ($format === 'csv') {
            return $this->sendCsvStatementResponse($statement);
        }

        return $this->respondWithData($statement);
    }

    /** GET /api/v2/admin/timebanking/user-search */
    public function userSearchApi(): JsonResponse
    {
        $this->requireAdmin();

        $query = trim($this->query('q', ''));

        if (strlen($query) < 2) {
            return $this->respondWithData(['users' => []]);
        }

        $tenantId = $this->getTenantId();
        $searchTerm = '%' . $query . '%';

        $userResults = DB::select(
            "SELECT id, first_name, last_name, email, balance
             FROM users
             WHERE tenant_id = ?
             AND (CONCAT(first_name, ' ', last_name) LIKE ? OR email LIKE ? OR id = ?)
             ORDER BY first_name, last_name
             LIMIT 20",
            [$tenantId, $searchTerm, $searchTerm, (int) $query]
        );
        $users = array_map(fn($r) => (array)$r, $userResults);

        return $this->respondWithData(['users' => $users]);
    }

    /** Generate CSV response for user statement */
    private function sendCsvStatementResponse(array $statement): JsonResponse
    {
        $csv = "Date,Type,Description,Amount,Balance After\n";
        $runningBalance = $statement['summary']['current_balance'];

        foreach ($statement['transactions'] as $t) {
            $isEarned = ((int) $t['receiver_id'] === $statement['user']['id']);
            $type = $isEarned ? 'Earned' : 'Spent';
            $amount = $isEarned ? '+' . $t['amount'] : '-' . $t['amount'];
            $desc = str_replace('"', '""', $t['description'] ?? $t['listing_title'] ?? '');
            $csv .= "\"{$t['created_at']}\",\"{$type}\",\"{$desc}\",\"{$amount}\",\"{$runningBalance}\"\n";
        }

        return $this->respondWithData([
            'csv' => $csv,
            'filename' => "statement_{$statement['user']['id']}_{$statement['period']['start']}_{$statement['period']['end']}.csv",
        ]);
    }
}
