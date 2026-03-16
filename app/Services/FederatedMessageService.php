<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * FederatedMessageService — Laravel DI wrapper for legacy \Nexus\Services\FederatedMessageService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class FederatedMessageService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy FederatedMessageService::sendMessage().
     */
    public function sendMessage(int $senderId, int $receiverId, int $receiverTenantId, string $subject, string $body): array
    {
        return \Nexus\Services\FederatedMessageService::sendMessage($senderId, $receiverId, $receiverTenantId, $subject, $body);
    }

    /**
     * Delegates to legacy FederatedMessageService::getInbox().
     */
    public function getInbox(int $userId, int $limit = 50, int $offset = 0): array
    {
        return \Nexus\Services\FederatedMessageService::getInbox($userId, $limit, $offset);
    }

    /**
     * Delegates to legacy FederatedMessageService::getThread().
     */
    public function getThread(int $userId, int $otherUserId, int $otherTenantId, int $limit = 100): array
    {
        return \Nexus\Services\FederatedMessageService::getThread($userId, $otherUserId, $otherTenantId, $limit);
    }

    /**
     * Delegates to legacy FederatedMessageService::markAsRead().
     */
    public function markAsRead(int $messageId, int $userId): bool
    {
        return \Nexus\Services\FederatedMessageService::markAsRead($messageId, $userId);
    }

    /**
     * Delegates to legacy FederatedMessageService::markThreadAsRead().
     */
    public function markThreadAsRead(int $userId, int $otherUserId, int $otherTenantId): int
    {
        return \Nexus\Services\FederatedMessageService::markThreadAsRead($userId, $otherUserId, $otherTenantId);
    }
}
