<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * CommunityFundService (W1)
 *
 * Manages the community fund — a special system account per tenant
 * that holds community-owned time credits.
 *
 * Features:
 * - Auto-create fund on tenant bootstrap
 * - Admin deposit/withdraw credits
 * - Accept member donations
 * - Track all movements with audit trail
 * - Dashboard balance reporting
 */
class CommunityFundService
{
    /**
     * Get or create the community fund for the current tenant
     *
     * @return array Fund record
     */
    public static function getOrCreateFund(): array
    {
        $tenantId = TenantContext::getId();

        $stmt = Database::query(
            "SELECT * FROM community_fund_accounts WHERE tenant_id = ?",
            [$tenantId]
        );
        $fund = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($fund) {
            return $fund;
        }

        // Auto-create
        Database::query(
            "INSERT INTO community_fund_accounts (tenant_id, balance, description)
             VALUES (?, 0.00, 'Community time credit fund')",
            [$tenantId]
        );

        return [
            'id' => Database::lastInsertId(),
            'tenant_id' => $tenantId,
            'balance' => 0.00,
            'total_deposited' => 0.00,
            'total_withdrawn' => 0.00,
            'total_donated' => 0.00,
            'description' => 'Community time credit fund',
        ];
    }

    /**
     * Get fund balance and statistics
     *
     * @return array Fund info
     */
    public static function getBalance(): array
    {
        $fund = self::getOrCreateFund();

        return [
            'id' => (int) $fund['id'],
            'balance' => (float) $fund['balance'],
            'total_deposited' => (float) $fund['total_deposited'],
            'total_withdrawn' => (float) $fund['total_withdrawn'],
            'total_donated' => (float) $fund['total_donated'],
            'description' => $fund['description'] ?? '',
        ];
    }

