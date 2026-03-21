<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\MatchingService;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class MatchingServiceTest extends TestCase
{
    public function test_getSuggestionsForUser_returns_results(): void
    {
        DB::shouldReceive('select')->once()->andReturn([
            (object) ['id' => 2, 'first_name' => 'Alice', 'last_name' => 'S', 'avatar_url' => null, 'location' => 'Dublin', 'skills' => 'cooking'],
        ]);

        $result = MatchingService::getSuggestionsForUser(1, 5);
        $this->assertCount(1, $result);
    }

    public function test_getSuggestionsForUser_handles_error(): void
    {
        DB::shouldReceive('select')->andThrow(new \Exception('Error'));

        $result = MatchingService::getSuggestionsForUser(1);
        $this->assertSame([], $result);
    }

    public function test_getHotMatches_delegates(): void
    {
        DB::shouldReceive('select')->andReturn([]);

        $result = MatchingService::getHotMatches(1);
        $this->assertSame([], $result);
    }

    public function test_getMutualMatches_returns_empty(): void
    {
        $this->assertSame([], MatchingService::getMutualMatches(1));
    }

    public function test_getMatchesByType_returns_structure(): void
    {
        DB::shouldReceive('select')->andReturn([]);

        $result = MatchingService::getMatchesByType(1);

        $this->assertArrayHasKey('hot', $result);
        $this->assertArrayHasKey('good', $result);
        $this->assertArrayHasKey('mutual', $result);
        $this->assertArrayHasKey('all', $result);
    }
}
