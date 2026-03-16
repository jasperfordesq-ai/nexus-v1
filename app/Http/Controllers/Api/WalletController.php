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
}
