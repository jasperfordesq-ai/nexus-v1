<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Models\Notification;
use App\Services\NotificationService;
use Mockery;
use Tests\Laravel\TestCase;

class NotificationServiceTest extends TestCase
{
    private NotificationService $service;
    private $mockNotification;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockNotification = Mockery::mock(Notification::class)->makePartial();
        $this->service = new NotificationService($this->mockNotification);
    }

    public function test_getAll_returns_paginated_structure(): void
    {
        $query = Mockery::mock();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('orderByDesc')->andReturnSelf();
        $query->shouldReceive('limit')->andReturnSelf();
        $query->shouldReceive('get')->andReturn(collect([]));
        $this->mockNotification->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->getAll(1);

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertFalse($result['has_more']);
    }

    public function test_getAll_with_cursor(): void
    {
        $query = Mockery::mock();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('orderByDesc')->andReturnSelf();
        $query->shouldReceive('limit')->andReturnSelf();
        $query->shouldReceive('get')->andReturn(collect([]));
        $this->mockNotification->shouldReceive('newQuery')->andReturn($query);

        $cursor = base64_encode('50');
        $result = $this->service->getAll(1, ['cursor' => $cursor]);

        $this->assertSame([], $result['items']);
    }

    public function test_getAll_unread_only_filter(): void
    {
        $query = Mockery::mock();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('unread')->once()->andReturnSelf();
        $query->shouldReceive('orderByDesc')->andReturnSelf();
        $query->shouldReceive('limit')->andReturnSelf();
        $query->shouldReceive('get')->andReturn(collect([]));
        $this->mockNotification->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->getAll(1, ['unread_only' => true]);
        $this->assertSame([], $result['items']);
    }

    public function test_getAll_has_more_when_exceeds_limit(): void
    {
        $items = collect();
        for ($i = 0; $i < 21; $i++) {
            $mock = Mockery::mock(Notification::class)->makePartial();
            $mock->id = 100 - $i;
            $mock->shouldReceive('toArray')->andReturn(['id' => 100 - $i]);
            $items->push($mock);
        }

        $query = Mockery::mock();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('orderByDesc')->andReturnSelf();
        $query->shouldReceive('limit')->andReturnSelf();
        $query->shouldReceive('get')->andReturn($items);
        $this->mockNotification->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->getAll(1, ['limit' => 20]);
        $this->assertTrue($result['has_more']);
        $this->assertNotNull($result['cursor']);
    }

    public function test_getCounts_returns_total(): void
    {
        $query = Mockery::mock();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('unread')->andReturnSelf();
        $query->shouldReceive('get')->andReturn(collect([]));
        $this->mockNotification->shouldReceive('newQuery')->andReturn($query);

        $result = $this->service->getCounts(1);
        $this->assertArrayHasKey('total', $result);
        $this->assertSame(0, $result['total']);
    }
}
