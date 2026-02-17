<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\Env;

/**
 * GeocodingService - Address to Coordinates Conversion
 *
 * Provides geocoding via Google Maps Geocoding API.
 * Used for batch/cron geocoding of records missing coordinates.
 * Interactive forms send lat/lng directly from the frontend
 * (via Google Places Autocomplete).
 *
 * Features:
 * - Batch geocoding for existing users/listings
 * - Caching to avoid redundant API calls (7-day TTL)
 * - SSRF protection on address inputs
 */
class GeocodingService
{
    // Cache duration in seconds (7 days)
    const CACHE_DURATION = 604800;

    /**
     * Sanitize address input to prevent SSRF attacks
     *
     * @param string $address Raw address input
     * @return string|null Sanitized address or null if invalid
     */
    private static function sanitizeAddress(string $address): ?string
    {
        // Remove null bytes and control characters
        $address = preg_replace('/[\x00-\x1F\x7F]/', '', $address);

        // Limit length to prevent abuse (typical addresses are under 200 chars)
        if (strlen($address) > 500) {
            return null;
        }

        // Block URL-like inputs that could be SSRF attempts
        if (preg_match('/^(https?|ftp|file|data|javascript|vbscript):/i', trim($address))) {
            error_log("GeocodingService: Blocked potential SSRF attempt: " . substr($address, 0, 100));
            return null;
        }

        // Block IP addresses (internal network probing)
        if (preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', trim($address))) {
            return null;
        }

        return trim($address);
    }

    /**
     * Geocode an address and return coordinates
     *
     * @param string $address The address to geocode
     * @return array|null ['latitude' => float, 'longitude' => float] or null on failure
     */
    public static function geocode(string $address): ?array
    {
        if (empty(trim($address))) {
            return null;
        }

        // Sanitize input to prevent SSRF
        $address = self::sanitizeAddress($address);
        if ($address === null) {
            return null;
        }

        // Check cache first
        $cached = self::getCached($address);
        if ($cached !== null) {
            return $cached;
        }

        // Geocode via Google Maps API
        $result = self::geocodeWithGoogle($address);

        // Cache the result
        if ($result !== null) {
            self::cacheResult($address, $result);
        }

        return $result;
    }

