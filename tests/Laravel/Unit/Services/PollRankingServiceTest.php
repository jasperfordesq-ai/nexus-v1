<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\PollRankingService;
use Illuminate\Support\Facades\DB;
use Mockery;

class PollRankingServiceTest extends TestCase
{
    private PollRankingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PollRankingService();
    }

    public function test_submitRanking_returns_false_if_already_voted(): void
    {
        DB::shouldReceive('table->where->where->exists')->andReturn(true);

        $result = $this->service->submitRanking(1, 1, []);
        $this->assertFalse($result);
    }

    public function test_submitRanking_inserts_rankings_and_returns_true(): void
    {
        DB::shouldReceive('table->where->where->exists')->andReturn(false);
        DB::shouldReceive('transaction')->andReturnUsing(fn ($cb) => $cb());
        DB::shouldReceive('table->insert')->twice();

        $rankings = [
            ['option_id' => 10, 'rank' => 1],
            ['option_id' => 11, 'rank' => 2],
        ];

        $result = $this->service->submitRanking(1, 1, $rankings);
        $this->assertTrue($result);
    }

    public function test_getUserRankings_returns_null_when_empty(): void
    {
        DB::shouldReceive('table->where->where->orderBy->get->all')->andReturn([]);

        $result = $this->service->getUserRankings(1, 1);
        $this->assertNull($result);
    }

    public function test_calculateResults_returns_structure(): void
    {
        $rankings = collect();
        DB::shouldReceive('table->where->orderBy->orderBy->get->groupBy')->andReturn($rankings);
        DB::shouldReceive('table->where->pluck->all')->andReturn([]);

        $result = $this->service->calculateResults(1);
        $this->assertArrayHasKey('total_voters', $result);
        $this->assertArrayHasKey('results', $result);
    }
}
