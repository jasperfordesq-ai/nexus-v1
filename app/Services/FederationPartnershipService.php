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
    public function __construct()
    {
    }

    /**
     * Delegates to legacy FederationPartnershipService::requestPartnership().
     */
    public function requestPartnership(int $requestingTenantId, int $targetTenantId, int $requestedBy, int $federationLevel = self::LEVEL_DISCOVERY, ?string $notes = null): array
    {
        return \Nexus\Services\FederationPartnershipService::requestPartnership($requestingTenantId, $targetTenantId, $requestedBy, $federationLevel, $notes);
    }

    /**
     * Delegates to legacy FederationPartnershipService::approvePartnership().
     */
    public function approvePartnership(int $partnershipId, int $approvedBy, array $permissions = []): array
    {
        return \Nexus\Services\FederationPartnershipService::approvePartnership($partnershipId, $approvedBy, $permissions);
    }

    /**
     * Delegates to legacy FederationPartnershipService::counterPropose().
     */
    public function counterPropose(int $partnershipId, int $proposedBy, int $newLevel, array $proposedPermissions = [], ?string $message = null): array
    {
        return \Nexus\Services\FederationPartnershipService::counterPropose($partnershipId, $proposedBy, $newLevel, $proposedPermissions, $message);
    }

    /**
     * Delegates to legacy FederationPartnershipService::acceptCounterProposal().
     */
    public function acceptCounterProposal(int $partnershipId, int $acceptedBy): array
    {
        return \Nexus\Services\FederationPartnershipService::acceptCounterProposal($partnershipId, $acceptedBy);
    }

    /**
     * Delegates to legacy FederationPartnershipService::rejectPartnership().
     */
    public function rejectPartnership(int $partnershipId, int $rejectedBy, ?string $reason = null): array
    {
        return \Nexus\Services\FederationPartnershipService::rejectPartnership($partnershipId, $rejectedBy, $reason);
    }
}
