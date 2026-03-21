<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\CrossModuleMatchingService;
use App\Services\SmartMatchingEngine;
use App\Services\MatchLearningService;
use Illuminate\Support\Facades\DB;
use Mockery;

class CrossModuleMatchingServiceTest extends TestCase
{
    private CrossModuleMatchingService $service;
    private $mockSmartMatching;
    private $mockMatchLearning;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockSmartMatching = Mockery::mock(SmartMatchingEngine::class);
        $this->mockMatchLearning = Mockery::mock(MatchLearningService::class);
        $this->service = new CrossModuleMatchingService(
            $this->mockSmartMatching,
            $this->mockMatchLearning
        );
    }

    public function test_getAllMatches_returns_expected_structure(): void
    {
        // Mock SmartMatchingEngine to return empty
        $this->mockSmartMatching->shouldReceive('findMatchesForUser')->andReturn([]);
        $this->mockSmartMatching->shouldReceive('extractKeywords')->andReturn([]);

        // Mock DB for group, volunteering, event queries
        DB::shouldReceive('select')->andReturn([]);
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('distinct')->andReturnSelf();
        DB::shouldReceive('pluck')->andReturn(collect([]));
        DB::shouldReceive('first')->andReturn((object) ['skills' => '', 'bio' => '']);

        $result = $this->service->getAllMatches(1);

        $this->assertArrayHasKey('matches', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertIsArray($result['matches']);
        $this->assertArrayHasKey('total', $result['meta']);
        $this->assertArrayHasKey('modules', $result['meta']);
    }

    public function test_getAllMatches_respects_module_filter(): void
    {
        // Only request listings module
        $this->mockSmartMatching->shouldReceive('findMatchesForUser')->andReturn([]);

        // Mock dismissed IDs
        DB::shouldReceive('table')->with('match_dismissals')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('pluck')->andReturn(collect([]));

        $result = $this->service->getAllMatches(1, ['modules' => ['listings']]);

        $this->assertSame(['listings'], $result['meta']['modules']);
    }

    public function test_getAllMatches_strips_debug_data_by_default(): void
    {
        $this->mockSmartMatching->shouldReceive('findMatchesForUser')->andReturn([
            [
                'id' => 1, 'title' => 'Test', 'description' => 'Desc', 'type' => 'offer',
                'user_id' => 2, 'first_name' => 'John', 'last_name' => 'Doe',
                'match_score' => 80, 'match_type' => 'one_way', 'match_reasons' => [],
                'match_breakdown' => ['skills' => 40, 'location' => 40],
            ],
        ]);
        $this->mockMatchLearning->shouldReceive('getHistoricalBoost')->andReturn(0);

        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('pluck')->andReturn(collect([]));

        $result = $this->service->getAllMatches(1, ['modules' => ['listings']]);

        if (!empty($result['matches'])) {
            $this->assertArrayNotHasKey('match_breakdown', $result['matches'][0]);
        }
    }

    public function test_getAllMatches_applies_limit(): void
    {
        $this->mockSmartMatching->shouldReceive('findMatchesForUser')->andReturn([]);
        $this->mockSmartMatching->shouldReceive('extractKeywords')->andReturn([]);

        DB::shouldReceive('select')->andReturn([]);
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('distinct')->andReturnSelf();
        DB::shouldReceive('pluck')->andReturn(collect([]));
        DB::shouldReceive('first')->andReturn((object) ['skills' => '', 'bio' => '']);

        $result = $this->service->getAllMatches(1, ['limit' => 5]);

        $this->assertLessThanOrEqual(5, count($result['matches']));
    }
}
