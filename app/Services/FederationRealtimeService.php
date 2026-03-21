<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Pusher\Pusher;

/**
 * FederationRealtimeService — Pusher-based realtime events for cross-tenant federation.
 *
 * Manages federation-specific channel naming, authentication, and broadcasting
 * for cross-community messages, typing indicators, and read receipts.
 */
class FederationRealtimeService
{
    public function __construct()
    {
    }

    /**
     * Get the federation channel name for a user.
     * Format: private-federation.user.{userId}.{tenantId}
     */
    public static function getUserFederationChannel(int $userId, int $tenantId): string
    {
        return "private-federation.user.{$userId}.{$tenantId}";
    }

    /**
     * Get the conversation channel between two federated users.
     * Uses sorted IDs to ensure both users subscribe to the same channel.
     * Format: private-federation.conversation.{lowerUserId}-{lowerTenantId}.{higherUserId}-{higherTenantId}
     */
    public static function getConversationChannel(int $user1Id, int $tenant1Id, int $user2Id, int $tenant2Id): string
    {
        // Sort to ensure deterministic channel name regardless of who is sender/receiver
        $pair1 = "{$user1Id}-{$tenant1Id}";
        $pair2 = "{$user2Id}-{$tenant2Id}";

        if ($pair1 <= $pair2) {
            return "private-federation.conversation.{$pair1}.{$pair2}";
        }

        return "private-federation.conversation.{$pair2}.{$pair1}";
    }

    /**
     * Broadcast a new federated message to the recipient's channel.
     */
    public static function broadcastNewMessage(int $senderUserId, int $senderTenantId, int $recipientUserId, int $recipientTenantId, array $messageData): bool
    {
        $pusher = self::getPusherInstance();
        if (!$pusher) {
            return false;
        }

        try {
            // Broadcast to the recipient's user channel
            $recipientChannel = self::getUserFederationChannel($recipientUserId, $recipientTenantId);

            $eventData = array_merge($messageData, [
                'sender_user_id' => $senderUserId,
                'sender_tenant_id' => $senderTenantId,
                'timestamp' => now()->toISOString(),
            ]);

            $pusher->trigger($recipientChannel, 'federation-message', $eventData);

            // Also broadcast to the conversation channel
            $conversationChannel = self::getConversationChannel(
                $senderUserId, $senderTenantId,
                $recipientUserId, $recipientTenantId
            );
            $pusher->trigger($conversationChannel, 'new-message', $eventData);

            Log::debug('[FederationRealtime] Message broadcast', [
                'sender' => $senderUserId,
                'recipient' => $recipientUserId,
                'channel' => $recipientChannel,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('[FederationRealtime] broadcastNewMessage failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Broadcast a typing indicator.
     */
    public static function broadcastTyping(int $userId, int $tenantId, int $recipientUserId, int $recipientTenantId, bool $isTyping = true): bool
    {
        $pusher = self::getPusherInstance();
        if (!$pusher) {
            return false;
        }

        try {
            $conversationChannel = self::getConversationChannel(
                $userId, $tenantId,
                $recipientUserId, $recipientTenantId
            );

            $pusher->trigger($conversationChannel, 'typing', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'is_typing' => $isTyping,
                'timestamp' => now()->toISOString(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('[FederationRealtime] broadcastTyping failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Broadcast a message read receipt.
     */
    public static function broadcastMessageRead(int $readerId, int $readerTenantId, int $senderUserId, int $senderTenantId): bool
    {
        $pusher = self::getPusherInstance();
        if (!$pusher) {
            return false;
        }

        try {
            $conversationChannel = self::getConversationChannel(
                $readerId, $readerTenantId,
                $senderUserId, $senderTenantId
            );

            $pusher->trigger($conversationChannel, 'message-read', [
                'reader_id' => $readerId,
                'reader_tenant_id' => $readerTenantId,
                'timestamp' => now()->toISOString(),
            ]);

            // Also notify the sender's user channel
            $senderChannel = self::getUserFederationChannel($senderUserId, $senderTenantId);
            $pusher->trigger($senderChannel, 'federation-read-receipt', [
                'reader_id' => $readerId,
                'reader_tenant_id' => $readerTenantId,
                'timestamp' => now()->toISOString(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('[FederationRealtime] broadcastMessageRead failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Authenticate a federation channel subscription via Pusher.
     *
     * Validates that the user has permission to subscribe to the requested channel,
     * then returns the Pusher auth string (raw JSON for the Pusher client).
     */
    public static function authFederationChannel(string $channelName, string $socketId, int $userId, int $tenantId): ?string
    {
        $pusher = self::getPusherInstance();
        if (!$pusher) {
            return null;
        }

        // Validate the user is allowed to subscribe to this channel
        if (!self::isUserAuthorizedForChannel($channelName, $userId, $tenantId)) {
            Log::warning('[FederationRealtime] Unauthorized channel access attempt', [
                'channel' => $channelName,
                'user' => $userId,
                'tenant' => $tenantId,
            ]);
            return null;
        }

        try {
            $auth = $pusher->authorizeChannel($channelName, $socketId);
            return is_string($auth) ? $auth : json_encode($auth);
        } catch (\Exception $e) {
            Log::error('[FederationRealtime] authFederationChannel failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Check if a user is authorized to subscribe to a federation channel.
     */
    private static function isUserAuthorizedForChannel(string $channelName, int $userId, int $tenantId): bool
    {
        // User's own federation channel
        $userChannel = self::getUserFederationChannel($userId, $tenantId);
        if ($channelName === $userChannel) {
            return true;
        }

        // Conversation channel — user must be one of the participants
        if (str_starts_with($channelName, 'private-federation.conversation.')) {
            $userPair = "{$userId}-{$tenantId}";
            return str_contains($channelName, $userPair);
        }

        return false;
    }

    /**
     * Get a Pusher instance from environment configuration.
     */
    private static function getPusherInstance(): ?Pusher
    {
        $appId = config('broadcasting.connections.pusher.app_id', env('PUSHER_APP_ID'));
        $key = config('broadcasting.connections.pusher.key', env('PUSHER_APP_KEY'));
        $secret = config('broadcasting.connections.pusher.secret', env('PUSHER_APP_SECRET'));
        $cluster = config('broadcasting.connections.pusher.options.cluster', env('PUSHER_APP_CLUSTER', 'eu'));

        if (empty($appId) || empty($key) || empty($secret)) {
            Log::debug('[FederationRealtime] Pusher not configured');
            return null;
        }

        try {
            return new Pusher($key, $secret, $appId, [
                'cluster' => $cluster,
                'useTLS' => true,
            ]);
        } catch (\Exception $e) {
            Log::error('[FederationRealtime] Failed to create Pusher instance', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
