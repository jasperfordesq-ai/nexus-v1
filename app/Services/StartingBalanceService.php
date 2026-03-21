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
 * StartingBalanceService — native DB query builder implementation.
 *
 * Manages the starting balance (initial time credits) granted to new members
 * of a tenant. The default amount is stored in tenant_settings under the key
 * 'wallet.starting_balance'. When a user is approved/joins, applyToNewUser()
 * credits their wallet once (idempotent).
 */
class StartingBalanceService
{
    private const SETTING_KEY = 'wallet.starting_balance';

    /**
     * Get the configured starting balance for the current tenant.
     *
     * @return float Always >= 0.
     */
    public static function getStartingBalance(): float
    {
        $tenantId = TenantContext::getId();
        $value = TenantSettingsService::get($tenantId, self::SETTING_KEY, '0');

        return max(0.0, (float) $value);
    }

    /**
     * Set the default starting balance for the current tenant.
     *
     * @param float $amount Will be clamped to >= 0.
     */
    public static function setStartingBalance(float $amount): void
    {
        $tenantId = TenantContext::getId();
        $amount = max(0.0, $amount);

        TenantSettingsService::set($tenantId, self::SETTING_KEY, (string) $amount, 'float');
    }

    /**
     * Apply the starting balance to a new user. Idempotent — if a
     * starting_balance transaction already exists for this user, it is a no-op.
     *
     * @param int $userId The user to credit.
     * @return array{success: bool, amount: float, source: string}
     */
    public static function applyToNewUser(int $userId): array
    {
        $tenantId = TenantContext::getId();
        $amount = static::getStartingBalance();

        // If starting balance is zero, nothing to do
        if ($amount <= 0.0) {
            return [
                'success' => true,
                'amount' => 0.0,
                'source' => 'none',
            ];
        }

        // Check idempotency — has this user already received a starting balance?
        $existing = DB::selectOne(
            "SELECT id FROM transactions
             WHERE tenant_id = ? AND receiver_id = ? AND transaction_type = 'starting_balance'
             LIMIT 1",
            [$tenantId, $userId]
        );

        if ($existing) {
            return [
                'success' => true,
                'amount' => 0.0,
                'source' => 'already_applied',
            ];
        }

        try {
            DB::beginTransaction();

            // Create the starting_balance transaction (sender_id=0 for system)
            DB::insert(
                "INSERT INTO transactions (tenant_id, sender_id, receiver_id, amount, description, status, transaction_type, created_at, updated_at)
                 VALUES (?, 0, ?, ?, 'Starting balance credit', 'completed', 'starting_balance', NOW(), NOW())",
                [$tenantId, $userId, $amount]
            );

            // Credit the user's balance
            DB::update(
                "UPDATE users SET balance = balance + ? WHERE id = ? AND tenant_id = ?",
                [$amount, $userId, $tenantId]
            );

            DB::commit();

            return [
                'success' => true,
                'amount' => $amount,
                'source' => 'starting_balance',
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[StartingBalanceService] applyToNewUser failed for user ' . $userId . ': ' . $e->getMessage());

            return [
                'success' => false,
                'amount' => 0.0,
                'source' => 'error',
            ];
        }
    }
}
