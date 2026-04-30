<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\BlogService;
use App\Models\Post;
use App\Models\Category;
use Mockery;

class BlogServiceTest extends TestCase
{
    private BlogService $service;
    private $mockPost;
    private $mockCategory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockPost = Mockery::mock(Post::class);
        $this->mockCategory = Mockery::mock(Category::class);
        $this->service = new BlogService($this->mockPost, $this->mockCategory);
    }

    public function test_getAll_returns_expected_structure(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('published')->andReturnSelf();
        $mockQuery->shouldReceive('with')->andReturnSelf();
        $mockQuery->shouldReceive('whereNotIn')->andReturnSelf();
        $mockQuery->shouldReceive('whereRaw')->andReturnSelf();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('orderByDesc')->andReturnSelf();
        $mockQuery->shouldReceive('limit')->andReturnSelf();
        $mockQuery->shouldReceive('get')->andReturn(collect([]));
        $mockQuery->shouldReceive('count')->andReturn(0);
        $mockQuery->shouldReceive('isNotEmpty')->andReturn(false);

        $this->mockPost->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->getAll();

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertEmpty($result['items']);
        $this->assertFalse($result['has_more']);
    }

    public function test_getBySlug_returns_null_when_not_found(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('published')->andReturnSelf();
        $mockQuery->shouldReceive('with')->andReturnSelf();
        $mockQuery->shouldReceive('whereNotIn')->andReturnSelf();
        $mockQuery->shouldReceive('whereRaw')->andReturnSelf();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('first')->andReturnNull();

        $this->mockPost->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->getBySlug('nonexistent');
        $this->assertNull($result);
    }

    public function test_getPosts_returns_expected_structure(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('published')->andReturnSelf();
        $mockQuery->shouldReceive('with')->andReturnSelf();
        $mockQuery->shouldReceive('whereNotIn')->andReturnSelf();
        $mockQuery->shouldReceive('whereRaw')->andReturnSelf();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('count')->andReturn(0);
        $mockQuery->shouldReceive('orderByDesc')->andReturnSelf();
        $mockQuery->shouldReceive('skip')->andReturnSelf();
        $mockQuery->shouldReceive('take')->andReturnSelf();
        $mockQuery->shouldReceive('get')->andReturn(collect([]));

        $this->mockPost->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->getPosts(2);

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
    }

    public function test_getCategories_returns_array(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('ofType')->with('blog')->andReturnSelf();
        $mockQuery->shouldReceive('active')->andReturnSelf();
        $mockQuery->shouldReceive('withCount')->andReturnSelf();
        $mockQuery->shouldReceive('orderBy')->andReturnSelf();
        $mockQuery->shouldReceive('get')->andReturn(collect([]));

        $this->mockCategory->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->getCategories();
        $this->assertIsArray($result);
    }
}
