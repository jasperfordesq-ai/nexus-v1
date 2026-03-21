<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\EventReminderService;
use Illuminate\Support\Facades\DB;

class EventReminderServiceTest extends TestCase
{
    private EventReminderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EventReminderService();
    }

    // =========================================================================
    // scheduleReminder()
    // =========================================================================

    public function test_scheduleReminder_returns_true_on_success(): void
    {
        DB::shouldReceive('statement')->once()->andReturn(true);

        $result = $this->service->scheduleReminder(2, 1, '2026-04-01 10:00:00');
        $this->assertTrue($result);
    }

    public function test_scheduleReminder_returns_false_on_exception(): void
    {
        DB::shouldReceive('statement')->andThrow(new \Exception('DB error'));

        $result = $this->service->scheduleReminder(2, 1, '2026-04-01 10:00:00');
        $this->assertFalse($result);
    }

    // =========================================================================
    // cancelReminder()
    // =========================================================================

    public function test_cancelReminder_returns_true_on_success(): void
    {
        DB::shouldReceive('delete')->once()->andReturn(1);

        $result = $this->service->cancelReminder(2, 1);
        $this->assertTrue($result);
    }

    public function test_cancelReminder_returns_false_on_exception(): void
    {
        DB::shouldReceive('delete')->andThrow(new \Exception('DB error'));

        $result = $this->service->cancelReminder(2, 1);
        $this->assertFalse($result);
    }

    // =========================================================================
    // sendDueReminders()
    // =========================================================================

    public function test_sendDueReminders_returns_zero_when_no_events(): void
    {
        DB::shouldReceive('select')->andReturn([]);

        $result = $this->service->sendDueReminders(2);
        $this->assertEquals(0, $result);
    }

    public function test_sendDueReminders_processes_both_reminder_types(): void
    {
        // For both 24h and 1h reminder types, returns empty events
        DB::shouldReceive('select')->twice()->andReturn([]);

        $result = $this->service->sendDueReminders(2);
        $this->assertEquals(0, $result);
    }
}
