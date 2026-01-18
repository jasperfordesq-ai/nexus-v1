<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\Notification;

/**
 * Federated Transaction Service
 *
 * Handles cross-tenant hour exchanges between federated timebank members.
 * Creates transaction records in both tenants for proper accounting.
 */
class FederatedTransactionService
{
    /**
     * Create a federated transaction (cross-tenant hour exchange)
     *
     * @param int $senderId Sender user ID (from current tenant)
     * @param int $receiverId Receiver user ID (from partner tenant)
     * @param int $receiverTenantId Tenant ID of the receiver
     * @param float $amount Hours to transfer
     * @param string $description Transaction description
     * @return array Result with success status
     */
    public static function createTransaction(
        int $senderId,
        int $receiverId,
        int $receiverTenantId,
        float $amount,
        string $description
    ): array {
        $senderTenantId = TenantContext::getId();

        // Validate amount
        if ($amount <= 0 || $amount > 100) {
            return ['success' => false, 'error' => 'Invalid amount (must be between 0.01 and 100 hours)'];
        }

        // Verify federation is enabled for both tenants
        if (!FederationFeatureService::isTenantFederationEnabled($senderTenantId)) {
            return ['success' => false, 'error' => 'Federation not enabled for your timebank'];
        }

        if (!FederationFeatureService::isTenantFederationEnabled($receiverTenantId)) {
            return ['success' => false, 'error' => 'Federation not enabled for recipient timebank'];
        }

        // Verify transactions are allowed between these tenants
        $canTransactResult = FederationGateway::canPerformTransaction($senderId, $senderTenantId, $receiverId, $receiverTenantId);
        if (!$canTransactResult['allowed']) {
            return ['success' => false, 'error' => 'Transactions not enabled with this partner timebank'];
        }

        // Verify sender has opted into federation with transactions enabled
        $senderSettings = FederationUserService::getUserSettings($senderId);
        if (!$senderSettings['federation_optin'] || !$senderSettings['transactions_enabled_federated']) {
            return ['success' => false, 'error' => 'You must enable federated transactions in your settings'];
        }

        // Verify receiver has federation transactions enabled
        $receiverSettings = self::getReceiverFederationSettings($receiverId);
        if (!$receiverSettings || !$receiverSettings['transactions_enabled_federated']) {
            return ['success' => false, 'error' => 'Recipient has not enabled federated transactions'];
        }

        // Check sender has sufficient balance
        $senderBalance = self::getUserBalance($senderId);
        if ($senderBalance < $amount) {
            return ['success' => false, 'error' => 'Insufficient balance'];
        }

        $db = Database::getConnection();

        try {
            $db->beginTransaction();

            // 1. Deduct from sender's balance
            $stmt = $db->prepare("UPDATE users SET balance = balance - ? WHERE id = ? AND balance >= ?");
            $stmt->execute([$amount, $senderId, $amount]);

            if ($stmt->rowCount() === 0) {
                $db->rollBack();
                return ['success' => false, 'error' => 'Insufficient balance or user not found'];
            }

            // 2. Add to receiver's balance
            $stmt = $db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$amount, $receiverId]);

