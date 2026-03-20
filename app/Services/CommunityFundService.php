<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\CommunityFundAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CommunityFundService — Eloquent/DB query builder service for the community fund.
 *
 * Manages the community fund: a special system account per tenant that holds
 * community-owned time credits. Supports admin deposit/withdraw, member donations,
 * and transaction audit trails.
 */
class CommunityFundService
{
    /**
     * Get or create the community fund for the current tenant.
     */
    public static function getOrCreateFund(): array
    {
        $tenantId = TenantContext::getId();

        $fund = CommunityFundAccount::first();

        if ($fund) {
            return $fund->toArray();
        }

        // Auto-create
        $fund = CommunityFundAccount::create([
            'balance' => 0.00,
            'description' => 'Community time credit fund',
        ]);

        return [
            'id' => $fund->id,
            'tenant_id' => $tenantId,
            'balance' => 0.00,
            'total_deposited' => 0.00,
            'total_withdrawn' => 0.00,
            'total_donated' => 0.00,
            'description' => 'Community time credit fund',
        ];
    }

    /**
     * Get fund balance and statistics.
     */
    public static function getBalance(): array
    {
        $fund = self::getOrCreateFund();

        return [
            'id' => (int) $fund['id'],
            'balance' => (float) $fund['balance'],
            'total_deposited' => (float) ($fund['total_deposited'] ?? 0),
            'total_withdrawn' => (float) ($fund['total_withdrawn'] ?? 0),
            'total_donated' => (float) ($fund['total_donated'] ?? 0),
            'description' => $fund['description'] ?? '',
        ];
    }

    /**
     * Admin deposits credits into the community fund.
     */
    public static function adminDeposit(int $adminId, float $amount, string $description = ''): array
    {
        if ($amount <= 0) {
            return ['success' => false, 'error' => 'Amount must be greater than 0'];
        }

        $tenantId = TenantContext::getId();
        $fund = self::getOrCreateFund();

        DB::beginTransaction();
        try {
            $newBalance = (float) $fund['balance'] + $amount;

            DB::table('community_fund_accounts')
                ->where('id', $fund['id'])
                ->where('tenant_id', $tenantId)
                ->update([
                    'balance' => DB::raw("balance + {$amount}"),
                    'total_deposited' => DB::raw("total_deposited + {$amount}"),
                ]);

            DB::table('community_fund_transactions')->insert([
                'tenant_id' => $tenantId,
                'fund_id' => $fund['id'],
                'user_id' => $adminId,
                'type' => 'deposit',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'description' => $description ?: 'Admin deposit',
                'admin_id' => $adminId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return ['success' => true, 'balance' => $newBalance];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('CommunityFundService::adminDeposit error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Deposit failed'];
        }
    }

    /**
     * Admin withdraws credits from the community fund and grants to a member.
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

        DB::beginTransaction();
        try {
            $newBalance = (float) $fund['balance'] - $amount;

            // Deduct from fund
            DB::table('community_fund_accounts')
                ->where('id', $fund['id'])
                ->where('tenant_id', $tenantId)
                ->where('balance', '>=', $amount)
                ->update([
                    'balance' => DB::raw("balance - {$amount}"),
                    'total_withdrawn' => DB::raw("total_withdrawn + {$amount}"),
                ]);

            // Credit the recipient user
            DB::table('users')
                ->where('id', $recipientId)
                ->where('tenant_id', $tenantId)
                ->update(['balance' => DB::raw("balance + {$amount}")]);

            // Log fund transaction
            DB::table('community_fund_transactions')->insert([
                'tenant_id' => $tenantId,
                'fund_id' => $fund['id'],
                'user_id' => $recipientId,
                'type' => 'withdrawal',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'description' => $description ?: 'Admin grant to member',
                'admin_id' => $adminId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Log wallet transaction for the recipient
            DB::table('transactions')->insert([
                'tenant_id' => $tenantId,
                'sender_id' => $recipientId,
                'receiver_id' => $recipientId,
                'amount' => $amount,
                'description' => $description ?: 'Community fund grant',
                'transaction_type' => 'community_fund',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return ['success' => true, 'balance' => $newBalance];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('CommunityFundService::adminWithdraw error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Withdrawal failed'];
        }
    }

    /**
     * Member donates credits to the community fund.
     */
    public static function receiveDonation(int $donorId, float $amount, string $message = ''): array
    {
        if ($amount <= 0) {
            return ['success' => false, 'error' => 'Amount must be greater than 0'];
        }

        $tenantId = TenantContext::getId();

        // Check donor balance
        $donor = DB::table('users')
            ->where('id', $donorId)
            ->where('tenant_id', $tenantId)
            ->first(['balance']);

        if (!$donor || (float) $donor->balance < $amount) {
            return ['success' => false, 'error' => 'Insufficient balance'];
        }

        $fund = self::getOrCreateFund();

        DB::beginTransaction();
        try {
            $newBalance = (float) $fund['balance'] + $amount;

            // Deduct from donor
            $affected = DB::table('users')
                ->where('id', $donorId)
                ->where('tenant_id', $tenantId)
                ->where('balance', '>=', $amount)
                ->update(['balance' => DB::raw("balance - {$amount}")]);

            if ($affected === 0) {
                DB::rollBack();
                return ['success' => false, 'error' => 'Insufficient balance'];
            }

            // Credit community fund
            DB::table('community_fund_accounts')
                ->where('id', $fund['id'])
                ->where('tenant_id', $tenantId)
                ->update([
                    'balance' => DB::raw("balance + {$amount}"),
                    'total_donated' => DB::raw("total_donated + {$amount}"),
                ]);

            // Log fund transaction
            DB::table('community_fund_transactions')->insert([
                'tenant_id' => $tenantId,
                'fund_id' => $fund['id'],
                'user_id' => $donorId,
                'type' => 'donation',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'description' => $message ?: 'Member donation',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Log donation record
            DB::table('credit_donations')->insert([
                'tenant_id' => $tenantId,
                'donor_id' => $donorId,
                'recipient_type' => 'community_fund',
                'amount' => $amount,
                'message' => $message,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Log wallet transaction for the donor
            DB::table('transactions')->insert([
                'tenant_id' => $tenantId,
                'sender_id' => $donorId,
                'receiver_id' => $donorId,
                'amount' => $amount,
                'description' => 'Donation to community fund' . ($message ? ": $message" : ''),
                'transaction_type' => 'donation',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return ['success' => true, 'balance' => $newBalance];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('CommunityFundService::receiveDonation error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Donation failed'];
        }
    }
}
