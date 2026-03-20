<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * EventNotificationService — handles in-app and email notifications for event actions.
 *
 * - RSVP status changes (notify organizer)
 * - Event updates/rescheduling (notify attendees)
 * - Event cancellation (notify attendees + waitlisted)
 * - Reminder dispatch (24h and 1h before event)
 * - Generic attendee message broadcast
 */
class EventNotificationService
{
    public function __construct()
    {
    }

    /**
     * Notify event attendees with a custom message.
     *
     * Creates in-app notifications for all attendees (going + interested)
     * excluding the organizer.
     *
     * @return int Number of notifications sent
     */
    public function notifyAttendees(int $tenantId, int $eventId, string $message): int
    {
        try {
            $event = DB::table('events')
                ->where('id', $eventId)
                ->where('tenant_id', $tenantId)
                ->select(['id', 'title', 'user_id'])
                ->first();

            if (!$event) {
                return 0;
            }

            $organizerId = (int) $event->user_id;

            $attendeeIds = DB::table('event_rsvps')
                ->where('event_id', $eventId)
                ->where('tenant_id', $tenantId)
                ->whereIn('status', ['going', 'interested'])
                ->distinct()
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (empty($attendeeIds)) {
                return 0;
            }

            $path = '/events/' . $eventId;
            $count = 0;

            foreach ($attendeeIds as $attendeeId) {
                if ($attendeeId === $organizerId) {
                    continue;
                }

                Notification::create([
                    'user_id' => $attendeeId,
                    'tenant_id' => $tenantId,
                    'message' => $message,
                    'link' => $path,
                    'type' => 'event_update',
                    'created_at' => now(),
                ]);
                $count++;
            }

            return $count;
        } catch (\Throwable $e) {
            Log::error("EventNotificationService::notifyAttendees error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Send event reminder to attendees who haven't been reminded yet.
     *
     * Processes both 24h and 1h reminder types for a specific event.
     * Uses the event_reminder_sent table for idempotency.
     *
     * @return int Number of reminders sent
     */
    public function sendReminder(int $tenantId, int $eventId): int
    {
        try {
            $event = DB::table('events')
                ->where('id', $eventId)
                ->where('tenant_id', $tenantId)
                ->select(['id', 'title', 'start_time', 'location', 'is_online', 'online_url'])
                ->first();

            if (!$event) {
                return 0;
            }

            $sent = 0;
            $reminderTypes = ['24h' => 24, '1h' => 1];

            foreach ($reminderTypes as $type => $hoursBeforeEvent) {
                // Get attendees who haven't been reminded for this type
                $attendees = DB::table('event_rsvps as r')
                    ->join('users as u', function ($join) use ($tenantId) {
                        $join->on('r.user_id', '=', 'u.id')
                            ->where('u.tenant_id', '=', $tenantId);
                    })
                    ->leftJoin('event_reminder_sent as ers', function ($join) use ($type, $tenantId) {
                        $join->on('ers.event_id', '=', 'r.event_id')
                            ->on('ers.user_id', '=', 'r.user_id')
                            ->where('ers.reminder_type', '=', $type)
                            ->where('ers.tenant_id', '=', $tenantId);
                    })
                    ->where('r.event_id', $eventId)
                    ->whereIn('r.status', ['going', 'interested'])
                    ->whereNull('ers.id')
                    ->select(['r.user_id', 'u.name', 'u.first_name', 'u.last_name', 'u.email'])
                    ->get();

                foreach ($attendees as $attendee) {
                    $userId = (int) $attendee->user_id;

                    try {
                        $this->createReminderNotification($userId, $event, $type);
                        $this->markReminderSent($tenantId, $eventId, $userId, $type);
                        $sent++;
                    } catch (\Throwable $e) {
                        Log::error("[EventNotificationService] Reminder failed: event={$eventId}, user={$userId}, type={$type}: " . $e->getMessage());
                    }
                }
            }

            return $sent;
        } catch (\Throwable $e) {
            Log::error("EventNotificationService::sendReminder error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Notify attendees and waitlisted users of event cancellation.
     *
     * @return int Number of notifications sent
     */
    public function notifyCancellation(int $tenantId, int $eventId, ?string $reason = null): int
    {
        try {
            $event = DB::table('events')
                ->where('id', $eventId)
                ->where('tenant_id', $tenantId)
                ->select(['id', 'title'])
                ->first();

            if (!$event) {
                return 0;
            }

            // Get all RSVP users (going, interested, invited)
            $rsvpUserIds = DB::table('event_rsvps')
                ->where('event_id', $eventId)
                ->where('tenant_id', $tenantId)
                ->whereIn('status', ['going', 'interested', 'invited'])
                ->distinct()
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            // Get waitlisted users
            $waitlistedUserIds = DB::table('event_waitlist')
                ->where('event_id', $eventId)
                ->where('tenant_id', $tenantId)
                ->where('status', 'waiting')
                ->distinct()
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $allUserIds = array_unique(array_merge($rsvpUserIds, $waitlistedUserIds));

            if (empty($allUserIds)) {
                return 0;
            }

            $message = "The event \"{$event->title}\" has been cancelled.";
            if (!empty($reason)) {
                $message .= " Reason: {$reason}";
            }

            $path = '/events/' . $eventId;
            $count = 0;

            foreach ($allUserIds as $uid) {
                try {
                    Notification::create([
                        'user_id' => $uid,
                        'tenant_id' => $tenantId,
                        'message' => $message,
                        'link' => $path,
                        'type' => 'event',
                        'created_at' => now(),
                    ]);
                    $count++;
                } catch (\Throwable $e) {
                    Log::warning("Failed to notify user {$uid} of event cancellation: " . $e->getMessage());
                }
            }

            return $count;
        } catch (\Throwable $e) {
            Log::error("EventNotificationService::notifyCancellation error: " . $e->getMessage());
            return 0;
        }
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

            $event = DB::table('events')
                ->where('id', $eventId)
                ->where('tenant_id', $tenantId)
                ->select(['id', 'title', 'start_time', 'location', 'user_id'])
                ->first();

            if (!$event) {
                return;
            }

            // Get all attendees (going + interested)
            $attendeeIds = DB::table('event_rsvps')
                ->where('event_id', $eventId)
                ->where('tenant_id', $tenantId)
                ->whereIn('status', ['going', 'interested'])
                ->distinct()
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (empty($attendeeIds)) {
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

            foreach ($attendeeIds as $attendeeId) {
                if ($attendeeId === $organizerId) {
                    continue;
                }

                Notification::create([
                    'user_id' => $attendeeId,
                    'tenant_id' => $tenantId,
                    'message' => $message,
                    'link' => $path,
                    'type' => 'event_update',
                    'created_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("EventNotificationService::notifyEventUpdated error: " . $e->getMessage());
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

            $event = DB::table('events')
                ->where('id', $eventId)
                ->where('tenant_id', $tenantId)
                ->select(['id', 'title', 'user_id'])
                ->first();

            if (!$event || (int) $event->user_id === $userId) {
                return;
            }

            $user = DB::table('users')
                ->where('id', $userId)
                ->where('tenant_id', $tenantId)
                ->select(['id', 'name', 'first_name', 'last_name'])
                ->first();

            if (!$user) {
                return;
            }

            $userName = $user->name ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            $eventTitle = $event->title;
            $organizerId = (int) $event->user_id;
            $statusLabel = $status === 'going' ? 'is going to' : 'is interested in';

            $path = '/events/' . $eventId;
            $message = "{$userName} {$statusLabel} your event: {$eventTitle}";

            Notification::create([
                'user_id' => $organizerId,
                'tenant_id' => $tenantId,
                'message' => $message,
                'link' => $path,
                'type' => 'event_rsvp',
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error("EventNotificationService::notifyRsvp error: " . $e->getMessage());
        }
    }

    /**
     * Create an in-app reminder notification for an event attendee.
     */
    private function createReminderNotification(int $userId, object $event, string $reminderType): void
    {
        $title = htmlspecialchars($event->title, ENT_QUOTES, 'UTF-8');
        $when = date('l, M j \a\t g:i A', strtotime($event->start_time));

        $locationText = '';
        if (!empty($event->is_online) && !empty($event->online_url)) {
            $locationText = ' (Online)';
        } elseif (!empty($event->location)) {
            $loc = htmlspecialchars($event->location, ENT_QUOTES, 'UTF-8');
            $locationText = " at {$loc}";
        }

        if ($reminderType === '24h') {
            $message = "Reminder: \"{$title}\" is tomorrow — {$when}{$locationText}";
        } else {
            $message = "Starting soon: \"{$title}\" begins in 1 hour — {$when}{$locationText}";
        }

        $link = "/events/{$event->id}";

        Notification::create([
            'user_id' => $userId,
            'tenant_id' => TenantContext::getId(),
            'message' => $message,
            'link' => $link,
            'type' => 'event_reminder',
            'created_at' => now(),
        ]);
    }

    /**
     * Record that a reminder was sent for idempotency.
     */
    private function markReminderSent(int $tenantId, int $eventId, int $userId, string $reminderType): void
    {
        try {
            DB::statement(
                "INSERT IGNORE INTO event_reminder_sent (tenant_id, event_id, user_id, reminder_type, sent_at) VALUES (?, ?, ?, ?, NOW())",
                [$tenantId, $eventId, $userId, $reminderType]
            );
        } catch (\Throwable $e) {
            Log::error("[EventNotificationService] markReminderSent error: " . $e->getMessage());
        }
    }
}
