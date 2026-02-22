<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\OrgNotificationService;

/**
 * OrgWallet Model
 *
 * Handles organization wallet balance operations.
 * Each organization has its own separate balance from the owner's personal balance.
 */
class OrgWallet
{
    /**
     * Get organization wallet balance
     */
    public static function getBalance($organizationId)
    {
        $tenantId = TenantContext::getId();

        $result = Database::query(
            "SELECT balance FROM org_wallets
             WHERE tenant_id = ? AND organization_id = ?",
            [$tenantId, $organizationId]
        )->fetch();

        return $result ? (float) $result['balance'] : 0.0;
    }

    /**
     * Get or create wallet for an organization
     */
    public static function getOrCreate($organizationId)
    {
        $tenantId = TenantContext::getId();

        $wallet = Database::query(
            "SELECT * FROM org_wallets
             WHERE tenant_id = ? AND organization_id = ?",
            [$tenantId, $organizationId]
        )->fetch();

        if (!$wallet) {
            Database::query(
                "INSERT INTO org_wallets (tenant_id, organization_id, balance)
                 VALUES (?, ?, 0)",
                [$tenantId, $organizationId]
            );

            $wallet = [
                'id' => Database::getInstance()->lastInsertId(),
                'tenant_id' => $tenantId,
                'organization_id' => $organizationId,
                'balance' => 0.0,
            ];
        }

        return $wallet;
    }

