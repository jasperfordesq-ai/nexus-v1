<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\SmartMatchingAnalyticsService;
use Illuminate\Support\Facades\DB;

class SmartMatchingAnalyticsServiceTest extends TestCase
{
    private SmartMatchingAnalyticsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SmartMatchingAnalyticsService();
    }

    // ── getOverallStats ──

    public function test_getOverallStats_returns_expected_keys(): void
    {
        // Mock all DB::selectOne calls
        DB::shouldReceive('selectOne')->andReturn(
            (object) ['cnt' => 100],
            (object) ['cnt' => 10],
            (object) ['avg_score' => 72.5],
            (object) ['avg_dist' => 12.3],
            (object) ['cnt' => 50],
            (object) ['cnt' => 30],
        );
        DB::shouldReceive('select')->andReturn([]);

        $result = $this->service->getOverallStats();
        $this->assertArrayHasKey('total_cached_matches', $result);
        $this->assertArrayHasKey('average_score', $result);
        $this->assertArrayHasKey('hot_matches', $result);
        $this->assertArrayHasKey('match_type_breakdown', $result);
    }

    public function test_getOverallStats_returns_defaults_on_error(): void
    {
        DB::shouldReceive('selectOne')->andThrow(new \RuntimeException('fail'));

        $result = $this->service->getOverallStats();
        $this->assertEquals(0, $result['total_cached_matches']);
    }

    // ── getScoreDistribution ──

    public function test_getScoreDistribution_returns_five_buckets(): void
    {
        DB::shouldReceive('selectOne')->times(5)->andReturn((object) ['cnt' => 5]);

        $result = $this->service->getScoreDistribution();
        $this->assertCount(5, $result);
        $this->assertEquals('0-20', $result[0]['range']);
        $this->assertEquals('81-100', $result[4]['range']);
    }

    public function test_getScoreDistribution_returns_empty_on_error(): void
    {
        DB::shouldReceive('selectOne')->andThrow(new \RuntimeException('fail'));
        $result = $this->service->getScoreDistribution();
        $this->assertEquals([], $result);
    }

    // ── getDistanceDistribution ──

    public function test_getDistanceDistribution_returns_buckets(): void
    {
        DB::shouldReceive('selectOne')->times(6)->andReturn((object) ['cnt' => 3]);

        $result = $this->service->getDistanceDistribution();
        $this->assertGreaterThanOrEqual(5, count($result));
        $this->assertEquals('0-5km', $result[0]['range']);
    }

    // ── getConversionFunnel ──

    public function test_getConversionFunnel_returns_expected_keys(): void
    {
        DB::shouldReceive('selectOne')->times(5)->andReturn(
            (object) ['cnt' => 100],
            (object) ['cnt' => 50],
            (object) ['cnt' => 20],
            (object) ['cnt' => 10],
            (object) ['cnt' => 5],
        );

        $result = $this->service->getConversionFunnel();
        $this->assertArrayHasKey('total_generated', $result);
        $this->assertArrayHasKey('viewed', $result);
        $this->assertArrayHasKey('contacted', $result);
        $this->assertArrayHasKey('conversion_rate', $result);
        $this->assertEquals(20.0, $result['conversion_rate']);
    }

    public function test_getConversionFunnel_returns_zero_rate_when_empty(): void
    {
        DB::shouldReceive('selectOne')->times(5)->andReturn((object) ['cnt' => 0]);

        $result = $this->service->getConversionFunnel();
        $this->assertEquals(0, $result['conversion_rate']);
    }

    // ── getDashboardSummary ──

    public function test_getDashboardSummary_returns_overview_and_conversion(): void
    {
        DB::shouldReceive('selectOne')->andReturn((object) ['cnt' => 0]);
        DB::shouldReceive('select')->andReturn([]);

        $result = $this->service->getDashboardSummary();
        $this->assertArrayHasKey('overview', $result);
        $this->assertArrayHasKey('conversion', $result);
        $this->assertEquals('last_30_days', $result['period']);
    }
}
