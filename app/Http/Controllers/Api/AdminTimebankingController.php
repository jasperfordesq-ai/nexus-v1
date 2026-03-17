<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Nexus\Core\TenantContext;
use Nexus\Services\AbuseDetectionService;

/**
 * AdminTimebankingController -- Admin timebanking stats, alerts, balance adjustments, org wallets, user reports.
 *
 * All methods require admin authentication.
 * CSV export (userStatement with format=csv) delegates to legacy for direct output.
 */
class AdminTimebankingController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct() {}

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
            $sql .= " ORDER BY CASE a.severity WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 END, a.created_at DESC LIMIT {$perPage} OFFSET {$offset}";

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
            AbuseDetectionService::updateAlertStatus($id, $status, $resolvedBy, $notes);
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

        $user = DB::selectOne("SELECT id, first_name, last_name, balance FROM users WHERE id = ? AND tenant_id = ?", [$userId, $tenantId]);
        if (!$user) return $this->respondWithError('NOT_FOUND', 'User not found', null, 404);

        $currentBalance = (float) ($user->balance ?? 0);
        $newBalance = $currentBalance + $amount;

        if ($newBalance < 0) {
            return $this->respondWithError('INSUFFICIENT_BALANCE', 'Adjustment would result in negative balance', null, 400);
        }

        DB::update("UPDATE users SET balance = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?", [$newBalance, $userId, $tenantId]);

        $absAmount = abs($amount);
        if ($amount > 0) {
            DB::insert("INSERT INTO transactions (tenant_id, sender_id, receiver_id, amount, description, status, created_at) VALUES (?, ?, ?, ?, ?, 'completed', NOW())",
                [$tenantId, $adminId, $userId, $absAmount, '[Admin Adjustment] ' . $reason]);
        } else {
            DB::insert("INSERT INTO transactions (tenant_id, sender_id, receiver_id, amount, description, status, created_at) VALUES (?, ?, ?, ?, ?, 'completed', NOW())",
                [$tenantId, $userId, $adminId, $absAmount, '[Admin Adjustment] ' . $reason]);
        }

        return $this->respondWithData([
            'user_id' => $userId,
            'user_name' => trim($user->first_name . ' ' . $user->last_name),
            'previous_balance' => $currentBalance,
            'adjustment' => $amount,
            'new_balance' => $newBalance,
        ]);
    }

    /** GET /api/v2/admin/timebanking/org-wallets */
    public function orgWallets(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminTimebankingApiController::class, 'orgWallets');
    }

    /** GET /api/v2/admin/timebanking/user-report */
    public function userReport(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminTimebankingApiController::class, 'userReport');
    }

    /** GET /api/v2/admin/timebanking/user-statement */
    public function userStatement(): JsonResponse
    {
        // CSV export uses direct output — delegate to legacy
        return $this->delegate(\Nexus\Controllers\Api\AdminTimebankingApiController::class, 'userStatement');
    }

    /** GET /api/v2/admin/timebanking/user-search */
    public function userSearchApi(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Admin\TimebankingController::class, 'userSearchApi');
    }

    private function delegate(string $legacyClass, string $method, array $params = []): JsonResponse
    {
        $controller = new $legacyClass();
        ob_start();
        $controller->$method(...$params);
        $output = ob_get_clean();
        $status = http_response_code();
        return response()->json(json_decode($output, true) ?: $output, $status ?: 200);
    }
}
