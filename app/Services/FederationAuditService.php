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
    /** Log levels — mirrored from legacy */
    public const LEVEL_DEBUG = \Nexus\Services\FederationAuditService::LEVEL_DEBUG;
    public const LEVEL_INFO = \Nexus\Services\FederationAuditService::LEVEL_INFO;
    public const LEVEL_WARNING = \Nexus\Services\FederationAuditService::LEVEL_WARNING;
    public const LEVEL_CRITICAL = \Nexus\Services\FederationAuditService::LEVEL_CRITICAL;

    /** Action categories — mirrored from legacy */
    public const CATEGORY_SYSTEM = \Nexus\Services\FederationAuditService::CATEGORY_SYSTEM;
    public const CATEGORY_TENANT = \Nexus\Services\FederationAuditService::CATEGORY_TENANT;
    public const CATEGORY_PARTNERSHIP = \Nexus\Services\FederationAuditService::CATEGORY_PARTNERSHIP;
    public const CATEGORY_PROFILE = \Nexus\Services\FederationAuditService::CATEGORY_PROFILE;
    public const CATEGORY_MESSAGING = \Nexus\Services\FederationAuditService::CATEGORY_MESSAGING;
    public const CATEGORY_TRANSACTION = \Nexus\Services\FederationAuditService::CATEGORY_TRANSACTION;
    public const CATEGORY_LISTING = \Nexus\Services\FederationAuditService::CATEGORY_LISTING;
    public const CATEGORY_EVENT = \Nexus\Services\FederationAuditService::CATEGORY_EVENT;
    public const CATEGORY_GROUP = \Nexus\Services\FederationAuditService::CATEGORY_GROUP;
    public const CATEGORY_SEARCH = \Nexus\Services\FederationAuditService::CATEGORY_SEARCH;

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

    /**
     * Delegates to legacy FederationAuditService::logPartnershipChange().
     */
    public function logPartnershipChange(int $tenantId, int $partnerTenantId, string $newStatus, ?int $actorUserId = null, ?string $reason = null): bool
    {
        return \Nexus\Services\FederationAuditService::logPartnershipChange($tenantId, $partnerTenantId, $newStatus, $actorUserId, $reason);
    }

    /**
     * Delegates to legacy FederationAuditService::getLog().
     */
    public function getLog(array $filters = []): array
    {
        return \Nexus\Services\FederationAuditService::getLog($filters);
    }

    /**
     * Delegates to legacy FederationAuditService::getStats().
     */
    public function getStats(int $days = 30): array
    {
        return \Nexus\Services\FederationAuditService::getStats($days);
    }

    /**
     * Delegates to legacy FederationAuditService::getRecentCritical().
     */
    public function getRecentCritical(int $limit = 10): array
    {
        return \Nexus\Services\FederationAuditService::getRecentCritical($limit);
    }

    /**
     * Delegates to legacy FederationAuditService::purgeOld().
     */
    public function purgeOld(int $retentionDays = 365): int
    {
        return \Nexus\Services\FederationAuditService::purgeOld($retentionDays);
    }

    /**
     * Delegates to legacy FederationAuditService::getActionLabel().
     */
    public function getActionLabel(string $actionType): string
    {
        return \Nexus\Services\FederationAuditService::getActionLabel($actionType);
    }

    /**
     * Delegates to legacy FederationAuditService::getActionIcon().
     */
    public function getActionIcon(string $actionType): string
    {
        return \Nexus\Services\FederationAuditService::getActionIcon($actionType);
    }

    /**
     * Delegates to legacy FederationAuditService::getLevelBadge().
     */
    public function getLevelBadge(string $level): string
    {
        return \Nexus\Services\FederationAuditService::getLevelBadge($level);
    }
}
