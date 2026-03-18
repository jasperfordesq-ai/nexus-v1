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
    /** Log levels */
    public const LEVEL_DEBUG = 'debug';
    public const LEVEL_INFO = 'info';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_CRITICAL = 'critical';

    /** Action categories */
    public const CATEGORY_SYSTEM = 'system';
    public const CATEGORY_TENANT = 'tenant';
    public const CATEGORY_PARTNERSHIP = 'partnership';
    public const CATEGORY_PROFILE = 'profile';
    public const CATEGORY_MESSAGING = 'messaging';
    public const CATEGORY_TRANSACTION = 'transaction';
    public const CATEGORY_LISTING = 'listing';
    public const CATEGORY_EVENT = 'event';
    public const CATEGORY_GROUP = 'group';
    public const CATEGORY_SEARCH = 'search';

    public function __construct()
    {
    }

    /**
     * Delegates to legacy FederationAuditService::log().
     */
    public function log(string $actionType, ?int $sourceTenantId = null, ?int $targetTenantId = null, ?int $actorUserId = null, array $data = [], string $level = self::LEVEL_INFO): bool
    {
        if (!class_exists('\Nexus\Services\FederationAuditService')) {
            return false;
        }
        return \Nexus\Services\FederationAuditService::log($actionType, $sourceTenantId, $targetTenantId, $actorUserId, $data, $level);
    }

    /**
     * Delegates to legacy FederationAuditService::logSearch().
     */
    public function logSearch(string $searchType, array $filters, int $resultsCount, ?int $actorUserId = null): bool
    {
        if (!class_exists('\Nexus\Services\FederationAuditService')) {
            return false;
        }
        return \Nexus\Services\FederationAuditService::logSearch($searchType, $filters, $resultsCount, $actorUserId);
    }

    /**
     * Delegates to legacy FederationAuditService::logProfileView().
     */
    public function logProfileView(int $viewerUserId, int $viewerTenantId, int $viewedUserId, int $viewedTenantId): bool
    {
        if (!class_exists('\Nexus\Services\FederationAuditService')) {
            return false;
        }
        return \Nexus\Services\FederationAuditService::logProfileView($viewerUserId, $viewerTenantId, $viewedUserId, $viewedTenantId);
    }

    /**
     * Delegates to legacy FederationAuditService::logMessage().
     */
    public function logMessage(int $senderUserId, int $senderTenantId, int $recipientUserId, int $recipientTenantId, ?int $messageId = null): bool
    {
        if (!class_exists('\Nexus\Services\FederationAuditService')) {
            return false;
        }
        return \Nexus\Services\FederationAuditService::logMessage($senderUserId, $senderTenantId, $recipientUserId, $recipientTenantId, $messageId);
    }

    /**
     * Delegates to legacy FederationAuditService::logTransaction().
     */
    public function logTransaction(int $initiatorUserId, int $initiatorTenantId, int $counterpartyUserId, int $counterpartyTenantId, int $transactionId, string $transactionType, float $amount): bool
    {
        if (!class_exists('\Nexus\Services\FederationAuditService')) {
            return false;
        }
        return \Nexus\Services\FederationAuditService::logTransaction($initiatorUserId, $initiatorTenantId, $counterpartyUserId, $counterpartyTenantId, $transactionId, $transactionType, $amount);
    }

    /**
     * Delegates to legacy FederationAuditService::logPartnershipChange().
     */
    public function logPartnershipChange(int $tenantId, int $partnerTenantId, string $newStatus, ?int $actorUserId = null, ?string $reason = null): bool
    {
        if (!class_exists('\Nexus\Services\FederationAuditService')) {
            return false;
        }
        return \Nexus\Services\FederationAuditService::logPartnershipChange($tenantId, $partnerTenantId, $newStatus, $actorUserId, $reason);
    }

    /**
     * Delegates to legacy FederationAuditService::getLog().
     */
    public function getLog(array $filters = []): array
    {
        if (!class_exists('\Nexus\Services\FederationAuditService')) {
            return [];
        }
        return \Nexus\Services\FederationAuditService::getLog($filters);
    }

    /**
     * Delegates to legacy FederationAuditService::getStats().
     */
    public function getStats(int $days = 30): array
    {
        if (!class_exists('\Nexus\Services\FederationAuditService')) {
            return [];
        }
        return \Nexus\Services\FederationAuditService::getStats($days);
    }

    /**
     * Delegates to legacy FederationAuditService::getRecentCritical().
     */
    public function getRecentCritical(int $limit = 10): array
    {
        if (!class_exists('\Nexus\Services\FederationAuditService')) {
            return [];
        }
        return \Nexus\Services\FederationAuditService::getRecentCritical($limit);
    }

    /**
     * Delegates to legacy FederationAuditService::purgeOld().
     */
    public function purgeOld(int $retentionDays = 365): int
    {
        if (!class_exists('\Nexus\Services\FederationAuditService')) {
            return 0;
        }
        return \Nexus\Services\FederationAuditService::purgeOld($retentionDays);
    }

    /**
     * Delegates to legacy FederationAuditService::getActionLabel().
     */
    public function getActionLabel(string $actionType): string
    {
        if (!class_exists('\Nexus\Services\FederationAuditService')) {
            return ucwords(str_replace('_', ' ', $actionType));
        }
        return \Nexus\Services\FederationAuditService::getActionLabel($actionType);
    }

    /**
     * Delegates to legacy FederationAuditService::getActionIcon().
     */
    public function getActionIcon(string $actionType): string
    {
        if (!class_exists('\Nexus\Services\FederationAuditService')) {
            return 'fa-circle';
        }
        return \Nexus\Services\FederationAuditService::getActionIcon($actionType);
    }

    /**
     * Delegates to legacy FederationAuditService::getLevelBadge().
     */
    public function getLevelBadge(string $level): string
    {
        if (!class_exists('\Nexus\Services\FederationAuditService')) {
            return 'badge-secondary';
        }
        return \Nexus\Services\FederationAuditService::getLevelBadge($level);
    }
}
