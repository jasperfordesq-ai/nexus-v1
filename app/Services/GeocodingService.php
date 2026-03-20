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
class GeocodingService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy GeocodingService::geocode().
     */
    public static function geocode(string $address): ?array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy GeocodingService::updateUserCoordinates().
     */
    public static function updateUserCoordinates(int $userId, ?string $location): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy GeocodingService::updateListingCoordinates().
     */
    public static function updateListingCoordinates(int $listingId, ?string $location): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy GeocodingService::batchGeocodeUsers().
     */
    public static function batchGeocodeUsers(int $limit = 100): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy GeocodingService::batchGeocodeListings().
     */
    public static function batchGeocodeListings(int $limit = 100): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }
}
