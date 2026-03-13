<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * FederationCreditService - Cross-community time credit pooling
 *
 * Enables credits to be spent across federated tenants:
 * - Credit agreements between tenant pairs (with exchange rates)
 * - Transfer credits between tenants on behalf of users
 * - Track inter-tenant balances for settlement/reconciliation
 * - Requires admin approval per tenant
 *
 * Tables:
 *   federation_credit_agreements — bilateral agreements
 *   federation_credit_transfers — individual transfers
 *   federation_credit_balances — running net balances
 */
class FederationCreditService
{
    private static array $errors = [];

    public static function getErrors(): array
    {
        return self::$errors;
    }

    // =========================================================================
    // AGREEMENT MANAGEMENT
    // =========================================================================

    /**
     * Create a credit agreement between two tenants
     */
    public static function createAgreement(
        int $fromTenantId,
        int $toTenantId,
        float $exchangeRate = 1.0,
        ?float $maxMonthlyCredits = null,
        int $approvedBy = 0
    ): array {
        self::$errors = [];

        if ($fromTenantId === $toTenantId) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Cannot create agreement with self'];
            return ['success' => false, 'errors' => self::$errors];
        }

        if ($exchangeRate <= 0) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Exchange rate must be positive', 'field' => 'exchange_rate'];
            return ['success' => false, 'errors' => self::$errors];
        }

        // Check for existing active agreement
        $existing = self::getAgreement($fromTenantId, $toTenantId);
        if ($existing && in_array($existing['status'], ['pending', 'active'])) {
            self::$errors[] = ['code' => 'CONFLICT', 'message' => 'An agreement already exists between these tenants'];
            return ['success' => false, 'errors' => self::$errors];
        }

        try {
            Database::query(
                "INSERT INTO federation_credit_agreements (from_tenant_id, to_tenant_id, exchange_rate, max_monthly_credits, status, approved_by_from, created_at)
                 VALUES (?, ?, ?, ?, 'pending', ?, NOW())",
                [$fromTenantId, $toTenantId, $exchangeRate, $maxMonthlyCredits, $approvedBy]
            );

            $id = (int)Database::getInstance()->lastInsertId();

            return ['success' => true, 'agreement_id' => $id];
        } catch (\Exception $e) {
            error_log("FederationCreditService::createAgreement error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to create agreement'];
        }
    }

    /**
     * Approve an agreement (by the receiving tenant's admin)
     */
    public static function approveAgreement(int $agreementId, int $approvedBy): array
    {
        try {
            $agreement = self::getAgreementById($agreementId);
            if (!$agreement) {
                return ['success' => false, 'error' => 'Agreement not found'];
            }
            if ($agreement['status'] !== 'pending') {
                return ['success' => false, 'error' => 'Agreement is not pending approval'];
            }

            Database::query(
                "UPDATE federation_credit_agreements SET status = 'active', approved_by_to = ?, updated_at = NOW() WHERE id = ?",
                [$approvedBy, $agreementId]
            );

            // Initialize balance record
            self::ensureBalanceRecord($agreement['from_tenant_id'], $agreement['to_tenant_id']);

            return ['success' => true, 'message' => 'Agreement approved'];
        } catch (\Exception $e) {
            error_log("FederationCreditService::approveAgreement error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to approve agreement'];
        }
    }

    /**
     * Suspend or terminate an agreement
     */
    public static function updateAgreementStatus(int $agreementId, string $status): array
    {
        if (!in_array($status, ['suspended', 'terminated'])) {
            return ['success' => false, 'error' => 'Invalid status'];
        }

        try {
            Database::query(
                "UPDATE federation_credit_agreements SET status = ?, updated_at = NOW() WHERE id = ?",
                [$status, $agreementId]
            );

            return ['success' => true, 'message' => "Agreement {$status}"];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to update agreement'];
        }
    }

    /**
     * Get an agreement between two tenants
     */
    public static function getAgreement(int $tenantA, int $tenantB): ?array
    {
        try {
            return Database::query(
                "SELECT * FROM federation_credit_agreements
                 WHERE (from_tenant_id = ? AND to_tenant_id = ?) OR (from_tenant_id = ? AND to_tenant_id = ?)
                 ORDER BY created_at DESC LIMIT 1",
                [$tenantA, $tenantB, $tenantB, $tenantA]
            )->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get agreement by ID
     */
    public static function getAgreementById(int $id): ?array
    {
        try {
            return Database::query(
                "SELECT a.*, t1.name as from_tenant_name, t2.name as to_tenant_name
                 FROM federation_credit_agreements a
                 LEFT JOIN tenants t1 ON a.from_tenant_id = t1.id
                 LEFT JOIN tenants t2 ON a.to_tenant_id = t2.id
                 WHERE a.id = ?",
                [$id]
            )->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * List all agreements for a tenant
     */
    public static function listAgreements(int $tenantId, ?string $status = null): array
    {
        try {
            $sql = "SELECT a.*, t1.name as from_tenant_name, t2.name as to_tenant_name
                    FROM federation_credit_agreements a
                    LEFT JOIN tenants t1 ON a.from_tenant_id = t1.id
                    LEFT JOIN tenants t2 ON a.to_tenant_id = t2.id
                    WHERE (a.from_tenant_id = ? OR a.to_tenant_id = ?)";
            $params = [$tenantId, $tenantId];

            if ($status) {
                $sql .= " AND a.status = ?";
                $params[] = $status;
            }

            $sql .= " ORDER BY a.created_at DESC";

            return Database::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    // =========================================================================
    // CREDIT TRANSFERS
    // =========================================================================

    /**
     * Transfer credits from one tenant to another
     *
     * @param int $fromTenantId Source tenant
     * @param int $toTenantId Destination tenant
     * @param int $userId User initiating transfer
     * @param float $amount Amount in source tenant's credits
     * @param string|null $description Optional description
     * @return array Result with transfer details
     */
    public static function transferCredits(
        int $fromTenantId,
        int $toTenantId,
        int $userId,
        float $amount,
        ?string $description = null
    ): array {
        self::$errors = [];

        if ($amount <= 0) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Amount must be positive'];
            return ['success' => false, 'errors' => self::$errors];
        }

        // Get active agreement
        $agreement = self::getAgreement($fromTenantId, $toTenantId);
        if (!$agreement || $agreement['status'] !== 'active') {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'No active credit agreement between these communities'];
            return ['success' => false, 'errors' => self::$errors];
        }

        // Determine exchange rate direction
        $exchangeRate = (float)$agreement['exchange_rate'];
        if ((int)$agreement['from_tenant_id'] !== $fromTenantId) {
            // Reverse direction — invert rate
            $exchangeRate = $exchangeRate > 0 ? (1 / $exchangeRate) : 1.0;
        }

        $convertedAmount = round($amount * $exchangeRate, 2);

        // Check monthly limit
        if ($agreement['max_monthly_credits'] !== null) {
            $monthlyUsed = self::getMonthlyTransferTotal($agreement['id'], $fromTenantId);
            if (($monthlyUsed + $amount) > (float)$agreement['max_monthly_credits']) {
                self::$errors[] = ['code' => 'LIMIT_EXCEEDED', 'message' => 'Monthly transfer limit would be exceeded'];
                return ['success' => false, 'errors' => self::$errors];
            }
        }

        // Check user has sufficient balance
        $userBalance = self::getUserBalance($userId, $fromTenantId);
        if ($userBalance < $amount) {
            self::$errors[] = ['code' => 'INSUFFICIENT_BALANCE', 'message' => 'Insufficient time credits'];
            return ['success' => false, 'errors' => self::$errors];
        }

        $db = Database::getConnection();
        try {
            $db->beginTransaction();

            // Create transfer record
            $db->prepare(
                "INSERT INTO federation_credit_transfers (agreement_id, from_tenant_id, to_tenant_id, user_id, amount, converted_amount, exchange_rate, description, status, created_at, completed_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed', NOW(), NOW())"
            )->execute([
                $agreement['id'], $fromTenantId, $toTenantId, $userId,
                $amount, $convertedAmount, $exchangeRate, $description
            ]);

            $transferId = (int)$db->lastInsertId();

            // Debit source user (deduct from their wallet)
            $db->prepare(
                "UPDATE users SET balance = balance - ? WHERE id = ? AND tenant_id = ? AND balance >= ?"
            )->execute([$amount, $userId, $fromTenantId, $amount]);

            // Update inter-tenant balance
            self::updateBalance($fromTenantId, $toTenantId, $convertedAmount);

            $db->commit();

            return [
                'success' => true,
                'transfer_id' => $transferId,
                'amount' => $amount,
                'converted_amount' => $convertedAmount,
                'exchange_rate' => $exchangeRate,
            ];
        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("FederationCreditService::transferCredits error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Transfer failed'];
        }
    }

    /**
     * Get transfer history
     */
    public static function getTransferHistory(int $tenantId, int $limit = 50): array
    {
        try {
            $limitInt = (int)$limit;
            return Database::query(
                "SELECT t.*, u.name as user_name,
                        t1.name as from_tenant_name, t2.name as to_tenant_name
                 FROM federation_credit_transfers t
                 JOIN users u ON t.user_id = u.id
                 LEFT JOIN tenants t1 ON t.from_tenant_id = t1.id
                 LEFT JOIN tenants t2 ON t.to_tenant_id = t2.id
                 WHERE t.from_tenant_id = ? OR t.to_tenant_id = ?
                 ORDER BY t.created_at DESC
                 LIMIT {$limitInt}",
                [$tenantId, $tenantId]
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    // =========================================================================
    // BALANCE & SETTLEMENT
    // =========================================================================

    /**
     * Get net balance between two tenants
     */
    public static function getBalance(int $tenantA, int $tenantB): float
    {
        try {
            $a = min($tenantA, $tenantB);
            $b = max($tenantA, $tenantB);

            $row = Database::query(
                "SELECT net_balance FROM federation_credit_balances WHERE tenant_id_a = ? AND tenant_id_b = ?",
                [$a, $b]
            )->fetch(\PDO::FETCH_ASSOC);

            if (!$row) {
                return 0.0;
            }

            // If tenantA is the "a" side, balance is as stored. If reversed, negate.
            $balance = (float)$row['net_balance'];
            return $tenantA === $a ? $balance : -$balance;
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    /**
     * Settle balances between two tenants (reset to zero)
     */
    public static function settleBalance(int $tenantA, int $tenantB, int $settledBy): array
    {
        try {
            $a = min($tenantA, $tenantB);
            $b = max($tenantA, $tenantB);

            Database::query(
                "UPDATE federation_credit_balances SET net_balance = 0, last_settlement_at = NOW(), updated_at = NOW()
                 WHERE tenant_id_a = ? AND tenant_id_b = ?",
                [$a, $b]
            );

            FederationAuditService::log(
                'credit_settlement',
                $tenantA,
                $tenantB,
                $settledBy,
                ['action' => 'balance_settled']
            );

            return ['success' => true, 'message' => 'Balance settled'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Settlement failed'];
        }
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Get user's current balance in a tenant
     */
    private static function getUserBalance(int $userId, int $tenantId): float
    {
        try {
            $row = Database::query(
                "SELECT balance FROM users WHERE id = ? AND tenant_id = ?",
                [$userId, $tenantId]
            )->fetch(\PDO::FETCH_ASSOC);

            return (float)($row['balance'] ?? 0);
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    /**
     * Get monthly transfer total for rate limiting
     */
    private static function getMonthlyTransferTotal(int $agreementId, int $fromTenantId): float
    {
        try {
            $row = Database::query(
                "SELECT COALESCE(SUM(amount), 0) as total
                 FROM federation_credit_transfers
                 WHERE agreement_id = ? AND from_tenant_id = ?
                   AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')",
                [$agreementId, $fromTenantId]
            )->fetch(\PDO::FETCH_ASSOC);

            return (float)($row['total'] ?? 0);
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    /**
     * Ensure a balance record exists between two tenants
     */
    private static function ensureBalanceRecord(int $tenantA, int $tenantB): void
    {
        $a = min($tenantA, $tenantB);
        $b = max($tenantA, $tenantB);

        try {
            Database::query(
                "INSERT IGNORE INTO federation_credit_balances (tenant_id_a, tenant_id_b, net_balance) VALUES (?, ?, 0)",
                [$a, $b]
            );
        } catch (\Exception $e) {
            // Already exists
        }
    }

    /**
     * Update inter-tenant balance
     */
    private static function updateBalance(int $fromTenantId, int $toTenantId, float $amount): void
    {
        $a = min($fromTenantId, $toTenantId);
        $b = max($fromTenantId, $toTenantId);

        self::ensureBalanceRecord($a, $b);

        // If from == a, we subtract (a owes b). If from == b, we add (b owes a, reduces a's debt).
        $sign = ($fromTenantId === $a) ? -1 : 1;

        try {
            Database::query(
                "UPDATE federation_credit_balances SET net_balance = net_balance + ?, updated_at = NOW()
                 WHERE tenant_id_a = ? AND tenant_id_b = ?",
                [$sign * $amount, $a, $b]
            );
        } catch (\Exception $e) {
            error_log("FederationCreditService::updateBalance error: " . $e->getMessage());
        }
    }
}
