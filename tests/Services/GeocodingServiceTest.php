<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\GeocodingService;
use App\Core\TenantContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * GeocodingServiceTest — tests for geocoding via Nominatim with caching.
 *
 * All HTTP calls are faked to avoid hitting the real Nominatim API.
 */
class GeocodingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(1);
        Cache::flush();
    }

    // =========================================================================
    // geocode — input validation
    // =========================================================================

    public function testGeocodeReturnsNullForEmptyAddress(): void
    {
        $this->assertNull(GeocodingService::geocode(''));
    }

    public function testGeocodeReturnsNullForWhitespaceAddress(): void
    {
        $this->assertNull(GeocodingService::geocode('   '));
    }

    // =========================================================================
    // geocode — cache behaviour
    // =========================================================================

    public function testGeocodeReturnsCachedResultOnHit(): void
    {
        $address = 'Dublin, Ireland';
        $cacheKey = 'geocode:' . md5(strtolower($address));
        $expected = ['latitude' => 53.3498, 'longitude' => -6.2603];

        Cache::put($cacheKey, $expected, 86400);

        // HTTP should NOT be called because cache is hit
        Http::fake([
            '*' => Http::response([], 200),
        ]);

        $result = GeocodingService::geocode($address);

        $this->assertNotNull($result);
        $this->assertEquals(53.3498, $result['latitude']);
        $this->assertEquals(-6.2603, $result['longitude']);
    }

    // =========================================================================
    // geocode — HTTP responses
    // =========================================================================

    public function testGeocodeReturnsCoordinatesOnSuccess(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '51.5074', 'lon' => '-0.1278', 'display_name' => 'London'],
            ], 200),
        ]);

        $result = GeocodingService::geocode('London, UK');

        $this->assertNotNull($result);
        $this->assertArrayHasKey('latitude', $result);
        $this->assertArrayHasKey('longitude', $result);
        $this->assertEquals(51.5074, $result['latitude']);
        $this->assertEquals(-0.1278, $result['longitude']);
    }

    public function testGeocodeCachesResultAfterSuccessfulLookup(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '48.8566', 'lon' => '2.3522'],
            ], 200),
        ]);

        $address = 'Paris, France';
        $cacheKey = 'geocode:' . md5(strtolower($address));

        $this->assertNull(Cache::get($cacheKey));

        GeocodingService::geocode($address);

        $cached = Cache::get($cacheKey);
        $this->assertNotNull($cached);
        $this->assertEquals(48.8566, $cached['latitude']);
    }

    public function testGeocodeReturnsNullOnEmptyResults(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([], 200),
        ]);

        $result = GeocodingService::geocode('xyznonexistentplace12345');
        $this->assertNull($result);
    }

    public function testGeocodeReturnsNullOnApiError(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([], 500),
        ]);

        $result = GeocodingService::geocode('Some Place');
        $this->assertNull($result);
    }

    public function testGeocodeReturnsNullOnNetworkException(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => function () {
                throw new \RuntimeException('Connection timeout');
            },
        ]);

        $result = GeocodingService::geocode('Some Place');
        $this->assertNull($result);
    }

    public function testGeocodeReturnsNullWhenResultMissingLatLon(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['display_name' => 'Somewhere'],
            ], 200),
        ]);

        $result = GeocodingService::geocode('Incomplete Result Place');
        $this->assertNull($result);
    }

    // =========================================================================
    // updateUserCoordinates
    // =========================================================================

    public function testUpdateUserCoordinatesReturnsFalseForEmptyLocation(): void
    {
        $this->assertFalse(GeocodingService::updateUserCoordinates(1, ''));
    }

    public function testUpdateUserCoordinatesReturnsFalseForNullLocation(): void
    {
        $this->assertFalse(GeocodingService::updateUserCoordinates(1, null));
    }

    public function testUpdateUserCoordinatesReturnsFalseWhenGeocodeReturnsNull(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([], 200),
        ]);

        $result = GeocodingService::updateUserCoordinates(1, 'xyznonexistent12345');
        $this->assertFalse($result);
    }

    public function testUpdateUserCoordinatesUpdatesDbOnSuccess(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '52.52', 'lon' => '13.405'],
            ], 200),
        ]);

        DB::shouldReceive('update')
            ->once()
            ->withArgs(function ($sql, $params) {
                return str_contains($sql, 'UPDATE users') &&
                       $params[0] === 52.52 &&
                       $params[1] === 13.405;
            })
            ->andReturn(1);

        $result = GeocodingService::updateUserCoordinates(1, 'Berlin, Germany');
        $this->assertTrue($result);
    }

    // =========================================================================
    // updateListingCoordinates
    // =========================================================================

    public function testUpdateListingCoordinatesReturnsFalseForEmptyLocation(): void
    {
        $this->assertFalse(GeocodingService::updateListingCoordinates(1, ''));
    }

    public function testUpdateListingCoordinatesReturnsFalseForNullLocation(): void
    {
        $this->assertFalse(GeocodingService::updateListingCoordinates(1, null));
    }

    // =========================================================================
    // Edge cases
    // =========================================================================

    public function testGeocodeWithSpecialCharactersDoesNotThrow(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([], 200),
        ]);

        GeocodingService::geocode("123 Test St. #456, City's Town & County");
        // Should not throw, null is acceptable
        $this->assertTrue(true);
    }

    public function testGeocodeWithUnicodeCharactersDoesNotThrow(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([], 200),
        ]);

        GeocodingService::geocode('Strasse 1, Munchen');
        $this->assertTrue(true);
    }

    public function testGeocodeCacheKeyIsCaseInsensitive(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '51.0', 'lon' => '-1.0'],
            ], 200),
        ]);

        GeocodingService::geocode('London');

        // Same address different case should use cached value
        $cacheKey = 'geocode:' . md5('london');
        $cached = Cache::get($cacheKey);
        $this->assertNotNull($cached);
    }
}
