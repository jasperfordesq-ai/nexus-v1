<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\ResourceService;
use App\Models\ResourceItem;
use Mockery;

class ResourceServiceTest extends TestCase
{
    private ResourceService $service;
    private $mockResource;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockResource = Mockery::mock(ResourceItem::class);
        $this->service = new ResourceService($this->mockResource);
    }

    // ── getAll ──

    public function test_getAll_returns_paginated_structure(): void
    {
        // getAll() fetches limit+1 rows, then serialises the page with
        // $items->toArray() and only consults isNotEmpty()/last() when there is
        // a next page. For an empty result set hasMore is false, so only count()
        // and toArray() are exercised.
        $collection = Mockery::mock(\Illuminate\Database\Eloquent\Collection::class);
        $collection->shouldReceive('count')->andReturn(0);
        $collection->shouldReceive('isNotEmpty')->andReturn(false);
        $collection->shouldReceive('toArray')->andReturn([]);

        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('with')->andReturnSelf();
        $mockQuery->shouldReceive('orderByDesc')->andReturnSelf();
        $mockQuery->shouldReceive('limit')->andReturnSelf();
        $mockQuery->shouldReceive('get')->andReturn($collection);

        $this->mockResource->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->getAll();
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertFalse($result['has_more']);
    }

    // ── download ──

    public function test_download_returns_null_when_not_found(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('find')->with(999)->andReturnNull();
        $this->mockResource->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->download(999);
        $this->assertNull($result);
    }

    public function test_download_returns_file_path(): void
    {
        // download() increments the counter then returns $resource->file_path.
        // On an Eloquent mock, reading file_path routes through getAttribute();
        // assigning the property (as before) instead fired an unexpected
        // setAttribute() on the strict mock.
        // Model::increment() is protected in this Laravel version, so the mock
        // must explicitly allow mocking protected methods.
        $resource = Mockery::mock(ResourceItem::class)->shouldAllowMockingProtectedMethods();
        $resource->shouldReceive('getAttribute')->with('file_path')->andReturn('/uploads/test.pdf');
        $resource->shouldReceive('increment')->with('downloads')->once();

        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('find')->with(1)->andReturn($resource);
        $this->mockResource->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->download(1);
        $this->assertEquals('/uploads/test.pdf', $result);
    }

    // ── delete ──

    public function test_delete_returns_false_for_wrong_user(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')->with('id', 1)->andReturnSelf();
        $mockQuery->shouldReceive('where')->with('user_id', 99)->andReturnSelf();
        $mockQuery->shouldReceive('delete')->andReturn(0);
        $this->mockResource->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->delete(1, 99);
        $this->assertFalse($result);
    }

    public function test_delete_returns_true_on_success(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')->with('id', 1)->andReturnSelf();
        $mockQuery->shouldReceive('where')->with('user_id', 1)->andReturnSelf();
        $mockQuery->shouldReceive('delete')->andReturn(1);
        $this->mockResource->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->delete(1, 1);
        $this->assertTrue($result);
    }
}
