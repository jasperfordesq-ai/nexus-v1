<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Models\Listing;
use App\Services\ListingService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Laravel\TestCase;

class ListingServiceTest extends TestCase
{
    public function test_getAll_returns_paginated_structure(): void
    {
        $query = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
        $query->shouldReceive('with')->andReturnSelf();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('whereNull')->andReturnSelf();
        $query->shouldReceive('orWhere')->andReturnSelf();
        $query->shouldReceive('orderByDesc')->andReturnSelf();
        $query->shouldReceive('limit')->andReturnSelf();
        $query->shouldReceive('get')->andReturn(collect([]));

        Listing::shouldReceive('query')->andReturn($query);

        $result = ListingService::getAll();

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertFalse($result['has_more']);
    }

    public function test_getAll_with_type_filter(): void
    {
        $query = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
        $query->shouldReceive('with')->andReturnSelf();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('whereNull')->andReturnSelf();
        $query->shouldReceive('orWhere')->andReturnSelf();
        $query->shouldReceive('orderByDesc')->andReturnSelf();
        $query->shouldReceive('limit')->andReturnSelf();
        $query->shouldReceive('get')->andReturn(collect([]));

        Listing::shouldReceive('query')->andReturn($query);

        $result = ListingService::getAll(['type' => 'offer']);
        $this->assertIsArray($result);
    }

    public function test_getAll_limits_to_100(): void
    {
        $query = Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
        $query->shouldReceive('with')->andReturnSelf();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('whereNull')->andReturnSelf();
        $query->shouldReceive('orWhere')->andReturnSelf();
        $query->shouldReceive('orderByDesc')->andReturnSelf();
        $query->shouldReceive('limit')->with(101)->andReturnSelf(); // 100 + 1
        $query->shouldReceive('get')->andReturn(collect([]));

        Listing::shouldReceive('query')->andReturn($query);

        $result = ListingService::getAll(['limit' => 500]);
        $this->assertIsArray($result);
    }
}