    /**
     * Admin deposits credits into the community fund
     * Creates credits "out of thin air" — increases fund balance.
     *
     * @param int $adminId Admin user performing the action
     * @param float $amount Amount to deposit
     * @param string $description Reason for deposit
     * @return array ['success' => bool, 'error' => string|null, 'balance' => float|null]
     */
    public static function adminDeposit(int $adminId, float $amount, string $description = ''): array
    {
        if ($amount <= 0) {
            return ['success' => false, 'error' => 'Amount must be greater than 0'];
        }

        $tenantId = TenantContext::getId();
        $fund = self::getOrCreateFund();

        Database::beginTransaction();
        try {
            $newBalance = (float) $fund['balance'] + $amount;

            Database::query(
                "UPDATE community_fund_accounts
                 SET balance = balance + ?, total_deposited = total_deposited + ?
                 WHERE id = ? AND tenant_id = ?",
                [$amount, $amount, $fund['id'], $tenantId]
            );

            Database::query(
                "INSERT INTO community_fund_transactions
                 (tenant_id, fund_id, user_id, type, amount, balance_after, description, admin_id)
                 VALUES (?, ?, ?, 'deposit', ?, ?, ?, ?)",
                [$tenantId, $fund['id'], $adminId, $amount, $newBalance, $description ?: 'Admin deposit', $adminId]
            );

            Database::commit();

            return ['success' => true, 'balance' => $newBalance];
        } catch (\Exception $e) {
            Database::rollback();
            error_log("CommunityFundService::adminDeposit error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Deposit failed'];
        }
    }

    /**
     * Admin withdraws credits from the community fund and grants to a member
     *
     * @param int $adminId Admin user performing the action
     * @param int $recipientId User receiving the credits
     * @param float $amount Amount to withdraw
     * @param string $description Reason for withdrawal
     * @return array ['success' => bool, 'error' => string|null, 'balance' => float|null]
     */
    public static function adminWithdraw(int $adminId, int $recipientId, float $amount, string $description = ''): array
    {
        if ($amount <= 0) {
            return ['success' => false, 'error' => 'Amount must be greater than 0'];
        }

        $tenantId = TenantContext::getId();
        $fund = self::getOrCreateFund();

        if ((float) $fund['balance'] < $amount) {
            return ['success' => false, 'error' => 'Insufficient community fund balance'];
        }

        Database::beginTransaction();
        try {
            $newBalance = (float) $fund['balance'] - $amount;

            // Deduct from fund
            Database::query(
                "UPDATE community_fund_accounts
                 SET balance = balance - ?, total_withdrawn = total_withdrawn + ?
                 WHERE id = ? AND tenant_id = ? AND balance >= ?",
                [$amount, $amount, $fund['id'], $tenantId, $amount]
            );

            // Credit the recipient user
            Database::query(
                "UPDATE users SET balance = balance + ? WHERE id = ? AND tenant_id = ?",
                [$amount, $recipientId, $tenantId]
            );

            // Log fund transaction
            Database::query(
                "INSERT INTO community_fund_transactions
                 (tenant_id, fund_id, user_id, type, amount, balance_after, description, admin_id)
                 VALUES (?, ?, ?, 'withdrawal', ?, ?, ?, ?)",
                [$tenantId, $fund['id'], $recipientId, $amount, $newBalance, $description ?: 'Admin grant to member', $adminId]
            );

            // Log wallet transaction for the recipient
            Database::query(
                "INSERT INTO transactions (tenant_id, sender_id, receiver_id, amount, description, transaction_type)
                 VALUES (?, ?, ?, ?, ?, 'community_fund')",
                [$tenantId, $recipientId, $recipientId, $amount, $description ?: 'Community fund grant']
            );

            Database::commit();

            return ['success' => true, 'balance' => $newBalance];
        } catch (\Exception $e) {
            Database::rollback();
            error_log("CommunityFundService::adminWithdraw error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Withdrawal failed'];
        }
    }

    /**
     * Member donates credits to the community fund
     *
     * @param int $donorId User donating
     * @param float $amount Amount to donate
     * @param string $message Optional donation message
     * @return array ['success' => bool, 'error' => string|null]
     */
    public static function receiveDonation(int $donorId, float $amount, string $message = ''): array
    {
        if ($amount <= 0) {
            return ['success' => false, 'error' => 'Amount must be greater than 0'];
        }

        $tenantId = TenantContext::getId();

        // Check donor balance
        $stmt = Database::query("SELECT balance FROM users WHERE id = ? AND tenant_id = ?", [$donorId, $tenantId]);
        $donor = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$donor || (float) $donor['balance'] < $amount) {
            return ['success' => false, 'error' => 'Insufficient balance'];
        }

        $fund = self::getOrCreateFund();

        Database::beginTransaction();
        try {
            $newBalance = (float) $fund['balance'] + $amount;

            // Deduct from donor
            $deductStmt = Database::query(
                "UPDATE users SET balance = balance - ? WHERE id = ? AND tenant_id = ? AND balance >= ?",
                [$amount, $donorId, $tenantId, $amount]
            );
            if ($deductStmt->rowCount() === 0) {
                Database::rollback();
                return ['success' => false, 'error' => 'Insufficient balance'];
            }

            // Credit community fund
            Database::query(
                "UPDATE community_fund_accounts
                 SET balance = balance + ?, total_donated = total_donated + ?
                 WHERE id = ? AND tenant_id = ?",
                [$amount, $amount, $fund['id'], $tenantId]
            );

            // Log fund transaction
            Database::query(
                "INSERT INTO community_fund_transactions
                 (tenant_id, fund_id, user_id, type, amount, balance_after, description)
                 VALUES (?, ?, ?, 'donation', ?, ?, ?)",
                [$tenantId, $fund['id'], $donorId, $amount, $newBalance, $message ?: 'Member donation']
            );

            // Log donation record
            Database::query(
                "INSERT INTO credit_donations (tenant_id, donor_id, recipient_type, amount, message)
                 VALUES (?, ?, 'community_fund', ?, ?)",
                [$tenantId, $donorId, $amount, $message]
            );

            // Log wallet transaction for the donor
            Database::query(
                "INSERT INTO transactions (tenant_id, sender_id, receiver_id, amount, description, transaction_type)
                 VALUES (?, ?, ?, ?, ?, 'donation')",
                [$tenantId, $donorId, $donorId, $amount, 'Donation to community fund' . ($message ? ": $message" : '')]
            );

            Database::commit();

            return ['success' => true, 'balance' => $newBalance];
        } catch (\Exception $e) {
            Database::rollback();
            error_log("CommunityFundService::receiveDonation error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Donation failed'];
        }
    }

    /**
     * Get fund transaction history
     *
     * @param int $limit Max records
     * @param int $offset Offset
     * @return array ['items' => [...], 'total' => int]
     */
    public static function getTransactions(int $limit = 20, int $offset = 0): array
    {
        $tenantId = TenantContext::getId();
        $fund = self::getOrCreateFund();

        $countStmt = Database::query(
            "SELECT COUNT(*) as total FROM community_fund_transactions WHERE tenant_id = ? AND fund_id = ?",
            [$tenantId, $fund['id']]
        );
        $total = (int) ($countStmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0);

        $stmt = Database::query(
            "SELECT cft.*,
                    u.name as user_name, u.avatar_url as user_avatar,
                    a.name as admin_name
             FROM community_fund_transactions cft
             LEFT JOIN users u ON cft.user_id = u.id
             LEFT JOIN users a ON cft.admin_id = a.id
             WHERE cft.tenant_id = ? AND cft.fund_id = ?
             ORDER BY cft.created_at DESC
             LIMIT ? OFFSET ?",
            [$tenantId, $fund['id'], $limit, $offset]
        );

        return [
            'items' => $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [],
            'total' => $total,
        ];
    }
}
