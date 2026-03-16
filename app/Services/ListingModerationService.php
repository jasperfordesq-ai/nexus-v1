<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * ListingModerationService — Laravel DI wrapper for legacy \Nexus\Services\ListingModerationService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class ListingModerationService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy ListingModerationService::flag().
     */
    public function flag(int $tenantId, int $listingId, int $userId, string $reason): bool
    {
        return \Nexus\Services\ListingModerationService::flag($tenantId, $listingId, $userId, $reason);
    }

    /**
     * Delegates to legacy ListingModerationService::approve().
     */
    public function approve(int $tenantId, int $listingId, int $adminId): bool
    {
        return \Nexus\Services\ListingModerationService::approve($tenantId, $listingId, $adminId);
    }

    /**
     * Delegates to legacy ListingModerationService::reject().
     */
    public function reject(int $tenantId, int $listingId, int $adminId, string $reason): bool
    {
        return \Nexus\Services\ListingModerationService::reject($tenantId, $listingId, $adminId, $reason);
    }

    /**
     * Delegates to legacy ListingModerationService::getPending().
     */
    public function getPending(int $tenantId): array
    {
        return \Nexus\Services\ListingModerationService::getPending($tenantId);
    }
}
