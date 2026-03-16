<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\WalletService;
use Illuminate\Http\JsonResponse;

/**
 * WalletController - Time-credit wallet operations.
 *
 * Endpoints (v2):
 *   GET   /api/v2/wallet/balance       balance()
 *   GET   /api/v2/wallet/transactions  transactions()
 *   POST  /api/v2/wallet/transfer      transfer()
 */
class WalletController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly WalletService $walletService,
    ) {}

    /**
     * Get the current user's time-credit balance.
     */
    public function balance(): JsonResponse
    {
        $userId = $this->requireAuth();

        $balance = $this->walletService->getBalance($userId);

        return $this->respondWithData($balance);
    }

    /**
     * List transaction history for the authenticated user.
     */
    public function transactions(): JsonResponse
    {
        $userId = $this->requireAuth();

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];
        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }
        if ($this->query('type')) {
            $filters['type'] = $this->query('type');
        }

        $result = $this->walletService->getTransactions($userId, $filters);

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'] ?? null,
            $filters['limit'],
            $result['has_more'] ?? false
        );
    }

    /**
     * Transfer time credits to another user. Requires authentication.
     */
    public function transfer(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('wallet_transfer', 10, 60);

        $result = $this->walletService->transfer($userId, $this->getAllInput());

        return $this->respondWithData($result, null, 201);
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


    public function balanceV2(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\WalletApiController::class, 'balanceV2');
    }


    public function transactionsV2(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\WalletApiController::class, 'transactionsV2');
    }


    public function showTransaction($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\WalletApiController::class, 'showTransaction', [$id]);
    }


    public function transferV2(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\WalletApiController::class, 'transferV2');
    }


    public function destroyTransaction($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\WalletApiController::class, 'destroyTransaction', [$id]);
    }


    public function userSearchV2(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\WalletApiController::class, 'userSearchV2');
    }


    public function pendingCount(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\WalletApiController::class, 'pendingCount');
    }


    public function delete(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\WalletApiController::class, 'delete');
    }


    public function userSearch(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\WalletApiController::class, 'userSearch');
    }

}
