<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Models\Transaction;
use Nexus\Models\User;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiAuth;

class WalletApiController
{
    use ApiAuth;

    private function jsonResponse($data, $status = 200)
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit;
    }

    private function getUserId()
    {
        // Unified auth supporting both session and Bearer token
        return $this->requireAuth();
    }

    public function balance()
    {
        $userId = $this->getUserId();
        // Direct query or User model? User model has balance.
        $user = User::findById($userId);
        $this->jsonResponse(['balance' => $user['balance'] ?? 0]);
    }

    public function transactions()
    {
        $userId = $this->getUserId();
        $history = Transaction::getHistory($userId);
        $this->jsonResponse(['data' => $history]);
    }

    /**
     * POST /api/wallet/user-search
     * Search users for wallet transfer autocomplete
     * Returns users matching query by name or username (privacy-preserving)
     */
    public function userSearch()
    {
        $userId = $this->getUserId();
        $input = json_decode(file_get_contents('php://input'), true);
        $query = trim($input['query'] ?? '');

        if (strlen($query) < 1) {
            $this->jsonResponse(['status' => 'success', 'users' => []]);
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

        $this->jsonResponse(['status' => 'success', 'users' => $results]);
    }

    public function transfer()
    {
        $senderId = $this->getUserId();
        $input = json_decode(file_get_contents('php://input'), true);

        // Support both username (new) and email (legacy) for backwards compatibility
        $recipientUsername = $input['username'] ?? null;
        $recipientEmail = $input['email'] ?? null;
        $amount = $input['amount'] ?? 0;
        $description = $input['description'] ?? '';

        if ((!$recipientUsername && !$recipientEmail) || !$amount) {
            $this->jsonResponse(['error' => 'Missing fields'], 400);
        }

        if ($amount <= 0) {
            $this->jsonResponse(['error' => 'Invalid amount'], 400);
        }

        // Find Recipient - prefer username over email
        $recipient = null;
        if ($recipientUsername) {
            $recipient = User::findByUsername($recipientUsername);
        } elseif ($recipientEmail) {
            $recipient = User::findByEmail($recipientEmail);
        }

        if (!$recipient) {
            $this->jsonResponse(['error' => 'User not found'], 404);
        }

        if ($recipient['id'] == $senderId) {
            $this->jsonResponse(['error' => 'Cannot send to self'], 400);
        }

        // Check Balance
        $sender = User::findById($senderId);
        if ($sender['balance'] < $amount) {
            $this->jsonResponse(['error' => 'Insufficient funds'], 400);
        }

        try {
            Transaction::create($senderId, $recipient['id'], $amount, $description);
            $this->jsonResponse(['success' => true, 'message' => 'Transfer successful']);
        } catch (\Exception $e) {
            $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/wallet/pending-count
     * Returns count of pending wallet transactions (for badge updates)
     * Currently transactions are instant, so this returns 0
     */
    public function pendingCount()
    {
        $userId = $this->getUserId();

        // Currently all transactions are instant (no pending status)
        // This endpoint exists for future pending transaction feature
        // For now, return 0 to satisfy the badge API call
        $this->jsonResponse(['count' => 0]);
    }

    public function delete()
    {
        $userId = $this->getUserId();
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? null;

        if (!$id) {
            $this->jsonResponse(['error' => 'Missing ID'], 400);
        }

        // Verify ownership (Sender OR Receiver can delete?)
        // Let's assume either party can delete the log from their view (HARD DELETE for POC)
        // Ideally Soft Delete, but Hard Delete requested.
        // SECURITY: Ensure user is part of the transaction
        $sql = "SELECT * FROM transactions WHERE id = ? AND (sender_id = ? OR receiver_id = ?)";
        $trx = Database::query($sql, [$id, $userId, $userId])->fetch();

        if (!$trx) {
            $this->jsonResponse(['error' => 'Transaction not found or denied'], 404);
        }

        Transaction::delete($id, $userId); // Pass userId for Soft Delete
        $this->jsonResponse(['success' => true]);
    }
}
