<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;

/**
 * EventNotificationService — Laravel DI service for event notifications.
 *
 * Handles in-app (bell) and email notifications for event-related actions:
 * - RSVP status changes (notify organizer)
 * - Event updates/rescheduling (notify attendees)
 * - Event cancellation (notify attendees)
 * - Reminder dispatch
 *
 * Note: notifyAttendees, sendReminder, and notifyCancellation delegate to legacy
 * because they depend on Mailer, EmailTemplate, and Notification model internals.
 */
class EventNotificationService
{
    public function __construct()
    {
    }

    /**
     * Notify event attendees with a message.
     * // TODO: Convert to Eloquent (depends on Mailer, EmailTemplate, Notification model)
     */
    public function notifyAttendees(int $tenantId, int $eventId, string $message): int
    {
        return \Nexus\Services\EventNotificationService::notifyAttendees($tenantId, $eventId, $message);
    }

    /**
     * Send event reminder to attendees.
     * // TODO: Convert to Eloquent (depends on Mailer, EmailTemplate, Notification model)
     */
    public function sendReminder(int $tenantId, int $eventId): int
    {
        return \Nexus\Services\EventNotificationService::sendReminder($tenantId, $eventId);
    }

    /**
     * Notify attendees of event cancellation.
     * // TODO: Convert to Eloquent (depends on Mailer, EmailTemplate, Notification model)
     */
    public function notifyCancellation(int $tenantId, int $eventId, ?string $reason = null): int
    {
        return \Nexus\Services\EventNotificationService::notifyCancellation($tenantId, $eventId, $reason);
    }

    /**
     * Notify attendees that an event has been updated.
     *
     * Only notifies for meaningful changes (start_time, end_time, location, title).
     * Creates in-app notifications for each attendee.
     */
    public function notifyEventUpdated(int $eventId, array $changes): void
    {
        try {
            $meaningfulKeys = ['start_time', 'end_time', 'location', 'title'];
            $meaningfulChanges = array_intersect_key($changes, array_flip($meaningfulKeys));
            if (empty($meaningfulChanges)) {
                return;
            }

            $tenantId = TenantContext::getId();
            $event = DB::selectOne(
                "SELECT id, title, start_time, location, user_id FROM events WHERE id = ? AND tenant_id = ?",
                [$eventId, $tenantId]
            );

            if (!$event) {
                return;
            }

            // Get all attendees (going + interested)
            $attendeeRows = DB::select(
                "SELECT DISTINCT user_id FROM event_rsvps WHERE event_id = ? AND tenant_id = ? AND status IN ('going', 'interested')",
                [$eventId, $tenantId]
            );
            $attendees = array_map(fn($r) => (int) $r->user_id, $attendeeRows);

            if (empty($attendees)) {
                return;
            }

            $eventTitle = $event->title;
            $path = '/events/' . $eventId;
            $organizerId = (int) $event->user_id;

            // Build change summary
            $changeParts = [];
            if (isset($meaningfulChanges['start_time'])) {
                $changeParts[] = 'date/time';
            }
            if (isset($meaningfulChanges['location'])) {
                $changeParts[] = 'location';
            }
            if (isset($meaningfulChanges['title'])) {
                $changeParts[] = 'title';
            }
            $changeLabel = implode(' and ', $changeParts);

            $message = "The event \"{$eventTitle}\" has been updated ({$changeLabel})";

            foreach ($attendees as $attendeeId) {
                if ($attendeeId === $organizerId) {
                    continue;
                }

                DB::insert(
                    "INSERT INTO notifications (user_id, tenant_id, message, link, type, created_at) VALUES (?, ?, ?, ?, 'event_update', NOW())",
                    [$attendeeId, $tenantId, $message, $path]
                );
            }
        } catch (\Throwable $e) {
            error_log("EventNotificationService::notifyEventUpdated error: " . $e->getMessage());
        }
    }

    /**
     * Notify event organizer when someone RSVPs.
     */
    public function notifyRsvp(int $eventId, int $userId, string $status): void
    {
        try {
            if (!in_array($status, ['going', 'interested'])) {
                return;
            }

            $tenantId = TenantContext::getId();
            $event = DB::selectOne(
                "SELECT id, title, user_id FROM events WHERE id = ? AND tenant_id = ?",
                [$eventId, $tenantId]
            );

            if (!$event || (int) $event->user_id === $userId) {
                return;
            }

            $user = DB::selectOne(
                "SELECT id, name, first_name, last_name FROM users WHERE id = ? AND tenant_id = ?",
                [$userId, $tenantId]
            );
            if (!$user) {
                return;
            }

            $userName = $user->name ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            $eventTitle = $event->title;
            $organizerId = (int) $event->user_id;
            $statusLabel = $status === 'going' ? 'is going to' : 'is interested in';

            $path = '/events/' . $eventId;
            $message = "{$userName} {$statusLabel} your event: {$eventTitle}";

            DB::insert(
                "INSERT INTO notifications (user_id, tenant_id, message, link, type, created_at) VALUES (?, ?, ?, ?, 'event_rsvp', NOW())",
                [$organizerId, $tenantId, $message, $path]
            );
        } catch (\Throwable $e) {
            error_log("EventNotificationService::notifyRsvp error: " . $e->getMessage());
        }
    }
}
