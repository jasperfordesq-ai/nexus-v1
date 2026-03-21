<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\TeamTaskService;
use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Mockery;

class TeamTaskServiceTest extends TestCase
{
    private TeamTaskService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TeamTaskService();
    }

    public function test_getTasks_returns_paginated_structure(): void
    {
        DB::shouldReceive('table')->with('team_tasks')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('orderByDesc')->with('id')->andReturnSelf();
        DB::shouldReceive('limit')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $result = $this->service->getTasks(1);

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
    }

    public function test_getById_returns_null_when_not_found(): void
    {
        DB::shouldReceive('table')->with('team_tasks')->andReturnSelf();
        DB::shouldReceive('where')->with('id', 999)->andReturnSelf();
        DB::shouldReceive('where')->with('tenant_id', TenantContext::getId())->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);

        $this->assertNull($this->service->getById(999));
    }

    public function test_create_returns_null_when_title_is_empty(): void
    {
        $result = $this->service->create(1, 1, ['title' => '']);

        $this->assertNull($result);
        $this->assertEquals('VALIDATION_ERROR', $this->service->getErrors()[0]['code']);
    }

    public function test_create_returns_null_for_invalid_status(): void
    {
        $result = $this->service->create(1, 1, ['title' => 'Test', 'status' => 'invalid']);

        $this->assertNull($result);
        $this->assertEquals('VALIDATION_ERROR', $this->service->getErrors()[0]['code']);
    }

    public function test_create_returns_null_for_invalid_priority(): void
    {
        $result = $this->service->create(1, 1, ['title' => 'Test', 'priority' => 'invalid']);

        $this->assertNull($result);
        $this->assertEquals('VALIDATION_ERROR', $this->service->getErrors()[0]['code']);
    }

    public function test_update_returns_false_when_task_not_found(): void
    {
        DB::shouldReceive('table')->with('team_tasks')->andReturnSelf();
        DB::shouldReceive('where')->with('id', 999)->andReturnSelf();
        DB::shouldReceive('where')->with('tenant_id', Mockery::any())->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);

        $result = $this->service->update(999, 1, ['title' => 'Updated']);

        $this->assertFalse($result);
        $this->assertEquals('RESOURCE_NOT_FOUND', $this->service->getErrors()[0]['code']);
    }

    public function test_update_returns_false_when_title_set_to_empty(): void
    {
        $task = (object) ['id' => 1, 'status' => 'todo'];
        DB::shouldReceive('table')->with('team_tasks')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn($task);

        $result = $this->service->update(1, 1, ['title' => '   ']);

        $this->assertFalse($result);
        $this->assertEquals('VALIDATION_ERROR', $this->service->getErrors()[0]['code']);
    }

    public function test_delete_returns_false_when_not_found(): void
    {
        DB::shouldReceive('table')->with('team_tasks')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('delete')->andReturn(0);

        $result = $this->service->delete(999, 1);

        $this->assertFalse($result);
    }

    public function test_getStats_returns_expected_keys(): void
    {
        $stats = (object) ['total' => 5, 'todo' => 2, 'in_progress' => 1, 'done' => 2, 'overdue' => 0];

        DB::shouldReceive('table')->with('team_tasks')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('selectRaw')->andReturnSelf();
        DB::shouldReceive('first')->andReturn($stats);

        $result = $this->service->getStats(1);

        $this->assertEquals(5, $result['total']);
        $this->assertEquals(2, $result['todo']);
        $this->assertEquals(1, $result['in_progress']);
        $this->assertEquals(2, $result['done']);
        $this->assertEquals(0, $result['overdue']);
    }
}
