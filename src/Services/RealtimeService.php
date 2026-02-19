<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\TenantContext;

/**
 * RealtimeService - High-level broadcast service for real-time events
 *
 * Provides a unified API for broadcasting real-time events via Pusher.
 * Handles notifications, messages, presence, and typing indicators.
 */
class RealtimeService
{
    /**
     * Broadcast a notification to a specific user
     *
     * @param int $userId Target user ID
     * @param array $notification Notification data (id, type, message, link, etc.)
     * @return bool Success status
     */
    public static function broadcastNotification(int $userId, array $notification): bool
    {
        if (!PusherService::isConfigured()) {
            return false;
        }

        $channel = PusherService::getUserChannel($userId);

        return PusherService::trigger($channel, 'notification', [
            'id' => $notification['id'] ?? null,
            'type' => $notification['type'] ?? 'general',
            'message' => $notification['message'] ?? '',
            'link' => $notification['link'] ?? null,
            'created_at' => $notification['created_at'] ?? date('Y-m-d H:i:s'),
            'timestamp' => time() * 1000,
        ]);
    }

    /**
     * Broadcast a new message in a conversation
     *
     * @param int $senderId Sender user ID
     * @param int $receiverId Receiver user ID
     * @param array $message Message data (id, body, subject, etc.)
     * @return bool Success status
     */
    public static function broadcastMessage(int $senderId, int $receiverId, array $message): bool
    {
        if (!PusherService::isConfigured()) {
            return false;
        }

        // Create a deterministic chat ID (smaller ID first for consistency)
        $chatId = min($senderId, $receiverId) . '-' . max($senderId, $receiverId);
        $channel = PusherService::getChatChannel($chatId);

        // Also notify the receiver on their personal channel
        $receiverChannel = PusherService::getUserChannel($receiverId);

        $eventData = [
            'id' => $message['id'] ?? null,
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'subject' => $message['subject'] ?? '',
            'body' => $message['body'] ?? '',
            'created_at' => $message['created_at'] ?? date('Y-m-d H:i:s'),
            'timestamp' => time() * 1000,
        ];

        // Broadcast to chat channel (for open conversation windows)
        $chatSent = PusherService::trigger($channel, 'message', $eventData);

        // Also send a notification event to receiver's personal channel
        $notifSent = PusherService::trigger($receiverChannel, 'new-message', [
            'from_user_id' => $senderId,
            'preview' => mb_substr($message['body'] ?? '', 0, 100),
            'timestamp' => time() * 1000,
        ]);

        return $chatSent || $notifSent;
    }

    /**
     * Broadcast typing indicator
     *
     * @param int $userId User who is typing
     * @param int $toUserId User being typed to
     * @param bool $isTyping Whether typing started or stopped
     * @return bool Success status
     */
    public static function broadcastTyping(int $userId, int $toUserId, bool $isTyping = true): bool
    {
        if (!PusherService::isConfigured()) {
            return false;
        }

        // Chat channel for the conversation
        $chatId = min($userId, $toUserId) . '-' . max($userId, $toUserId);
        $channel = PusherService::getChatChannel($chatId);

        return PusherService::trigger($channel, 'typing', [
            'user_id' => $userId,
            'is_typing' => $isTyping,
            'timestamp' => time() * 1000,
        ]);
    }

    /**
     * Broadcast unread count update to a user
     *
     * @param int $userId User ID
     * @param int $notificationCount Unread notifications count
     * @param int $messageCount Unread messages count
     * @return bool Success status
     */
    public static function broadcastUnreadCount(int $userId, int $notificationCount, int $messageCount = 0): bool
    {
        if (!PusherService::isConfigured()) {
            return false;
        }

        $channel = PusherService::getUserChannel($userId);

        return PusherService::trigger($channel, 'unread-count', [
            'notifications' => $notificationCount,
            'messages' => $messageCount,
            'timestamp' => time() * 1000,
        ]);
    }

    /**
     * Broadcast that notifications were marked as read
     *
     * @param int $userId User ID
     * @param array|null $notificationIds Specific IDs marked as read, or null for all
     * @return bool Success status
     */
    public static function broadcastNotificationsRead(int $userId, ?array $notificationIds = null): bool
    {
        if (!PusherService::isConfigured()) {
            return false;
        }

        $channel = PusherService::getUserChannel($userId);

        return PusherService::trigger($channel, 'notifications-read', [
            'ids' => $notificationIds,
            'all' => $notificationIds === null,
            'timestamp' => time() * 1000,
        ]);
    }

    /**
     * Broadcast a custom event to a user
     *
     * @param int $userId Target user ID
     * @param string $event Event name
     * @param array $data Event data
     * @return bool Success status
     */
    public static function broadcastToUser(int $userId, string $event, array $data): bool
    {
        if (!PusherService::isConfigured()) {
            return false;
        }

        $channel = PusherService::getUserChannel($userId);
        $data['timestamp'] = $data['timestamp'] ?? time() * 1000;

        return PusherService::trigger($channel, $event, $data);
    }

    /**
     * Broadcast to multiple users
     *
     * @param array $userIds Array of user IDs
     * @param string $event Event name
     * @param array $data Event data
     * @return bool Success status (true if any succeeded)
     */
    public static function broadcastToUsers(array $userIds, string $event, array $data): bool
    {
        if (!PusherService::isConfigured() || empty($userIds)) {
            return false;
        }

        $channels = array_map(function ($userId) {
            return PusherService::getUserChannel($userId);
        }, $userIds);

        $data['timestamp'] = $data['timestamp'] ?? time() * 1000;

        // Pusher allows up to 100 channels per trigger
        $chunks = array_chunk($channels, 100);
        $success = false;

        foreach ($chunks as $channelChunk) {
            if (PusherService::trigger($channelChunk, $event, $data)) {
                $success = true;
            }
        }

        return $success;
    }

    /**
     * Get Pusher configuration for frontend initialization
     *
     * @return array Configuration for JavaScript client
     */
    public static function getFrontendConfig(): array
    {
        return [
            'key' => PusherService::getPublicKey(),
            'cluster' => PusherService::getCluster(),
            'authEndpoint' => '/api/pusher/auth',
            'enabled' => PusherService::isConfigured(),
        ];
    }
}
