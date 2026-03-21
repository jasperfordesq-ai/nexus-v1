<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\CategoryService;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Mockery;

class CategoryServiceTest extends TestCase
{
    private CategoryService $service;
    private $mockCategory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockCategory = Mockery::mock(Category::class);
        $this->service = new CategoryService($this->mockCategory);
    }

    public function test_getByType_queries_with_type_and_active_filters(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('ofType')->with('listing')->andReturnSelf();
        $mockQuery->shouldReceive('active')->andReturnSelf();
        $mockQuery->shouldReceive('orderBy')->with('sort_order')->andReturnSelf();
        $mockQuery->shouldReceive('orderBy')->with('name')->andReturnSelf();
        $mockQuery->shouldReceive('get')->andReturn(new Collection());

        $this->mockCategory->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->getByType('listing');
        $this->assertInstanceOf(Collection::class, $result);
    }

    public function test_getAll_returns_all_active_categories(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('active')->andReturnSelf();
        $mockQuery->shouldReceive('orderBy')->with('type')->andReturnSelf();
        $mockQuery->shouldReceive('orderBy')->with('sort_order')->andReturnSelf();
        $mockQuery->shouldReceive('orderBy')->with('name')->andReturnSelf();
        $mockQuery->shouldReceive('get')->andReturn(new Collection());

        $this->mockCategory->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->getAll();
        $this->assertInstanceOf(Collection::class, $result);
    }
}
