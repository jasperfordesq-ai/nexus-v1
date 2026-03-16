<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * ListingExpiryService — Laravel DI wrapper for legacy \Nexus\Services\ListingExpiryService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class ListingExpiryService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy ListingExpiryService::processExpiredListings().
     */
    public function processExpiredListings(): array
    {
        return \Nexus\Services\ListingExpiryService::processExpiredListings();
    }

    /**
     * Delegates to legacy ListingExpiryService::processAllTenants().
     */
    public function processAllTenants(): array
    {
        return \Nexus\Services\ListingExpiryService::processAllTenants();
    }

    /**
     * Delegates to legacy ListingExpiryService::renewListing().
     */
    public function renewListing(int $listingId, int $userId): array
    {
        return \Nexus\Services\ListingExpiryService::renewListing($listingId, $userId);
    }

    /**
     * Delegates to legacy ListingExpiryService::setExpiry().
     */
    public function setExpiry(int $listingId, ?string $expiresAt): bool
    {
        return \Nexus\Services\ListingExpiryService::setExpiry($listingId, $expiresAt);
    }
}
