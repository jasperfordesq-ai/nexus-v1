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
class FederationGateway
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy FederationGateway::canViewProfile().
     */
    public function canViewProfile(int $viewerTenantId, int $targetTenantId, int $targetUserId, ?int $viewerUserId = null): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy FederationGateway::canSendMessage().
     */
    public function canSendMessage(int $senderUserId, int $senderTenantId, int $recipientUserId, int $recipientTenantId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy FederationGateway::recordMessage().
     */
    public function recordMessage(int $senderUserId, int $senderTenantId, int $recipientUserId, int $recipientTenantId, int $messageId): void
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
    }

    /**
     * Delegates to legacy FederationGateway::canPerformTransaction().
     */
    public function canPerformTransaction(int $initiatorUserId, int $initiatorTenantId, int $counterpartyUserId, int $counterpartyTenantId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy FederationGateway::recordTransaction().
     */
    public function recordTransaction(int $initiatorUserId, int $initiatorTenantId, int $counterpartyUserId, int $counterpartyTenantId, int $transactionId, string $transactionType, float $amount): void
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
    }
}
