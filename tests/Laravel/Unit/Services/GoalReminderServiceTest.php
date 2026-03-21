<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\GoalReminderService;
use Illuminate\Support\Facades\DB;

class GoalReminderServiceTest extends TestCase
{
    private GoalReminderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GoalReminderService();
    }

    public function test_getReminder_returns_null_when_not_found(): void
    {
        DB::shouldReceive('table->where->where->first')->andReturn(null);

        $this->assertNull($this->service->getReminder(1, 1));
    }

    public function test_getReminder_returns_array_when_found(): void
    {
        $row = (object) ['id' => 1, 'goal_id' => 1, 'user_id' => 1, 'frequency' => 'weekly', 'enabled' => true];
        DB::shouldReceive('table->where->where->first')->andReturn($row);

        $result = $this->service->getReminder(1, 1);
        $this->assertIsArray($result);
        $this->assertEquals(1, $result['id']);
    }

    public function test_deleteReminder_returns_true_when_deleted(): void
    {
        DB::shouldReceive('table->where->where->delete')->andReturn(1);

        $this->assertTrue($this->service->deleteReminder(1, 1));
    }

    public function test_deleteReminder_returns_false_when_not_found(): void
    {
        DB::shouldReceive('table->where->where->delete')->andReturn(0);

        $this->assertFalse($this->service->deleteReminder(999, 1));
    }
}
