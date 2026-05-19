<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\EmailTemplateBuilder;
use App\Core\Mailer;
use App\Core\TenantContext;
use App\I18n\LocaleContext;
use Illuminate\Support\Facades\DB;

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

    public static function claimReminderDelivery(int $tenantId, int $eventId, int $userId, string $reminderType): bool
    {
        try {
            $inserted = DB::table('event_reminder_delivery_claims')->insertOrIgnore([
                'tenant_id' => $tenantId,
                'event_id' => $eventId,
                'user_id' => $userId,
                'reminder_type' => $reminderType,
                'status' => 'claimed',
                'claimed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return (int) $inserted > 0;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[EventReminderService] reminder delivery claim failed', [
                'tenant_id' => $tenantId,
                'event_id' => $eventId,
                'user_id' => $userId,
                'reminder_type' => $reminderType,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public static function releaseReminderDeliveryClaim(int $tenantId, int $eventId, int $userId, string $reminderType): void
    {
        DB::table('event_reminder_delivery_claims')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('user_id', $userId)
            ->where('reminder_type', $reminderType)
            ->whereNull('delivered_at')
            ->delete();
    }

    public static function markReminderDeliverySent(int $tenantId, int $eventId, int $userId, string $reminderType): bool
    {
        $inserted = DB::table('event_reminder_sent')->insertOrIgnore([
            'tenant_id' => $tenantId,
            'event_id' => $eventId,
            'user_id' => $userId,
            'reminder_type' => $reminderType,
            'sent_at' => now(),
        ]);

        DB::table('event_reminder_delivery_claims')
            ->where('tenant_id', $tenantId)
            ->where('event_id', $eventId)
            ->where('user_id', $userId)
            ->where('reminder_type', $reminderType)
            ->update([
                'status' => 'delivered',
                'delivered_at' => now(),
                'updated_at' => now(),
            ]);

        return (int) $inserted > 0;
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
            \Illuminate\Support\Facades\Log::warning("[EventReminderService] scheduleReminder error: " . $e->getMessage());
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
        return (int) TenantContext::runForTenant($tenantId, function () use ($tenantId): int {
            $sent = 0;

            foreach (self::REMINDER_INTERVALS as $type => $hoursBeforeEvent) {
                $result = $this->processReminderType($tenantId, $type, $hoursBeforeEvent);
                $sent += $result['sent'];
            }

            $configured = $this->processConfiguredReminders($tenantId);
            $sent += $configured['sent'];

            return $sent;
        });
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
            \Illuminate\Support\Facades\Log::warning("[EventReminderService] cancelReminder error: " . $e->getMessage());
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
            \Illuminate\Support\Facades\Log::warning("[EventReminderService] Query error: " . $e->getMessage());
            return ['sent' => 0, 'errors' => 1];
        }

        foreach ($events as $event) {
            $eventId = (int) $event->id;

            // Get attendees who haven't been reminded yet
            $attendees = DB::select(
                "SELECT r.user_id, u.name, u.first_name, u.last_name, u.email, u.preferred_language, r.status
                 FROM event_rsvps r
                 JOIN users u ON r.user_id = u.id AND u.tenant_id = ?
                 LEFT JOIN event_reminder_sent ers
                     ON ers.event_id = r.event_id
                     AND ers.user_id = r.user_id
                     AND ers.reminder_type = ?
                     AND ers.tenant_id = ?
                 WHERE r.event_id = ?
                   AND r.status IN ('going', 'interested')
                   AND NOT EXISTS (
                       SELECT 1
                       FROM event_reminders er
                       WHERE er.event_id = r.event_id
                         AND er.user_id = r.user_id
                         AND er.tenant_id = ?
                         AND er.status = 'pending'
                         AND er.remind_before_minutes = ?
                   )
                   AND ers.id IS NULL",
                [$tenantId, $reminderType, $tenantId, $eventId, $tenantId, $hoursBeforeEvent * 60]
            );

            foreach ($attendees as $attendee) {
                $userId = (int) $attendee->user_id;

                try {
                    if (!self::claimReminderDelivery($tenantId, $eventId, $userId, $reminderType)) {
                        continue;
                    }

                    $emailAccepted = false;
                    // Render notification + email in the RECIPIENT's language — cron
                    // workers default to config('app.locale') = 'en' otherwise.
                    LocaleContext::withLocale($attendee, function () use ($tenantId, $eventId, $reminderType, $event, $attendee, $userId, &$sent, &$emailAccepted) {
                        $title = htmlspecialchars($event->title, ENT_QUOTES, 'UTF-8');
                        $when = date('l, M j \\a\\t g:i A', strtotime($event->start_time));

                        if ($reminderType === '24h') {
                            $message = __('svc_notifications_2.event.reminder_tomorrow', ['title' => $title, 'when' => $when]);
                        } else {
                            $message = __('svc_notifications_2.event.reminder_1h', ['title' => $title, 'when' => $when]);
                        }

                        $link = "/events/{$eventId}";

                        // Email notification
                        $emailOk = true;
                        if (!empty($attendee->email)) {
                            try {
                                $subjectKey = $reminderType === '24h'
                                    ? 'notifications.event_reminder_subject_24h'
                                    : 'notifications.event_reminder_subject_1h';
                                $subject  = __($subjectKey, ['title' => $title]);
                                $eventUrl = TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix() . $link;
                                $name     = $attendee->first_name ?? $attendee->name ?? __('emails.common.fallback_name');

                                $html = EmailTemplateBuilder::make()
                                    ->title(__('emails_misc.events.reminder_email_title'))
                                    ->previewText($message)
                                    ->greeting($name)
                                    ->paragraph($message)
                                    ->button(__('emails_misc.events.reminder_email_cta'), $eventUrl)
                                    ->render();

                                $emailOk = \App\Services\EmailDispatchService::sendRaw(
                                    $attendee->email,
                                    $subject,
                                    $html,
                                    null,
                                    null,
                                    null,
                                    'event_reminder',
                                    ['tenant_id' => $tenantId]
                                );
                                if (!$emailOk) {
                                    \Illuminate\Support\Facades\Log::warning("[EventReminderService] Mailer returned false: event={$eventId}, user={$userId}");
                                }
                            } catch (\Exception $emailEx) {
                                \Illuminate\Support\Facades\Log::warning("[EventReminderService] Email failed: event={$eventId}, user={$userId}: " . $emailEx->getMessage());
                                $emailOk = false;
                            }
                        }

                        if (!$emailOk) {
                            self::releaseReminderDeliveryClaim($tenantId, $eventId, $userId, $reminderType);
                            return;
                        }

                        $emailAccepted = true;
                        $markedSent = self::markReminderDeliverySent($tenantId, $eventId, $userId, $reminderType);

                        // Create in-app notification after email acceptance so
                        // retrying a transient mail failure cannot duplicate bells.
                        DB::insert(
                            "INSERT INTO notifications (user_id, tenant_id, message, link, type, created_at) VALUES (?, ?, ?, ?, 'event_reminder', NOW())",
                            [$userId, $tenantId, $message, $link]
                        );

                        if ($markedSent) {
                            $sent++;
                        }
                    });
                } catch (\Exception $e) {
                    if (empty($emailAccepted)) {
                        self::releaseReminderDeliveryClaim($tenantId, $eventId, $userId, $reminderType);
                    }
                    \Illuminate\Support\Facades\Log::warning("[EventReminderService] Failed: event={$eventId}, user={$userId}: " . $e->getMessage());
                    $errors++;
                }
            }
        }

        return ['sent' => $sent, 'errors' => $errors];
    }

    /**
     * Process user-configured reminders from event_reminders.
     *
     * The React/Laravel event settings allow 60, 1440, and 10080 minute
     * reminders with platform/email/both delivery. These rows are the user's
     * explicit schedule, so they are handled separately from the legacy fixed
     * 24h/1h fallback scan above.
     */
    private function processConfiguredReminders(int $tenantId): array
    {
        $sent = 0;
        $errors = 0;

        try {
            $reminders = DB::select(
                "SELECT er.id AS reminder_id, er.remind_before_minutes, er.reminder_type AS delivery_type,
                        e.id AS event_id, e.title, e.start_time, e.location, e.is_online,
                        u.id AS user_id, u.name, u.first_name, u.last_name, u.email, u.preferred_language
                 FROM event_reminders er
                 JOIN events e ON e.id = er.event_id AND e.tenant_id = er.tenant_id
                 JOIN users u ON u.id = er.user_id AND u.tenant_id = er.tenant_id
                 WHERE er.tenant_id = ?
                   AND er.status = 'pending'
                   AND er.scheduled_for <= NOW()
                   AND e.start_time > NOW()
                 ORDER BY er.scheduled_for ASC
                 LIMIT 200",
                [$tenantId]
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('[EventReminderService] configured reminder query error: ' . $e->getMessage());
            return ['sent' => 0, 'errors' => 1];
        }

        foreach ($reminders as $reminder) {
            $eventId = (int) $reminder->event_id;
            $userId = (int) $reminder->user_id;
            $deliveryType = (string) $reminder->delivery_type;
            $reminderType = $this->reminderTypeForMinutes((int) $reminder->remind_before_minutes);
            $emailAccepted = false;

            try {
                if (!self::claimReminderDelivery($tenantId, $eventId, $userId, $reminderType)) {
                    continue;
                }

                LocaleContext::withLocale($reminder, function () use ($tenantId, $eventId, $userId, $reminder, $deliveryType, $reminderType, &$sent, &$emailAccepted): void {
                    $title = htmlspecialchars($reminder->title, ENT_QUOTES, 'UTF-8');
                    $when = date('l, M j \\a\\t g:i A', strtotime($reminder->start_time));
                    $message = match ((int) $reminder->remind_before_minutes) {
                        10080 => __('svc_notifications_2.event.reminder_7d', ['title' => $title, 'when' => $when]),
                        1440 => __('svc_notifications_2.event.reminder_tomorrow', ['title' => $title, 'when' => $when]),
                        default => __('svc_notifications_2.event.reminder_1h', ['title' => $title, 'when' => $when]),
                    };
                    $link = "/events/{$eventId}";

                    $needsEmail = in_array($deliveryType, ['email', 'both'], true);
                    $needsPlatform = in_array($deliveryType, ['platform', 'both'], true);
                    $emailOk = true;

                    if ($needsEmail && !empty($reminder->email)) {
                        $subjectKey = match ((int) $reminder->remind_before_minutes) {
                            10080 => 'notifications.event_reminder_subject_7d',
                            1440 => 'notifications.event_reminder_subject_24h',
                            default => 'notifications.event_reminder_subject_1h',
                        };
                        $subject = __($subjectKey, ['title' => $title]);
                        $eventUrl = TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix() . $link;
                        $name = $reminder->first_name ?? $reminder->name ?? __('emails.common.fallback_name');

                        $html = EmailTemplateBuilder::make()
                            ->title(__('emails_misc.events.reminder_email_title'))
                            ->previewText($message)
                            ->greeting($name)
                            ->paragraph($message)
                            ->button(__('emails_misc.events.reminder_email_cta'), $eventUrl)
                            ->render();

                        $emailOk = \App\Services\EmailDispatchService::sendRaw(
                            $reminder->email,
                            $subject,
                            $html,
                            null,
                            null,
                            null,
                            'event_reminder',
                            ['tenant_id' => $tenantId]
                        );
                    }

                    if (!$emailOk) {
                        self::releaseReminderDeliveryClaim($tenantId, $eventId, $userId, $reminderType);
                        return;
                    }

                    $emailAccepted = true;
                    $markedSent = self::markReminderDeliverySent($tenantId, $eventId, $userId, $reminderType);

                    if ($needsPlatform) {
                        DB::insert(
                            "INSERT INTO notifications (user_id, tenant_id, message, link, type, created_at) VALUES (?, ?, ?, ?, 'event_reminder', NOW())",
                            [$userId, $tenantId, $message, $link]
                        );
                    }

                    if ($markedSent) {
                        $sent++;
                    }
                });

                if ($emailAccepted) {
                    DB::table('event_reminders')
                        ->where('id', $reminder->reminder_id)
                        ->where('tenant_id', $tenantId)
                        ->update(['status' => 'sent', 'sent_at' => now(), 'updated_at' => now()]);
                }
            } catch (\Throwable $e) {
                if (!$emailAccepted) {
                    self::releaseReminderDeliveryClaim($tenantId, $eventId, $userId, $reminderType);
                    DB::table('event_reminders')
                        ->where('id', $reminder->reminder_id)
                        ->where('tenant_id', $tenantId)
                        ->update(['status' => 'failed', 'updated_at' => now()]);
                }

                \Illuminate\Support\Facades\Log::warning('[EventReminderService] configured reminder failed', [
                    'tenant_id' => $tenantId,
                    'event_id' => $eventId,
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
                $errors++;
            }
        }

        return ['sent' => $sent, 'errors' => $errors];
    }

    private function reminderTypeForMinutes(int $minutes): string
    {
        return match ($minutes) {
            10080 => '7d',
            1440 => '24h',
            default => '1h',
        };
    }
}
