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
class FederatedConnectionService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy FederatedConnectionService::sendRequest().
     */
    public function sendRequest(int $requesterId, int $receiverId, int $receiverTenantId, ?string $message = null): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy FederatedConnectionService::acceptRequest().
     */
    public function acceptRequest(int $connectionId, int $userId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy FederatedConnectionService::rejectRequest().
     */
    public function rejectRequest(int $connectionId, int $userId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy FederatedConnectionService::removeConnection().
     */
    public function removeConnection(int $connectionId, int $userId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy FederatedConnectionService::getStatus().
     */
    public function getStatus(int $userId, int $otherUserId, int $otherTenantId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy FederatedConnectionService::getConnections().
     */
    public function getConnections(int $userId, string $statusFilter = 'accepted', int $limit = 50, int $offset = 0): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy FederatedConnectionService::getPendingCount().
     */
    public function getPendingCount(int $userId): int
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return 0;
    }
}
