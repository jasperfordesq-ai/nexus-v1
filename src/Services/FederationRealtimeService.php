<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

/**
 * FederationRealtimeService
 *
 * Handles real-time updates for federated messaging and activities.
 * Uses Pusher channels with federation-specific naming conventions.
 */
class FederationRealtimeService
{
    /**
     * Get federation channel for a user (receives cross-tenant notifications)
     * This channel is unique per user+tenant combination
     */
    public static function getUserFederationChannel(int $userId, int $tenantId): string
    {
        return "private-federation.user.{$tenantId}.{$userId}";
    }

    /**
     * Get federation channel for a conversation between two federated users
     * Channel name includes both tenant and user IDs for uniqueness
     */
    public static function getConversationChannel(
        int $user1Id, int $tenant1Id,
        int $user2Id, int $tenant2Id
    ): string {
        // Normalize ordering to ensure same channel regardless of who initiates
        if ($tenant1Id < $tenant2Id || ($tenant1Id === $tenant2Id && $user1Id < $user2Id)) {
            return "private-federation.chat.{$tenant1Id}.{$user1Id}.{$tenant2Id}.{$user2Id}";
        }
        return "private-federation.chat.{$tenant2Id}.{$user2Id}.{$tenant1Id}.{$user1Id}";
    }

    /**
     * Broadcast a new federated message event
     */
    public static function broadcastNewMessage(
        int $senderUserId,
        int $senderTenantId,
        int $recipientUserId,
        int $recipientTenantId,
        array $messageData
    ): bool {
        if (!PusherService::isConfigured()) {
            return false;
        }

        // Get the recipient's federation channel
        $recipientChannel = self::getUserFederationChannel($recipientUserId, $recipientTenantId);

        // Get the conversation channel
        $conversationChannel = self::getConversationChannel(
            $senderUserId, $senderTenantId,
            $recipientUserId, $recipientTenantId
        );

        // Broadcast to recipient's federation notification channel
        PusherService::trigger($recipientChannel, 'federation.new-message', [
            'sender_user_id' => $senderUserId,
            'sender_tenant_id' => $senderTenantId,
            'sender_name' => $messageData['sender_name'] ?? 'Unknown',
            'sender_tenant_name' => $messageData['sender_tenant_name'] ?? 'Partner Timebank',
            'subject' => $messageData['subject'] ?? '',
            'preview' => substr($messageData['body'] ?? '', 0, 100),
            'timestamp' => date('c'),
        ]);

        // Broadcast to conversation channel for live thread updates
        PusherService::trigger($conversationChannel, 'federation.message', [
            'id' => $messageData['message_id'] ?? 0,
            'sender_user_id' => $senderUserId,
            'sender_tenant_id' => $senderTenantId,
            'sender_name' => $messageData['sender_name'] ?? 'Unknown',
            'body' => $messageData['body'] ?? '',
            'created_at' => date('c'),
        ]);

        return true;
    }

    /**
     * Broadcast typing indicator for federated messaging
     */
    public static function broadcastTyping(
        int $userId,
        int $tenantId,
        int $recipientUserId,
        int $recipientTenantId,
        bool $isTyping = true
    ): bool {
        if (!PusherService::isConfigured()) {
            return false;
        }

        $conversationChannel = self::getConversationChannel(
            $userId, $tenantId,
            $recipientUserId, $recipientTenantId
        );

        PusherService::trigger($conversationChannel, 'federation.typing', [
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'is_typing' => $isTyping,
        ]);

        return true;
    }

    /**
     * Broadcast message read status
     */
    public static function broadcastMessageRead(
        int $readerId,
        int $readerTenantId,
        int $senderUserId,
        int $senderTenantId
    ): bool {
        if (!PusherService::isConfigured()) {
            return false;
        }

        // Notify the sender their message was read
        $senderChannel = self::getUserFederationChannel($senderUserId, $senderTenantId);

        PusherService::trigger($senderChannel, 'federation.message-read', [
            'reader_user_id' => $readerId,
            'reader_tenant_id' => $readerTenantId,
            'timestamp' => date('c'),
        ]);

        return true;
    }

