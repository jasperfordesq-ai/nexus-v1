<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\FeedSocialService;
use Illuminate\Support\Facades\DB;

class FeedSocialServiceTest extends TestCase
{
    private FeedSocialService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FeedSocialService();
    }

    public function test_sharePost_returns_share_id(): void
    {
        DB::shouldReceive('table->insertGetId')->andReturn(42);
        DB::shouldReceive('table->where->where->increment')->once();

        $result = $this->service->sharePost(1, 5, 'Great post!');
        $this->assertEquals(42, $result);
    }

    public function test_getTrendingHashtags_returns_array(): void
    {
        DB::shouldReceive('table->join->where->where->select->groupBy->orderByDesc->limit->get->map->all')
            ->andReturn([['hashtag' => 'timebank', 'usage_count' => 10]]);

        $result = $this->service->getTrendingHashtags();
        $this->assertCount(1, $result);
        $this->assertEquals('timebank', $result[0]['hashtag']);
    }
}
