<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\Notification;

/**
 * Federated Message Service
 *
 * Handles cross-tenant messaging between federated timebank members.
 * Messages are stored locally but contain cross-tenant references.
 */
class FederatedMessageService
{
    /**
     * Send a federated message to a user in a partner tenant
     *
     * @param int $senderId The sender's user ID (from current tenant)
     * @param int $receiverId The receiver's user ID (from partner tenant)
     * @param int $receiverTenantId The tenant ID of the receiver
     * @param string $subject Message subject
     * @param string $body Message body
     * @return array Result with success status and message ID
     */
    public static function sendMessage(int $senderId, int $receiverId, int $receiverTenantId, string $subject, string $body): array
    {
        $senderTenantId = TenantContext::getId();

        // Verify federation is enabled for both tenants
        if (!FederationFeatureService::isTenantFederationEnabled($senderTenantId)) {
            return ['success' => false, 'error' => 'Federation not enabled for your timebank'];
        }

        if (!FederationFeatureService::isTenantFederationEnabled($receiverTenantId)) {
            return ['success' => false, 'error' => 'Federation not enabled for recipient timebank'];
        }

        // Verify active partnership exists
        $canSendResult = FederationGateway::canSendMessage($senderId, $senderTenantId, $receiverId, $receiverTenantId);
        if (!$canSendResult['allowed']) {
            return ['success' => false, 'error' => 'Cannot send message - partnership not active or messaging not enabled'];
        }

        // Verify sender has opted into federation with messaging enabled
        $senderSettings = FederationUserService::getUserSettings($senderId);
        if (!$senderSettings['federation_optin']) {
            return ['success' => false, 'error' => 'You must opt into federation to send federated messages'];
        }

        // Verify receiver has federation messaging enabled
        $receiverSettings = self::getReceiverFederationSettings($receiverId);
        if (!$receiverSettings || !$receiverSettings['messaging_enabled_federated']) {
            return ['success' => false, 'error' => 'Recipient has not enabled federated messaging'];
        }

        $db = Database::getConnection();

        try {
            $db->beginTransaction();

            // Insert message in sender's tenant (outbox record)
            $stmt = $db->prepare("
                INSERT INTO federation_messages
                (sender_tenant_id, sender_user_id, receiver_tenant_id, receiver_user_id,
                 subject, body, direction, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'outbound', 'delivered', NOW())
            ");
            $stmt->execute([$senderTenantId, $senderId, $receiverTenantId, $receiverId, $subject, $body]);
            $messageId = $db->lastInsertId();

            // Create a corresponding record for the receiver (inbox record)
            $stmt = $db->prepare("
                INSERT INTO federation_messages
                (sender_tenant_id, sender_user_id, receiver_tenant_id, receiver_user_id,
                 subject, body, direction, status, reference_message_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'inbound', 'unread', ?, NOW())
            ");
            $stmt->execute([$senderTenantId, $senderId, $receiverTenantId, $receiverId, $subject, $body, $messageId]);

            // Log the federated action
            FederationAuditService::log(
                'federated_message_sent',
                $senderTenantId,
                $receiverTenantId,
                $senderId,
                ['receiver_user_id' => $receiverId, 'message_id' => $messageId]
            );

            $db->commit();

            // Send email notification to recipient (async - don't block on failure)
            try {
                FederationEmailService::sendNewMessageNotification(
                    $receiverId,
                    $senderId,
                    $senderTenantId,
                    substr($body, 0, 200)
                );
            } catch (\Exception $e) {
                error_log("Failed to send federation message email notification: " . $e->getMessage());
            }

            // Send real-time notification via Pusher (async - don't block on failure)
            $senderInfo = ['name' => 'Unknown', 'tenant_name' => 'Partner Timebank'];
            try {
                $senderInfo = self::getSenderInfo($senderId, $senderTenantId);
                FederationRealtimeService::broadcastNewMessage(
                    $senderId,
                    $senderTenantId,
                    $receiverId,
                    $receiverTenantId,
                    [
                        'message_id' => $messageId,
                        'sender_name' => $senderInfo['name'] ?? 'Unknown',
                        'sender_tenant_name' => $senderInfo['tenant_name'] ?? 'Partner Timebank',
                        'subject' => $subject,
                        'body' => $body,
                    ]
                );
            } catch (\Exception $e) {
                error_log("Failed to send federation message realtime notification: " . $e->getMessage());
            }

            // Create in-app notification + push notification (async - don't block on failure)
            try {
                $senderName = $senderInfo['name'] ?? 'A federation member';
                $tenantName = $senderInfo['tenant_name'] ?? 'a partner timebank';
                $notificationMessage = "New federated message from {$senderName} ({$tenantName}): " . substr($subject, 0, 50);
                if (strlen($subject) > 50) {
                    $notificationMessage .= '...';
                }

                Notification::create(
                    $receiverId,
                    $notificationMessage,
                    '/federation/messages', // Link to federation messages
                    'federation_message',   // Notification type
                    true                    // Send push notification
                );
            } catch (\Exception $e) {
                error_log("Failed to send federation message in-app notification: " . $e->getMessage());
            }

            return ['success' => true, 'message_id' => $messageId];

        } catch (\Exception $e) {
            $db->rollBack();
            error_log("FederatedMessageService::sendMessage error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to send message'];
        }
    }

    /**
     * Get federated inbox for a user
     * Includes both internal federated messages AND external partner messages
     */
    public static function getInbox(int $userId, int $limit = 50, int $offset = 0): array
    {
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        try {
            $allMessages = [];

            // Get inbound messages (received from internal federated members) grouped by sender
            $stmt = $db->prepare("
                SELECT fm.*,
                       u.name as sender_name,
                       u.avatar_url as sender_avatar,
                       t.name as sender_tenant_name,
                       0 as is_external,
                       NULL as external_partner_name,
                       (SELECT COUNT(*) FROM federation_messages fm2
                        WHERE fm2.receiver_tenant_id = fm.receiver_tenant_id
                        AND fm2.receiver_user_id = fm.receiver_user_id
                        AND fm2.sender_tenant_id = fm.sender_tenant_id
                        AND fm2.sender_user_id = fm.sender_user_id
                        AND fm2.direction = 'inbound'
                        AND fm2.status = 'unread') as unread_count
                FROM federation_messages fm
                INNER JOIN users u ON fm.sender_user_id = u.id
                INNER JOIN tenants t ON fm.sender_tenant_id = t.id
                WHERE fm.receiver_tenant_id = ?
                AND fm.receiver_user_id = ?
                AND fm.direction = 'inbound'
                AND (fm.external_partner_id IS NULL OR fm.external_partner_id = 0)
                AND fm.id IN (
                    SELECT MAX(id)
                    FROM federation_messages
                    WHERE receiver_tenant_id = ?
                    AND receiver_user_id = ?
                    AND direction = 'inbound'
                    AND (external_partner_id IS NULL OR external_partner_id = 0)
                    GROUP BY sender_tenant_id, sender_user_id
                )
            ");
            $stmt->execute([$tenantId, $userId, $tenantId, $userId]);
            $inboundMessages = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $allMessages = array_merge($allMessages, $inboundMessages);

            // Get outbound messages to external partners (sent by this user)
            $stmt = $db->prepare("
                SELECT fm.*,
                       fm.external_receiver_name as receiver_name,
                       NULL as sender_avatar,
                       COALESCE(fep.partner_name, fep.name) as external_partner_name,
                       COALESCE(fep.partner_name, fep.name) as sender_tenant_name,
                       1 as is_external,
                       0 as unread_count
                FROM federation_messages fm
                INNER JOIN federation_external_partners fep ON fm.external_partner_id = fep.id
                WHERE fm.sender_tenant_id = ?
                AND fm.sender_user_id = ?
                AND fm.direction = 'outbound'
                AND fm.external_partner_id IS NOT NULL
                AND fm.external_partner_id > 0
                AND fm.id IN (
                    SELECT MAX(id)
                    FROM federation_messages
                    WHERE sender_tenant_id = ?
                    AND sender_user_id = ?
                    AND direction = 'outbound'
                    AND external_partner_id IS NOT NULL
                    AND external_partner_id > 0
                    GROUP BY external_partner_id, receiver_user_id
                )
            ");
            $stmt->execute([$tenantId, $userId, $tenantId, $userId]);
            $externalMessages = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $allMessages = array_merge($allMessages, $externalMessages);

            // Sort by created_at descending
            usort($allMessages, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });

            // Apply limit and offset
            return array_slice($allMessages, $offset, $limit);

        } catch (\Exception $e) {
            error_log("FederatedMessageService::getInbox error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get conversation thread between current user and a federated user
     */
    public static function getThread(int $userId, int $otherUserId, int $otherTenantId, int $limit = 100): array
    {
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        try {
            $stmt = $db->prepare("
                SELECT fm.*,
                       CASE WHEN fm.direction = 'outbound' THEN 'sent' ELSE 'received' END as message_type
                FROM federation_messages fm
                WHERE (
                    (fm.sender_tenant_id = ? AND fm.sender_user_id = ? AND fm.receiver_tenant_id = ? AND fm.receiver_user_id = ?)
                    OR
                    (fm.sender_tenant_id = ? AND fm.sender_user_id = ? AND fm.receiver_tenant_id = ? AND fm.receiver_user_id = ?)
                )
                AND (
                    (fm.direction = 'outbound' AND fm.sender_tenant_id = ? AND fm.sender_user_id = ?)
                    OR
                    (fm.direction = 'inbound' AND fm.receiver_tenant_id = ? AND fm.receiver_user_id = ?)
                )
                ORDER BY fm.created_at ASC
                LIMIT ?
            ");
            $stmt->execute([
                $tenantId, $userId, $otherTenantId, $otherUserId,      // outbound from me
                $otherTenantId, $otherUserId, $tenantId, $userId,      // inbound to me
                $tenantId, $userId,                                     // filter outbound by me
                $tenantId, $userId,                                     // filter inbound to me
                $limit
            ]);

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("FederatedMessageService::getThread error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Mark a federated message as read
     */
    public static function markAsRead(int $messageId, int $userId): bool
    {
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        try {
            $stmt = $db->prepare("
                UPDATE federation_messages
                SET status = 'read', read_at = NOW()
                WHERE id = ?
                AND receiver_tenant_id = ?
                AND receiver_user_id = ?
                AND direction = 'inbound'
                AND status = 'unread'
            ");
            $stmt->execute([$messageId, $tenantId, $userId]);

            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            error_log("FederatedMessageService::markAsRead error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark entire thread as read
     */
    public static function markThreadAsRead(int $userId, int $otherUserId, int $otherTenantId): int
    {
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        try {
            $stmt = $db->prepare("
                UPDATE federation_messages
                SET status = 'read', read_at = NOW()
                WHERE receiver_tenant_id = ?
                AND receiver_user_id = ?
                AND sender_tenant_id = ?
                AND sender_user_id = ?
                AND direction = 'inbound'
                AND status = 'unread'
            ");
            $stmt->execute([$tenantId, $userId, $otherTenantId, $otherUserId]);

            return $stmt->rowCount();
        } catch (\Exception $e) {
            error_log("FederatedMessageService::markThreadAsRead error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get unread count for federated messages
     */
    public static function getUnreadCount(int $userId): int
    {
        $tenantId = TenantContext::getId();
        $db = Database::getConnection();

        try {
            $stmt = $db->prepare("
                SELECT COUNT(*) as count
                FROM federation_messages
                WHERE receiver_tenant_id = ?
                AND receiver_user_id = ?
                AND direction = 'inbound'
                AND status = 'unread'
            ");
            $stmt->execute([$tenantId, $userId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            return (int)($result['count'] ?? 0);
        } catch (\Exception $e) {
            error_log("FederatedMessageService::getUnreadCount error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get sender info for realtime notifications
     */
    private static function getSenderInfo(int $senderId, int $senderTenantId): array
    {
        $db = Database::getConnection();

        try {
            $stmt = $db->prepare("
                SELECT u.name, u.first_name, u.last_name, t.name as tenant_name
                FROM users u
                JOIN tenants t ON u.tenant_id = t.id
                WHERE u.id = ? AND u.tenant_id = ?
            ");
            $stmt->execute([$senderId, $senderTenantId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result) {
                return [
                    'name' => $result['name'] ?: trim($result['first_name'] . ' ' . $result['last_name']),
                    'tenant_name' => $result['tenant_name']
                ];
            }
        } catch (\Exception $e) {
            error_log("FederatedMessageService::getSenderInfo error: " . $e->getMessage());
        }

        return ['name' => 'Unknown', 'tenant_name' => 'Partner Timebank'];
    }

    /**
     * Store a message sent to an external partner (for local inbox display)
     *
     * External partner messages are sent via API but we need a local record
     * so they appear in the user's federation messages inbox.
     *
     * @param int $senderId The sender's user ID (local user)
     * @param int $externalPartnerId The external partner ID
     * @param int $receiverId The receiver's user ID on the external system
     * @param string $receiverName The receiver's display name
     * @param string $partnerName The external partner's name
     * @param string $subject Message subject
     * @param string $body Message body
     * @param string|null $externalMessageId Message ID returned from external API
     * @return array Result with success status and local message ID
     */
    public static function storeExternalMessage(
        int $senderId,
        int $externalPartnerId,
        int $receiverId,
        string $receiverName,
        string $partnerName,
        string $subject,
        string $body,
        ?string $externalMessageId = null
    ): array {
        $senderTenantId = TenantContext::getId();
        $db = Database::getConnection();

        try {
            // Store as outbound message with external partner reference
            // We use negative external_partner_id as a "virtual tenant" to distinguish external messages
            $stmt = $db->prepare("
                INSERT INTO federation_messages
                (sender_tenant_id, sender_user_id, receiver_tenant_id, receiver_user_id,
                 subject, body, direction, status, external_partner_id, external_receiver_name,
                 external_message_id, created_at)
                VALUES (?, ?, 0, ?, ?, ?, 'outbound', 'delivered', ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $senderTenantId,
                $senderId,
                $receiverId,
                $subject,
                $body,
                $externalPartnerId,
                $receiverName,
                $externalMessageId
            ]);
            $messageId = $db->lastInsertId();

            // Log the federated action
            FederationAuditService::log(
                'external_message_sent',
                $senderTenantId,
                0, // No tenant ID for external partner
                $senderId,
                [
                    'external_partner_id' => $externalPartnerId,
                    'external_partner_name' => $partnerName,
                    'receiver_id' => $receiverId,
                    'receiver_name' => $receiverName,
                    'message_id' => $messageId,
                    'external_message_id' => $externalMessageId
                ]
            );

            return ['success' => true, 'message_id' => $messageId];

        } catch (\Exception $e) {
            error_log("FederatedMessageService::storeExternalMessage error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to store message locally'];
        }
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
     * Get federated user info for display in thread
     *
     * Note: This uses LEFT JOIN so we can still display threads with users who
     * may have disabled federation after initial contact. The caller should check
     * messaging_enabled_federated before allowing new messages.
     */
    public static function getFederatedUserInfo(int $userId, int $tenantId): ?array
    {
        $db = Database::getConnection();

        try {
            // First check if user exists at all (for debugging)
            $checkStmt = $db->prepare("SELECT id, tenant_id, name FROM users WHERE id = ?");
            $checkStmt->execute([$userId]);
            $checkResult = $checkStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$checkResult) {
                error_log("FederatedMessageService::getFederatedUserInfo - User {$userId} does not exist in any tenant");
            } elseif ((int)$checkResult['tenant_id'] !== $tenantId) {
                error_log("FederatedMessageService::getFederatedUserInfo - User {$userId} exists but in tenant {$checkResult['tenant_id']}, not {$tenantId}");
            }

            $stmt = $db->prepare("
                SELECT u.id, u.name, u.avatar_url, u.bio,
                       t.name as tenant_name, t.domain as tenant_domain,
                       COALESCE(fus.service_reach, 'none') as service_reach,
                       COALESCE(fus.messaging_enabled_federated, 0) as messaging_enabled_federated,
                       COALESCE(fus.federation_optin, 0) as federation_optin
                FROM users u
                INNER JOIN tenants t ON u.tenant_id = t.id
                LEFT JOIN federation_user_settings fus ON u.id = fus.user_id
                WHERE u.id = ?
                AND u.tenant_id = ?
            ");
            $stmt->execute([$userId, $tenantId]);

            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Exception $e) {
            error_log("FederatedMessageService::getFederatedUserInfo error: " . $e->getMessage());
            return null;
        }
    }
}
