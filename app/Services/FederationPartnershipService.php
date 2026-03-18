<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * FederationPartnershipService — Laravel DI wrapper for legacy \Nexus\Services\FederationPartnershipService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class FederationPartnershipService
{
    /** Partnership level constants */
    public const LEVEL_DISCOVERY = 1;
    public const LEVEL_SOCIAL = 2;
    public const LEVEL_ECONOMIC = 3;
    public const LEVEL_INTEGRATED = 4;

    public function __construct()
    {
    }

    /**
     * Delegates to legacy FederationPartnershipService::requestPartnership().
     */
    public function requestPartnership(int $requestingTenantId, int $targetTenantId, int $requestedBy, int $federationLevel = self::LEVEL_DISCOVERY, ?string $notes = null): array
    {
        if (!class_exists('\Nexus\Services\FederationPartnershipService')) {
            return ['success' => false, 'error' => 'Legacy service unavailable'];
        }
        return \Nexus\Services\FederationPartnershipService::requestPartnership($requestingTenantId, $targetTenantId, $requestedBy, $federationLevel, $notes);
    }

    /**
     * Delegates to legacy FederationPartnershipService::approvePartnership().
     */
    public function approvePartnership(int $partnershipId, int $approvedBy, array $permissions = []): array
    {
        if (!class_exists('\Nexus\Services\FederationPartnershipService')) {
            return ['success' => false, 'error' => 'Legacy service unavailable'];
        }
        return \Nexus\Services\FederationPartnershipService::approvePartnership($partnershipId, $approvedBy, $permissions);
    }

    /**
     * Delegates to legacy FederationPartnershipService::counterPropose().
     */
    public function counterPropose(int $partnershipId, int $proposedBy, int $newLevel, array $proposedPermissions = [], ?string $message = null): array
    {
        if (!class_exists('\Nexus\Services\FederationPartnershipService')) {
            return ['success' => false, 'error' => 'Legacy service unavailable'];
        }
        return \Nexus\Services\FederationPartnershipService::counterPropose($partnershipId, $proposedBy, $newLevel, $proposedPermissions, $message);
    }

    /**
     * Delegates to legacy FederationPartnershipService::acceptCounterProposal().
     */
    public function acceptCounterProposal(int $partnershipId, int $acceptedBy): array
    {
        if (!class_exists('\Nexus\Services\FederationPartnershipService')) {
            return ['success' => false, 'error' => 'Legacy service unavailable'];
        }
        return \Nexus\Services\FederationPartnershipService::acceptCounterProposal($partnershipId, $acceptedBy);
    }

    /**
     * Delegates to legacy FederationPartnershipService::rejectPartnership().
     */
    public function rejectPartnership(int $partnershipId, int $rejectedBy, ?string $reason = null): array
    {
        if (!class_exists('\Nexus\Services\FederationPartnershipService')) {
            return ['success' => false, 'error' => 'Legacy service unavailable'];
        }
        return \Nexus\Services\FederationPartnershipService::rejectPartnership($partnershipId, $rejectedBy, $reason);
    }

    /**
     * Delegates to legacy FederationPartnershipService::suspendPartnership().
     */
    public function suspendPartnership(int $partnershipId, int $suspendedBy, ?string $reason = null): array
    {
        if (!class_exists('\Nexus\Services\FederationPartnershipService')) {
            return ['success' => false, 'error' => 'Legacy service unavailable'];
        }
        return \Nexus\Services\FederationPartnershipService::suspendPartnership($partnershipId, $suspendedBy, $reason);
    }

    /**
     * Delegates to legacy FederationPartnershipService::reactivatePartnership().
     */
    public function reactivatePartnership(int $partnershipId, int $reactivatedBy): array
    {
        if (!class_exists('\Nexus\Services\FederationPartnershipService')) {
            return ['success' => false, 'error' => 'Legacy service unavailable'];
        }
        return \Nexus\Services\FederationPartnershipService::reactivatePartnership($partnershipId, $reactivatedBy);
    }

    /**
     * Delegates to legacy FederationPartnershipService::terminatePartnership().
     */
    public function terminatePartnership(int $partnershipId, int $terminatedBy, ?string $reason = null): array
    {
        if (!class_exists('\Nexus\Services\FederationPartnershipService')) {
            return ['success' => false, 'error' => 'Legacy service unavailable'];
        }
        return \Nexus\Services\FederationPartnershipService::terminatePartnership($partnershipId, $terminatedBy, $reason);
    }

    /**
     * Delegates to legacy FederationPartnershipService::updatePermissions().
     */
    public function updatePermissions(int $partnershipId, int $updatedBy, array $permissions): array
    {
        if (!class_exists('\Nexus\Services\FederationPartnershipService')) {
            return ['success' => false, 'error' => 'Legacy service unavailable'];
        }
        return \Nexus\Services\FederationPartnershipService::updatePermissions($partnershipId, $updatedBy, $permissions);
    }

    /**
     * Delegates to legacy FederationPartnershipService::getPartnershipById().
     */
    public function getPartnershipById(int $id): ?array
    {
        if (!class_exists('\Nexus\Services\FederationPartnershipService')) {
            return null;
        }
        return \Nexus\Services\FederationPartnershipService::getPartnershipById($id);
    }

    /**
     * Delegates to legacy FederationPartnershipService::getPartnership().
     */
    public function getPartnership(int $tenantId1, int $tenantId2): ?array
    {
        if (!class_exists('\Nexus\Services\FederationPartnershipService')) {
            return null;
        }
        return \Nexus\Services\FederationPartnershipService::getPartnership($tenantId1, $tenantId2);
    }

    /**
     * Delegates to legacy FederationPartnershipService::getTenantPartnerships().
     */
    public function getTenantPartnerships(int $tenantId, ?string $status = null): array
    {
        if (!class_exists('\Nexus\Services\FederationPartnershipService')) {
            return [];
        }
        return \Nexus\Services\FederationPartnershipService::getTenantPartnerships($tenantId, $status);
    }

    /**
     * Delegates to legacy FederationPartnershipService::getPendingRequests().
     */
    public function getPendingRequests(int $tenantId): array
    {
        if (!class_exists('\Nexus\Services\FederationPartnershipService')) {
            return [];
        }
        return \Nexus\Services\FederationPartnershipService::getPendingRequests($tenantId);
    }

    /**
     * Delegates to legacy FederationPartnershipService::getCounterProposals().
     */
    public function getCounterProposals(int $tenantId): array
    {
        if (!class_exists('\Nexus\Services\FederationPartnershipService')) {
            return [];
        }
        return \Nexus\Services\FederationPartnershipService::getCounterProposals($tenantId);
    }

    /**
     * Delegates to legacy FederationPartnershipService::getOutgoingRequests().
     */
    public function getOutgoingRequests(int $tenantId): array
    {
        if (!class_exists('\Nexus\Services\FederationPartnershipService')) {
            return [];
        }
        return \Nexus\Services\FederationPartnershipService::getOutgoingRequests($tenantId);
    }

    /**
     * Delegates to legacy FederationPartnershipService::getAllPartnerships().
     */
    public function getAllPartnerships(?string $status = null, int $limit = 100): array
    {
        if (!class_exists('\Nexus\Services\FederationPartnershipService')) {
            return [];
        }
        return \Nexus\Services\FederationPartnershipService::getAllPartnerships($status, $limit);
    }

    /**
     * Delegates to legacy FederationPartnershipService::getStats().
     */
    public function getStats(): array
    {
        if (!class_exists('\Nexus\Services\FederationPartnershipService')) {
            return ['total' => 0, 'active' => 0, 'pending' => 0, 'suspended' => 0, 'terminated' => 0, 'recent' => []];
        }
        return \Nexus\Services\FederationPartnershipService::getStats();
    }

    /**
     * Delegates to legacy FederationPartnershipService::getDefaultPermissions().
     */
    public function getDefaultPermissions(int $level): array
    {
        if (!class_exists('\Nexus\Services\FederationPartnershipService')) {
            return ['profiles' => false, 'messaging' => false, 'transactions' => false, 'listings' => false, 'events' => false, 'groups' => false];
        }
        return \Nexus\Services\FederationPartnershipService::getDefaultPermissions($level);
    }

    /**
     * Delegates to legacy FederationPartnershipService::getLevelName().
     */
    public function getLevelName(int $level): string
    {
        if (!class_exists('\Nexus\Services\FederationPartnershipService')) {
            return 'Unknown';
        }
        return \Nexus\Services\FederationPartnershipService::getLevelName($level);
    }

    /**
     * Delegates to legacy FederationPartnershipService::getLevelDescription().
     */
    public function getLevelDescription(int $level): string
    {
        if (!class_exists('\Nexus\Services\FederationPartnershipService')) {
            return '';
        }
        return \Nexus\Services\FederationPartnershipService::getLevelDescription($level);
    }
}
