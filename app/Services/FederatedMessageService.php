<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
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
    public static function sendMessage(int $senderId, int $receiverId, int $receiverTenantId, string $subject, string $body): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy FederatedMessageService::getInbox().
     */
    public static function getInbox(int $userId, int $limit = 50, int $offset = 0): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy FederatedMessageService::getThread().
     */
    public static function getThread(int $userId, int $otherUserId, int $otherTenantId, int $limit = 100): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy FederatedMessageService::markAsRead().
     */
    public static function markAsRead(int $messageId, int $userId): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy FederatedMessageService::markThreadAsRead().
     */
    public static function markThreadAsRead(int $userId, int $otherUserId, int $otherTenantId): int
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return 0;
    }
}
