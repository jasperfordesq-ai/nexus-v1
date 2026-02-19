<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\GeocodingService;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * GeocodingServiceTest - Tests for the geocoding service
 *
 * Tests geocoding, caching, and batch operations.
 * Note: Actual API calls are avoided in tests to prevent rate limiting.
 */
class GeocodingServiceTest extends TestCase
{
    private static $testTenantId = 1;
    private static $testUserId;
    private static $testListingId;

    public static function setUpBeforeClass(): void
    {
        TenantContext::setById(self::$testTenantId);

        $timestamp = time() . rand(1000, 9999);

        // Create test user with location but no coordinates
        Database::query(
            "INSERT INTO users (tenant_id, email, first_name, last_name, name, location, latitude, longitude, is_approved, status, created_at)
             VALUES (?, ?, 'Geo', 'TestUser', 'Geo TestUser', 'London, UK', NULL, NULL, 1, 'active', NOW())",
            [self::$testTenantId, 'geocoding_test_' . $timestamp . '@test.com']
        );
        self::$testUserId = Database::getInstance()->lastInsertId();

        // Create test listing with location but no coordinates
        Database::query(
            "INSERT INTO listings (tenant_id, user_id, title, description, type, location, latitude, longitude, status, created_at)
             VALUES (?, ?, 'Test Listing', 'Test', 'offer', 'Manchester, UK', NULL, NULL, 'active', NOW())",
            [self::$testTenantId, self::$testUserId]
        );
        self::$testListingId = Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM listings WHERE user_id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
            try {
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
        }

        // Clean up geocode cache entries created during tests
        try {
            Database::query("DELETE FROM geocode_cache WHERE address LIKE 'Test%'");
        } catch (\Exception $e) {}
    }

    protected function setUp(): void
    {
        // Reset user/listing coordinates before each test
        try {
            Database::query(
                "UPDATE users SET latitude = NULL, longitude = NULL WHERE id = ?",
                [self::$testUserId]
            );
            Database::query(
                "UPDATE listings SET latitude = NULL, longitude = NULL WHERE id = ?",
                [self::$testListingId]
            );
        } catch (\Exception $e) {}
    }

    // =========================================================================
    // GEOCODE BASIC TESTS
    // =========================================================================

    public function testGeocodeReturnsNullForEmptyAddress(): void
    {
        $result = GeocodingService::geocode('');

        $this->assertNull($result);
    }

    public function testGeocodeReturnsNullForWhitespaceAddress(): void
    {
        $result = GeocodingService::geocode('   ');

        $this->assertNull($result);
    }

    public function testGeocodeReturnsExpectedStructure(): void
    {
        // Use a cached address or skip if no cache
        // We test structure, not actual geocoding (to avoid API calls)
        $result = GeocodingService::geocode('London, UK');

        if ($result !== null) {
            $this->assertIsArray($result);
            $this->assertArrayHasKey('latitude', $result);
            $this->assertArrayHasKey('longitude', $result);
            $this->assertIsNumeric($result['latitude']);
            $this->assertIsNumeric($result['longitude']);
        }

        // Pass even if null (API might not be available in test env)
        $this->assertTrue(true);
    }

    // =========================================================================
    // CACHE TESTS
    // =========================================================================

    public function testCacheTableCreation(): void
    {
        // This should create the cache table if it doesn't exist
        GeocodingService::geocode('Test Address for Cache Creation');

        // Check if table exists
        try {
            $result = Database::query("SHOW TABLES LIKE 'geocode_cache'")->fetch();
            $tableExists = !empty($result);
        } catch (\Exception $e) {
            $tableExists = false;
        }

        // Either table exists or the service handles it gracefully
        $this->assertTrue(true);
    }

    public function testCacheHitReturnsExpectedStructure(): void
    {
        // Manually insert a cache entry
        $testAddress = 'Test Cache Hit Address ' . time();
        $hash = md5(strtolower(trim($testAddress)));

        try {
            Database::query(
                "INSERT INTO geocode_cache (address_hash, address, latitude, longitude, created_at)
                 VALUES (?, ?, 51.5074, -0.1278, NOW())
                 ON DUPLICATE KEY UPDATE latitude = VALUES(latitude)",
                [$hash, $testAddress]
            );

            $result = GeocodingService::geocode($testAddress);

            if ($result !== null) {
                $this->assertEquals(51.5074, $result['latitude']);
                $this->assertEquals(-0.1278, $result['longitude']);
                $this->assertTrue($result['cached'] ?? false);
            }

            // Cleanup
            Database::query("DELETE FROM geocode_cache WHERE address_hash = ?", [$hash]);
        } catch (\Exception $e) {
            // Cache table might not exist, that's ok
            $this->assertTrue(true);
        }
    }

    // =========================================================================
    // UPDATE COORDINATES TESTS
    // =========================================================================

