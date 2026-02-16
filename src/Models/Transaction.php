<?php

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Models\ActivityLog;
use Nexus\Models\Notification;

class Transaction
{
    /**
     * Create a new transaction
     *
     * @param int $senderId User sending the time credits
     * @param int $receiverId User receiving the time credits
     * @param float $amount Amount of time credits
     * @param string $description Transaction description
     * @param int|null $sourceMatchId Optional match history ID for conversion tracking
     * @return int Transaction ID
     */
    public static function create($senderId, $receiverId, $amount, $description, $sourceMatchId = null)
    {
        $pdo = Database::getInstance();
        $pdo->beginTransaction();

        try {
            // Deduct from sender
            $sql = "UPDATE users SET balance = balance - ? WHERE id = ?";
            Database::query($sql, [$amount, $senderId]);

            // Add to receiver
            $sql = "UPDATE users SET balance = balance + ? WHERE id = ?";
            Database::query($sql, [$amount, $receiverId]);

            // Log transaction with optional match attribution
            $tenantId = \Nexus\Core\TenantContext::getId();

            if ($sourceMatchId !== null) {
                $sql = "INSERT INTO transactions (tenant_id, sender_id, receiver_id, amount, description, source_match_id) VALUES (?, ?, ?, ?, ?, ?)";
                Database::query($sql, [$tenantId, $senderId, $receiverId, $amount, $description, $sourceMatchId]);
            } else {
                $sql = "INSERT INTO transactions (tenant_id, sender_id, receiver_id, amount, description) VALUES (?, ?, ?, ?, ?)";
                Database::query($sql, [$tenantId, $senderId, $receiverId, $amount, $description]);
            }

            $lastId = $pdo->lastInsertId();

            // Update match history for conversion tracking
            if ($sourceMatchId !== null) {
                self::markMatchConversion($sourceMatchId, $lastId);
            }

            ActivityLog::log($senderId, "sent_payment", "Sent $amount Hours");
            ActivityLog::log($receiverId, "received_payment", "Received $amount Hours");

            // Get sender name for a user-friendly notification
            $senderStmt = Database::query("SELECT name, first_name, last_name FROM users WHERE id = ?", [$senderId]);
            $senderRow = $senderStmt->fetch();
            $senderName = $senderRow ? ($senderRow['name'] ?: trim($senderRow['first_name'] . ' ' . $senderRow['last_name'])) : "User #{$senderId}";
            $hourLabel = $amount == 1 ? 'hour' : 'hours';

            Notification::create($receiverId, "💰 {$senderName} sent you {$amount} {$hourLabel}", '/wallet', 'credit_received');

            $pdo->commit();

            return $lastId;
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Mark a match as converted to a transaction
     *
     * @param int $matchHistoryId Match history record ID
     * @param int $transactionId Transaction ID
     * @return bool Success
     */
    private static function markMatchConversion($matchHistoryId, $transactionId)
    {
        try {
            Database::query(
                "UPDATE match_history
                 SET resulted_in_transaction = 1,
                     transaction_id = ?,
                     conversion_time = NOW()
                 WHERE id = ?",
                [$transactionId, $matchHistoryId]
            );
            return true;
        } catch (\Exception $e) {
            // Column might not exist yet
            return false;
        }
    }

    /**
     * Attribute an existing transaction to a match
     * Used for retroactive attribution
     *
     * @param int $transactionId Transaction ID
     * @param int $matchHistoryId Match history ID to attribute
     * @return bool Success
     */
    public static function attributeToMatch($transactionId, $matchHistoryId)
    {
        try {
            // Update transaction
            Database::query(
                "UPDATE transactions SET source_match_id = ? WHERE id = ?",
                [$matchHistoryId, $transactionId]
            );

            // Update match history
            self::markMatchConversion($matchHistoryId, $transactionId);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get transactions attributed to smart matching
     *
     * @param int|null $tenantId Optional tenant filter
     * @return array Transactions with match attribution
     */
    public static function getMatchAttributedTransactions($tenantId = null)
    {
        $tenantId = $tenantId ?? \Nexus\Core\TenantContext::getId();

        try {
            return Database::query(
                "SELECT t.*, mh.match_score, mh.distance_km, mh.action as match_action
                 FROM transactions t
                 JOIN match_history mh ON t.source_match_id = mh.id
                 WHERE t.tenant_id = ?
                 ORDER BY t.created_at DESC",
                [$tenantId]
            )->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function getHistory($userId)
    {
        $sql = "SELECT t.*, 
                CONCAT(s.first_name, ' ', s.last_name) as sender_name, 
                CONCAT(r.first_name, ' ', r.last_name) as receiver_name 
                FROM transactions t 
                JOIN users s ON t.sender_id = s.id 
                JOIN users r ON t.receiver_id = r.id 
                WHERE (t.sender_id = ? AND t.deleted_for_sender = 0) 
                   OR (t.receiver_id = ? AND t.deleted_for_receiver = 0) 
                ORDER BY created_at DESC";

        return Database::query($sql, [$userId, $userId])->fetchAll();
    }

    public static function countForUser($userId)
    {
        $sql = "SELECT COUNT(*) as total FROM transactions 
                WHERE (sender_id = ? AND deleted_for_sender = 0) 
                   OR (receiver_id = ? AND deleted_for_receiver = 0)";
        $result = Database::query($sql, [$userId, $userId])->fetch();
        return $result ? (int)$result['total'] : 0;
    }

    public static function getTotalEarned($userId)
    {
        // Match home page query pattern - no tenant_id filter needed for user's own transactions
        $sql = "SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE receiver_id = ?";
        $result = Database::query($sql, [$userId])->fetch();
        return (float)($result['total'] ?? 0);
    }

    public static function delete($id, $userId)
    {
        // Determine if user is sender or receiver
        $pdo = Database::getInstance();
        $sql = "SELECT sender_id, receiver_id FROM transactions WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $trx = $stmt->fetch();

        if ($trx) {
            if ($trx['sender_id'] == $userId) {
                $sql = "UPDATE transactions SET deleted_for_sender = 1 WHERE id = ?";
                Database::query($sql, [$id]);
            }
            if ($trx['receiver_id'] == $userId) {
                $sql = "UPDATE transactions SET deleted_for_receiver = 1 WHERE id = ?";
                Database::query($sql, [$id]);
            }
        }
    }
}
