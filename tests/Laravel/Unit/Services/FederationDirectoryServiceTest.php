<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\FederationDirectoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FederationDirectoryServiceTest extends TestCase
{
    public function test_getDiscoverableTimebanks_returns_empty_on_error(): void
    {
        DB::shouldReceive('select')->andThrow(new \Exception('error'));
        Log::shouldReceive('error')->once();

        $result = FederationDirectoryService::getDiscoverableTimebanks(1);
        $this->assertEquals([], $result);
    }

    public function test_getAvailableRegions_returns_empty_on_error(): void
    {
        DB::shouldReceive('select')->andThrow(new \Exception('error'));
        Log::shouldReceive('error')->once();

        $this->assertEquals([], FederationDirectoryService::getAvailableRegions());
    }

    public function test_getAvailableCategories_returns_empty_on_error(): void
    {
        DB::shouldReceive('select')->andThrow(new \Exception('error'));
        Log::shouldReceive('error')->once();

        $this->assertEquals([], FederationDirectoryService::getAvailableCategories());
    }

    public function test_getTimebankProfile_returns_null_when_not_found(): void
    {
        DB::shouldReceive('selectOne')->andReturn(null);

        $this->assertNull(FederationDirectoryService::getTimebankProfile(999));
    }

    public function test_getTimebankProfile_returns_array_when_found(): void
    {
        $row = (object) [
            'id' => 1, 'name' => 'Test', 'slug' => 'test', 'is_active' => 1,
            'created_at' => '2026-01-01', 'display_name' => null, 'tagline' => null,
            'description' => null, 'logo_url' => null, 'cover_image_url' => null,
            'website_url' => null, 'country_code' => null, 'region' => null,
            'city' => null, 'latitude' => null, 'longitude' => null,
            'member_count' => 50, 'active_listings_count' => 10,
            'total_hours_exchanged' => 100.5, 'show_member_count' => 1,
            'show_activity_stats' => 1, 'show_location' => 1,
            'live_member_count' => 45,
        ];
        DB::shouldReceive('selectOne')->andReturn($row);

        $result = FederationDirectoryService::getTimebankProfile(1);
        $this->assertIsArray($result);
        $this->assertEquals(1, $result['id']);
    }

    public function test_updateDirectoryProfile_returns_true_when_nothing_to_update(): void
    {
        $this->assertTrue(FederationDirectoryService::updateDirectoryProfile(1, []));
    }

    public function test_updateDirectoryProfile_returns_false_on_error(): void
    {
        DB::shouldReceive('selectOne')->andThrow(new \Exception('error'));
        Log::shouldReceive('error')->once();

        $this->assertFalse(FederationDirectoryService::updateDirectoryProfile(1, ['tagline' => 'New']));
    }
}
