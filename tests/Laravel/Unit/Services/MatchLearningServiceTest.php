<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\MatchLearningService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Laravel\TestCase;

class MatchLearningServiceTest extends TestCase
{
    private MatchLearningService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MatchLearningService();
    }

    public function test_getHistoricalBoost_returns_zero_for_no_owner(): void
    {
        $result = $this->service->getHistoricalBoost(1, ['user_id' => 0, 'category_id' => 1]);
        $this->assertSame(0.0, $result);
    }

    public function test_getHistoricalBoost_clamped_to_range(): void
    {
        DB::shouldReceive('select')->andReturn([
            (object) ['action' => 'accept', 'cnt' => 10, 'latest_at' => now()->toDateTimeString()],
        ]);

        $result = $this->service->getHistoricalBoost(1, ['user_id' => 2, 'category_id' => 0]);
        $this->assertGreaterThanOrEqual(-15.0, $result);
        $this->assertLessThanOrEqual(15.0, $result);
    }

    public function test_getHistoricalBoost_handles_error(): void
    {
        DB::shouldReceive('select')->andThrow(new \Exception('Error'));

        $result = $this->service->getHistoricalBoost(1, ['user_id' => 2, 'category_id' => 1]);
        $this->assertSame(0.0, $result);
    }

    public function test_recordInteraction_valid_action(): void
    {
        DB::shouldReceive('table')->with('match_history')->andReturnSelf();
        DB::shouldReceive('insert')->once()->andReturn(true);

        $result = $this->service->recordInteraction(1, 10, 'save', ['match_score' => 80]);
        $this->assertTrue($result);
    }

    public function test_recordInteraction_maps_alias(): void
    {
        DB::shouldReceive('table')->with('match_history')->andReturnSelf();
        DB::shouldReceive('insert')->once()->with(\Mockery::on(function ($data) {
            return $data['action'] === 'dismiss';
        }))->andReturn(true);

        $result = $this->service->recordInteraction(1, 10, 'dismissed');
        $this->assertTrue($result);
    }

    public function test_recordInteraction_unknown_action_defaults_to_view(): void
    {
        DB::shouldReceive('table')->with('match_history')->andReturnSelf();
        DB::shouldReceive('insert')->once()->with(\Mockery::on(function ($data) {
            return $data['action'] === 'view';
        }))->andReturn(true);

        $this->service->recordInteraction(1, 10, 'unknown_action');
    }

    public function test_recordInteraction_handles_failure(): void
    {
        DB::shouldReceive('table')->andThrow(new \Exception('Error'));
        Log::shouldReceive('warning')->once();

        $result = $this->service->recordInteraction(1, 10, 'view');
        $this->assertFalse($result);
    }

    public function test_getCategoryAffinities_empty_history(): void
    {
        DB::shouldReceive('select')->andReturn([]);

        $result = $this->service->getCategoryAffinities(1);
        $this->assertSame([], $result);
    }

    public function test_getCategoryAffinities_normalizes_scores(): void
    {
        DB::shouldReceive('select')->andReturn([
            (object) ['category_id' => 1, 'action' => 'accept', 'cnt' => 5, 'latest_at' => now()->toDateTimeString()],
            (object) ['category_id' => 2, 'action' => 'dismiss', 'cnt' => 3, 'latest_at' => now()->toDateTimeString()],
        ]);

        $result = $this->service->getCategoryAffinities(1);

        $this->assertArrayHasKey(1, $result);
        $this->assertArrayHasKey(2, $result);
        $this->assertGreaterThan(0, $result[1]); // accept = positive
        $this->assertLessThan(0, $result[2]); // dismiss = negative
    }

    public function test_getLearnedDistancePreference_returns_defaults_when_no_data(): void
    {
        DB::shouldReceive('select')->andReturn([]);

        $result = $this->service->getLearnedDistancePreference(1);

        $this->assertSame(25.0, $result['preferred_km']);
        $this->assertSame(50.0, $result['max_km']);
        $this->assertSame(0.0, $result['confidence']);
        $this->assertSame(0, $result['sample_size']);
    }

    public function test_getLearnedDistancePreference_insufficient_samples(): void
    {
        DB::shouldReceive('select')->andReturn([
            (object) ['action' => 'accept', 'distance_km' => 10],
        ]);

        $result = $this->service->getLearnedDistancePreference(1);
        $this->assertSame(1, $result['sample_size']);
    }

    public function test_getLearningStats_returns_structure(): void
    {
        DB::shouldReceive('selectOne')->andReturn((object) ['total' => 100, 'unique_users' => 20]);
        DB::shouldReceive('select')->andReturn([
            (object) ['action' => 'view', 'cnt' => 50],
            (object) ['action' => 'accept', 'cnt' => 30],
        ]);
        DB::shouldReceive('selectOne')->andReturn((object) ['cnt' => 5]);

        $result = $this->service->getLearningStats();

        $this->assertArrayHasKey('total_interactions', $result);
        $this->assertArrayHasKey('unique_users', $result);
        $this->assertArrayHasKey('action_breakdown', $result);
        $this->assertArrayHasKey('avg_interactions_per_user', $result);
    }

    public function test_getLearningStats_handles_error(): void
    {
        DB::shouldReceive('selectOne')->andThrow(new \Exception('Error'));
        Log::shouldReceive('warning')->once();

        $result = $this->service->getLearningStats();
        $this->assertSame(0, $result['total_interactions']);
    }
}
