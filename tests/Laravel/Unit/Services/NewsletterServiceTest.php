<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Models\Newsletter;
use App\Services\NewsletterService;
use Mockery;
use Tests\Laravel\TestCase;

class NewsletterServiceTest extends TestCase
{
    private NewsletterService $service;
    private $mockNewsletter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockNewsletter = Mockery::mock(Newsletter::class)->makePartial();
        $this->service = new NewsletterService($this->mockNewsletter);
    }

    public function test_getAll_returns_paginated_structure(): void
    {
        $query = Mockery::mock();
        $query->shouldReceive('with')->andReturnSelf();
        $query->shouldReceive('orderByDesc')->andReturnSelf();
        $query->shouldReceive('limit')->andReturnSelf();
        $query->shouldReceive('get')->andReturn(collect([]));
        $this->mockNewsletter->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->getAll();

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
    }

    public function test_getAll_with_status_filter(): void
    {
        $query = Mockery::mock();
        $query->shouldReceive('with')->andReturnSelf();
        $query->shouldReceive('where')->with('status', 'draft')->andReturnSelf();
        $query->shouldReceive('orderByDesc')->andReturnSelf();
        $query->shouldReceive('limit')->andReturnSelf();
        $query->shouldReceive('get')->andReturn(collect([]));
        $this->mockNewsletter->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->getAll(['status' => 'draft']);
        $this->assertSame([], $result['items']);
    }

    public function test_getById_returns_null_when_not_found(): void
    {
        $query = Mockery::mock();
        $query->shouldReceive('with')->andReturnSelf();
        $query->shouldReceive('find')->with(999)->andReturn(null);
        $this->mockNewsletter->shouldReceive('newQuery')->andReturn($query);

        $this->assertNull($this->service->getById(999));
    }

    public function test_create_returns_newsletter(): void
    {
        $newsletter = Mockery::mock(Newsletter::class)->makePartial();
        $newsletter->shouldReceive('save')->once();
        $newsletter->shouldReceive('fresh')->with(['creator'])->andReturn($newsletter);

        $this->mockNewsletter->shouldReceive('newInstance')->andReturn($newsletter);

        $result = $this->service->create(1, [
            'subject' => 'Test Newsletter',
            'content' => '<p>Hello</p>',
        ]);

        $this->assertInstanceOf(Newsletter::class, $result);
    }
}
