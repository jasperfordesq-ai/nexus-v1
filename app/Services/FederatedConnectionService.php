<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * FederatedConnectionService — Laravel DI wrapper for legacy \Nexus\Services\FederatedConnectionService.
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
        return \Nexus\Services\FederatedConnectionService::sendRequest($requesterId, $receiverId, $receiverTenantId, $message);
    }

    /**
     * Delegates to legacy FederatedConnectionService::acceptRequest().
     */
    public function acceptRequest(int $connectionId, int $userId): array
    {
        return \Nexus\Services\FederatedConnectionService::acceptRequest($connectionId, $userId);
    }

    /**
     * Delegates to legacy FederatedConnectionService::rejectRequest().
     */
    public function rejectRequest(int $connectionId, int $userId): array
    {
        return \Nexus\Services\FederatedConnectionService::rejectRequest($connectionId, $userId);
    }

    /**
     * Delegates to legacy FederatedConnectionService::removeConnection().
     */
    public function removeConnection(int $connectionId, int $userId): array
    {
        return \Nexus\Services\FederatedConnectionService::removeConnection($connectionId, $userId);
    }

    /**
     * Delegates to legacy FederatedConnectionService::getStatus().
     */
    public function getStatus(int $userId, int $otherUserId, int $otherTenantId): array
    {
        return \Nexus\Services\FederatedConnectionService::getStatus($userId, $otherUserId, $otherTenantId);
    }

    /**
     * Delegates to legacy FederatedConnectionService::getConnections().
     */
    public function getConnections(int $userId, string $statusFilter = 'accepted', int $limit = 50, int $offset = 0): array
    {
        return \Nexus\Services\FederatedConnectionService::getConnections($userId, $statusFilter, $limit, $offset);
    }

    /**
     * Delegates to legacy FederatedConnectionService::getPendingCount().
     */
    public function getPendingCount(int $userId): int
    {
        return \Nexus\Services\FederatedConnectionService::getPendingCount($userId);
    }
}
