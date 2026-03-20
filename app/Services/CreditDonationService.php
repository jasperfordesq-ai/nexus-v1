<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\CreditDonation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CreditDonationService
 *
 * Manages credit donations — distinct from exchanges.
 * Members can donate credits to another member or the community fund.
 *
 * All queries are tenant-scoped via TenantContext::getId().
 */
class CreditDonationService
{
    /**
     * Donate credits from one user to another.
     *
     * @param int $tenantId Tenant ID
     * @param int $fromUserId Donor user ID
     * @param int $toUserId Recipient user ID
     * @param float $amount Amount to donate
     * @param string|null $message Optional message
     * @return bool
     */
    public function donate(int $tenantId, int $fromUserId, int $toUserId, float $amount, ?string $message = null): bool
    {
        if ($amount <= 0 || $fromUserId === $toUserId) {
            return false;
        }

        $donor = User::find($fromUserId);
        if (!$donor || (float) ($donor->balance ?? 0) < $amount) {
            return false;
        }

        $recipient = User::find($toUserId);
        if (!$recipient) {
            return false;
        }

        return DB::transaction(function () use ($tenantId, $fromUserId, $toUserId, $amount, $message) {
            // Atomic deduct
            $affected = DB::table('users')
                ->where('id', $fromUserId)
                ->where('tenant_id', $tenantId)
                ->where('balance', '>=', $amount)
                ->decrement('balance', $amount);

            if ($affected === 0) {
                return false;
            }

            DB::table('users')
                ->where('id', $toUserId)
                ->where('tenant_id', $tenantId)
                ->increment('balance', $amount);

            $transactionId = DB::table('transactions')->insertGetId([
                'tenant_id' => $tenantId,
                'sender_id' => $fromUserId,
                'receiver_id' => $toUserId,
                'amount' => $amount,
                'description' => 'Donation' . ($message ? ": $message" : ''),
                'transaction_type' => 'donation',
                'created_at' => now(),
            ]);

            CreditDonation::create([
                'tenant_id' => $tenantId,
                'donor_id' => $fromUserId,
                'recipient_type' => 'user',
                'recipient_id' => $toUserId,
                'amount' => $amount,
                'message' => $message ?? '',
                'transaction_id' => $transactionId,
            ]);

            return true;
        });
    }

    /**
     * Get donation history for a user.
     *
     * @param int $tenantId Tenant ID
     * @param int $userId User ID
     * @param string $direction 'sent' or 'received'
     * @return array
     */
    public function getDonations(int $tenantId, int $userId, string $direction = 'sent'): array
    {
        $query = CreditDonation::with(['donor:id,name,avatar_url', 'recipient:id,name,avatar_url'])
            ->where('tenant_id', $tenantId);

        if ($direction === 'sent') {
            $query->where('donor_id', $userId);
        } else {
            $query->where('recipient_type', 'user')->where('recipient_id', $userId);
        }

        return $query->orderByDesc('created_at')->get()->toArray();
    }

    /**
     * Get total amount donated by a user.
     *
     * @param int $tenantId Tenant ID
     * @param int $userId User ID
     * @return float
     */
    public function getTotalDonated(int $tenantId, int $userId): float
    {
        return (float) CreditDonation::where('tenant_id', $tenantId)
            ->where('donor_id', $userId)
            ->sum('amount');
    }
}
