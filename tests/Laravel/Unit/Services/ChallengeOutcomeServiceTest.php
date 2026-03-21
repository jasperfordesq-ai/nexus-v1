<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\ChallengeOutcomeService;
use App\Models\ChallengeOutcome;
use Illuminate\Support\Facades\DB;
use Mockery;

class ChallengeOutcomeServiceTest extends TestCase
{
    private ChallengeOutcomeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ChallengeOutcomeService();
    }

    public function test_getForChallenge_returns_null_when_not_found(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('leftJoin')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturnNull();

        $result = $this->service->getForChallenge(999);
        $this->assertNull($result);
    }

    public function test_upsert_returns_null_when_not_admin(): void
    {
        DB::shouldReceive('table')->with('users')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) ['role' => 'member']);

        $result = $this->service->upsert(1, 1, ['status' => 'in_progress']);
        $this->assertNull($result);
        $this->assertSame('RESOURCE_FORBIDDEN', $this->service->getErrors()[0]['code']);
    }

    public function test_upsert_returns_null_when_challenge_not_found(): void
    {
        DB::shouldReceive('table')->with('users')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) ['role' => 'admin']);

        DB::shouldReceive('table')->with('ideation_challenges')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturnNull();

        $result = $this->service->upsert(999, 1, ['status' => 'in_progress']);
        $this->assertNull($result);
        $this->assertSame('RESOURCE_NOT_FOUND', $this->service->getErrors()[0]['code']);
    }

    public function test_upsert_returns_null_for_invalid_status(): void
    {
        DB::shouldReceive('table')->with('users')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) ['role' => 'admin']);

        DB::shouldReceive('table')->with('ideation_challenges')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) ['id' => 1, 'status' => 'active']);

        $result = $this->service->upsert(1, 1, ['status' => 'invalid_status']);
        $this->assertNull($result);
    }

    public function test_getDashboard_returns_outcomes_and_stats(): void
    {
        $this->markTestIncomplete('Requires integration test — uses DB query builder with multiple joins');
    }
}