    /**
     * Geocode using Google Maps Geocoding API
     */
    private static function geocodeWithGoogle(string $address): ?array
    {
        $apiKey = Env::get('GOOGLE_MAPS_API_KEY');
        if (empty($apiKey)) {
            return null;
        }

        $url = 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query([
            'address' => $address,
            'key' => $apiKey
        ]);

        try {
            $response = @file_get_contents($url);
            if ($response === false) {
                return null;
            }

            $data = json_decode($response, true);
            if ($data['status'] !== 'OK' || empty($data['results'])) {
                return null;
            }

            $location = $data['results'][0]['geometry']['location'];
            return [
                'latitude' => (float)$location['lat'],
                'longitude' => (float)$location['lng'],
                'formatted_address' => $data['results'][0]['formatted_address'] ?? null,
                'provider' => 'google'
            ];
        } catch (\Exception $e) {
            error_log("Google geocoding error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get cached geocode result
     */
    private static function getCached(string $address): ?array
    {
        $hash = md5(strtolower(trim($address)));

        try {
            $sql = "SELECT latitude, longitude FROM geocode_cache
                    WHERE address_hash = ?
                    AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)";
            $result = Database::query($sql, [$hash, self::CACHE_DURATION])->fetch();

            if ($result) {
                return [
                    'latitude' => (float)$result['latitude'],
                    'longitude' => (float)$result['longitude'],
                    'cached' => true
                ];
            }
        } catch (\Exception $e) {
            // Cache table might not exist
        }

        return null;
    }

    /**
     * Cache a geocode result
     */
    private static function cacheResult(string $address, array $coords): void
    {
        $hash = md5(strtolower(trim($address)));

        try {
            $sql = "INSERT INTO geocode_cache (address_hash, address, latitude, longitude, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE latitude = VALUES(latitude), longitude = VALUES(longitude), created_at = NOW()";
            Database::query($sql, [$hash, substr($address, 0, 255), $coords['latitude'], $coords['longitude']]);
        } catch (\Exception $e) {
            // Cache table might not exist, try to create it
            self::ensureCacheTable();
        }
    }

    /**
     * Ensure the geocode cache table exists
     */
    private static function ensureCacheTable(): void
    {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS geocode_cache (
                id INT AUTO_INCREMENT PRIMARY KEY,
                address_hash VARCHAR(32) UNIQUE,
                address VARCHAR(255),
                latitude DECIMAL(10, 8),
                longitude DECIMAL(11, 8),
                created_at DATETIME,
                INDEX idx_hash (address_hash),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            Database::query($sql);
        } catch (\Exception $e) {
            error_log("Failed to create geocode_cache table: " . $e->getMessage());
        }
    }

    /**
     * Update user coordinates based on their location string
     *
     * @param int $userId User ID
     * @param string|null $location Location string (address)
     * @return bool Success
     */
    public static function updateUserCoordinates(int $userId, ?string $location): bool
    {
        if (empty($location)) {
            return false;
        }

        $coords = self::geocode($location);
        if ($coords === null) {
            return false;
        }

        try {
            $sql = "UPDATE users SET latitude = ?, longitude = ? WHERE id = ?";
            Database::query($sql, [$coords['latitude'], $coords['longitude'], $userId]);

            // Also invalidate the user's match cache since their location changed
            SmartMatchingEngine::invalidateCacheForUser($userId);

            return true;
        } catch (\Exception $e) {
            error_log("Failed to update user coordinates: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update listing coordinates based on location string
     *
     * @param int $listingId Listing ID
     * @param string|null $location Location string
     * @return bool Success
     */
    public static function updateListingCoordinates(int $listingId, ?string $location): bool
    {
        if (empty($location)) {
            return false;
        }

        $coords = self::geocode($location);
        if ($coords === null) {
            return false;
        }

        try {
            $sql = "UPDATE listings SET latitude = ?, longitude = ? WHERE id = ?";
            Database::query($sql, [$coords['latitude'], $coords['longitude'], $listingId]);
            return true;
        } catch (\Exception $e) {
            error_log("Failed to update listing coordinates: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Batch geocode all users missing coordinates
     *
     * @param int $limit Max users to process
     * @return array ['processed' => int, 'success' => int, 'failed' => int]
     */
    public static function batchGeocodeUsers(int $limit = 100): array
    {
        $tenantId = TenantContext::getId();
        $results = ['processed' => 0, 'success' => 0, 'failed' => 0];

        try {
            $sql = "SELECT id, location FROM users
                    WHERE tenant_id = ?
                    AND location IS NOT NULL
                    AND location != ''
                    AND (latitude IS NULL OR longitude IS NULL)
                    LIMIT ?";
            $users = Database::query($sql, [$tenantId, $limit])->fetchAll();

            foreach ($users as $user) {
                $results['processed']++;

                if (self::updateUserCoordinates($user['id'], $user['location'])) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                }

                // Sleep briefly to respect rate limits
                usleep(100000); // 100ms
            }
        } catch (\Exception $e) {
            error_log("Batch geocode error: " . $e->getMessage());
        }

        return $results;
    }

    /**
     * Batch geocode all listings missing coordinates
     *
     * @param int $limit Max listings to process
     * @return array ['processed' => int, 'success' => int, 'failed' => int]
     */
    public static function batchGeocodeListings(int $limit = 100): array
    {
        $tenantId = TenantContext::getId();
        $results = ['processed' => 0, 'success' => 0, 'failed' => 0];

        try {
            $sql = "SELECT id, location FROM listings
                    WHERE tenant_id = ?
                    AND location IS NOT NULL
                    AND location != ''
                    AND (latitude IS NULL OR longitude IS NULL)
                    LIMIT ?";
            $listings = Database::query($sql, [$tenantId, $limit])->fetchAll();

            foreach ($listings as $listing) {
                $results['processed']++;

                if (self::updateListingCoordinates($listing['id'], $listing['location'])) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                }

                // Sleep briefly to respect rate limits
                usleep(100000); // 100ms
            }
        } catch (\Exception $e) {
            error_log("Batch geocode error: " . $e->getMessage());
        }

        return $results;
    }

    /**
     * Get geocoding statistics
     */
    public static function getStats(): array
    {
        $tenantId = TenantContext::getId();

        $stats = [
            'users_with_coords' => 0,
            'users_without_coords' => 0,
            'listings_with_coords' => 0,
            'listings_without_coords' => 0,
            'cache_entries' => 0
        ];

        try {
            // Users with coordinates
            $sql = "SELECT COUNT(*) as count FROM users
                    WHERE tenant_id = ? AND latitude IS NOT NULL AND longitude IS NOT NULL";
            $stats['users_with_coords'] = (int)Database::query($sql, [$tenantId])->fetchColumn();

            // Users without coordinates
            $sql = "SELECT COUNT(*) as count FROM users
                    WHERE tenant_id = ? AND location IS NOT NULL AND location != ''
                    AND (latitude IS NULL OR longitude IS NULL)";
            $stats['users_without_coords'] = (int)Database::query($sql, [$tenantId])->fetchColumn();

            // Listings with coordinates
            $sql = "SELECT COUNT(*) as count FROM listings
                    WHERE tenant_id = ? AND latitude IS NOT NULL AND longitude IS NOT NULL";
            $stats['listings_with_coords'] = (int)Database::query($sql, [$tenantId])->fetchColumn();

            // Listings without coordinates
            $sql = "SELECT COUNT(*) as count FROM listings
                    WHERE tenant_id = ? AND location IS NOT NULL AND location != ''
                    AND (latitude IS NULL OR longitude IS NULL)";
            $stats['listings_without_coords'] = (int)Database::query($sql, [$tenantId])->fetchColumn();

            // Cache entries
            $sql = "SELECT COUNT(*) as count FROM geocode_cache";
            $stats['cache_entries'] = (int)Database::query($sql)->fetchColumn();
        } catch (\Exception $e) {
            // Some tables might not exist
        }

        return $stats;
    }
}