    /**
     * Broadcast federated transaction notification
     */
    public static function broadcastTransaction(
        int $senderUserId,
        int $senderTenantId,
        int $recipientUserId,
        int $recipientTenantId,
        float $amount,
        string $description
    ): bool {
        if (!PusherService::isConfigured()) {
            return false;
        }

        $recipientChannel = self::getUserFederationChannel($recipientUserId, $recipientTenantId);

        PusherService::trigger($recipientChannel, 'federation.transaction', [
            'sender_user_id' => $senderUserId,
            'sender_tenant_id' => $senderTenantId,
            'amount' => $amount,
            'description' => substr($description, 0, 100),
            'timestamp' => date('c'),
        ]);

        return true;
    }

    /**
     * Authenticate a federation channel subscription
     * Federation channels can be accessed by users from different tenants
     */
    public static function authFederationChannel(
        string $channelName,
        string $socketId,
        int $userId,
        int $tenantId
    ): ?string {
        $pusher = PusherService::getInstance();
        if ($pusher === null) {
            return null;
        }

        // Validate channel format
        if (strpos($channelName, 'private-federation.') !== 0) {
            return null;
        }

        // For user channels, verify the user+tenant matches
        if (preg_match('/^private-federation\.user\.(\d+)\.(\d+)$/', $channelName, $matches)) {
            $channelTenantId = (int)$matches[1];
            $channelUserId = (int)$matches[2];

            if ($channelTenantId !== $tenantId || $channelUserId !== $userId) {
                error_log("[FederationRealtime] Channel auth denied: User {$userId}@{$tenantId} cannot access {$channelName}");
                return null;
            }
        }

        // For chat channels, verify the user is one of the participants
        if (preg_match('/^private-federation\.chat\.(\d+)\.(\d+)\.(\d+)\.(\d+)$/', $channelName, $matches)) {
            $tenant1 = (int)$matches[1];
            $user1 = (int)$matches[2];
            $tenant2 = (int)$matches[3];
            $user2 = (int)$matches[4];

            $isParticipant = ($tenantId === $tenant1 && $userId === $user1) ||
                             ($tenantId === $tenant2 && $userId === $user2);

            if (!$isParticipant) {
                error_log("[FederationRealtime] Chat channel auth denied: User {$userId}@{$tenantId} not a participant in {$channelName}");
                return null;
            }

            // Also verify federation partnership exists between tenants
            $partnerTenantId = ($tenantId === $tenant1) ? $tenant2 : $tenant1;
            if (!FederationGateway::hasActivePartnership($tenantId, $partnerTenantId)) {
                error_log("[FederationRealtime] Chat channel auth denied: No active partnership between tenants {$tenantId} and {$partnerTenantId}");
                return null;
            }
        }

        try {
            return $pusher->authorizeChannel($channelName, $socketId);
        } catch (\Exception $e) {
            error_log('[FederationRealtime] Channel auth failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Broadcast partnership status change
     */
    public static function broadcastPartnershipUpdate(
        int $tenantId,
        int $partnerTenantId,
        string $status,
        array $partnerData = []
    ): bool {
        if (!PusherService::isConfigured()) {
            // Queue for SSE fallback
            self::queueSSEEvent($tenantId, 'partnership.update', [
                'partner_tenant_id' => $partnerTenantId,
                'status' => $status,
                'partner_name' => $partnerData['name'] ?? 'Partner Timebank',
                'timestamp' => date('c'),
            ]);
            return true;
        }

        // Broadcast to tenant admin channel
        $channel = "private-federation.admin.{$tenantId}";
        PusherService::trigger($channel, 'federation.partnership-update', [
            'partner_tenant_id' => $partnerTenantId,
            'status' => $status,
            'partner_name' => $partnerData['name'] ?? 'Partner Timebank',
            'timestamp' => date('c'),
        ]);

        return true;
    }

    /**
     * Broadcast new member joined federation
     */
    public static function broadcastNewMember(
        int $tenantId,
        int $userId,
        string $userName,
        string $privacyLevel
    ): bool {
        if (!PusherService::isConfigured()) {
            self::queueSSEEvent($tenantId, 'member.joined', [
                'user_id' => $userId,
                'user_name' => $userName,
                'privacy_level' => $privacyLevel,
                'timestamp' => date('c'),
            ]);
            return true;
        }

        $channel = "private-federation.admin.{$tenantId}";
        PusherService::trigger($channel, 'federation.member-joined', [
            'user_id' => $userId,
            'user_name' => $userName,
            'privacy_level' => $privacyLevel,
            'timestamp' => date('c'),
        ]);

        return true;
    }

    /**
     * Broadcast activity event for user's feed
     */
    public static function broadcastActivityEvent(
        int $userId,
        int $tenantId,
        string $eventType,
        array $eventData
    ): bool {
        $eventData['event_type'] = $eventType;
        $eventData['timestamp'] = date('c');

        if (!PusherService::isConfigured()) {
            self::queueUserSSEEvent($userId, $tenantId, 'activity', $eventData);
            return true;
        }

        $channel = self::getUserFederationChannel($userId, $tenantId);
        PusherService::trigger($channel, 'federation.activity', $eventData);

        return true;
    }

    /**
     * Queue event for SSE delivery (fallback when Pusher unavailable)
     */
    public static function queueSSEEvent(int $tenantId, string $eventType, array $data): void
    {
        $db = \Nexus\Core\Database::getInstance();
        $stmt = $db->prepare("
            INSERT INTO federation_realtime_queue
            (tenant_id, user_id, event_type, event_data, created_at)
            VALUES (?, NULL, ?, ?, NOW())
        ");
        $stmt->execute([$tenantId, $eventType, json_encode($data)]);
    }

    /**
     * Queue user-specific event for SSE delivery
     */
    public static function queueUserSSEEvent(int $userId, int $tenantId, string $eventType, array $data): void
    {
        $db = \Nexus\Core\Database::getInstance();
        $stmt = $db->prepare("
            INSERT INTO federation_realtime_queue
            (tenant_id, user_id, event_type, event_data, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$tenantId, $userId, $eventType, json_encode($data)]);
    }

    /**
     * Get pending SSE events for a user
     */
    public static function getPendingEvents(int $userId, int $tenantId, ?string $lastEventId = null): array
    {
        $db = \Nexus\Core\Database::getInstance();

        $sql = "
            SELECT id, event_type, event_data, created_at
            FROM federation_realtime_queue
            WHERE tenant_id = ?
            AND (user_id = ? OR user_id IS NULL)
            AND delivered_at IS NULL
        ";
        $params = [$tenantId, $userId];

        if ($lastEventId) {
            $sql .= " AND id > ?";
            $params[] = (int)$lastEventId;
        }

        $sql .= " ORDER BY id ASC LIMIT 50";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Mark events as delivered
     */
    public static function markEventsDelivered(array $eventIds): void
    {
        if (empty($eventIds)) return;

        $db = \Nexus\Core\Database::getInstance();
        $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
        $stmt = $db->prepare("
            UPDATE federation_realtime_queue
            SET delivered_at = NOW()
            WHERE id IN ({$placeholders})
        ");
        $stmt->execute($eventIds);
    }

    /**
     * Clean up old delivered events (run periodically)
     */
    public static function cleanupOldEvents(int $hoursOld = 24): int
    {
        $db = \Nexus\Core\Database::getInstance();
        $stmt = $db->prepare("
            DELETE FROM federation_realtime_queue
            WHERE delivered_at IS NOT NULL
            AND delivered_at < DATE_SUB(NOW(), INTERVAL ? HOUR)
        ");
        $stmt->execute([$hoursOld]);
        return $stmt->rowCount();
    }

    /**
     * Check if real-time features are available (Pusher or SSE)
     */
    public static function isAvailable(): bool
    {
        return true; // SSE fallback always available
    }

    /**
     * Get connection method
     */
    public static function getConnectionMethod(): string
    {
        return PusherService::isConfigured() ? 'pusher' : 'sse';
    }
}
