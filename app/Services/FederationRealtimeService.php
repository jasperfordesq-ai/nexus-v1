<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * FederationRealtimeService — Laravel DI wrapper for legacy \Nexus\Services\FederationRealtimeService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class FederationRealtimeService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy FederationRealtimeService::getUserFederationChannel().
     */
    public function getUserFederationChannel(int $userId, int $tenantId): string
    {
        return \Nexus\Services\FederationRealtimeService::getUserFederationChannel($userId, $tenantId);
    }

    /**
     * Delegates to legacy FederationRealtimeService::getConversationChannel().
     */
    public function getConversationChannel(int $user1Id, int $tenant1Id, int $user2Id, int $tenant2Id): string
    {
        return \Nexus\Services\FederationRealtimeService::getConversationChannel($user1Id, $tenant1Id, $user2Id, $tenant2Id);
    }

    /**
     * Delegates to legacy FederationRealtimeService::broadcastNewMessage().
     */
    public function broadcastNewMessage(int $senderUserId, int $senderTenantId, int $recipientUserId, int $recipientTenantId, array $messageData): bool
    {
        return \Nexus\Services\FederationRealtimeService::broadcastNewMessage($senderUserId, $senderTenantId, $recipientUserId, $recipientTenantId, $messageData);
    }

    /**
     * Delegates to legacy FederationRealtimeService::broadcastTyping().
     */
    public function broadcastTyping(int $userId, int $tenantId, int $recipientUserId, int $recipientTenantId, bool $isTyping = true): bool
    {
        return \Nexus\Services\FederationRealtimeService::broadcastTyping($userId, $tenantId, $recipientUserId, $recipientTenantId, $isTyping);
    }

    /**
     * Delegates to legacy FederationRealtimeService::broadcastMessageRead().
     */
    public function broadcastMessageRead(int $readerId, int $readerTenantId, int $senderUserId, int $senderTenantId): bool
    {
        return \Nexus\Services\FederationRealtimeService::broadcastMessageRead($readerId, $readerTenantId, $senderUserId, $senderTenantId);
    }

    /**
     * Delegates to legacy FederationRealtimeService::authFederationChannel().
     */
    public function authFederationChannel(string $channelName, string $socketId, int $userId, int $tenantId): ?string
    {
        return \Nexus\Services\FederationRealtimeService::authFederationChannel($channelName, $socketId, $userId, $tenantId);
    }
}