    /**
     * Credit (add) to organization wallet
     *
     * @param int $organizationId
     * @param float $amount Must be positive
     * @return bool
     */
    public static function credit($organizationId, $amount)
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Credit amount must be positive');
        }

        $tenantId = TenantContext::getId();

        // Upsert pattern - create wallet if doesn't exist
        Database::query(
            "INSERT INTO org_wallets (tenant_id, organization_id, balance)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE balance = balance + ?, updated_at = NOW()",
            [$tenantId, $organizationId, $amount, $amount]
        );

        return true;
    }

    /**
     * Debit (subtract) from organization wallet
     *
     * @param int $organizationId
     * @param float $amount Must be positive
     * @return bool
     * @throws \Exception If insufficient funds
     */
    public static function debit($organizationId, $amount)
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Debit amount must be positive');
        }

        $tenantId = TenantContext::getId();

        // Atomic guard — prevents double-spend race condition
        $stmt = Database::query(
            "UPDATE org_wallets SET balance = balance - ?, updated_at = NOW()
             WHERE tenant_id = ? AND organization_id = ? AND balance >= ?",
            [$amount, $tenantId, $organizationId, $amount]
        );

        if ($stmt->rowCount() === 0) {
            throw new \Exception('Insufficient organization wallet balance');
        }

        return true;
    }

    /**
     * Transfer from user to organization wallet
     *
     * @param int $userId User making the deposit
     * @param int $organizationId Target organization
     * @param float $amount Amount to transfer
     * @param string $description Transaction description
     * @return int Transaction ID
     */
    public static function depositFromUser($userId, $organizationId, $amount, $description = '')
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        $pdo = Database::getInstance();
        $pdo->beginTransaction();

        try {
            $tenantId = TenantContext::getId();

            // Deduct from user — atomic guard prevents double-spend
            $stmt = Database::query(
                "UPDATE users SET balance = balance - ? WHERE id = ? AND tenant_id = ? AND balance >= ?",
                [$amount, $userId, $tenantId, $amount]
            );

            if ($stmt->rowCount() === 0) {
                throw new \Exception('Insufficient user balance');
            }

            // Credit to organization
            self::credit($organizationId, $amount);

            // Log the transaction
            $transactionId = OrgTransaction::log(
                $organizationId,
                'user',
                $userId,
                'organization',
                $organizationId,
                $amount,
                $description ?: 'Deposit to organization wallet'
            );

            // Activity log
            ActivityLog::log($userId, 'org_deposit', "Deposited $amount credits to organization wallet");

            // Notify admins about the deposit (email)
            OrgNotificationService::notifyDepositReceived($organizationId, $userId, $amount, $description);

            $pdo->commit();
            return $transactionId;

        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Transfer from organization wallet to user
     *
     * @param int $organizationId Source organization
     * @param int $userId Target user
     * @param float $amount Amount to transfer
     * @param string $description Transaction description
     * @param int|null $transferRequestId Optional transfer request this fulfills
     * @return int Transaction ID
     */
    public static function withdrawToUser($organizationId, $userId, $amount, $description = '', $transferRequestId = null)
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        $pdo = Database::getInstance();
        $ownTransaction = !$pdo->inTransaction();

        if ($ownTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $tenantId = TenantContext::getId();

            // Check org has sufficient balance
            $balance = self::getBalance($organizationId);
            if ($balance < $amount) {
                throw new \Exception('Insufficient organization wallet balance');
            }

            // Deduct from organization
            self::debit($organizationId, $amount);

            // Credit to user — scoped by tenant_id
            Database::query(
                "UPDATE users SET balance = balance + ? WHERE id = ? AND tenant_id = ?",
                [$amount, $userId, $tenantId]
            );

            // Log the transaction
            $transactionId = OrgTransaction::log(
                $organizationId,
                'organization',
                $organizationId,
                'user',
                $userId,
                $amount,
                $description ?: 'Withdrawal from organization wallet',
                $transferRequestId
            );

            // Activity log
            ActivityLog::log($userId, 'org_withdrawal', "Received $amount credits from organization wallet");

            // Notification to user (platform)
            $org = VolOrganization::find($organizationId);
            $orgName = $org ? $org['name'] : 'Organization';
            Notification::create(
                $userId,
                "You received $amount time credits from $orgName",
                TenantContext::getBasePath() . '/wallet',
                'transaction'
            );

            // Email notification to recipient
            OrgNotificationService::notifyPaymentReceived($userId, $organizationId, $amount, $description);

            if ($ownTransaction) {
                $pdo->commit();
            }
            return $transactionId;

        } catch (\Exception $e) {
            if ($ownTransaction) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Get wallet transaction history for an organization
     */
    public static function getTransactionHistory($organizationId, $limit = 50, $offset = 0)
    {
        $tenantId = TenantContext::getId();
        $limit = (int) $limit;
        $offset = (int) $offset;

        return Database::query(
            "SELECT ot.*,
                    CASE WHEN ot.sender_type = 'user' THEN CONCAT(su.first_name, ' ', su.last_name) ELSE 'Organization' END as sender_name,
                    CASE WHEN ot.receiver_type = 'user' THEN CONCAT(ru.first_name, ' ', ru.last_name) ELSE 'Organization' END as receiver_name
             FROM org_transactions ot
             LEFT JOIN users su ON ot.sender_type = 'user' AND ot.sender_id = su.id
             LEFT JOIN users ru ON ot.receiver_type = 'user' AND ot.receiver_id = ru.id
             WHERE ot.tenant_id = ? AND ot.organization_id = ?
             ORDER BY ot.created_at DESC
             LIMIT $limit OFFSET $offset",
            [$tenantId, $organizationId]
        )->fetchAll();
    }

    /**
     * Get total credits received by organization
     */
    public static function getTotalReceived($organizationId)
    {
        $tenantId = TenantContext::getId();

        $result = Database::query(
            "SELECT COALESCE(SUM(amount), 0) as total FROM org_transactions
             WHERE tenant_id = ? AND organization_id = ?
             AND receiver_type = 'organization' AND receiver_id = ?",
            [$tenantId, $organizationId, $organizationId]
        )->fetch();

        return (float) ($result['total'] ?? 0);
    }

    /**
     * Get total credits paid out by organization
     */
    public static function getTotalPaidOut($organizationId)
    {
        $tenantId = TenantContext::getId();

        $result = Database::query(
            "SELECT COALESCE(SUM(amount), 0) as total FROM org_transactions
             WHERE tenant_id = ? AND organization_id = ?
             AND sender_type = 'organization' AND sender_id = ?",
            [$tenantId, $organizationId, $organizationId]
        )->fetch();

        return (float) ($result['total'] ?? 0);
    }

    /**
     * Get total transaction count for organization
     */
    public static function getTransactionCount($organizationId)
    {
        $tenantId = TenantContext::getId();

        $result = Database::query(
            "SELECT COUNT(*) as count FROM org_transactions
             WHERE tenant_id = ? AND organization_id = ?",
            [$tenantId, $organizationId]
        )->fetch();

        return (int) ($result['count'] ?? 0);
    }
}
