<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * VolOrgWalletService — Manages time credit wallets for volunteer organizations.
 *
 * All balance-mutating operations use DB::transaction() with lockForUpdate()
 * to prevent race conditions. Every mutation records an entry in
 * vol_org_transactions with the resulting balance_after for audit integrity.
 */
class VolOrgWalletService
{
    // =========================================================================
    // READ OPERATIONS
    // =========================================================================

    /**
     * Get wallet balance for a volunteer organization.
     *
     * @return array{balance: float, org_id: int, name: string}
     */
    public static function getBalance(int $volOrgId): array
    {
        $tenantId = TenantContext::getId();
        $org = DB::selectOne(
            "SELECT id, name, balance FROM vol_organizations WHERE id = ? AND tenant_id = ?",
            [$volOrgId, $tenantId]
        );

        if (!$org) {
            return ['balance' => 0, 'org_id' => $volOrgId, 'name' => ''];
        }

        return [
            'balance' => (float) $org->balance,
            'org_id' => (int) $org->id,
            'name' => $org->name,
        ];
    }

    /**
     * Get paginated transaction history for an organization wallet.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public static function getTransactions(int $volOrgId, array $filters = []): array
    {
        $tenantId = TenantContext::getId();
        // Hard cap at 50 rows to bound the LEFT JOIN users payload per request.
        $requested = (int) ($filters['limit'] ?? 20);
        $limit = max(1, min($requested, 50));
        $cursor = $filters['cursor'] ?? null;

        $params = [$volOrgId, $tenantId];
        $cursorClause = '';
        if ($cursor) {
            $cursorClause = ' AND t.id < ?';
            $params[] = (int) $cursor;
        }

        $typeFilter = '';
        if (!empty($filters['type'])) {
            $typeFilter = ' AND t.type = ?';
            $params[] = $filters['type'];
        }

        $params[] = $limit + 1;

        $rows = DB::select("
            SELECT t.id, t.type, t.amount, t.balance_after, t.description, t.created_at,
                   t.user_id, t.vol_log_id,
                   u.name as user_name, u.avatar_url as user_avatar_url
            FROM vol_org_transactions t
            LEFT JOIN users u ON t.user_id = u.id
            WHERE t.vol_organization_id = ? AND t.tenant_id = ?
            {$cursorClause}
            {$typeFilter}
            ORDER BY t.id DESC
            LIMIT ?
        ", $params);

        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        $items = array_map(fn ($row) => [
            'id' => (int) $row->id,
            'type' => $row->type,
            'amount' => (float) $row->amount,
            'balance_after' => (float) $row->balance_after,
            'description' => $row->description,
            'created_at' => $row->created_at,
            'vol_log_id' => $row->vol_log_id ? (int) $row->vol_log_id : null,
            'user' => $row->user_id ? [
                'id' => (int) $row->user_id,
                'name' => $row->user_name ?? 'Unknown',
                'avatar_url' => $row->user_avatar_url,
            ] : null,
        ], $rows);

        $lastItem = end($items);
        $nextCursor = $lastItem ? (string) $lastItem['id'] : null;

        return [
            'items' => $items,
            'cursor' => $hasMore ? $nextCursor : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get wallet summary with aggregate stats.
     *
     * @return array{balance: float, total_deposited: float, total_paid_out: float, transaction_count: int, pending_hours_value: float}
     */
    public static function getWalletSummary(int $volOrgId): array
    {
        $tenantId = TenantContext::getId();

        $org = DB::selectOne(
            "SELECT balance FROM vol_organizations WHERE id = ? AND tenant_id = ?",
            [$volOrgId, $tenantId]
        );

        $stats = DB::selectOne("
            SELECT
                COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) as total_deposited,
                COALESCE(SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END), 0) as total_paid_out,
                COUNT(*) as transaction_count
            FROM vol_org_transactions
            WHERE vol_organization_id = ? AND tenant_id = ?
        ", [$volOrgId, $tenantId]);

        // Calculate pending hours value (hours awaiting approval)
        $pendingHours = DB::selectOne("
            SELECT COALESCE(SUM(hours), 0) as total
            FROM vol_logs
            WHERE organization_id = ? AND tenant_id = ? AND status = 'pending'
        ", [$volOrgId, $tenantId]);

        return [
            'balance' => (float) ($org->balance ?? 0),
            'total_deposited' => (float) $stats->total_deposited,
            'total_paid_out' => (float) $stats->total_paid_out,
            'transaction_count' => (int) $stats->transaction_count,
            'pending_hours_value' => (float) $pendingHours->total,
        ];
    }

