<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\EventReminderService;

/**
 * EventReminderService Tests
 *
 * Tests reminder scheduling, cancellation, and due reminder processing.
 */
class EventReminderServiceTest extends TestCase
{
    private function svc(): EventReminderService
    {
        return new EventReminderService();
    }

    // =========================================================================
    // scheduleReminder
    // =========================================================================

    public function test_schedule_reminder_returns_bool(): void
    {
        $result = $this->svc()->scheduleReminder(2, 999999, '2026-12-25 10:00:00');
        $this->assertIsBool($result);
    }

    // =========================================================================
    // sendDueReminders
    // =========================================================================

    public function test_send_due_reminders_returns_int(): void
    {
        $result = $this->svc()->sendDueReminders(2);
        $this->assertIsInt($result);
    }

    public function test_send_due_reminders_returns_zero_when_no_events(): void
    {
        // Tenant 999999 unlikely to have upcoming events
        $result = $this->svc()->sendDueReminders(999999);
        $this->assertSame(0, $result);
    }

    // =========================================================================
    // cancelReminder
    // =========================================================================

    public function test_cancel_reminder_returns_bool(): void
    {
        $result = $this->svc()->cancelReminder(2, 999999);
        $this->assertIsBool($result);
    }

    public function test_cancel_reminder_for_nonexistent_event(): void
    {
        $result = $this->svc()->cancelReminder(2, 999999);
        $this->assertTrue($result, 'Cancelling nonexistent reminder should succeed (no-op)');
    }

    // =========================================================================
    // Constants / internals
    // =========================================================================

    public function test_reminder_intervals_exist(): void
    {
        $reflection = new \ReflectionClass(EventReminderService::class);
        $constant = $reflection->getConstant('REMINDER_INTERVALS');

        $this->assertIsArray($constant);
        $this->assertArrayHasKey('24h', $constant);
        $this->assertArrayHasKey('1h', $constant);
        $this->assertSame(24, $constant['24h']);
        $this->assertSame(1, $constant['1h']);
    }

    public function test_lookahead_minutes_is_30(): void
    {
        $reflection = new \ReflectionClass(EventReminderService::class);
        $constant = $reflection->getConstant('LOOKAHEAD_MINUTES');

        $this->assertSame(30, $constant);
    }
}
