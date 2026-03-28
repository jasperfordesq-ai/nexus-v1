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
                'status' => 'completed',
                'created_at' => now(),
                'updated_at' => now(),
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

    /**
     * Donate credits to the community fund.
     *
     * Deducts from user balance and credits the community fund via CommunityFundService.
     *
     * @param int $userId Donor user ID
     * @param float $amount Amount to donate
     * @param string $message Optional message
     * @return array{success: bool, error?: string}
     */
    public function donateToCommunityFund(int $userId, float $amount, string $message = ''): array
    {
        if ($amount <= 0) {
            return ['success' => false, 'error' => 'Amount must be greater than 0'];
        }

        // CommunityFundService::receiveDonation handles balance checks, deduction,
        // fund credit, transaction logging, and credit_donations record creation atomically.
        return CommunityFundService::receiveDonation($userId, $amount, $message);
    }

    /**
     * Donate credits to another member.
     *
     * Delegates to the existing donate() method which handles everything atomically.
     *
     * @param int $userId Donor user ID
     * @param int $recipientId Recipient user ID
     * @param float $amount Amount to donate
     * @param string $message Optional message
     * @return array{success: bool, error?: string}
     */
    public function donateToMember(int $userId, int $recipientId, float $amount, string $message = ''): array
    {
        $tenantId = TenantContext::getId();

        $result = $this->donate($tenantId, $userId, $recipientId, $amount, $message);

        if ($result) {
            return ['success' => true];
        }

        return ['success' => false, 'error' => 'Donation failed. Check balance and recipient.'];
    }

    /**
     * Get paginated donation history for a user (both sent and received).
     *
     * @param int $userId User ID
     * @param int $limit Max records to return
     * @param int $offset Offset for pagination
     * @return array{items: array, total: int}
     */
    public function getDonationHistory(int $userId, int $limit = 20, int $offset = 0): array
    {
        $tenantId = TenantContext::getId();

        $total = (int) DB::table('credit_donations')
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($userId) {
                $query->where('donor_id', $userId)
                    ->orWhere(function ($q) use ($userId) {
                        $q->where('recipient_type', 'user')
                          ->where('recipient_id', $userId);
                    });
            })
            ->count();

        $rows = DB::table('credit_donations as cd')
            ->leftJoin('users as donor', 'cd.donor_id', '=', 'donor.id')
            ->leftJoin('users as recipient', function ($join) {
                $join->on('cd.recipient_id', '=', 'recipient.id')
                     ->where('cd.recipient_type', '=', 'user');
            })
            ->where('cd.tenant_id', $tenantId)
            ->where(function ($query) use ($userId) {
                $query->where('cd.donor_id', $userId)
                    ->orWhere(function ($q) use ($userId) {
                        $q->where('cd.recipient_type', 'user')
                          ->where('cd.recipient_id', $userId);
                    });
            })
            ->orderByDesc('cd.created_at')
            ->offset($offset)
            ->limit($limit)
            ->select(
                'cd.id',
                'cd.donor_id',
                'cd.recipient_type',
                'cd.recipient_id',
                'cd.amount',
                'cd.message',
                'cd.created_at',
                DB::raw("CONCAT(donor.first_name, ' ', donor.last_name) as donor_name"),
                'donor.avatar_url as donor_avatar',
                DB::raw("CONCAT(recipient.first_name, ' ', recipient.last_name) as recipient_name"),
                'recipient.avatar_url as recipient_avatar'
            )
            ->get();

        $items = $rows->map(function ($row) use ($userId) {
            return [
                'id' => (int) $row->id,
                'donor_id' => (int) $row->donor_id,
                'recipient_type' => $row->recipient_type,
                'recipient_id' => $row->recipient_id ? (int) $row->recipient_id : null,
                'amount' => round((float) $row->amount, 2),
                'message' => $row->message ?? '',
                'created_at' => $row->created_at,
                'direction' => $row->donor_id == $userId ? 'sent' : 'received',
                'donor_name' => trim($row->donor_name ?? ''),
                'donor_avatar' => $row->donor_avatar ?? '',
                'recipient_name' => $row->recipient_type === 'community_fund'
                    ? 'Community Fund'
                    : trim($row->recipient_name ?? ''),
                'recipient_avatar' => $row->recipient_avatar ?? '',
            ];
        })->all();

        return ['items' => $items, 'total' => $total];
    }
}
