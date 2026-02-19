<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Models\Transaction;
use Nexus\Models\User;
use Nexus\Core\TenantContext;
use Nexus\Services\WalletService;

/**
 * WalletApiController - Financial operations API
 *
 * Handles balance queries, transfers, and transaction history.
 * All financial operations are rate-limited for security.
 *
 * V2 Endpoints (new standardized API):
 * - GET    /api/v2/wallet/balance       - Get balance with stats
 * - GET    /api/v2/wallet/transactions  - List transactions (cursor paginated)
 * - GET    /api/v2/wallet/transactions/{id} - Get single transaction
 * - POST   /api/v2/wallet/transfer      - Transfer credits
 * - DELETE /api/v2/wallet/transactions/{id} - Hide transaction
 * - GET    /api/v2/wallet/user-search   - Search users for transfer
 *
 * Legacy V1 Endpoints (maintained for backwards compatibility):
 * - GET    /api/wallet/balance
 * - GET    /api/wallet/transactions
 * - POST   /api/wallet/transfer
 * - POST   /api/wallet/delete
 * - POST   /api/wallet/user-search
 * - GET    /api/wallet/pending-count
 */
class WalletApiController extends BaseApiController
{
    // ============================================
    // V2 ENDPOINTS (New standardized API)
    // ============================================

    /**
     * GET /api/v2/wallet/balance
     * Get user's wallet balance with statistics
     */
    public function balanceV2(): void
    {
        $userId = $this->getUserId();
        $this->rateLimit('wallet_balance', 60, 60);

        $data = WalletService::getBalance($userId);

        $this->respondWithData($data);
    }

    /**
     * GET /api/v2/wallet/transactions
     * Get transaction history with cursor-based pagination
     *
     * Query Parameters:
     * - type: 'all' (default), 'sent', 'received'
     * - cursor: string (pagination cursor)
     * - per_page: int (default 20, max 100)
     */
    public function transactionsV2(): void
    {
        $userId = $this->getUserId();
        $this->rateLimit('wallet_transactions', 30, 60);

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];

        if ($this->query('type')) {
            $filters['type'] = $this->query('type');
        }

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = WalletService::getTransactions($userId, $filters);

        $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /**
     * GET /api/v2/wallet/transactions/{id}
     * Get a single transaction
     */
    public function showTransaction(int $id): void
    {
        $userId = $this->getUserId();
        $this->rateLimit('wallet_transactions', 60, 60);

        $transaction = WalletService::getTransaction($id, $userId);

        if (!$transaction) {
            $this->respondWithError('NOT_FOUND', 'Transaction not found', null, 404);
        }

        $this->respondWithData($transaction);
    }

    /**
     * POST /api/v2/wallet/transfer
     * Transfer time credits to another user
     *
     * Request Body (JSON):
     * {
     *   "recipient": "user_id, username, or email (required)",
     *   "amount": float (required),
     *   "description": "string"
     * }
     */
    public function transferV2(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('wallet_transfer', 10, 60);

        $recipient = $this->input('recipient') ?? $this->input('user_id') ?? $this->input('username') ?? $this->input('email');
        $amount = (float)($this->input('amount') ?? 0);
        $description = trim($this->input('description', ''));

        if (empty($recipient)) {
            $this->respondWithError('VALIDATION_ERROR', 'Recipient is required', 'recipient', 400);
        }

        if ($amount <= 0) {
            $this->respondWithError('VALIDATION_ERROR', 'Amount must be greater than 0', 'amount', 400);
        }

        $transactionId = WalletService::transfer($userId, $recipient, $amount, $description);

        if ($transactionId === null) {
            $errors = WalletService::getErrors();
            $status = 422;

            foreach ($errors as $error) {
                if ($error['code'] === 'NOT_FOUND') {
                    $status = 404;
                    break;
                }
                if ($error['code'] === 'INSUFFICIENT_FUNDS') {
                    $status = 400;
                    break;
                }
            }

            $this->respondWithErrors($errors, $status);
        }

        // Get the created transaction
        $transaction = WalletService::getTransaction($transactionId, $userId);

        $this->respondWithData($transaction, null, 201);
    }

    /**
     * DELETE /api/v2/wallet/transactions/{id}
     * Hide a transaction from user's history
     */
    public function destroyTransaction(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('wallet_delete', 20, 60);

        $success = WalletService::deleteTransaction($id, $userId);

        if (!$success) {
            $errors = WalletService::getErrors();
            $status = 400;

            foreach ($errors as $error) {
                if ($error['code'] === 'NOT_FOUND') {
                    $status = 404;
                    break;
                }
                if ($error['code'] === 'FORBIDDEN') {
                    $status = 403;
                    break;
                }
            }

            $this->respondWithErrors($errors, $status);
        }

        $this->noContent();
    }

