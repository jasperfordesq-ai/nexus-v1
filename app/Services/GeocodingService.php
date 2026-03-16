<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * GeocodingService — Laravel DI wrapper for legacy \Nexus\Services\GeocodingService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class GeocodingService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy GeocodingService::geocode().
     */
    public function geocode(string $address): ?array
    {
        return \Nexus\Services\GeocodingService::geocode($address);
    }

    /**
     * Delegates to legacy GeocodingService::updateUserCoordinates().
     */
    public function updateUserCoordinates(int $userId, ?string $location): bool
    {
        return \Nexus\Services\GeocodingService::updateUserCoordinates($userId, $location);
    }

    /**
     * Delegates to legacy GeocodingService::updateListingCoordinates().
     */
    public function updateListingCoordinates(int $listingId, ?string $location): bool
    {
        return \Nexus\Services\GeocodingService::updateListingCoordinates($listingId, $location);
    }

    /**
     * Delegates to legacy GeocodingService::batchGeocodeUsers().
     */
    public function batchGeocodeUsers(int $limit = 100): array
    {
        return \Nexus\Services\GeocodingService::batchGeocodeUsers($limit);
    }

    /**
     * Delegates to legacy GeocodingService::batchGeocodeListings().
     */
    public function batchGeocodeListings(int $limit = 100): array
    {
        return \Nexus\Services\GeocodingService::batchGeocodeListings($limit);
    }
}
