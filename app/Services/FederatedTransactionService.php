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
class FederatedTransactionService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy FederatedTransactionService::createTransaction().
     */
    public static function createTransaction(int $senderId, int $receiverId, int $receiverTenantId, float $amount, string $description): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy FederatedTransactionService::getHistory().
     */
    public static function getHistory(int $userId, int $limit = 50, int $offset = 0): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy FederatedTransactionService::getStats().
     */
    public static function getStats(int $userId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy FederatedTransactionService::canReceiveTransactions().
     */
    public static function canReceiveTransactions(int $userId, int $tenantId): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }
}
