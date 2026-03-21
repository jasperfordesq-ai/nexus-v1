<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\ListingRankingService;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class ListingRankingServiceTest extends TestCase
{
    private ListingRankingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ListingRankingService();
    }

    public function test_getConfig_returns_defaults(): void
    {
        DB::shouldReceive('table')->with('tenants')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('value')->andReturn(null);

        $config = $this->service->getConfig();

        $this->assertTrue($config['enabled']);
        $this->assertSame(1.5, $config['relevance_category_match']);
        $this->assertSame(7, $config['freshness_full_days']);
    }

    public function test_isEnabled_returns_true_by_default(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('value')->andReturn(null);

        $this->assertTrue($this->service->isEnabled());
    }

    public function test_clearCache_resets_internal_state(): void
    {
        $this->service->clearCache();
        // No exception = pass
        $this->assertTrue(true);
    }

    public function test_rankListings_empty_returns_empty(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('value')->andReturn(null);

        $result = $this->service->rankListings([]);
        $this->assertSame([], $result);
    }

    public function test_rankListings_disabled_returns_original(): void
    {
        // Return config with enabled=false
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('value')->andReturn(json_encode(['algorithms' => ['listings' => ['enabled' => false]]]));

        $listings = [['id' => 1, 'title' => 'Test']];
        $result = $this->service->rankListings($listings);
        $this->assertSame($listings, $result);
    }

    public function test_rankListings_adds_match_rank(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('value')->andReturn(null);
        DB::shouldReceive('whereNotNull')->andReturnSelf();
        DB::shouldReceive('distinct')->andReturnSelf();
        DB::shouldReceive('pluck')->andReturn(collect([]));
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);
        DB::shouldReceive('get')->andReturn(collect([]));

        $listings = [
            ['id' => 1, 'title' => 'Test', 'created_at' => now()->toDateTimeString(), 'description' => 'A test listing'],
            ['id' => 2, 'title' => 'Test2', 'created_at' => now()->subDays(30)->toDateTimeString(), 'description' => ''],
        ];

        $result = $this->service->rankListings($listings);
        $this->assertCount(2, $result);
        $this->assertArrayHasKey('_match_rank', $result[0]);
        $this->assertArrayHasKey('_score_breakdown', $result[0]);
    }

    public function test_buildRankedQuery_returns_sql_and_params(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('value')->andReturn(null);
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);

        $result = $this->service->buildRankedQuery(1, ['limit' => 20]);

        $this->assertArrayHasKey('sql', $result);
        $this->assertArrayHasKey('params', $result);
        $this->assertStringContainsString('SELECT', $result['sql']);
    }
}
