<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\GroupSearchService;
use App\Services\SearchService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;

class GroupSearchServiceTest extends TestCase
{
    public function test_indexGroupContent_returns_zero_when_meilisearch_unavailable(): void
    {
        // SearchService::isAvailable() is a static method — mock it
        Mockery::mock('alias:' . SearchService::class)
            ->shouldReceive('isAvailable')
            ->once()
            ->andReturn(false);

        $result = GroupSearchService::indexGroupContent(1);
        $this->assertEquals(0, $result);
    }

    public function test_searchGroupContent_returns_empty_when_meilisearch_unavailable(): void
    {
        Mockery::mock('alias:' . SearchService::class)
            ->shouldReceive('isAvailable')
            ->once()
            ->andReturn(false);

        $result = GroupSearchService::searchGroupContent(1, 'test query');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_removeGroupContent_does_nothing_when_meilisearch_unavailable(): void
    {
        Mockery::mock('alias:' . SearchService::class)
            ->shouldReceive('isAvailable')
            ->once()
            ->andReturn(false);

        // Should not throw, should just return
        GroupSearchService::removeGroupContent(1);
        $this->assertTrue(true); // No exception = pass (method is void)
    }

    public function test_reindexAll_returns_zero_when_meilisearch_unavailable(): void
    {
        Mockery::mock('alias:' . SearchService::class)
            ->shouldReceive('isAvailable')
            ->once()
            ->andReturn(false);

        $result = GroupSearchService::reindexAll(2);
        $this->assertEquals(0, $result);
    }

    public function test_indexGroupContent_returns_zero_when_no_content_found(): void
    {
        Mockery::mock('alias:' . SearchService::class)
            ->shouldReceive('isAvailable')
            ->once()
            ->andReturn(true);

        // DB::select returns empty arrays for both discussions and posts
        DB::shouldReceive('select')
            ->twice()
            ->andReturn([]);

        $result = GroupSearchService::indexGroupContent(1);
        $this->assertEquals(0, $result);
    }
}
