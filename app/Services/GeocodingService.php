<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * GeocodingService — geocodes addresses to lat/lng using OpenStreetMap Nominatim.
 *
 * Native Laravel implementation (replaces legacy wrapper).
 */
class GeocodingService
{
    private const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/search';
    private const USER_AGENT = 'ProjectNEXUS/1.0 (https://project-nexus.ie)';
    private const CACHE_TTL = 86400; // 24 hours

    public function __construct()
    {
    }

    /**
     * Geocode an address string to latitude/longitude.
     *
     * @return array{latitude: float, longitude: float}|null
     */
    public static function geocode(string $address): ?array
    {
        $address = trim($address);
        if (empty($address)) {
            return null;
        }

        // Check cache first
        $cacheKey = 'geocode:' . md5(strtolower($address));
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENT,
            ])->timeout(10)->get(self::NOMINATIM_URL, [
                'q' => $address,
                'format' => 'json',
                'limit' => 1,
                'addressdetails' => 0,
            ]);

            if (!$response->successful()) {
                Log::warning('Geocoding API error', [
                    'address' => $address,
                    'status' => $response->status(),
                ]);
                return null;
            }

            $results = $response->json();

            if (empty($results) || !isset($results[0]['lat'], $results[0]['lon'])) {
                Log::info('Geocoding returned no results', ['address' => $address]);
                return null;
            }

            $coords = [
                'latitude' => (float) $results[0]['lat'],
                'longitude' => (float) $results[0]['lon'],
            ];

            Cache::put($cacheKey, $coords, self::CACHE_TTL);

            return $coords;
        } catch (\Throwable $e) {
            Log::error('Geocoding exception', [
                'address' => $address,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Update coordinates for a user based on their location field.
     */
    public static function updateUserCoordinates(int $userId, ?string $location): bool
    {
        if (empty($location)) {
            return false;
        }

        $coords = static::geocode($location);
        if (!$coords) {
            return false;
        }

        $tenantId = TenantContext::getId();

        $affected = DB::update(
            "UPDATE users SET latitude = ?, longitude = ? WHERE id = ? AND tenant_id = ?",
            [$coords['latitude'], $coords['longitude'], $userId, $tenantId]
        );

        return $affected > 0;
    }

    /**
     * Update coordinates for a listing based on its location field.
     */
    public static function updateListingCoordinates(int $listingId, ?string $location): bool
    {
        if (empty($location)) {
            return false;
        }

        $coords = static::geocode($location);
        if (!$coords) {
            return false;
        }

        $tenantId = TenantContext::getId();

        $affected = DB::update(
            "UPDATE listings SET latitude = ?, longitude = ? WHERE id = ? AND tenant_id = ?",
            [$coords['latitude'], $coords['longitude'], $listingId, $tenantId]
        );

        return $affected > 0;
    }

    /**
     * Batch geocode users that have a location but no coordinates.
     *
     * @return array{processed: int, success: int, failed: int}
     */
    public static function batchGeocodeUsers(int $limit = 100): array
    {
        $tenantId = TenantContext::getId();

        $users = DB::select(
            "SELECT id, location FROM users
             WHERE tenant_id = ? AND location IS NOT NULL AND location != ''
             AND (latitude IS NULL OR longitude IS NULL)
             LIMIT ?",
            [$tenantId, $limit]
        );

        $success = 0;
        $failed = 0;

        foreach ($users as $user) {
            $coords = static::geocode($user->location);
            if ($coords) {
                DB::update(
                    "UPDATE users SET latitude = ?, longitude = ? WHERE id = ? AND tenant_id = ?",
                    [$coords['latitude'], $coords['longitude'], $user->id, $tenantId]
                );
                $success++;
            } else {
                $failed++;
            }
            // Respect Nominatim rate limit (1 request/second)
            usleep(100000);
        }

        return [
            'processed' => count($users),
            'success' => $success,
            'failed' => $failed,
        ];
    }

    /**
     * Batch geocode listings that have a location but no coordinates.
     *
     * @return array{processed: int, success: int, failed: int}
     */
    public static function batchGeocodeListings(int $limit = 100): array
    {
        $tenantId = TenantContext::getId();

        $listings = DB::select(
            "SELECT id, location FROM listings
             WHERE tenant_id = ? AND location IS NOT NULL AND location != ''
             AND (latitude IS NULL OR longitude IS NULL)
             LIMIT ?",
            [$tenantId, $limit]
        );

        $success = 0;
        $failed = 0;

        foreach ($listings as $listing) {
            $coords = static::geocode($listing->location);
            if ($coords) {
                DB::update(
                    "UPDATE listings SET latitude = ?, longitude = ? WHERE id = ? AND tenant_id = ?",
                    [$coords['latitude'], $coords['longitude'], $listing->id, $tenantId]
                );
                $success++;
            } else {
                $failed++;
            }
            // Respect Nominatim rate limit
            usleep(100000);
        }

        return [
            'processed' => count($listings),
            'success' => $success,
            'failed' => $failed,
        ];
    }

    /**
     * Get geocoding statistics for the current tenant.
     *
     * @return array{users_with_coords: int, users_without_coords: int, listings_with_coords: int, listings_without_coords: int}
     */
    public static function getStats(): array
    {
        $tenantId = TenantContext::getId();

        $usersWithCoords = (int) DB::selectOne(
            "SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ? AND latitude IS NOT NULL AND longitude IS NOT NULL",
            [$tenantId]
        )->cnt;

        $usersWithoutCoords = (int) DB::selectOne(
            "SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ? AND location IS NOT NULL AND location != '' AND (latitude IS NULL OR longitude IS NULL)",
            [$tenantId]
        )->cnt;

        $listingsWithCoords = (int) DB::selectOne(
            "SELECT COUNT(*) as cnt FROM listings WHERE tenant_id = ? AND latitude IS NOT NULL AND longitude IS NOT NULL",
            [$tenantId]
        )->cnt;

        $listingsWithoutCoords = (int) DB::selectOne(
            "SELECT COUNT(*) as cnt FROM listings WHERE tenant_id = ? AND location IS NOT NULL AND location != '' AND (latitude IS NULL OR longitude IS NULL)",
            [$tenantId]
        )->cnt;

        return [
            'users_with_coords' => $usersWithCoords,
            'users_without_coords' => $usersWithoutCoords,
            'listings_with_coords' => $listingsWithCoords,
            'listings_without_coords' => $listingsWithoutCoords,
        ];
    }
}
