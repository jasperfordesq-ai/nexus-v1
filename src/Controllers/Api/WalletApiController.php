<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Models\Transaction;
use Nexus\Models\User;
use Nexus\Core\TenantContext;

/**
 * WalletApiController - Financial operations API
 *
 * Handles balance queries, transfers, and transaction history.
 * All financial operations are rate-limited for security.
 */
class WalletApiController extends BaseApiController
{
    /**
     * GET /api/wallet/balance
     * Get current user's balance
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