    /**
     * GET /api/v2/wallet/user-search
     * Search users for wallet transfer autocomplete
     *
     * Query Parameters:
     * - q: string (search term)
     * - limit: int (default 10, max 20)
     */
    public function userSearchV2(): void
    {
        $userId = $this->getUserId();
        $this->rateLimit('wallet_user_search', 30, 60);

        $query = trim($this->query('q', ''));
        $limit = $this->queryInt('limit', 10, 1, 20);

        $users = WalletService::searchUsers($userId, $query, $limit);

        $this->respondWithData(['users' => $users]);
    }

    // ============================================
    // LEGACY V1 ENDPOINTS (Backwards Compatibility)
    // ============================================

    /**
     * GET /api/wallet/balance
     * Get current user's balance
     * @deprecated Use GET /api/v2/wallet/balance instead
     */
    public function balance()
    {
        $userId = $this->getUserId();
        $this->rateLimit('wallet_balance', 60, 60);

        $user = User::findById($userId);
        $this->success(['balance' => $user['balance'] ?? 0]);
    }

    /**
     * GET /api/wallet/transactions
     * Get transaction history
     */
    public function transactions()
    {
        $userId = $this->getUserId();
        $this->rateLimit('wallet_transactions', 30, 60);

        $history = Transaction::getHistory($userId);
        $this->success($history);
    }

    /**
     * POST /api/wallet/user-search
     * Search users for wallet transfer autocomplete
     * Returns users matching query by name or username (privacy-preserving)
     */
    public function userSearch()
    {
        $userId = $this->getUserId();
        $this->rateLimit('wallet_user_search', 30, 60);

        $query = trim($this->input('query', ''));

        if (strlen($query) < 1) {
            $this->success(['users' => []]);
        }

        $users = User::searchForWallet($query, $userId, 10);

        // Format response with only necessary fields
        $results = array_map(function ($user) {
            return [
                'id' => $user['id'],
                'username' => $user['username'],
                'display_name' => $user['display_name'],
                'avatar_url' => $user['avatar_url']
            ];
        }, $users);

        $this->success(['users' => $results]);
    }

    /**
     * POST /api/wallet/transfer
     * Transfer time credits to another user
     */
    public function transfer()
    {
        $senderId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('wallet_transfer', 10, 60); // Strict rate limit for transfers

        // Support both username (new) and email (legacy) for backwards compatibility
        $recipientUsername = $this->input('username');
        $recipientEmail = $this->input('email');
        $amount = $this->inputInt('amount', 0, 1);
        $description = trim($this->input('description', ''));

        if (!$recipientUsername && !$recipientEmail) {
            $this->error('Recipient username or email required', 400, 'VALIDATION_ERROR');
        }

        if (!$amount || $amount <= 0) {
            $this->error('Invalid amount', 400, 'VALIDATION_ERROR');
        }

        // Find Recipient - prefer username over email
        $recipient = null;
        if ($recipientUsername) {
            $recipient = User::findByUsername($recipientUsername);
        } elseif ($recipientEmail) {
            $recipient = User::findByEmail($recipientEmail);
        }

        if (!$recipient) {
            $this->error('User not found', 404, 'USER_NOT_FOUND');
        }

        if ($recipient['id'] == $senderId) {
            $this->error('Cannot send to self', 400, 'VALIDATION_ERROR');
        }

        // Check Balance
        $sender = User::findById($senderId);
        if ($sender['balance'] < $amount) {
            $this->error('Insufficient funds', 400, 'INSUFFICIENT_FUNDS');
        }

        try {
            Transaction::create($senderId, $recipient['id'], $amount, $description);
            $this->success(['message' => 'Transfer successful']);
        } catch (\Exception $e) {
            error_log("Wallet transfer error: " . $e->getMessage());
            $this->error('Transfer failed', 500, 'TRANSFER_FAILED');
        }
    }

    /**
     * GET /api/wallet/pending-count
     * Returns count of pending wallet transactions (for badge updates)
     * Currently transactions are instant, so this returns 0
     */
    public function pendingCount()
    {
        $this->getUserId();
        $this->rateLimit('wallet_pending', 60, 60);

        // Currently all transactions are instant (no pending status)
        // This endpoint exists for future pending transaction feature
        $this->success(['count' => 0]);
    }

    /**
     * POST /api/wallet/delete
     * Delete a transaction (soft delete from user's view)
     */
    public function delete()
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('wallet_delete', 10, 60);

        $id = $this->inputInt('id');

        if (!$id) {
            $this->error('Missing transaction ID', 400, 'VALIDATION_ERROR');
        }

        // SECURITY: Verify user is part of the transaction
        $sql = "SELECT * FROM transactions WHERE id = ? AND (sender_id = ? OR receiver_id = ?)";
        $trx = Database::query($sql, [$id, $userId, $userId])->fetch();

        if (!$trx) {
            $this->error('Transaction not found', 404, 'NOT_FOUND');
        }

        Transaction::delete($id, $userId);
        $this->success(['message' => 'Transaction deleted']);
    }
}
