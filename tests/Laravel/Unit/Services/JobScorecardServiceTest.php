<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\JobScorecardService;
use App\Models\JobApplication;
use App\Models\JobScorecard;
use Illuminate\Support\Facades\Log;
use Mockery;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class JobScorecardServiceTest extends TestCase
{
    // ── upsert ──────────────────────────────────────────────────

    public function test_upsert_returns_false_when_application_not_found(): void
    {
        $mock = Mockery::mock('alias:' . JobApplication::class);
        $mock->shouldReceive('with')->with('vacancy')->andReturnSelf();
        $mock->shouldReceive('find')->with(999)->andReturnNull();

        $result = JobScorecardService::upsert(999, 1, ['criteria' => []]);
        $this->assertFalse($result);
    }

    public function test_upsert_returns_false_when_vacancy_missing(): void
    {
        $app = Mockery::mock();
        $app->vacancy = null;

        $mock = Mockery::mock('alias:' . JobApplication::class);
        $mock->shouldReceive('with')->with('vacancy')->andReturnSelf();
        $mock->shouldReceive('find')->with(1)->andReturn($app);

        $result = JobScorecardService::upsert(1, 1, ['criteria' => []]);
        $this->assertFalse($result);
    }

    public function test_upsert_returns_false_when_tenant_mismatch(): void
    {
        $vacancy = Mockery::mock();
        $vacancy->tenant_id = 999; // wrong tenant

        $app = Mockery::mock();
        $app->vacancy = $vacancy;

        $mock = Mockery::mock('alias:' . JobApplication::class);
        $mock->shouldReceive('with')->with('vacancy')->andReturnSelf();
        $mock->shouldReceive('find')->with(1)->andReturn($app);

        $result = JobScorecardService::upsert(1, 1, ['criteria' => []]);
        $this->assertFalse($result);
    }

    public function test_upsert_calculates_total_and_max_scores(): void
    {
        $vacancy = Mockery::mock();
        $vacancy->tenant_id = $this->testTenantId;

        $app = Mockery::mock();
        $app->vacancy = $vacancy;
        $app->vacancy_id = 10;

        $scorecard = Mockery::mock();
        $scorecard->shouldReceive('toArray')->andReturn([
            'id' => 1, 'total_score' => 15.0, 'max_score' => 30.0,
        ]);

        $mock = Mockery::mock('alias:' . JobApplication::class);
        $mock->shouldReceive('with')->with('vacancy')->andReturnSelf();
        $mock->shouldReceive('find')->with(1)->andReturn($app);

        $scMock = Mockery::mock('alias:' . JobScorecard::class);
        $scMock->shouldReceive('updateOrCreate')->withArgs(function ($keys, $data) {
            // criteria: [{score:8, max_score:10}, {score:7, max_score:10}, {score:0, max_score:10}]
            // total=15, max=30
            return $data['total_score'] === 15.0 && $data['max_score'] === 30.0;
        })->andReturn($scorecard);

        $result = JobScorecardService::upsert(1, 5, [
            'criteria' => [
                ['label' => 'Communication', 'score' => 8, 'max_score' => 10],
                ['label' => 'Technical', 'score' => 7, 'max_score' => 10],
                ['label' => 'Culture fit', 'score' => 0, 'max_score' => 10],
            ],
            'notes' => 'Good candidate',
        ]);
        $this->assertIsArray($result);
        $this->assertSame(15.0, $result['total_score']);
    }

    public function test_upsert_defaults_max_score_to_100_when_zero(): void
    {
        $vacancy = Mockery::mock();
        $vacancy->tenant_id = $this->testTenantId;

        $app = Mockery::mock();
        $app->vacancy = $vacancy;
        $app->vacancy_id = 10;

        $scorecard = Mockery::mock();
        $scorecard->shouldReceive('toArray')->andReturn([
            'id' => 1, 'total_score' => 0.0, 'max_score' => 100,
        ]);

        $mock = Mockery::mock('alias:' . JobApplication::class);
        $mock->shouldReceive('with')->with('vacancy')->andReturnSelf();
        $mock->shouldReceive('find')->with(1)->andReturn($app);

        $scMock = Mockery::mock('alias:' . JobScorecard::class);
        $scMock->shouldReceive('updateOrCreate')->withArgs(function ($keys, $data) {
            // Empty criteria => maxScore=0 => defaults to 100
            return $data['max_score'] === 100;
        })->andReturn($scorecard);

        $result = JobScorecardService::upsert(1, 5, ['criteria' => []]);
        $this->assertIsArray($result);
    }

    public function test_upsert_returns_false_on_exception(): void
    {
        Log::shouldReceive('error')->once();

        $mock = Mockery::mock('alias:' . JobApplication::class);
        $mock->shouldReceive('with')->andThrow(new \Exception('DB error'));

        $result = JobScorecardService::upsert(1, 1, []);
        $this->assertFalse($result);
    }

    // ── getForApplication ───────────────────────────────────────

    public function test_getForApplication_returns_scorecards_array(): void
    {
        $builder = Mockery::mock();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('orderByDesc')->with('updated_at')->andReturnSelf();
        $builder->shouldReceive('get')->andReturn(collect([]));

        $collection = collect([]);
        $builder->shouldReceive('get->toArray')->andReturn([]);

        $mock = Mockery::mock('alias:' . JobScorecard::class);
        $mock->shouldReceive('with')->andReturn($builder);

        $result = JobScorecardService::getForApplication(1);
        $this->assertIsArray($result);
    }

    public function test_getForApplication_returns_empty_on_exception(): void
    {
        Log::shouldReceive('error')->once();

        $mock = Mockery::mock('alias:' . JobScorecard::class);
        $mock->shouldReceive('with')->andThrow(new \Exception('DB error'));

        $result = JobScorecardService::getForApplication(1);
        $this->assertSame([], $result);
    }

    // ── getMine ─────────────────────────────────────────────────

    public function test_getMine_returns_scorecard_when_found(): void
    {
        $card = Mockery::mock();
        $card->shouldReceive('toArray')->andReturn([
            'id' => 1, 'reviewer_id' => 5, 'total_score' => 8.5,
        ]);

        $builder = Mockery::mock();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('first')->andReturn($card);

        $mock = Mockery::mock('alias:' . JobScorecard::class);
        $mock->shouldReceive('where')->andReturn($builder);

        $result = JobScorecardService::getMine(1, 5);
        $this->assertIsArray($result);
        $this->assertSame(5, $result['reviewer_id']);
    }

    public function test_getMine_returns_null_when_not_found(): void
    {
        $builder = Mockery::mock();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('first')->andReturn(null);

        $mock = Mockery::mock('alias:' . JobScorecard::class);
        $mock->shouldReceive('where')->andReturn($builder);

        $result = JobScorecardService::getMine(1, 5);
        $this->assertNull($result);
    }

    public function test_getMine_returns_null_on_exception(): void
    {
        Log::shouldReceive('error')->once();

        $mock = Mockery::mock('alias:' . JobScorecard::class);
        $mock->shouldReceive('where')->andThrow(new \Exception('DB error'));

        $result = JobScorecardService::getMine(1, 5);
        $this->assertNull($result);
    }
}
