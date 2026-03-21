<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\FederationActivityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FederationActivityServiceTest extends TestCase
{
    public function test_getActivityFeed_returns_empty_on_error(): void
    {
        DB::shouldReceive('select')->andThrow(new \Exception('DB error'));
        Log::shouldReceive('error')->once();

        $result = FederationActivityService::getActivityFeed(1);
        $this->assertEquals([], $result);
    }

    public function test_getActivityFeed_clamps_limit(): void
    {
        DB::shouldReceive('select')->andReturn([]);

        $result = FederationActivityService::getActivityFeed(1, 500);
        $this->assertEquals([], $result);
    }

    public function test_getUnreadCount_returns_integer(): void
    {
        DB::shouldReceive('selectOne')->andReturn((object) ['cnt' => 5]);

        $this->assertEquals(5, FederationActivityService::getUnreadCount(1));
    }

    public function test_getUnreadCount_returns_zero_on_error(): void
    {
        DB::shouldReceive('selectOne')->andThrow(new \Exception('error'));
        Log::shouldReceive('error')->once();

        $this->assertEquals(0, FederationActivityService::getUnreadCount(1));
    }

    public function test_getActivityStats_returns_expected_structure(): void
    {
        DB::shouldReceive('selectOne')->andReturn((object) ['cnt' => 0]);
        DB::shouldReceive('select')->andReturn([]);

        $result = FederationActivityService::getActivityStats(1);
        $this->assertArrayHasKey('total_activities', $result);
        $this->assertArrayHasKey('activities_this_week', $result);
        $this->assertArrayHasKey('activities_this_month', $result);
        $this->assertArrayHasKey('by_category', $result);
    }

    public function test_getActivityStats_returns_defaults_on_error(): void
    {
        DB::shouldReceive('selectOne')->andThrow(new \Exception('error'));
        Log::shouldReceive('error')->once();

        $result = FederationActivityService::getActivityStats(1);
        $this->assertEquals(0, $result['total_activities']);
    }
}