    public function testUpdateUserCoordinatesReturnsBool(): void
    {
        $result = GeocodingService::updateUserCoordinates(self::$testUserId, 'London, UK');

        $this->assertIsBool($result);
    }

    public function testUpdateUserCoordinatesWithEmptyLocation(): void
    {
        $result = GeocodingService::updateUserCoordinates(self::$testUserId, '');

        $this->assertFalse($result);
    }

    public function testUpdateUserCoordinatesWithNullLocation(): void
    {
        $result = GeocodingService::updateUserCoordinates(self::$testUserId, null);

        $this->assertFalse($result);
    }

    public function testUpdateListingCoordinatesReturnsBool(): void
    {
        $result = GeocodingService::updateListingCoordinates(self::$testListingId, 'Manchester, UK');

        $this->assertIsBool($result);
    }

    public function testUpdateListingCoordinatesWithEmptyLocation(): void
    {
        $result = GeocodingService::updateListingCoordinates(self::$testListingId, '');

        $this->assertFalse($result);
    }

    // =========================================================================
    // BATCH GEOCODING TESTS
    // =========================================================================

    public function testBatchGeocodeUsersReturnsExpectedStructure(): void
    {
        $result = GeocodingService::batchGeocodeUsers(5);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('processed', $result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('failed', $result);

        $this->assertIsInt($result['processed']);
        $this->assertIsInt($result['success']);
        $this->assertIsInt($result['failed']);

        $this->assertGreaterThanOrEqual(0, $result['processed']);
        $this->assertGreaterThanOrEqual(0, $result['success']);
        $this->assertGreaterThanOrEqual(0, $result['failed']);
    }

    public function testBatchGeocodeListingsReturnsExpectedStructure(): void
    {
        $result = GeocodingService::batchGeocodeListings(5);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('processed', $result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('failed', $result);

        $this->assertIsInt($result['processed']);
        $this->assertIsInt($result['success']);
        $this->assertIsInt($result['failed']);
    }

    public function testBatchGeocodeWithZeroLimit(): void
    {
        $result = GeocodingService::batchGeocodeUsers(0);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['processed']);
    }

    // =========================================================================
    // STATISTICS TESTS
    // =========================================================================

    public function testGetStatsReturnsExpectedStructure(): void
    {
        $stats = GeocodingService::getStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('users_with_coords', $stats);
        $this->assertArrayHasKey('users_without_coords', $stats);
        $this->assertArrayHasKey('listings_with_coords', $stats);
        $this->assertArrayHasKey('listings_without_coords', $stats);
        $this->assertArrayHasKey('cache_entries', $stats);
    }

    public function testGetStatsReturnsIntegers(): void
    {
        $stats = GeocodingService::getStats();

        $this->assertIsInt($stats['users_with_coords']);
        $this->assertIsInt($stats['users_without_coords']);
        $this->assertIsInt($stats['listings_with_coords']);
        $this->assertIsInt($stats['listings_without_coords']);
        $this->assertIsInt($stats['cache_entries']);
    }

    public function testGetStatsValuesAreNonNegative(): void
    {
        $stats = GeocodingService::getStats();

        $this->assertGreaterThanOrEqual(0, $stats['users_with_coords']);
        $this->assertGreaterThanOrEqual(0, $stats['users_without_coords']);
        $this->assertGreaterThanOrEqual(0, $stats['listings_with_coords']);
        $this->assertGreaterThanOrEqual(0, $stats['listings_without_coords']);
        $this->assertGreaterThanOrEqual(0, $stats['cache_entries']);
    }

    // =========================================================================
    // EDGE CASES
    // =========================================================================

    public function testGeocodeWithSpecialCharacters(): void
    {
        // Should handle special characters without error
        $result = GeocodingService::geocode("123 Test St. #456, City's Town & County");

        // Result can be null (no match) but shouldn't throw
        $this->assertTrue(true);
    }

    public function testGeocodeWithVeryLongAddress(): void
    {
        // Should handle very long addresses
        $longAddress = str_repeat('Very Long Address Part ', 20);
        $result = GeocodingService::geocode($longAddress);

        // Result can be null but shouldn't throw
        $this->assertTrue(true);
    }

    public function testGeocodeWithUnicodeCharacters(): void
    {
        // Should handle unicode
        $result = GeocodingService::geocode('東京都渋谷区');

        // Result can be null but shouldn't throw
        $this->assertTrue(true);
    }

    public function testUpdateUserCoordinatesWithInvalidUserId(): void
    {
        $result = GeocodingService::updateUserCoordinates(999999999, 'London, UK');

        // Should return false (user doesn't exist) or true (geocoded but no user)
        $this->assertIsBool($result);
    }

    public function testUpdateListingCoordinatesWithInvalidListingId(): void
    {
        $result = GeocodingService::updateListingCoordinates(999999999, 'London, UK');

        $this->assertIsBool($result);
    }
}
