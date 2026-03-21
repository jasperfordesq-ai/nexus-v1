<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\VolunteerMatchingService;
use Illuminate\Support\Facades\DB;

class VolunteerMatchingServiceTest extends TestCase
{
    private VolunteerMatchingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new VolunteerMatchingService();
    }

    public function test_findMatches_returns_empty_when_opportunity_not_found(): void
    {
        DB::shouldReceive('table')->with('vol_opportunities')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);

        $result = $this->service->findMatches(2, 999);
        $this->assertEmpty($result);
    }

    public function test_findMatches_returns_empty_when_no_candidates(): void
    {
        $opp = (object) ['id' => 1, 'skills_needed' => 'gardening,cooking'];
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn($opp);
        DB::shouldReceive('pluck')->andReturn(collect([]));
        DB::shouldReceive('whereNotIn')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('limit')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $result = $this->service->findMatches(2, 1);
        $this->assertEmpty($result);
    }

    public function test_getMatchScore_returns_zero_when_opportunity_not_found(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);
        DB::shouldReceive('pluck')->andReturn(collect([]));
        DB::shouldReceive('whereNotIn')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('limit')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $result = $this->service->getMatchScore(2, 999, 1);
        $this->assertEquals(0.0, $result);
    }
}
