<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * OrgWalletController -- Organization wallet (balance, transactions, transfers, members).
 *
 * All endpoints migrated to native DB facade — no legacy delegation.
 */
class OrgWalletController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct() {}

    /** GET /api/v2/organizations/{orgId}/wallet/balance */
    public function balance(int $orgId): JsonResponse
    {
        $this->requireAuth();
        $tenantId = $this->getTenantId();

        $org = DB::selectOne(
            'SELECT id FROM organizations WHERE id = ? AND tenant_id = ?',
            [$orgId, $tenantId]
        );

        if ($org === null) {
            return $this->respondWithError('NOT_FOUND', 'Organization not found', null, 404);
        }

        $wallet = DB::selectOne(
            'SELECT balance, currency, updated_at FROM org_wallets WHERE org_id = ? AND tenant_id = ?',
            [$orgId, $tenantId]
        );

        return $this->respondWithData($wallet ?? ['balance' => 0, 'currency' => 'hours', 'updated_at' => null]);
    }

    /** GET /api/v2/organizations/{orgId}/wallet/transactions */
    public function transactions(int $orgId): JsonResponse
    {
        $this->requireAuth();
        $tenantId = $this->getTenantId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $offset = ($page - 1) * $perPage;

        $org = DB::selectOne(
            'SELECT id FROM organizations WHERE id = ? AND tenant_id = ?',
            [$orgId, $tenantId]
        );

        if ($org === null) {
            return $this->respondWithError('NOT_FOUND', 'Organization not found', null, 404);
        }

        $items = DB::select(
            'SELECT * FROM org_wallet_transactions WHERE org_id = ? AND tenant_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?',
            [$orgId, $tenantId, $perPage, $offset]
        );
        $total = DB::selectOne(
            'SELECT COUNT(*) as cnt FROM org_wallet_transactions WHERE org_id = ? AND tenant_id = ?',
            [$orgId, $tenantId]
        )->cnt;

        return $this->respondWithPaginatedCollection($items, (int) $total, $page, $perPage);
    }

    /** POST /api/v2/organizations/wallet/transfer */
    public function transfer(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();
        $this->rateLimit('org_wallet_transfer', 10, 60);

        $fromOrgId = (int) $this->requireInput('from_org_id');
        $toOrgId = (int) $this->requireInput('to_org_id');
        $amount = (float) $this->requireInput('amount');
        $note = $this->input('note', '');

        if ($amount <= 0) {
            return $this->respondWithError('VALIDATION_ERROR', 'Amount must be positive', 'amount');
        }

        $fromWallet = DB::selectOne(
            'SELECT balance FROM org_wallets WHERE org_id = ? AND tenant_id = ?',
            [$fromOrgId, $tenantId]
        );

        if ($fromWallet === null || $fromWallet->balance < $amount) {
            return $this->respondWithError('INSUFFICIENT_BALANCE', 'Insufficient balance for transfer');
        }

        DB::beginTransaction();
        try {
            DB::update('UPDATE org_wallets SET balance = balance - ? WHERE org_id = ? AND tenant_id = ?', [$amount, $fromOrgId, $tenantId]);
            DB::statement(
                'INSERT INTO org_wallets (org_id, tenant_id, balance, currency) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE balance = balance + ?',
                [$toOrgId, $tenantId, $amount, 'hours', $amount]
            );
            DB::insert(
                'INSERT INTO org_wallet_transactions (tenant_id, org_id, type, amount, counterparty_org_id, note, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
                [$tenantId, $fromOrgId, 'transfer_out', -$amount, $toOrgId, $note, $userId]
            );
            DB::insert(
                'INSERT INTO org_wallet_transactions (tenant_id, org_id, type, amount, counterparty_org_id, note, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
                [$tenantId, $toOrgId, 'transfer_in', $amount, $fromOrgId, $note, $userId]
            );
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->respondWithError('TRANSFER_FAILED', 'Transfer failed', null, 500);
        }

        return $this->respondWithData(['from_org_id' => $fromOrgId, 'to_org_id' => $toOrgId, 'amount' => $amount]);
    }

    /**
     * GET /api/v2/organizations/{orgId}/members
     *
     * List active members of an organization.
     * Response: { "success": true, "members": [ { id, name, email, avatar_url, role }, ... ] }
     */
    public function apiMembers(int $orgId): JsonResponse
    {
        $this->requireAuth();
        $tenantId = $this->getTenantId();

        $members = DB::table('org_members as om')
            ->join('users as u', 'om.user_id', '=', 'u.id')
            ->where('om.tenant_id', $tenantId)
            ->where('om.organization_id', $orgId)
            ->where('om.status', 'active')
            ->orderByRaw("FIELD(om.role, 'owner', 'admin', 'member')")
            ->orderBy('u.first_name')
            ->select(
                'om.user_id',
                DB::raw("CONCAT(u.first_name, ' ', u.last_name) as display_name"),
                'u.email',
                'u.avatar_url',
                'om.role'
            )
            ->get();

        $result = $members->map(fn ($m) => [
            'id'         => (int) $m->user_id,
            'name'       => $m->display_name,
            'email'      => $m->email,
            'avatar_url' => $m->avatar_url ?? '',
            'role'       => $m->role,
        ])->all();

        return response()->json(['success' => true, 'members' => $result]);
    }

    /**
     * GET /api/v2/organizations/{orgId}/wallet/api-balance
     *
     * Live wallet balance for an organization.
     * Response: { "success": true, "balance": 42.5, "pending_count": 3, "timestamp": 1234567890 }
     */
    public function apiBalance(int $orgId): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        // Check if user is admin or org member
        $user = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->first(['role']);

        $isAdmin = $user && in_array($user->role ?? '', ['super_admin', 'admin', 'tenant_admin']);

        if (! $isAdmin) {
            $isMember = DB::table('org_members')
                ->where('tenant_id', $tenantId)
                ->where('organization_id', $orgId)
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->exists();

            if (! $isMember) {
                return response()->json(['success' => false, 'error' => 'Access denied'], 403);
            }
        }

        $walletRow = DB::table('org_wallets')
            ->where('tenant_id', $tenantId)
            ->where('organization_id', $orgId)
            ->first(['balance']);

        $balance = $walletRow ? (float) $walletRow->balance : 0.0;

        $pendingCount = (int) DB::table('org_transfer_requests')
            ->where('tenant_id', $tenantId)
            ->where('organization_id', $orgId)
            ->where('status', 'pending')
            ->count();

        return response()->json([
            'success'       => true,
            'balance'       => $balance,
            'pending_count' => $pendingCount,
            'timestamp'     => time(),
        ]);
    }
}
