<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\Mailer;
use Nexus\Core\TenantContext;
use Nexus\Models\Notification;

/**
 * EventReminderService - Automated Event Reminders
 *
 * Sends reminder notifications to event attendees (RSVP 'going' or 'interested')
 * at two intervals before the event starts:
 *   - 24 hours before
 *   - 1 hour before
 *
 * Designed to be called from a cron job. The service is idempotent —
 * duplicate reminders are prevented by the `event_reminder_sent` tracking table.
 *
 * Usage (cron):
 *   docker exec nexus-php-app php /var/www/html/scripts/cron-event-reminders.php
 *
 * Recommended schedule: every 15 minutes
 *   *\/15 * * * * docker exec nexus-php-app php /var/www/html/scripts/cron-event-reminders.php
 */
class EventReminderService
{
    /**
     * Reminder intervals in hours before event start_time
     */
    private const REMINDER_INTERVALS = [
        '24h' => 24,
        '1h'  => 1,
    ];

    /**
     * Window in minutes — how far ahead to look for events needing reminders.
     * Should be >= cron frequency to avoid missing events.
     */
    private const LOOKAHEAD_MINUTES = 30;

    /**
     * Send all due event reminders for a specific tenant.
     *
     * Queries upcoming events whose start_time falls within the reminder
     * window, finds attendees who haven't been reminded yet, and sends
     * in-app + push notifications.
     *
     * @return array ['sent' => int, 'errors' => int]
     */
    public static function sendDueReminders(): array
    {
        $tenantId = TenantContext::getId();
        $sent = 0;
        $errors = 0;

        foreach (self::REMINDER_INTERVALS as $type => $hoursBeforeEvent) {
            $result = self::processReminderType($tenantId, $type, $hoursBeforeEvent);
            $sent += $result['sent'];
            $errors += $result['errors'];
        }

        return ['sent' => $sent, 'errors' => $errors];
    }

    /**
     * Process a single reminder type (24h or 1h) for the current tenant.
     *
     * @param int $tenantId
     * @param string $reminderType '24h' or '1h'
     * @param int $hoursBeforeEvent Hours before event start
     * @return array ['sent' => int, 'errors' => int]
     */
    private static function processReminderType(int $tenantId, string $reminderType, int $hoursBeforeEvent): array
    {
        $sent = 0;
        $errors = 0;

        // Find events starting between ($hoursBeforeEvent) and ($hoursBeforeEvent - lookahead) from now.
        // For 24h reminder: events starting between 23.5h and 24h from now.
        // For 1h reminder:  events starting between 0.5h and 1h from now.
        //
        // We use a slightly wider window to account for cron timing drift.
        $windowStart = $hoursBeforeEvent * 60 - self::LOOKAHEAD_MINUTES; // minutes
        $windowEnd   = $hoursBeforeEvent * 60 + self::LOOKAHEAD_MINUTES; // minutes

        $sql = "
            SELECT e.id, e.title, e.start_time, e.location, e.is_online, e.online_url
            FROM events e
            WHERE e.tenant_id = ?
              AND e.start_time > NOW()
              AND e.start_time BETWEEN
                  DATE_ADD(NOW(), INTERVAL ? MINUTE)
                  AND DATE_ADD(NOW(), INTERVAL ? MINUTE)
        ";

        try {
            $events = Database::query($sql, [$tenantId, $windowStart, $windowEnd])
                ->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("[EventReminderService] Query error: " . $e->getMessage());
            return ['sent' => 0, 'errors' => 1];
        }

        foreach ($events as $event) {
            $eventId = (int)$event['id'];

            // Get attendees who RSVP'd 'going' or 'interested' and haven't been reminded
            $attendees = self::getUnremindedAttendees($tenantId, $eventId, $reminderType);

            foreach ($attendees as $attendee) {
                $userId = (int)$attendee['user_id'];

                try {
                    self::sendReminder($userId, $event, $reminderType);
                    self::markReminderSent($tenantId, $eventId, $userId, $reminderType);
                    $sent++;
                } catch (\Exception $e) {
                    error_log("[EventReminderService] Failed to send reminder: event={$eventId}, user={$userId}, type={$reminderType}: " . $e->getMessage());
                    $errors++;
                }
            }
        }

        return ['sent' => $sent, 'errors' => $errors];
    }

