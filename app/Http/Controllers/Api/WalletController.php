<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Events\TransactionCompleted;
use App\Models\Transaction;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;

/**
 * WalletController - Time-credit wallet operations.
 *
 * Endpoints (v2):
 *   GET    /api/v2/wallet/balance              balance()
 *   GET    /api/v2/wallet/transactions         transactions()
 *   GET    /api/v2/wallet/transactions/{id}    showTransaction()
 *   POST   /api/v2/wallet/transfer             transfer()
 *   DELETE /api/v2/wallet/transactions/{id}    destroyTransaction()
 *   GET    /api/v2/wallet/user-search          userSearch()
 *   GET    /api/v2/wallet/pending-count        pendingCount()
 */
class WalletController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly WalletService $walletService,
    ) {}

    // -----------------------------------------------------------------
    //  GET /api/v2/wallet/balance
    // -----------------------------------------------------------------

    /**
     * Get the current user's time-credit balance with summary stats.
     */
    public function balance(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('wallet_balance', 60, 60);

        $balance = $this->walletService->getBalance($userId);

        return $this->respondWithData($balance);
    }

    // -----------------------------------------------------------------
    //  GET /api/v2/wallet/transactions
    // -----------------------------------------------------------------

    /**
     * List transaction history for the authenticated user.
     *
     * Query params: type (all|sent|received), cursor, per_page (default 20, max 100).
     */
    public function transactions(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('wallet_transactions', 30, 60);

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

    // -----------------------------------------------------------------
    //  GET /api/v2/wallet/transactions/{id}
    // -----------------------------------------------------------------

    /**
     * Get a single transaction by ID.
     */
    public function showTransaction(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('wallet_transactions', 60, 60);

        $transaction = $this->walletService->getTransaction($id, $userId);

        if ($transaction === null) {
            return $this->respondWithError('NOT_FOUND', __('api.transaction_not_found'), null, 404);
        }

        return $this->respondWithData($transaction);
    }

    // -----------------------------------------------------------------
    //  POST /api/v2/wallet/transfer
    // -----------------------------------------------------------------

    /**
     * Transfer time credits to another user.
     *
     * Body: recipient (user_id, username, or email), amount, description.
     */
    public function transfer(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('wallet_transfer', 10, 60);

        try {
            $result = $this->walletService->transfer($userId, $this->getAllInput());
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 400);
        } catch (\RuntimeException $e) {
            $code = 'TRANSFER_FAILED';
            $status = 422;
            $msg = $e->getMessage();

            if (str_contains($msg, 'not found')) {
                $code = 'NOT_FOUND';
                $status = 404;
            } elseif (str_contains($msg, 'Insufficient')) {
                $code = 'INSUFFICIENT_FUNDS';
                $status = 400;
            } elseif (str_contains($msg, 'yourself')) {
                $code = 'VALIDATION_ERROR';
                $status = 400;
            }

            return $this->respondWithError($code, $msg, null, $status);
        }

        // Dispatch TransactionCompleted event (handles XP awards via UpdateWalletBalance listener)
        try {
            $transactionId = (int) ($result['id'] ?? 0);
            $receiverId = (int) ($result['receiver']['id'] ?? 0);
            if ($transactionId > 0 && $receiverId > 0) {
                $txn = Transaction::find($transactionId);
                $sender = User::find($userId);
                $receiver = User::find($receiverId);
                if ($txn && $sender && $receiver) {
                    event(new TransactionCompleted($txn, $sender, $receiver, $this->getTenantId()));
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('TransactionCompleted event dispatch failed', ['transaction' => $result['id'] ?? null, 'error' => $e->getMessage()]);
        }

        return $this->respondWithData($result, null, 201);
    }

    // -----------------------------------------------------------------
    //  DELETE /api/v2/wallet/transactions/{id}
    // -----------------------------------------------------------------

    /**
     * Hide a transaction from the user's history (soft delete).
     */
    public function destroyTransaction(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('wallet_delete', 20, 60);

        $success = $this->walletService->deleteTransaction($id, $userId);

        if (! $success) {
            return $this->respondWithError('NOT_FOUND', __('api.transaction_not_found'), null, 404);
        }

        return $this->noContent();
    }

    // -----------------------------------------------------------------
    //  GET /api/v2/wallet/user-search
    // -----------------------------------------------------------------

    /**
     * Search users for wallet transfer autocomplete.
     *
     * Query params: q (search term), limit (default 10, max 20).
     */
    public function userSearch(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('wallet_user_search', 30, 60);

        $query = trim($this->query('q', ''));
        $limit = $this->queryInt('limit', 10, 1, 20);

        $users = $this->walletService->searchUsers($userId, $query, $limit);

        return $this->respondWithData(['users' => $users]);
    }

    // -----------------------------------------------------------------
    //  GET /api/v2/wallet/pending-count
    // -----------------------------------------------------------------

    /**
     * Get count of pending wallet transactions (for badge updates).
     * Currently all transactions are instant, so this returns 0.
     */
    public function pendingCount(): JsonResponse
    {
        $this->requireAuth();
        $this->rateLimit('wallet_pending', 60, 60);

        return $this->respondWithData(['count' => 0]);
    }
}
