<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\Mailer;
use Nexus\Core\EmailTemplate;
use Nexus\Core\TenantContext;
use Nexus\Models\Notification;
use Nexus\Models\User;

/**
 * EventNotificationService
 *
 * Handles in-app (bell) and email notifications for event-related actions:
 * - RSVP status changes (notify organizer)
 * - Event updates/rescheduling (notify attendees)
 */
class EventNotificationService
{
    /**
     * Notify event organizer when someone RSVPs
     */
    public static function notifyRsvp(int $eventId, int $userId, string $status): void
    {
        try {
            if (!in_array($status, ['going', 'interested'])) {
                return; // Only notify for positive RSVPs
            }

            $tenantId = TenantContext::getId();
            $event = Database::query(
                "SELECT id, title, user_id FROM events WHERE id = ? AND tenant_id = ?",
                [$eventId, $tenantId]
            )->fetch();

            if (!$event || (int)$event['user_id'] === $userId) {
                return; // Event not found or user is the organizer
            }

            $user = User::findById($userId);
            if (!$user) return;

            $userName = $user['name'] ?? trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            $eventTitle = $event['title'];
            $organizerId = (int)$event['user_id'];
            $statusLabel = $status === 'going' ? 'is going to' : 'is interested in';

            $basePath = TenantContext::getSlugPrefix();
            $link = $basePath . '/events/' . $eventId;
            $message = "{$userName} {$statusLabel} your event: {$eventTitle}";

            // In-app notification
            Notification::create($organizerId, $message, $link, 'event_rsvp');

            // Email notification
            $organizer = User::findById($organizerId);
            if ($organizer && !empty($organizer['email'])) {
                $tenantName = TenantContext::getSetting('site_name', 'Project NEXUS');
                $frontendUrl = TenantContext::getFrontendUrl();
                $eventUrl = $frontendUrl . $link;

                $emoji = $status === 'going' ? '&#9989;' : '&#11088;';
                $html = EmailTemplate::render(
                    "New RSVP for Your Event",
                    "{$userName} {$statusLabel} your event",
                    "{$emoji} <strong>" . htmlspecialchars($userName) . "</strong> {$statusLabel} your event <strong>" . htmlspecialchars($eventTitle) . "</strong>.<br><br>" .
                    "Check your event page to see all attendees.",
                    "View Event",
                    $eventUrl,
                    $tenantName
                );

                $mailer = new Mailer();
                $mailer->send($organizer['email'], "New RSVP: {$eventTitle} - {$tenantName}", $html);
            }
        } catch (\Throwable $e) {
            error_log("EventNotificationService::notifyRsvp error: " . $e->getMessage());
        }
    }

    /**
     * Notify attendees when an event is updated (time/location changes)
     */
    public static function notifyEventUpdated(int $eventId, array $changes): void
    {
        try {
            // Only notify for meaningful changes
            $meaningfulKeys = ['start_time', 'end_time', 'location', 'title'];
            $meaningfulChanges = array_intersect_key($changes, array_flip($meaningfulKeys));
            if (empty($meaningfulChanges)) {
                return;
            }

            $tenantId = TenantContext::getId();
            $event = Database::query(
                "SELECT id, title, start_time, location, user_id FROM events WHERE id = ? AND tenant_id = ?",
                [$eventId, $tenantId]
            )->fetch();

            if (!$event) return;

            // Get all attendees (going + interested)
            $attendees = Database::query(
                "SELECT DISTINCT user_id FROM event_rsvps WHERE event_id = ? AND tenant_id = ? AND status IN ('going', 'interested')",
                [$eventId, $tenantId]
            )->fetchAll(\PDO::FETCH_COLUMN);

            if (empty($attendees)) return;

            $eventTitle = $event['title'];
            $basePath = TenantContext::getSlugPrefix();
            $link = $basePath . '/events/' . $eventId;
            $organizerId = (int)$event['user_id'];

            // Build change summary
            $changeParts = [];
            if (isset($meaningfulChanges['start_time'])) $changeParts[] = 'date/time';
            if (isset($meaningfulChanges['location'])) $changeParts[] = 'location';
            if (isset($meaningfulChanges['title'])) $changeParts[] = 'title';
            $changeLabel = implode(' and ', $changeParts);

            $message = "The event \"{$eventTitle}\" has been updated ({$changeLabel})";

            foreach ($attendees as $attendeeId) {
                $attendeeId = (int)$attendeeId;
                if ($attendeeId === $organizerId) continue;

                // In-app notification
                Notification::create($attendeeId, $message, $link, 'event_update');
            }

            // Email to attendees
            $tenantName = TenantContext::getSetting('site_name', 'Project NEXUS');
            $frontendUrl = TenantContext::getFrontendUrl();
            $eventUrl = $frontendUrl . $link;

            $bodyParts = [];
            if (isset($meaningfulChanges['start_time'])) {
                $bodyParts[] = "<strong>New date/time:</strong> " . date('M j, Y g:ia', strtotime($meaningfulChanges['start_time']));
            }
            if (isset($meaningfulChanges['location'])) {
                $bodyParts[] = "<strong>New location:</strong> " . htmlspecialchars($meaningfulChanges['location']);
            }
            if (isset($meaningfulChanges['title'])) {
                $bodyParts[] = "<strong>New title:</strong> " . htmlspecialchars($meaningfulChanges['title']);
            }
            $bodyHtml = implode('<br><br>', $bodyParts);

            foreach ($attendees as $attendeeId) {
                $attendeeId = (int)$attendeeId;
                if ($attendeeId === $organizerId) continue;

                $attendee = User::findById($attendeeId);
                if (!$attendee || empty($attendee['email'])) continue;

                $html = EmailTemplate::render(
                    "Event Updated",
                    "An event you're attending has been updated",
                    "The event <strong>" . htmlspecialchars($eventTitle) . "</strong> has been updated:<br><br>" .
                    $bodyHtml . "<br><br>Please check the event page for full details.",
                    "View Event",
                    $eventUrl,
                    $tenantName
                );

                $mailer = new Mailer();
                $mailer->send($attendee['email'], "Event Updated: {$eventTitle} - {$tenantName}", $html);
            }
        } catch (\Throwable $e) {
            error_log("EventNotificationService::notifyEventUpdated error: " . $e->getMessage());
        }
    }
}
