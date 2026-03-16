<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * FederationAuditService — Laravel DI wrapper for legacy \Nexus\Services\FederationAuditService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class FederationAuditService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy FederationAuditService::log().
     */
    public function log(string $actionType, ?int $sourceTenantId = null, ?int $targetTenantId = null, ?int $actorUserId = null, array $data = [], string $level = self::LEVEL_INFO): bool
    {
        return \Nexus\Services\FederationAuditService::log($actionType, $sourceTenantId, $targetTenantId, $actorUserId, $data, $level);
    }

    /**
     * Delegates to legacy FederationAuditService::logSearch().
     */
    public function logSearch(string $searchType, array $filters, int $resultsCount, ?int $actorUserId = null): bool
    {
        return \Nexus\Services\FederationAuditService::logSearch($searchType, $filters, $resultsCount, $actorUserId);
    }

    /**
     * Delegates to legacy FederationAuditService::logProfileView().
     */
    public function logProfileView(int $viewerUserId, int $viewerTenantId, int $viewedUserId, int $viewedTenantId): bool
    {
        return \Nexus\Services\FederationAuditService::logProfileView($viewerUserId, $viewerTenantId, $viewedUserId, $viewedTenantId);
    }

    /**
     * Delegates to legacy FederationAuditService::logMessage().
     */
    public function logMessage(int $senderUserId, int $senderTenantId, int $recipientUserId, int $recipientTenantId, ?int $messageId = null): bool
    {
        return \Nexus\Services\FederationAuditService::logMessage($senderUserId, $senderTenantId, $recipientUserId, $recipientTenantId, $messageId);
    }

    /**
     * Delegates to legacy FederationAuditService::logTransaction().
     */
    public function logTransaction(int $initiatorUserId, int $initiatorTenantId, int $counterpartyUserId, int $counterpartyTenantId, int $transactionId, string $transactionType, float $amount): bool
    {
        return \Nexus\Services\FederationAuditService::logTransaction($initiatorUserId, $initiatorTenantId, $counterpartyUserId, $counterpartyTenantId, $transactionId, $transactionType, $amount);
    }
}
