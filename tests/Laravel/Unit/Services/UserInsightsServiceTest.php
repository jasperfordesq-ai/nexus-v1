<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\UserInsightsService;
use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Mockery;

class UserInsightsServiceTest extends TestCase
{
    private UserInsightsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UserInsightsService();
    }

    public function test_getInsights_returns_expected_keys(): void
    {
        // Mock all the DB calls for getSummary, getMonthlyTrends, getPartnerStats
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('sum')->andReturn(10.0);
        DB::shouldReceive('value')->andReturn(5.0);
        DB::shouldReceive('whereYear')->andReturnSelf();
        DB::shouldReceive('whereMonth')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('orWhere')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) [
            'earned_this_month' => 5.0,
            'spent_this_month' => 3.0,
            'received_count' => 2,
            'sent_count' => 1,
        ]);
        DB::shouldReceive('count')->andReturn(5);
        DB::shouldReceive('distinct')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('on')->andReturnSelf();
        DB::shouldReceive('select')->andReturn([]);

        $result = $this->service->getInsights(1);

        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('monthly_trends', $result);
        $this->assertArrayHasKey('partner_stats', $result);
    }

    public function test_getTotalSpent_returns_float(): void
    {
        DB::shouldReceive('table')->with('transactions')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('sum')->with('amount')->andReturn(25.5);

        $result = $this->service->getTotalSpent(1);

        $this->assertIsFloat($result);
        $this->assertEquals(25.5, $result);
    }

    public function test_getMonthlyTrends_returns_array(): void
    {
        DB::shouldReceive('select')->andReturn([]);

        $result = $this->service->getMonthlyTrends(1, 12);

        $this->assertIsArray($result);
    }

    public function test_getWeeklyTrends_returns_array(): void
    {
        DB::shouldReceive('select')->andReturn([]);

        $result = $this->service->getWeeklyTrends(1, 12);

        $this->assertIsArray($result);
    }
}