            // 3. Create federated transaction record
            $stmt = $db->prepare("
                INSERT INTO federation_transactions
                (sender_tenant_id, sender_user_id, receiver_tenant_id, receiver_user_id,
                 amount, description, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'completed', NOW())
            ");
            $stmt->execute([
                $senderTenantId, $senderId,
                $receiverTenantId, $receiverId,
                $amount, $description
            ]);
            $fedTransactionId = $db->lastInsertId();

            // 4. Create local transaction record for sender (for their history)
            $stmt = $db->prepare("
                INSERT INTO transactions
                (tenant_id, sender_id, receiver_id, amount, description, status, transaction_type, created_at)
                VALUES (?, ?, ?, ?, ?, 'completed', 'exchange', NOW())
            ");
            $stmt->execute([
                $senderTenantId, $senderId, $receiverId,
                -$amount, // Negative for outgoing
                "[Federated] " . $description
            ]);

            // 5. Create local transaction record for receiver (for their history)
            $stmt = $db->prepare("
                INSERT INTO transactions
                (tenant_id, sender_id, receiver_id, amount, description, status, transaction_type, created_at)
                VALUES (?, ?, ?, ?, ?, 'completed', 'exchange', NOW())
            ");
            $stmt->execute([
                $receiverTenantId, $senderId, $receiverId,
                $amount, // Positive for incoming
                "[Federated] " . $description
            ]);

            // 6. Log the federated action
            FederationAuditService::log(
                'federated_transaction',
                $senderTenantId,
                $receiverTenantId,
                $senderId,
                ['receiver_user_id' => $receiverId, 'amount' => $amount]
            );

            // 7. Create notifications
            $senderName = self::getUserName($senderId);
            $receiverName = self::getUserName($receiverId);
            $newBalance = $senderBalance - $amount;

            // In-app + push notification to receiver
            Notification::create(
                $receiverId,
                "You received {$amount} hour(s) from {$senderName} (federated transfer)",
                "/wallet",
                'federation_transaction',
                true // Send push notification
            );

            // In-app + push notification to sender (confirmation)
            Notification::create(
                $senderId,
                "Transfer complete: {$amount} hour(s) sent to {$receiverName} (federated transfer)",
                "/wallet",
                'federation_transaction',
                true // Send push notification
            );

            $db->commit();

            // 8. Send email notification to recipient (async - don't block on failure)
            try {
                FederationEmailService::sendTransactionNotification(
                    $receiverId,
                    $senderId,
                    $senderTenantId,
                    $amount,
                    $description
                );
            } catch (\Exception $e) {
                error_log("Failed to send federation transaction email notification: " . $e->getMessage());
            }

            // 9. Send confirmation email to sender (async - don't block on failure)
            try {
                FederationEmailService::sendTransactionConfirmation(
                    $senderId,
                    $receiverId,
                    $receiverTenantId,
                    $amount,
                    $description,
                    $newBalance
                );
            } catch (\Exception $e) {
                error_log("Failed to send federation transaction confirmation email: " . $e->getMessage());
            }

            // 10. Send real-time notification via Pusher (async - don't block on failure)
            try {
                FederationRealtimeService::broadcastTransaction(
                    $senderId,
                    $senderTenantId,
                    $receiverId,
                    $receiverTenantId,
                    $amount,
                    $description
                );
            } catch (\Exception $e) {
                error_log("Failed to send federation transaction realtime notification: " . $e->getMessage());
            }

            return [
                'success' => true,
                'transaction_id' => $fedTransactionId,
                'amount' => $amount,
                'new_balance' => $newBalance
            ];

        } catch (\Exception $e) {
            $db->rollBack();
            error_log("FederatedTransactionService::createTransaction error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Transaction failed. Please try again.'];
        }
    }

    /**
     * Get federated transaction history for a user
     */
    public static function getHistory(int $userId, int $limit = 50, int $offset = 0): array
    {
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        try {
            $stmt = $db->prepare("
                SELECT ft.*,
                       CASE WHEN ft.sender_user_id = ? THEN 'sent' ELSE 'received' END as direction,
                       CASE WHEN ft.sender_user_id = ?
                            THEN (SELECT name FROM users WHERE id = ft.receiver_user_id)
                            ELSE (SELECT name FROM users WHERE id = ft.sender_user_id)
                       END as other_user_name,
                       CASE WHEN ft.sender_user_id = ?
                            THEN (SELECT name FROM tenants WHERE id = ft.receiver_tenant_id)
                            ELSE (SELECT name FROM tenants WHERE id = ft.sender_tenant_id)
                       END as other_tenant_name
                FROM federation_transactions ft
                WHERE (ft.sender_tenant_id = ? AND ft.sender_user_id = ?)
                   OR (ft.receiver_tenant_id = ? AND ft.receiver_user_id = ?)
                ORDER BY ft.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([
                $userId, $userId, $userId,
                $tenantId, $userId,
                $tenantId, $userId,
                $limit, $offset
            ]);

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("FederatedTransactionService::getHistory error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get federated transaction statistics for a user
     */
    public static function getStats(int $userId): array
    {
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        try {
            $stmt = $db->prepare("
                SELECT
                    COUNT(CASE WHEN sender_user_id = ? THEN 1 END) as total_sent_count,
                    COALESCE(SUM(CASE WHEN sender_user_id = ? THEN amount END), 0) as total_sent_hours,
                    COUNT(CASE WHEN receiver_user_id = ? THEN 1 END) as total_received_count,
                    COALESCE(SUM(CASE WHEN receiver_user_id = ? THEN amount END), 0) as total_received_hours
                FROM federation_transactions
                WHERE ((sender_tenant_id = ? AND sender_user_id = ?)
                    OR (receiver_tenant_id = ? AND receiver_user_id = ?))
                AND status = 'completed'
            ");
            $stmt->execute([
                $userId, $userId, $userId, $userId,
                $tenantId, $userId, $tenantId, $userId
            ]);

            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [
                'total_sent_count' => 0,
                'total_sent_hours' => 0,
                'total_received_count' => 0,
                'total_received_hours' => 0
            ];
        } catch (\Exception $e) {
            return [
                'total_sent_count' => 0,
                'total_sent_hours' => 0,
                'total_received_count' => 0,
                'total_received_hours' => 0
            ];
        }
    }

    /**
     * Get user's balance
     */
    private static function getUserBalance(int $userId): float
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (float)($result['balance'] ?? 0);
    }

    /**
     * Get user's name
     */
    private static function getUserName(int $userId): string
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT name, first_name, last_name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($result) {
            return $result['name'] ?: trim($result['first_name'] . ' ' . $result['last_name']) ?: 'A member';
        }
        return 'A member';
    }

    /**
     * Get federation settings for a receiver in another tenant
     */
    private static function getReceiverFederationSettings(int $userId): ?array
    {
        $db = Database::getConnection();

        try {
            $stmt = $db->prepare("
                SELECT * FROM federation_user_settings
                WHERE user_id = ?
                AND federation_optin = 1
            ");
            $stmt->execute([$userId]);

            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if a federated user can receive transactions
     */
    public static function canReceiveTransactions(int $userId, int $tenantId): bool
    {
        $currentTenantId = TenantContext::getId();

        // Check partnership allows transactions (using 0 for current user as we're checking general access)
        $canTransactResult = FederationGateway::canPerformTransaction(0, $currentTenantId, $userId, $tenantId);
        if (!$canTransactResult['allowed']) {
            return false;
        }

        // Check user settings
        $settings = self::getReceiverFederationSettings($userId);
        return $settings && $settings['transactions_enabled_federated'];
    }
}
