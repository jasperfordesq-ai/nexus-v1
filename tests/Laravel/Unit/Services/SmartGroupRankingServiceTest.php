<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\SmartGroupRankingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SmartGroupRankingServiceTest extends TestCase
{
    // ── updateFeaturedLocalHubs ──

    public function test_updateFeaturedLocalHubs_returns_expected_keys(): void
    {
        DB::shouldReceive('select')->andReturn([]);
        DB::shouldReceive('update')->andReturn(0);

        $result = SmartGroupRankingService::updateFeaturedLocalHubs($this->testTenantId);
        $this->assertArrayHasKey('featured', $result);
        $this->assertArrayHasKey('cleared', $result);
        $this->assertArrayHasKey('scores', $result);
    }

    // ── updateFeaturedCommunityGroups ──

    public function test_updateFeaturedCommunityGroups_returns_expected_keys(): void
    {
        DB::shouldReceive('select')->andReturn([]);

        $result = SmartGroupRankingService::updateFeaturedCommunityGroups($this->testTenantId);
        $this->assertArrayHasKey('featured', $result);
    }

    // ── updateAllFeaturedGroups ──

    public function test_updateAllFeaturedGroups_returns_both_types(): void
    {
        DB::shouldReceive('select')->andReturn([]);
        Cache::shouldReceive('put')->once();

        $result = SmartGroupRankingService::updateAllFeaturedGroups($this->testTenantId);
        $this->assertArrayHasKey('local_hubs', $result);
        $this->assertArrayHasKey('community', $result);
    }

    // ── getFeaturedGroupsWithScores ──

    public function test_getFeaturedGroupsWithScores_returns_array(): void
    {
        DB::shouldReceive('select')->andReturn([]);

        $result = SmartGroupRankingService::getFeaturedGroupsWithScores('local_hubs', $this->testTenantId);
        $this->assertIsArray($result);
    }

    public function test_getFeaturedGroupsWithScores_returns_empty_on_error(): void
    {
        DB::shouldReceive('select')->andThrow(new \RuntimeException('fail'));

        $result = SmartGroupRankingService::getFeaturedGroupsWithScores('local_hubs', $this->testTenantId);
        $this->assertEquals([], $result);
    }

    // ── getLastUpdateTime ──

    public function test_getLastUpdateTime_returns_null_when_not_cached(): void
    {
        Cache::shouldReceive('get')
            ->with("featured_groups_updated:{$this->testTenantId}")
            ->andReturnNull();

        $result = SmartGroupRankingService::getLastUpdateTime($this->testTenantId);
        $this->assertNull($result);
    }
}
