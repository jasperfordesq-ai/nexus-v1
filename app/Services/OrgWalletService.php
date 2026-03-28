<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * OrgWalletService — Laravel DI-based service for organization wallet operations.
 *
 * Manages organization time-credit balances and inter-org transfers.
 * Provides both instance methods (for DI) and static methods (for legacy callers/tests).
 */
class OrgWalletService
{
    /**
     * Get the balance for an organization.
     */
    public function getBalance(int $orgId, int $tenantId): array
    {
        $org = DB::table('organizations')
            ->where('id', $orgId)
            ->where('tenant_id', $tenantId)
            ->first(['id', 'name', 'balance']);

        if (! $org) {
            return ['balance' => 0.0, 'org_id' => $orgId];
        }

        return ['balance' => (float) $org->balance, 'org_id' => $orgId, 'name' => $org->name];
    }

    /**
     * Get transactions for an organization.
     */
    public function getTransactions(int $orgId, int $tenantId, int $limit = 20, int $offset = 0): array
    {
        $query = DB::table('org_transactions')
            ->where('org_id', $orgId)
            ->where('tenant_id', $tenantId);

        $total = $query->count();
        $items = $query->orderByDesc('created_at')
            ->offset($offset)
            ->limit(min($limit, 100))
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Transfer credits between organizations.
     */
    public function transfer(int $fromOrgId, int $toOrgId, float $amount, int $tenantId, ?string $note = null): bool
    {
        if ($amount <= 0 || $fromOrgId === $toOrgId) {
            return false;
        }

        return DB::transaction(function () use ($fromOrgId, $toOrgId, $amount, $tenantId, $note) {
            $from = DB::table('organizations')
                ->where('id', $fromOrgId)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->first();

            if (! $from || (float) $from->balance < $amount) {
                return false;
            }

            DB::table('organizations')->where('id', $fromOrgId)->where('tenant_id', $tenantId)->decrement('balance', $amount);
            DB::table('organizations')->where('id', $toOrgId)->where('tenant_id', $tenantId)->increment('balance', $amount);

            $now = now();
            DB::table('org_transactions')->insert([
                ['org_id' => $fromOrgId, 'tenant_id' => $tenantId, 'type' => 'transfer_out', 'amount' => -$amount, 'related_org_id' => $toOrgId, 'note' => $note, 'created_at' => $now],
                ['org_id' => $toOrgId, 'tenant_id' => $tenantId, 'type' => 'transfer_in', 'amount' => $amount, 'related_org_id' => $fromOrgId, 'note' => $note, 'created_at' => $now],
            ]);

            return true;
        });
    }

    // =========================================================================
    // STATIC METHODS — Legacy API for backward compatibility
    // =========================================================================

    /**
     * Deposit time credits from a user to an organization wallet.
     */
    public static function depositToOrg(int $userId, int $orgId, float $amount, ?string $note = null): array
    {
        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Amount must be greater than zero'];
        }

        $tenantId = TenantContext::getId();

        // Check membership
        $member = DB::table('org_members')
            ->where('organization_id', $orgId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        if (! $member) {
            return ['success' => false, 'message' => 'User is not an active member of this organization'];
        }

        return DB::transaction(function () use ($userId, $orgId, $amount, $note, $tenantId) {
            // Check user balance
            $user = DB::table('users')->where('id', $userId)->where('tenant_id', $tenantId)->lockForUpdate()->first();
            if (! $user || (float) $user->balance < $amount) {
                return ['success' => false, 'message' => 'Insufficient balance'];
            }

            // Deduct from user
            DB::table('users')->where('id', $userId)->where('tenant_id', $tenantId)->decrement('balance', $amount);

            // Credit to org wallet
            DB::table('org_wallets')
                ->where('organization_id', $orgId)
                ->where('tenant_id', $tenantId)
                ->increment('balance', $amount);

            // Record transaction
            $now = now();
            DB::table('org_transactions')->insert([
                'tenant_id' => $tenantId,
                'organization_id' => $orgId,
                'user_id' => $userId,
                'type' => 'deposit',
                'amount' => $amount,
                'description' => $note,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return ['success' => true];
        });
    }

    /**
     * Create a transfer request from an organization wallet to a user.
     */
    public static function createTransferRequest(int $orgId, int $requesterId, int $recipientId, float $amount, ?string $description = null): array
    {
        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Amount must be greater than zero'];
        }

        $tenantId = TenantContext::getId();

        // Check requester is a member
        $member = DB::table('org_members')
            ->where('organization_id', $orgId)
            ->where('user_id', $requesterId)
            ->where('status', 'active')
            ->first();

        if (! $member) {
            return ['success' => false, 'message' => 'Requester is not an active member of this organization'];
        }

        // Check org wallet balance
        $wallet = DB::table('org_wallets')
            ->where('organization_id', $orgId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $wallet || (float) $wallet->balance < $amount) {
            return ['success' => false, 'message' => 'Insufficient organization wallet balance'];
        }

        $now = now();
        $requestId = DB::table('org_transfer_requests')->insertGetId([
            'organization_id' => $orgId,
            'requester_id' => $requesterId,
            'recipient_id' => $recipientId,
            'amount' => $amount,
            'description' => $description,
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['success' => true, 'request_id' => $requestId];
    }

    /**
     * Approve a transfer request (admin/owner only).
     */
    public static function approveRequest(int $requestId, int $approverId): array
    {
        $tenantId = TenantContext::getId();
        $request = DB::table('org_transfer_requests')->where('id', $requestId)->where('tenant_id', $tenantId)->first();
        if (! $request || $request->status !== 'pending') {
            return ['success' => false, 'message' => 'Transfer request not found or not pending'];
        }

        // Check approver is admin or owner
        $approverMember = DB::table('org_members')
            ->where('organization_id', $request->organization_id)
            ->where('user_id', $approverId)
            ->where('status', 'active')
            ->whereIn('role', ['admin', 'owner'])
            ->first();

        if (! $approverMember) {
            return ['success' => false, 'message' => 'Only admin or owner can approve requests'];
        }

        return DB::transaction(function () use ($request, $requestId, $approverId, $tenantId) {
            // Check wallet balance
            $wallet = DB::table('org_wallets')
                ->where('organization_id', $request->organization_id)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->first();

            if (! $wallet || (float) $wallet->balance < (float) $request->amount) {
                return ['success' => false, 'message' => 'Insufficient organization wallet balance'];
            }

            // Deduct from org wallet
            DB::table('org_wallets')
                ->where('organization_id', $request->organization_id)
                ->where('tenant_id', $tenantId)
                ->decrement('balance', $request->amount);

            // Credit to recipient
            DB::table('users')
                ->where('id', $request->recipient_id)
                ->where('tenant_id', $tenantId)
                ->increment('balance', $request->amount);

            // Update request status
            DB::table('org_transfer_requests')
                ->where('id', $requestId)
                ->update([
                    'status' => 'approved',
                    'approved_by' => $approverId,
                    'approved_at' => now(),
                    'updated_at' => now(),
                ]);

            // Record transaction
            $now = now();
            DB::table('org_transactions')->insert([
                'organization_id' => $request->organization_id,
                'user_id' => $request->recipient_id,
                'type' => 'transfer_out',
                'amount' => -$request->amount,
                'description' => "Transfer to user #{$request->recipient_id}",
                'transfer_request_id' => $requestId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return ['success' => true];
        });
    }

    /**
     * Reject a transfer request (admin/owner only).
     */
    public static function rejectRequest(int $requestId, int $adminId, ?string $reason = null): array
    {
        $tenantId = TenantContext::getId();
        $request = DB::table('org_transfer_requests')->where('id', $requestId)->where('tenant_id', $tenantId)->first();
        if (! $request || $request->status !== 'pending') {
            return ['success' => false, 'message' => 'Transfer request not found or not pending'];
        }

        // Check admin/owner role
        $adminMember = DB::table('org_members')
            ->where('organization_id', $request->organization_id)
            ->where('user_id', $adminId)
            ->where('status', 'active')
            ->whereIn('role', ['admin', 'owner'])
            ->first();

        if (! $adminMember) {
            return ['success' => false, 'message' => 'Only admin or owner can reject requests'];
        }

        DB::table('org_transfer_requests')
            ->where('id', $requestId)
            ->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'approved_by' => $adminId,
                'updated_at' => now(),
            ]);

        return ['success' => true];
    }

    /**
     * Cancel a transfer request (by the requester).
     */
    public static function cancelRequest(int $requestId, int $userId): array
    {
        $tenantId = TenantContext::getId();
        $request = DB::table('org_transfer_requests')->where('id', $requestId)->where('tenant_id', $tenantId)->first();
        if (! $request || $request->status !== 'pending') {
            return ['success' => false, 'message' => 'Transfer request not found or not pending'];
        }

        if ((int) $request->requester_id !== $userId) {
            return ['success' => false, 'message' => 'Only the requester can cancel this request'];
        }

        DB::table('org_transfer_requests')
            ->where('id', $requestId)
            ->update([
                'status' => 'cancelled',
                'updated_at' => now(),
            ]);

        return ['success' => true];
    }

    /**
     * Direct transfer from organization wallet to a user (admin/owner only, no approval needed).
     */
    public static function directTransferFromOrg(int $orgId, int $recipientId, float $amount, ?string $note = null, ?int $adminId = null): array
    {
        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Amount must be greater than zero'];
        }

        // Check admin/owner role
        if ($adminId !== null) {
            $adminMember = DB::table('org_members')
                ->where('organization_id', $orgId)
                ->where('user_id', $adminId)
                ->where('status', 'active')
                ->whereIn('role', ['admin', 'owner'])
                ->first();

            if (! $adminMember) {
                return ['success' => false, 'message' => 'Only admin or owner can perform direct transfers'];
            }
        }

        $tenantId = TenantContext::getId();

        return DB::transaction(function () use ($orgId, $recipientId, $amount, $note, $adminId, $tenantId) {
            // Check wallet balance
            $wallet = DB::table('org_wallets')
                ->where('organization_id', $orgId)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->first();

            if (! $wallet || (float) $wallet->balance < $amount) {
                return ['success' => false, 'message' => 'Insufficient organization wallet balance'];
            }

            // Deduct from org wallet
            DB::table('org_wallets')
                ->where('organization_id', $orgId)
                ->where('tenant_id', $tenantId)
                ->decrement('balance', $amount);

            // Credit to recipient
            DB::table('users')
                ->where('id', $recipientId)
                ->where('tenant_id', $tenantId)
                ->increment('balance', $amount);

            // Record transaction
            $now = now();
            DB::table('org_transactions')->insert([
                'organization_id' => $orgId,
                'user_id' => $recipientId,
                'type' => 'direct_transfer',
                'amount' => -$amount,
                'description' => $note,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return ['success' => true];
        });
    }

    /**
     * Get a wallet summary for an organization.
     */
    public static function getWalletSummary(int $orgId): array
    {
        $tenantId = TenantContext::getId();

        $wallet = DB::table('org_wallets')
            ->where('organization_id', $orgId)
            ->where('tenant_id', $tenantId)
            ->first();

        $balance = $wallet ? (float) $wallet->balance : 0.0;

        $totalReceived = (float) DB::table('org_transactions')
            ->where('organization_id', $orgId)
            ->where('tenant_id', $tenantId)
            ->where('amount', '>', 0)
            ->sum('amount');

        $totalPaidOut = (float) abs(DB::table('org_transactions')
            ->where('organization_id', $orgId)
            ->where('tenant_id', $tenantId)
            ->where('amount', '<', 0)
            ->sum('amount'));

        $transactionCount = (int) DB::table('org_transactions')
            ->where('organization_id', $orgId)
            ->where('tenant_id', $tenantId)
            ->count();

        $pendingRequests = (int) DB::table('org_transfer_requests')
            ->where('organization_id', $orgId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->count();

        return [
            'balance' => $balance,
            'total_received' => $totalReceived,
            'total_paid_out' => $totalPaidOut,
            'transaction_count' => $transactionCount,
            'pending_requests' => $pendingRequests,
        ];
    }
}
