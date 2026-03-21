<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\GroupChatroomService;
use Illuminate\Support\Facades\DB;

class GroupChatroomServiceTest extends TestCase
{
    private GroupChatroomService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GroupChatroomService();
    }

    public function test_getChatrooms_returns_array(): void
    {
        $rows = collect([
            (object) ['id' => 1, 'group_id' => 1, 'name' => 'General', 'description' => null, 'is_default' => 1, 'created_by' => 5, 'created_at' => '2026-01-01'],
        ]);

        DB::shouldReceive('table->where->where->orderByDesc->orderBy->get')->andReturn($rows);

        $result = $this->service->getChatrooms(1);
        $this->assertCount(1, $result);
        $this->assertEquals('General', $result[0]['name']);
        $this->assertTrue($result[0]['is_default']);
    }

    public function test_getById_returns_null_when_not_found(): void
    {
        DB::shouldReceive('table->where->where->first')->andReturn(null);

        $this->assertNull($this->service->getById(999));
    }

    public function test_getById_returns_array_when_found(): void
    {
        $chatroom = (object) ['id' => 1, 'group_id' => 1, 'name' => 'General', 'description' => null, 'is_default' => 1, 'created_by' => 5, 'created_at' => '2026-01-01'];
        DB::shouldReceive('table->where->where->first')->andReturn($chatroom);

        $result = $this->service->getById(1);
        $this->assertIsArray($result);
        $this->assertEquals(1, $result['id']);
    }

    public function test_getErrors_returns_array(): void
    {
        $this->assertIsArray($this->service->getErrors());
    }
}
