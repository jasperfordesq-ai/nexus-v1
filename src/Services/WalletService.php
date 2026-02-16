<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\Transaction;
use Nexus\Models\User;
use Nexus\Models\Notification;
use Nexus\Models\ActivityLog;

/**
 * WalletService - Business logic for time credit wallet operations
 *
 * This service extracts business logic from the Transaction model and WalletApiController
 * to be shared between HTML and API controllers.
 *
 * Key operations:
 * - Get balance
 * - Get transaction history (cursor paginated)
 * - Transfer credits
 * - Delete/hide transaction
 */
class WalletService
{
    /**
     * Validation error messages
     */
    private static array $errors = [];

    /**
     * Get validation errors
     */
    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * Get user balance
     *
     * @param int $userId
     * @return array ['balance' => float, 'total_earned' => float, 'transaction_count' => int]
     */
    public static function getBalance(int $userId): array
    {
        $user = User::findById($userId);

        return [
            'balance' => (float)($user['balance'] ?? 0),
            'total_earned' => Transaction::getTotalEarned($userId),
            'transaction_count' => Transaction::countForUser($userId),
        ];
    }

    /**
     * Get transaction history with cursor-based pagination
     *
     * @param int $userId
     * @param array $filters [
     *   'type' => 'all' (default), 'sent', 'received',
     *   'cursor' => string,
     *   'limit' => int (default: 20, max: 100)
     * ]
     * @return array ['items' => [], 'cursor' => string|null, 'has_more' => bool]
     */
    public static function getTransactions(int $userId, array $filters = []): array
    {
        $db = Database::getConnection();

        $limit = min($filters['limit'] ?? 20, 100);
        $type = $filters['type'] ?? 'all';
        $cursor = $filters['cursor'] ?? null;

        $cursorId = null;
        if ($cursor) {
            $decoded = base64_decode($cursor, true);
            if ($decoded && is_numeric($decoded)) {
                $cursorId = (int)$decoded;
            }
        }

        // Build query based on type filter
        $sql = "
            SELECT t.*,
                   s.name as sender_name, s.avatar_url as sender_avatar,
                   r.name as receiver_name, r.avatar_url as receiver_avatar
            FROM transactions t
            JOIN users s ON t.sender_id = s.id
            JOIN users r ON t.receiver_id = r.id
            WHERE 1=1
        ";
        $params = [];

        if ($type === 'sent') {
            $sql .= " AND t.sender_id = ? AND t.deleted_for_sender = 0";
            $params[] = $userId;
        } elseif ($type === 'received') {
            $sql .= " AND t.receiver_id = ? AND t.deleted_for_receiver = 0";
            $params[] = $userId;
        } else {
            $sql .= " AND ((t.sender_id = ? AND t.deleted_for_sender = 0) OR (t.receiver_id = ? AND t.deleted_for_receiver = 0))";
            $params[] = $userId;
            $params[] = $userId;
        }

        if ($cursorId) {
            $sql .= " AND t.id < ?";
            $params[] = $cursorId;
        }

        $sql .= " ORDER BY t.created_at DESC, t.id DESC";
        $sql .= " LIMIT " . ($limit + 1);

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $transactions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $hasMore = count($transactions) > $limit;
        if ($hasMore) {
            array_pop($transactions);
        }

        $items = [];
        $lastId = null;

        foreach ($transactions as $t) {
            $lastId = $t['id'];
            $isSender = (int)$t['sender_id'] === $userId;

            $items[] = [
                'id' => (int)$t['id'],
                'type' => $isSender ? 'sent' : 'received',
                'amount' => (float)$t['amount'],
                'description' => $t['description'],
                'other_user' => [
                    'id' => (int)($isSender ? $t['receiver_id'] : $t['sender_id']),
                    'name' => $isSender ? $t['receiver_name'] : $t['sender_name'],
                    'avatar_url' => $isSender ? $t['receiver_avatar'] : $t['sender_avatar'],
                ],
                'created_at' => $t['created_at'],
            ];
        }

        return [
            'items' => $items,
            'cursor' => $hasMore && $lastId ? base64_encode((string)$lastId) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Transfer time credits to another user
     *
     * @param int $senderId Sender user ID
     * @param int|string $recipientIdentifier Recipient user ID, username, or email
     * @param float $amount Amount to transfer
     * @param string $description Optional description
     * @return int|null Transaction ID or null on failure
     */
    public static function transfer(int $senderId, $recipientIdentifier, float $amount, string $description = ''): ?int
    {
        self::$errors = [];

        // Validate amount
        if ($amount <= 0) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Amount must be greater than 0', 'field' => 'amount'];
            return null;
        }

        // Find recipient
        $recipient = null;
        if (is_int($recipientIdentifier) || ctype_digit($recipientIdentifier)) {
            $recipient = User::findById((int)$recipientIdentifier);
        } elseif (filter_var($recipientIdentifier, FILTER_VALIDATE_EMAIL)) {
            $recipient = User::findByEmail($recipientIdentifier);
        } else {
            $recipient = User::findByUsername($recipientIdentifier);
        }

        if (!$recipient) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Recipient not found'];
            return null;
        }

        $receiverId = (int)$recipient['id'];

        // Can't send to self
        if ($senderId === $receiverId) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Cannot transfer to yourself', 'field' => 'recipient'];
            return null;
        }

