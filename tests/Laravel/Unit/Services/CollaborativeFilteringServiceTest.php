<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\CollaborativeFilteringService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CollaborativeFilteringServiceTest extends TestCase
{
    public function test_getSimilarListings_returns_cached_result(): void
    {
        Cache::shouldReceive('get')
            ->with('cf_listings_2_1_5')
            ->andReturn([10, 20, 30]);

        $result = CollaborativeFilteringService::getSimilarListings(1, 2, 5);
        $this->assertSame([10, 20, 30], $result);
    }

    public function test_getSimilarListings_returns_fallback_when_no_interactions(): void
    {
        Cache::shouldReceive('get')->andReturnNull();

        // loadListingInteractions returns empty
        DB::shouldReceive('select')->andReturn([]);

        // getPopularListingsFallback
        DB::shouldReceive('table')->with('listings')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('orderByDesc')->andReturnSelf();
        DB::shouldReceive('limit')->andReturnSelf();
        DB::shouldReceive('pluck')->andReturn(collect([5, 6, 7]));

        $result = CollaborativeFilteringService::getSimilarListings(1, 2, 5);
        $this->assertIsArray($result);
    }

    public function test_getSuggestedMembers_returns_cached_result(): void
    {
        Cache::shouldReceive('get')
            ->with('cf_members_2_1_5')
            ->andReturn([100, 200]);

        $result = CollaborativeFilteringService::getSuggestedMembers(1, 2, 5);
        $this->assertSame([100, 200], $result);
    }

    public function test_getSuggestedMembers_returns_fallback_when_no_interactions(): void
    {
        Cache::shouldReceive('get')->andReturnNull();

        DB::shouldReceive('select')->andReturn([]);

        DB::shouldReceive('table')->with('users')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('orderByDesc')->andReturnSelf();
        DB::shouldReceive('limit')->andReturnSelf();
        DB::shouldReceive('pluck')->andReturn(collect([1, 2, 3]));

        $result = CollaborativeFilteringService::getSuggestedMembers(1, 2, 5);
        $this->assertIsArray($result);
    }

    public function test_getSuggestedListingsForUser_returns_cached_result(): void
    {
        Cache::shouldReceive('get')
            ->with('cf_uu_listings_2_1_10')
            ->andReturn([50, 60]);

        $result = CollaborativeFilteringService::getSuggestedListingsForUser(1, 2, 10);
        $this->assertSame([50, 60], $result);
    }

    public function test_getSuggestedListingsForUser_returns_fallback_when_no_member_interactions(): void
    {
        Cache::shouldReceive('get')->andReturnNull();

        DB::shouldReceive('select')->andReturn([]);

        DB::shouldReceive('table')->with('listings')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('orderByDesc')->andReturnSelf();
        DB::shouldReceive('limit')->andReturnSelf();
        DB::shouldReceive('pluck')->andReturn(collect([]));

        $result = CollaborativeFilteringService::getSuggestedListingsForUser(1, 2, 5);
        $this->assertIsArray($result);
    }

    public function test_cosine_similarity_via_item_based_recommendations(): void
    {
        // Test via reflection since cosineSimilarity is private
        $reflection = new \ReflectionClass(CollaborativeFilteringService::class);
        $method = $reflection->getMethod('cosineSimilarity');
        $method->setAccessible(true);

        // Identical vectors should give similarity = 1.0
        $a = [1 => 1.0, 2 => 1.0];
        $b = [1 => 1.0, 2 => 1.0];
        $this->assertEqualsWithDelta(1.0, $method->invoke(null, $a, $b), 0.001);

        // Orthogonal vectors should give similarity = 0.0
        $a = [1 => 1.0];
        $b = [2 => 1.0];
        $this->assertEqualsWithDelta(0.0, $method->invoke(null, $a, $b), 0.001);

        // Empty vectors
        $this->assertEqualsWithDelta(0.0, $method->invoke(null, [], []), 0.001);
    }
}
