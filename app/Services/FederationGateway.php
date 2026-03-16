<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * FederationGateway — Laravel DI wrapper for legacy \Nexus\Services\FederationGateway.
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
        return \Nexus\Services\FederationGateway::canViewProfile($viewerTenantId, $targetTenantId, $targetUserId, $viewerUserId);
    }

    /**
     * Delegates to legacy FederationGateway::canSendMessage().
     */
    public function canSendMessage(int $senderUserId, int $senderTenantId, int $recipientUserId, int $recipientTenantId): array
    {
        return \Nexus\Services\FederationGateway::canSendMessage($senderUserId, $senderTenantId, $recipientUserId, $recipientTenantId);
    }

    /**
     * Delegates to legacy FederationGateway::recordMessage().
     */
    public function recordMessage(int $senderUserId, int $senderTenantId, int $recipientUserId, int $recipientTenantId, int $messageId): void
    {
        \Nexus\Services\FederationGateway::recordMessage($senderUserId, $senderTenantId, $recipientUserId, $recipientTenantId, $messageId);
    }

    /**
     * Delegates to legacy FederationGateway::canPerformTransaction().
     */
    public function canPerformTransaction(int $initiatorUserId, int $initiatorTenantId, int $counterpartyUserId, int $counterpartyTenantId): array
    {
        return \Nexus\Services\FederationGateway::canPerformTransaction($initiatorUserId, $initiatorTenantId, $counterpartyUserId, $counterpartyTenantId);
    }

    /**
     * Delegates to legacy FederationGateway::recordTransaction().
     */
    public function recordTransaction(int $initiatorUserId, int $initiatorTenantId, int $counterpartyUserId, int $counterpartyTenantId, int $transactionId, string $transactionType, float $amount): void
    {
        \Nexus\Services\FederationGateway::recordTransaction($initiatorUserId, $initiatorTenantId, $counterpartyUserId, $counterpartyTenantId, $transactionId, $transactionType, $amount);
    }
}