        // Check balance
        $sender = User::findById($senderId);
        if ((float)$sender['balance'] < $amount) {
            self::$errors[] = ['code' => 'INSUFFICIENT_FUNDS', 'message' => 'Insufficient balance'];
            return null;
        }

        try {
            $transactionId = Transaction::create($senderId, $receiverId, $amount, $description);

            // Send email notification to recipient
            try {
                $senderName = $sender['name'] ?? trim(($sender['first_name'] ?? '') . ' ' . ($sender['last_name'] ?? ''));
                NotificationDispatcher::sendCreditEmail($receiverId, $senderName, $amount, $description);
            } catch (\Throwable $e) {
                error_log("Credit email error: " . $e->getMessage());
            }

            // Gamification
            try {
                GamificationService::checkTransactionBadges($senderId);
                GamificationService::checkTransactionBadges($receiverId);
            } catch (\Throwable $e) {
                error_log("Gamification transaction error: " . $e->getMessage());
            }

            return (int)$transactionId;
        } catch (\Exception $e) {
            error_log("WalletService::transfer error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Transfer failed'];
            return null;
        }
    }

    /**
     * Get a single transaction by ID
     *
     * @param int $transactionId
     * @param int $userId User requesting (must be sender or receiver)
     * @return array|null
     */
    public static function getTransaction(int $transactionId, int $userId): ?array
    {
        $db = Database::getConnection();

        $sql = "
            SELECT t.*,
                   s.name as sender_name, s.avatar_url as sender_avatar,
                   r.name as receiver_name, r.avatar_url as receiver_avatar
            FROM transactions t
            JOIN users s ON t.sender_id = s.id
            JOIN users r ON t.receiver_id = r.id
            WHERE t.id = ?
              AND ((t.sender_id = ? AND t.deleted_for_sender = 0) OR (t.receiver_id = ? AND t.deleted_for_receiver = 0))
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([$transactionId, $userId, $userId]);
        $t = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$t) {
            return null;
        }

        $isSender = (int)$t['sender_id'] === $userId;

        return [
            'id' => (int)$t['id'],
            'type' => $isSender ? 'sent' : 'received',
            'amount' => (float)$t['amount'],
            'description' => $t['description'],
            'sender' => [
                'id' => (int)$t['sender_id'],
                'name' => $t['sender_name'],
                'avatar_url' => $t['sender_avatar'],
            ],
            'receiver' => [
                'id' => (int)$t['receiver_id'],
                'name' => $t['receiver_name'],
                'avatar_url' => $t['receiver_avatar'],
            ],
            'created_at' => $t['created_at'],
        ];
    }

    /**
     * Delete (hide) a transaction for a user
     *
     * @param int $transactionId
     * @param int $userId
     * @return bool Success
     */
    public static function deleteTransaction(int $transactionId, int $userId): bool
    {
        self::$errors = [];

        $db = Database::getConnection();

        // Verify user is part of transaction
        $stmt = $db->prepare("SELECT sender_id, receiver_id FROM transactions WHERE id = ?");
        $stmt->execute([$transactionId]);
        $t = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$t) {
            self::$errors[] = ['code' => 'NOT_FOUND', 'message' => 'Transaction not found'];
            return false;
        }

        $isSender = (int)$t['sender_id'] === $userId;
        $isReceiver = (int)$t['receiver_id'] === $userId;

        if (!$isSender && !$isReceiver) {
            self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You are not part of this transaction'];
            return false;
        }

        try {
            Transaction::delete($transactionId, $userId);
            return true;
        } catch (\Exception $e) {
            error_log("WalletService::deleteTransaction error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to delete transaction'];
            return false;
        }
    }

    /**
     * Search users for wallet transfer autocomplete
     *
     * @param int $userId Current user (excluded from results)
     * @param string $query Search term
     * @param int $limit Max results
     * @return array
     */
    public static function searchUsers(int $userId, string $query, int $limit = 10): array
    {
        if (strlen($query) < 1) {
            return [];
        }

        $users = User::searchForWallet($query, $userId, $limit);

        return array_map(function ($user) {
            return [
                'id' => (int)$user['id'],
                'username' => $user['username'] ?? null,
                'name' => $user['display_name'] ?? $user['name'] ?? null,
                'avatar_url' => $user['avatar_url'] ?? null,
            ];
        }, $users);
    }
}
