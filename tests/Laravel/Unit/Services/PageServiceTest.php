<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\PageService;
use App\Models\Page;
use Mockery;

class PageServiceTest extends TestCase
{
    private PageService $service;
    private $mockPage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockPage = Mockery::mock(Page::class);
        $this->service = new PageService($this->mockPage);
    }

    public function test_getBySlug_returns_null_when_not_found(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('published')->andReturnSelf();
        $mockQuery->shouldReceive('where')->with('slug', 'nonexistent')->andReturnSelf();
        $mockQuery->shouldReceive('first')->andReturnNull();

        $this->mockPage->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->getBySlug('nonexistent');
        $this->assertNull($result);
    }

    public function test_getBySlug_returns_page_when_found(): void
    {
        $page = Mockery::mock(Page::class);
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('published')->andReturnSelf();
        $mockQuery->shouldReceive('where')->with('slug', 'about-us')->andReturnSelf();
        $mockQuery->shouldReceive('first')->andReturn($page);

        $this->mockPage->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->getBySlug('about-us');
        $this->assertNotNull($result);
    }
}
