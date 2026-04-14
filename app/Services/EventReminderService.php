<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;

/**
 * EventReminderService — Laravel DI service for automated event reminders.
 *
 * Sends reminder notifications to event attendees at two intervals:
 *   - 24 hours before
 *   - 1 hour before
 *
 * Designed to be called from a cron job. Idempotent — duplicate reminders
 * are prevented by the event_reminder_sent tracking table.
 */
class EventReminderService
{
    /**
     * Reminder intervals in hours before event start_time.
     */
    private const REMINDER_INTERVALS = [
        '24h' => 24,
        '1h'  => 1,
    ];

    /**
     * Window in minutes — how far ahead to look for events needing reminders.
     */
    private const LOOKAHEAD_MINUTES = 30;

    public function __construct()
    {
    }

    /**
     * Schedule a reminder for an event.
     */
    public function scheduleReminder(int $tenantId, int $eventId, string $remindAt): bool
    {
        try {
            DB::statement(
                "INSERT IGNORE INTO event_reminder_sent (tenant_id, event_id, user_id, reminder_type, sent_at)
                 VALUES (?, ?, 0, 'scheduled', ?)",
                [$tenantId, $eventId, $remindAt]
            );
            return true;
        } catch (\Exception $e) {
            error_log("[EventReminderService] scheduleReminder error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send all due event reminders for the current tenant.
     *
     * @return int Number of reminders sent
     */
    public function sendDueReminders(int $tenantId): int
    {
        $sent = 0;

        foreach (self::REMINDER_INTERVALS as $type => $hoursBeforeEvent) {
            $result = $this->processReminderType($tenantId, $type, $hoursBeforeEvent);
            $sent += $result['sent'];
        }

        return $sent;
    }

    /**
     * Cancel a reminder for an event.
     */
    public function cancelReminder(int $tenantId, int $eventId): bool
    {
        try {
            DB::delete(
                "DELETE FROM event_reminder_sent WHERE tenant_id = ? AND event_id = ?",
                [$tenantId, $eventId]
            );
            return true;
        } catch (\Exception $e) {
            error_log("[EventReminderService] cancelReminder error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Process a single reminder type (24h or 1h) for a tenant.
     */
    private function processReminderType(int $tenantId, string $reminderType, int $hoursBeforeEvent): array
    {
        $sent = 0;
        $errors = 0;

        $windowStart = $hoursBeforeEvent * 60 - self::LOOKAHEAD_MINUTES;
        $windowEnd = $hoursBeforeEvent * 60 + self::LOOKAHEAD_MINUTES;

        try {
            $events = DB::select(
                "SELECT e.id, e.title, e.start_time, e.location, e.is_online
                 FROM events e
                 WHERE e.tenant_id = ?
                   AND e.start_time > NOW()
                   AND e.start_time BETWEEN
                       DATE_ADD(NOW(), INTERVAL ? MINUTE)
                       AND DATE_ADD(NOW(), INTERVAL ? MINUTE)",
                [$tenantId, $windowStart, $windowEnd]
            );
        } catch (\Exception $e) {
            error_log("[EventReminderService] Query error: " . $e->getMessage());
            return ['sent' => 0, 'errors' => 1];
        }

        foreach ($events as $event) {
            $eventId = (int) $event->id;

            // Get attendees who haven't been reminded yet
            $attendees = DB::select(
                "SELECT r.user_id, u.name, u.first_name, u.last_name, u.email, r.status
                 FROM event_rsvps r
                 JOIN users u ON r.user_id = u.id AND u.tenant_id = ?
                 LEFT JOIN event_reminder_sent ers
                     ON ers.event_id = r.event_id
                     AND ers.user_id = r.user_id
                     AND ers.reminder_type = ?
                     AND ers.tenant_id = ?
                 WHERE r.event_id = ?
                   AND r.status IN ('going', 'interested')
                   AND ers.id IS NULL",
                [$tenantId, $reminderType, $tenantId, $eventId]
            );

            foreach ($attendees as $attendee) {
                $userId = (int) $attendee->user_id;

                try {
                    $title = htmlspecialchars($event->title, ENT_QUOTES, 'UTF-8');
                    $when = date('l, M j \\a\\t g:i A', strtotime($event->start_time));

                    if ($reminderType === '24h') {
                        $message = __('svc_notifications_2.event.reminder_tomorrow', ['title' => $title, 'when' => $when]);
                    } else {
                        $message = __('svc_notifications_2.event.reminder_1h', ['title' => $title, 'when' => $when]);
                    }

                    $link = "/events/{$eventId}";

                    // Create in-app notification
                    DB::insert(
                        "INSERT INTO notifications (user_id, tenant_id, message, link, type, created_at) VALUES (?, ?, ?, ?, 'event_reminder', NOW())",
                        [$userId, $tenantId, $message, $link]
                    );

                    // Mark reminder as sent
                    DB::statement(
                        "INSERT IGNORE INTO event_reminder_sent (tenant_id, event_id, user_id, reminder_type, sent_at) VALUES (?, ?, ?, ?, NOW())",
                        [$tenantId, $eventId, $userId, $reminderType]
                    );

                    $sent++;
                } catch (\Exception $e) {
                    error_log("[EventReminderService] Failed: event={$eventId}, user={$userId}: " . $e->getMessage());
                    $errors++;
                }
            }
        }

        return ['sent' => $sent, 'errors' => $errors];
    }
}
