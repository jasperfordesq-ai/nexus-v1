<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * StartingBalanceService (W7)
 *
 * Manages starting time credit balances for new members.
 * When configured, new members automatically receive initial credits
 * on registration from the community fund.
 *
 * Settings:
 * - `wallet.starting_balance` (float): Amount of initial credits (0 = disabled)
 * - Stored in `tenant_settings` table via TenantSettingsService
 */
class StartingBalanceService
{
    /**
     * Get the configured starting balance for the current tenant
     *
     * @return float Starting balance amount (0 if disabled)
     */
    public static function getStartingBalance(): float
    {
        try {
            $tenantId = TenantContext::getId();
            $value = TenantSettingsService::get($tenantId, 'wallet.starting_balance');
            return max(0, (float) ($value ?? 0));
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Set the starting balance for the current tenant (admin only)
     *
     * @param float $amount Starting balance amount
     */
    public static function setStartingBalance(float $amount): void
    {
        $amount = max(0, $amount);
        TenantSettingsService::set(TenantContext::getId(), 'wallet.starting_balance', (string) $amount, 'float');
    }

    /**
     * Apply starting balance to a newly registered user
     *
     * Should be called during user registration/onboarding.
     * Credits come from the community fund if sufficient, otherwise created fresh.
     *
     * @param int $userId New user's ID
     * @return array ['success' => bool, 'amount' => float, 'source' => string]
     */
    public static function applyToNewUser(int $userId): array
    {
        $amount = self::getStartingBalance();

        if ($amount <= 0) {
            return ['success' => true, 'amount' => 0, 'source' => 'none'];
        }

        $tenantId = TenantContext::getId();

        // Check if starting balance was already applied
        $existingStmt = Database::query(
            "SELECT 1 FROM transactions
             WHERE tenant_id = ? AND receiver_id = ? AND transaction_type = 'starting_balance'",
            [$tenantId, $userId]
        );
        if ($existingStmt->fetch()) {
            return ['success' => true, 'amount' => 0, 'source' => 'already_applied'];
        }

        Database::beginTransaction();
        try {
            // Try to deduct from community fund first
            $source = 'system';
            try {
                $fund = CommunityFundService::getOrCreateFund();
                if ((float) $fund['balance'] >= $amount) {
                    Database::query(
                        "UPDATE community_fund_accounts
                         SET balance = balance - ?, total_withdrawn = total_withdrawn + ?
                         WHERE id = ? AND tenant_id = ? AND balance >= ?",
                        [$amount, $amount, $fund['id'], $tenantId, $amount]
                    );

                    Database::query(
                        "INSERT INTO community_fund_transactions
                         (tenant_id, fund_id, user_id, type, amount, balance_after, description)
                         VALUES (?, ?, ?, 'starting_balance_grant', ?, ?, ?)",
                        [
                            $tenantId,
                            $fund['id'],
                            $userId,
                            $amount,
                            (float) $fund['balance'] - $amount,
                            'Starting balance grant for new member',
                        ]
                    );
                    $source = 'community_fund';
                }
            } catch (\Exception $e) {
                // Community fund tables may not exist — create credits from system
            }

            // Credit the new user
            Database::query(
                "UPDATE users SET balance = balance + ? WHERE id = ? AND tenant_id = ?",
                [$amount, $userId, $tenantId]
            );

            // Create transaction record
            Database::query(
                "INSERT INTO transactions (tenant_id, sender_id, receiver_id, amount, description, transaction_type)
                 VALUES (?, ?, ?, ?, ?, 'starting_balance')",
                [$tenantId, $userId, $userId, $amount, 'Welcome starting balance']
            );

            Database::commit();

            return ['success' => true, 'amount' => $amount, 'source' => $source];
        } catch (\Exception $e) {
            Database::rollback();
            error_log("StartingBalanceService::applyToNewUser error: " . $e->getMessage());
            return ['success' => false, 'amount' => 0, 'source' => 'error'];
        }
    }
}