    // =========================================================================
    // BALANCE-MUTATING OPERATIONS (all use DB::transaction + lockForUpdate)
    // =========================================================================

    /**
     * User deposits their personal time credits into an org wallet.
     *
     * @return array{success: bool, message: string, new_balance?: float}
     */
    public static function depositFromUser(int $userId, int $volOrgId, float $amount, ?string $note = null): array
    {
        if ($amount <= 0) {
            return ['success' => false, 'message' => __('svc_notifications_2.vol_org_wallet.amount_must_be_greater_than_zero')];
        }
        if ($amount > 1000) {
            return ['success' => false, 'message' => __('svc_notifications_2.vol_org_wallet.deposit_cannot_exceed_1000')];
        }

        $tenantId = TenantContext::getId();

        return DB::transaction(function () use ($userId, $volOrgId, $amount, $note, $tenantId) {
            // Lock user row to prevent concurrent balance changes
            $user = DB::selectOne(
                "SELECT id, balance, name FROM users WHERE id = ? AND tenant_id = ? FOR UPDATE",
                [$userId, $tenantId]
            );

            if (!$user) {
                return ['success' => false, 'message' => __('svc_notifications_2.vol_org_wallet.user_not_found')];
            }

            // Lock org row BEFORE validating user balance (prevent race condition on org balance_after)
            $org = DB::selectOne(
                "SELECT id, name, balance FROM vol_organizations WHERE id = ? AND tenant_id = ? FOR UPDATE",
                [$volOrgId, $tenantId]
            );

            if (!$org) {
                return ['success' => false, 'message' => __('svc_notifications_2.vol_org_wallet.organization_not_found')];
            }

            // Use floor (not ceil) to prevent phantom debit: user loses same INT as org gains
            $intAmount = (int) floor($amount);
            if ($intAmount <= 0) {
                return ['success' => false, 'message' => __('svc_notifications_2.vol_org_wallet.amount_must_be_at_least_1')];
            }

            if ((int) $user->balance < $intAmount) {
                return ['success' => false, 'message' => __('svc_notifications_2.vol_org_wallet.insufficient_personal_balance')];
            }
            DB::update(
                "UPDATE users SET balance = balance - ? WHERE id = ? AND tenant_id = ?",
                [$intAmount, $userId, $tenantId]
            );

            // Credit to org (same INT amount as deducted from user — no phantom credits)
            DB::update(
                "UPDATE vol_organizations SET balance = balance + ? WHERE id = ? AND tenant_id = ?",
                [$intAmount, $volOrgId, $tenantId]
            );

            $newBalance = (float) $org->balance + $intAmount;

            // Record org transaction
            DB::insert("
                INSERT INTO vol_org_transactions (tenant_id, vol_organization_id, user_id, type, amount, balance_after, description, created_at)
                VALUES (?, ?, ?, 'deposit', ?, ?, ?, NOW())
            ", [$tenantId, $volOrgId, $userId, $amount, $newBalance, $note ?: "Deposit from {$user->name}"]);

            return ['success' => true, 'message' => __('svc_notifications_2.vol_org_wallet.deposit_successful'), 'new_balance' => $newBalance];
        });
    }

    /**
     * Pay a volunteer from the org wallet (used on hours approval or manual pay).
     *
     * @return array{success: bool, message: string, new_balance?: float}
     */
    public static function payVolunteer(
        int $volOrgId,
        int $volunteerId,
        float $amount,
        int $adminId,
        ?string $note = null,
        ?int $logId = null
    ): array {
        if ($amount <= 0) {
            return ['success' => false, 'message' => __('svc_notifications_2.vol_org_wallet.amount_must_be_greater_than_zero')];
        }

        $tenantId = TenantContext::getId();

        return DB::transaction(function () use ($volOrgId, $volunteerId, $amount, $adminId, $note, $logId, $tenantId) {
            // Lock org row
            $org = DB::selectOne(
                "SELECT id, name, balance, user_id FROM vol_organizations WHERE id = ? AND tenant_id = ? FOR UPDATE",
                [$volOrgId, $tenantId]
            );

            if (!$org) {
                return ['success' => false, 'message' => __('svc_notifications_2.vol_org_wallet.organization_not_found')];
            }

            if ((float) $org->balance < $amount) {
                return ['success' => false, 'message' => __('svc_notifications_2.vol_org_wallet.insufficient_organization_balance')];
            }

            // Lock volunteer user row
            $volunteer = DB::selectOne(
                "SELECT id, name FROM users WHERE id = ? AND tenant_id = ? FOR UPDATE",
                [$volunteerId, $tenantId]
            );

            if (!$volunteer) {
                return ['success' => false, 'message' => __('svc_notifications_2.vol_org_wallet.volunteer_not_found')];
            }

            // users.balance is an INT (whole hours). If the org is paying out
            // a fractional amount, we pay only the integer floor to the
            // volunteer and retain the fractional remainder in the org balance
            // so no hours vanish.
            $intAmount = (int) floor($amount);
            $fractional = round($amount - $intAmount, 4);
            $netOrgDebit = $amount - $fractional; // == $intAmount as float

            // Deduct from org (only the integer portion actually leaves the org;
            // the fractional remainder stays so it can accumulate with future payouts)
            DB::update(
                "UPDATE vol_organizations SET balance = balance - ? WHERE id = ? AND tenant_id = ?",
                [$netOrgDebit, $volOrgId, $tenantId]
            );

            $newOrgBalance = (float) $org->balance - $netOrgDebit;

            // Credit to volunteer (users.balance is INT)
            if ($intAmount > 0) {
                DB::update(
                    "UPDATE users SET balance = balance + ? WHERE id = ? AND tenant_id = ?",
                    [$intAmount, $volunteerId, $tenantId]
                );
            }

            // Record in vol_org_transactions
            $description = $note ?: "Payment to {$volunteer->name}" . ($logId ? " for approved hours" : '');
            DB::insert("
                INSERT INTO vol_org_transactions (tenant_id, vol_organization_id, user_id, vol_log_id, type, amount, balance_after, description, created_at)
                VALUES (?, ?, ?, ?, 'volunteer_payment', ?, ?, ?, NOW())
            ", [$tenantId, $volOrgId, $volunteerId, $logId, -$amount, $newOrgBalance, $description]);

            // Also record in main transactions table (uses existing 'volunteer' type)
            DB::insert("
                INSERT INTO transactions (tenant_id, sender_id, receiver_id, amount, description, transaction_type, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 'volunteer', 'completed', NOW(), NOW())
            ", [$tenantId, (int) $org->user_id, $volunteerId, $intAmount, $description]);

            return ['success' => true, 'message' => __('svc_notifications_2.vol_org_wallet.payment_successful'), 'new_balance' => $newOrgBalance];
        });
    }

    /**
     * Admin adjustment (top-up or deduct) on an org wallet.
     *
     * @return array{success: bool, message: string, new_balance?: float}
     */
    public static function adminAdjustment(int $volOrgId, float $amount, int $adminId, string $reason): array
    {
        if ($amount == 0) {
            return ['success' => false, 'message' => __('svc_notifications_2.vol_org_wallet.amount_cannot_be_zero')];
        }

        $tenantId = TenantContext::getId();

        return DB::transaction(function () use ($volOrgId, $amount, $adminId, $reason, $tenantId) {
            $org = DB::selectOne(
                "SELECT id, balance FROM vol_organizations WHERE id = ? AND tenant_id = ? FOR UPDATE",
                [$volOrgId, $tenantId]
            );

            if (!$org) {
                return ['success' => false, 'message' => __('svc_notifications_2.vol_org_wallet.organization_not_found')];
            }

            $newBalance = (float) $org->balance + $amount;
            if ($newBalance < 0) {
                return ['success' => false, 'message' => __('svc_notifications_2.vol_org_wallet.adjustment_negative_balance')];
            }

            DB::update(
                "UPDATE vol_organizations SET balance = balance + ? WHERE id = ? AND tenant_id = ?",
                [$amount, $volOrgId, $tenantId]
            );

            DB::insert("
                INSERT INTO vol_org_transactions (tenant_id, vol_organization_id, user_id, type, amount, balance_after, description, created_at)
                VALUES (?, ?, ?, 'admin_adjustment', ?, ?, ?, NOW())
            ", [$tenantId, $volOrgId, $adminId, $amount, $newBalance, "Admin adjustment: {$reason}"]);

            return ['success' => true, 'message' => __('svc_notifications_2.vol_org_wallet.adjustment_applied'), 'new_balance' => $newBalance];
        });
    }
}
