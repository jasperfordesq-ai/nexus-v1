<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\GeocodingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingServiceTest extends TestCase
{
    // =========================================================================
    // geocode()
    // =========================================================================

    public function test_geocode_returns_null_for_empty_address(): void
    {
        $this->assertNull(GeocodingService::geocode(''));
        $this->assertNull(GeocodingService::geocode('   '));
    }

    public function test_geocode_returns_cached_result(): void
    {
        $cached = ['latitude' => 53.35, 'longitude' => -6.26];
        Cache::shouldReceive('get')->andReturn($cached);

        $result = GeocodingService::geocode('Dublin, Ireland');
        $this->assertEquals(53.35, $result['latitude']);
        $this->assertEquals(-6.26, $result['longitude']);
    }

    public function test_geocode_calls_nominatim_on_cache_miss(): void
    {
        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        Http::shouldReceive('withHeaders->timeout->get')->andReturn(
            new \Illuminate\Http\Client\Response(
                new \GuzzleHttp\Psr7\Response(200, [], json_encode([
                    ['lat' => '53.35', 'lon' => '-6.26']
                ]))
            )
        );

        $result = GeocodingService::geocode('Dublin, Ireland');
        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(53.35, $result['latitude'], 0.01);
    }

    public function test_geocode_returns_null_on_empty_results(): void
    {
        Cache::shouldReceive('get')->andReturn(null);

        Http::shouldReceive('withHeaders->timeout->get')->andReturn(
            new \Illuminate\Http\Client\Response(
                new \GuzzleHttp\Psr7\Response(200, [], json_encode([]))
            )
        );
        Log::shouldReceive('info')->once();

        $this->assertNull(GeocodingService::geocode('NonexistentPlace12345'));
    }

    public function test_geocode_returns_null_on_api_error(): void
    {
        Cache::shouldReceive('get')->andReturn(null);

        Http::shouldReceive('withHeaders->timeout->get')->andReturn(
            new \Illuminate\Http\Client\Response(
                new \GuzzleHttp\Psr7\Response(500, [], '')
            )
        );
        Log::shouldReceive('warning')->once();

        $this->assertNull(GeocodingService::geocode('Dublin'));
    }

    public function test_geocode_returns_null_on_exception(): void
    {
        Cache::shouldReceive('get')->andReturn(null);
        Http::shouldReceive('withHeaders->timeout->get')->andThrow(new \Exception('Network error'));
        Log::shouldReceive('error')->once();

        $this->assertNull(GeocodingService::geocode('Dublin'));
    }

    // =========================================================================
    // updateUserCoordinates()
    // =========================================================================

    public function test_updateUserCoordinates_returns_false_for_empty_location(): void
    {
        $this->assertFalse(GeocodingService::updateUserCoordinates(1, null));
        $this->assertFalse(GeocodingService::updateUserCoordinates(1, ''));
    }

    public function test_updateUserCoordinates_returns_false_when_geocode_fails(): void
    {
        Cache::shouldReceive('get')->andReturn(null);
        Http::shouldReceive('withHeaders->timeout->get')->andReturn(
            new \Illuminate\Http\Client\Response(
                new \GuzzleHttp\Psr7\Response(200, [], json_encode([]))
            )
        );
        Log::shouldReceive('info')->once();

        $this->assertFalse(GeocodingService::updateUserCoordinates(1, 'NonexistentPlace'));
    }

    // =========================================================================
    // updateListingCoordinates()
    // =========================================================================

    public function test_updateListingCoordinates_returns_false_for_empty_location(): void
    {
        $this->assertFalse(GeocodingService::updateListingCoordinates(1, null));
    }

    // =========================================================================
    // getStats()
    // =========================================================================

    public function test_getStats_returns_expected_structure(): void
    {
        DB::shouldReceive('selectOne')->andReturn(
            (object) ['cnt' => 50],
            (object) ['cnt' => 10],
            (object) ['cnt' => 30],
            (object) ['cnt' => 5],
        );

        $result = GeocodingService::getStats();
        $this->assertArrayHasKey('users_with_coords', $result);
        $this->assertArrayHasKey('users_without_coords', $result);
        $this->assertArrayHasKey('listings_with_coords', $result);
        $this->assertArrayHasKey('listings_without_coords', $result);
        $this->assertEquals(50, $result['users_with_coords']);
    }
}
