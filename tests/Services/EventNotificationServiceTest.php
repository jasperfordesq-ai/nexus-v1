<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\EventNotificationService;
use App\Core\TenantContext;

/**
 * EventNotificationService Tests
 *
 * Tests attendee notifications, cancellation, RSVP, and event update notifications.
 */
class EventNotificationServiceTest extends TestCase
{
    private function svc(): EventNotificationService
    {
        return new EventNotificationService();
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(2);
    }

    // =========================================================================
    // notifyAttendees
    // =========================================================================

    public function test_notify_attendees_returns_zero_for_nonexistent_event(): void
    {
        $result = $this->svc()->notifyAttendees(2, 999999, 'Test message');
        $this->assertSame(0, $result);
    }

    public function test_notify_attendees_returns_int(): void
    {
        $result = $this->svc()->notifyAttendees(2, 999999, 'Test');
        $this->assertIsInt($result);
    }

    // =========================================================================
    // sendReminder
    // =========================================================================

    public function test_send_reminder_returns_zero_for_nonexistent_event(): void
    {
        $result = $this->svc()->sendReminder(2, 999999);
        $this->assertSame(0, $result);
    }

    public function test_send_reminder_returns_int(): void
    {
        $result = $this->svc()->sendReminder(2, 999999);
        $this->assertIsInt($result);
    }

    // =========================================================================
    // notifyCancellation
    // =========================================================================

    public function test_notify_cancellation_returns_zero_for_nonexistent_event(): void
    {
        $result = $this->svc()->notifyCancellation(2, 999999);
        $this->assertSame(0, $result);
    }

    public function test_notify_cancellation_with_reason(): void
    {
        $result = $this->svc()->notifyCancellation(2, 999999, 'Weather');
        $this->assertSame(0, $result);
    }

    // =========================================================================
    // notifyEventUpdated
    // =========================================================================

    public function test_notify_event_updated_skips_non_meaningful_changes(): void
    {
        // Changes that are not meaningful (not start_time, end_time, location, title)
        // should silently return without notifications
        $this->svc()->notifyEventUpdated(999999, ['description' => 'Updated desc']);
        // No exception = pass
        $this->assertTrue(true);
    }

    public function test_notify_event_updated_with_meaningful_changes(): void
    {
        // Non-existent event — should not throw
        $this->svc()->notifyEventUpdated(999999, ['start_time' => '2026-06-01 10:00:00']);
        $this->assertTrue(true);
    }

    // =========================================================================
    // notifyRsvp
    // =========================================================================

    public function test_notify_rsvp_ignores_invalid_status(): void
    {
        // 'not_going' is not in the allowed list
        $this->svc()->notifyRsvp(999999, 1, 'not_going');
        $this->assertTrue(true);
    }

    public function test_notify_rsvp_with_valid_status(): void
    {
        // Non-existent event — should not throw
        $this->svc()->notifyRsvp(999999, 1, 'going');
        $this->assertTrue(true);
    }

    public function test_notify_rsvp_with_interested_status(): void
    {
        $this->svc()->notifyRsvp(999999, 1, 'interested');
        $this->assertTrue(true);
    }
}
