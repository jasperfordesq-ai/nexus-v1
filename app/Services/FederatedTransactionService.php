<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * FederatedTransactionService — Laravel DI wrapper for legacy \Nexus\Services\FederatedTransactionService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class FederatedTransactionService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy FederatedTransactionService::createTransaction().
     */
    public function createTransaction(int $senderId, int $receiverId, int $receiverTenantId, float $amount, string $description): array
    {
        return \Nexus\Services\FederatedTransactionService::createTransaction($senderId, $receiverId, $receiverTenantId, $amount, $description);
    }

    /**
     * Delegates to legacy FederatedTransactionService::getHistory().
     */
    public function getHistory(int $userId, int $limit = 50, int $offset = 0): array
    {
        return \Nexus\Services\FederatedTransactionService::getHistory($userId, $limit, $offset);
    }

    /**
     * Delegates to legacy FederatedTransactionService::getStats().
     */
    public function getStats(int $userId): array
    {
        return \Nexus\Services\FederatedTransactionService::getStats($userId);
    }

    /**
     * Delegates to legacy FederatedTransactionService::canReceiveTransactions().
     */
    public function canReceiveTransactions(int $userId, int $tenantId): bool
    {
        return \Nexus\Services\FederatedTransactionService::canReceiveTransactions($userId, $tenantId);
    }
}
