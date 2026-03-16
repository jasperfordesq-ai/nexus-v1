<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * OrgWalletController -- Organization wallet (balance, transactions, transfers).
 *
 * All methods require authentication.
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
     * Delegate to legacy controller via output buffering.
     */
    private function delegate(string $legacyClass, string $method, array $params = []): JsonResponse
    {
        $controller = new $legacyClass();
        ob_start();
        $controller->$method(...$params);
        $output = ob_get_clean();
        $status = http_response_code();
        return response()->json(json_decode($output, true) ?: $output, $status ?: 200);
    }


    public function apiMembers($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\OrgWalletController::class, 'apiMembers', [$id]);
    }


    public function apiBalance($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\OrgWalletController::class, 'apiBalance', [$id]);
    }

}