    /**
     * Get attendees who have RSVP'd but have NOT yet received this reminder type.
     *
     * @param int $tenantId
     * @param int $eventId
     * @param string $reminderType
     * @return array Array of ['user_id', 'name', 'email', 'status']
     */
    private static function getUnremindedAttendees(int $tenantId, int $eventId, string $reminderType): array
    {
        $sql = "
            SELECT r.user_id, u.name, u.first_name, u.last_name, u.email, r.status
            FROM event_rsvps r
            JOIN users u ON r.user_id = u.id AND u.tenant_id = ?
            LEFT JOIN event_reminder_sent ers
                ON ers.event_id = r.event_id
                AND ers.user_id = r.user_id
                AND ers.reminder_type = ?
                AND ers.tenant_id = ?
            WHERE r.event_id = ?
              AND r.status IN ('going', 'interested')
              AND ers.id IS NULL
        ";

        try {
            return Database::query($sql, [$tenantId, $reminderType, $tenantId, $eventId])
                ->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("[EventReminderService] getUnremindedAttendees error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Send a reminder notification to a user for an event.
     *
     * Creates an in-app notification (which automatically triggers push via
     * Notification::create) and optionally sends an email.
     *
     * @param int $userId
     * @param array $event Event data (id, title, start_time, location, is_online, online_url)
     * @param string $reminderType '24h' or '1h'
     */
    private static function sendReminder(int $userId, array $event, string $reminderType): void
    {
        $eventId = (int)$event['id'];
        $title = htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8');
        $startTime = $event['start_time'];

        // Format a human-readable time
        $when = date('l, M j \a\t g:i A', strtotime($startTime));

        // Build location text
        $locationText = '';
        if (!empty($event['is_online']) && !empty($event['online_url'])) {
            $locationText = ' (Online)';
        } elseif (!empty($event['location'])) {
            $loc = htmlspecialchars($event['location'], ENT_QUOTES, 'UTF-8');
            $locationText = " at {$loc}";
        }

        // Build notification message
        if ($reminderType === '24h') {
            $message = "Reminder: \"{$title}\" is tomorrow — {$when}{$locationText}";
        } else {
            $message = "Starting soon: \"{$title}\" begins in 1 hour — {$when}{$locationText}";
        }

        $link = "/events/{$eventId}";

        // Create in-app notification (also triggers push + FCM via Notification model)
        Notification::create($userId, $message, $link, 'event_reminder');

        // Send email for 24h reminders only (1h is too late for email)
        if ($reminderType === '24h') {
            self::sendReminderEmail($userId, $event, $when, $locationText);
        }
    }

    /**
     * Send a reminder email for a 24h event reminder.
     *
     * @param int $userId
     * @param array $event
     * @param string $when Formatted time string
     * @param string $locationText Location/online text
     */
    private static function sendReminderEmail(int $userId, array $event, string $when, string $locationText): void
    {
        try {
            // Get user email
            $user = Database::query(
                "SELECT email, name, first_name FROM users WHERE id = ? AND tenant_id = ?",
                [$userId, TenantContext::getId()]
            )->fetch(\PDO::FETCH_ASSOC);

            if (!$user || empty($user['email'])) {
                return;
            }

            $eventTitle = htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8');
            $eventId = (int)$event['id'];
            $userName = $user['first_name'] ?? $user['name'] ?? 'there';
            $frontendUrl = TenantContext::getFrontendUrl();
            $slugPrefix = TenantContext::getSlugPrefix();
            $tenantName = TenantContext::getSetting('site_name', 'Project NEXUS');

            $eventUrl = "{$frontendUrl}{$slugPrefix}/events/{$eventId}";

            $builder = new EmailTemplateBuilder($tenantName);
            $builder->setPreviewText("Reminder: {$eventTitle} is tomorrow")
                ->addHero(
                    "Event Reminder",
                    "Don't miss out!",
                    null,
                    'View Event',
                    $eventUrl
                )
                ->addText("Hi {$userName},")
                ->addText("This is a friendly reminder that you RSVP'd for an upcoming event:")
                ->addCard(
                    $eventTitle,
                    "{$when}{$locationText}",
                    $event['cover_image'] ?? null,
                    'View Event Details',
                    $eventUrl
                )
                ->addText("We look forward to seeing you there!")
                ->addButton('View Event', $eventUrl, 'primary');

            $html = $builder->render();
            $subject = "Reminder: {$eventTitle} is tomorrow";

            $mailer = new Mailer();
            $mailer->send($user['email'], $subject, $html);
        } catch (\Exception $e) {
            error_log("[EventReminderService] Email error for user {$userId}: " . $e->getMessage());
        }
    }

    /**
     * Record that a reminder was sent.
     *
     * Uses INSERT IGNORE on the unique key (tenant, event, user, type) for idempotency.
     *
     * @param int $tenantId
     * @param int $eventId
     * @param int $userId
     * @param string $reminderType
     */
    private static function markReminderSent(int $tenantId, int $eventId, int $userId, string $reminderType): void
    {
        try {
            Database::query(
                "INSERT IGNORE INTO event_reminder_sent (tenant_id, event_id, user_id, reminder_type, sent_at)
                 VALUES (?, ?, ?, ?, NOW())",
                [$tenantId, $eventId, $userId, $reminderType]
            );
        } catch (\Exception $e) {
            error_log("[EventReminderService] markReminderSent error: " . $e->getMessage());
        }
    }

    /**
     * Clean up old reminder tracking records (older than 7 days past event).
     *
     * Intentionally cross-tenant: removes expired records for all tenants.
     *
     * @return int Number of records deleted
     */
    public static function cleanupOldRecords(): int
    {
        try {
            $result = Database::query(
                "DELETE ers FROM event_reminder_sent ers
                 JOIN events e ON ers.event_id = e.id
                 WHERE e.start_time < DATE_SUB(NOW(), INTERVAL 7 DAY)"
            );
            return $result->rowCount();
        } catch (\Exception $e) {
            error_log("[EventReminderService] Cleanup error: " . $e->getMessage());
            return 0;
        }
    }
}
