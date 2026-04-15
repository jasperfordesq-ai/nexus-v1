<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\Mailer;
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
 *
 * Each notification method creates an in-app bell notification AND queues/sends
 * an email through the tenant mailer, respecting the user's notification frequency
 * preferences where applicable.
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

            $attendees = DB::table('event_rsvps as r')
                ->join('users as u', function ($join) use ($tenantId) {
                    $join->on('r.user_id', '=', 'u.id')
                        ->where('u.tenant_id', '=', $tenantId);
                })
                ->where('r.event_id', $eventId)
                ->where('r.tenant_id', $tenantId)
                ->whereIn('r.status', ['going', 'interested'])
                ->select(['r.user_id', 'u.email', 'u.name', 'u.first_name'])
                ->distinct()
                ->get();

            if ($attendees->isEmpty()) {
                return 0;
            }

            $path = '/events/' . $eventId;
            $count = 0;
            $eventTitle = htmlspecialchars($event->title, ENT_QUOTES, 'UTF-8');

            foreach ($attendees as $attendee) {
                $attendeeId = (int) $attendee->user_id;
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

                // Send email notification
                try {
                    $this->sendEventEmail(
                        $attendee,
                        __('emails.events.update_subject', ['title' => $eventTitle]),
                        $message,
                        $path,
                        'event_update'
                    );
                } catch (\Throwable $e) {
                    Log::warning("[EventNotificationService] Email failed for user {$attendeeId}: " . $e->getMessage());
                }

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
                        $message = $this->createReminderNotification($userId, $event, $type);
                        $this->markReminderSent($tenantId, $eventId, $userId, $type);

                        // Send reminder email
                        try {
                            $eventTitle = htmlspecialchars($event->title, ENT_QUOTES, 'UTF-8');
                            $subject = $type === '24h'
                                ? __('notifications.event_reminder_subject_24h', ['title' => $eventTitle])
                                : __('notifications.event_reminder_subject_1h', ['title' => $eventTitle]);

                            $this->sendEventEmail(
                                $attendee,
                                $subject,
                                $message,
                                '/events/' . $eventId,
                                'event_reminder',
                                $this->buildReminderEmailHtml($event, $type, $attendee)
                            );
                        } catch (\Throwable $e) {
                            Log::warning("[EventNotificationService] Reminder email failed for user {$userId}: " . $e->getMessage());
                        }

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
                ->select(['id', 'title', 'start_time', 'location'])
                ->first();

            if (!$event) {
                return 0;
            }

            // Get all RSVP users (going, interested, invited) with email info
            $rsvpUsers = DB::table('event_rsvps as r')
                ->join('users as u', function ($join) use ($tenantId) {
                    $join->on('r.user_id', '=', 'u.id')
                        ->where('u.tenant_id', '=', $tenantId);
                })
                ->where('r.event_id', $eventId)
                ->where('r.tenant_id', $tenantId)
                ->whereIn('r.status', ['going', 'interested', 'invited'])
                ->select(['r.user_id', 'u.email', 'u.name', 'u.first_name'])
                ->distinct()
                ->get()
                ->keyBy('user_id');

            // Get waitlisted users with email info
            $waitlistedUsers = DB::table('event_waitlist as w')
                ->join('users as u', function ($join) use ($tenantId) {
                    $join->on('w.user_id', '=', 'u.id')
                        ->where('u.tenant_id', '=', $tenantId);
                })
                ->where('w.event_id', $eventId)
                ->where('w.tenant_id', $tenantId)
                ->where('w.status', 'waiting')
                ->select(['w.user_id', 'u.email', 'u.name', 'u.first_name'])
                ->distinct()
                ->get()
                ->keyBy('user_id');

            // Merge all users (RSVP + waitlisted), deduplicated by user_id
            $allUsers = $rsvpUsers->union($waitlistedUsers);

            if ($allUsers->isEmpty()) {
                return 0;
            }

            $eventTitle = htmlspecialchars($event->title, ENT_QUOTES, 'UTF-8');
            $message = __('notifications.event_cancelled', ['title' => $event->title]);
            if (!empty($reason)) {
                $message .= ' ' . __('notifications.event_cancelled_reason', ['reason' => $reason]);
            }

            $path = '/events/' . $eventId;
            $count = 0;

            foreach ($allUsers as $uid => $user) {
                try {
                    Notification::create([
                        'user_id' => (int) $uid,
                        'tenant_id' => $tenantId,
                        'message' => $message,
                        'link' => $path,
                        'type' => 'event',
                        'created_at' => now(),
                    ]);

                    // Send cancellation email
                    try {
                        $this->sendEventEmail(
                            $user,
                            __('emails.events.cancelled_subject', ['title' => $eventTitle]),
                            $message,
                            $path,
                            'event_cancellation',
                            $this->buildCancellationEmailHtml($event, $reason, $user)
                        );
                    } catch (\Throwable $e) {
                        Log::warning("[EventNotificationService] Cancellation email failed for user {$uid}: " . $e->getMessage());
                    }

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

            // Get all attendees (going + interested) with email info
            $attendees = DB::table('event_rsvps as r')
                ->join('users as u', function ($join) use ($tenantId) {
                    $join->on('r.user_id', '=', 'u.id')
                        ->where('u.tenant_id', '=', $tenantId);
                })
                ->where('r.event_id', $eventId)
                ->where('r.tenant_id', $tenantId)
                ->whereIn('r.status', ['going', 'interested'])
                ->select(['r.user_id', 'u.email', 'u.name', 'u.first_name'])
                ->distinct()
                ->get();

            if ($attendees->isEmpty()) {
                return;
            }

            $eventTitle = $event->title;
            $path = '/events/' . $eventId;
            $organizerId = (int) $event->user_id;

            // Build change summary
            $changeParts = [];
            if (isset($meaningfulChanges['start_time'])) {
                $changeParts[] = __('notifications.event_change_date_time');
            }
            if (isset($meaningfulChanges['location'])) {
                $changeParts[] = __('notifications.event_change_location');
            }
            if (isset($meaningfulChanges['title'])) {
                $changeParts[] = __('notifications.event_change_title');
            }
            $changeLabel = implode(' and ', $changeParts);
            $message = __('notifications.event_updated', ['title' => $eventTitle, 'changes' => $changeLabel]);

            $safeTitle = htmlspecialchars($eventTitle, ENT_QUOTES, 'UTF-8');

            foreach ($attendees as $attendee) {
                $attendeeId = (int) $attendee->user_id;
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

                // Send update email
                try {
                    $this->sendEventEmail(
                        $attendee,
                        __('emails.events.updated_subject', ['title' => $safeTitle, 'changes' => $changeLabel]),
                        $message,
                        $path,
                        'event_update',
                        $this->buildUpdateEmailHtml($event, $meaningfulChanges, $attendee)
                    );
                } catch (\Throwable $e) {
                    Log::warning("[EventNotificationService] Update email failed for user {$attendeeId}: " . $e->getMessage());
                }
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
                ->select(['id', 'title', 'user_id', 'start_time', 'location'])
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

            $statusLabel = $status === 'going'
                ? __('emails.events.rsvp_status_going')
                : __('emails.events.rsvp_status_interested');

            $path = '/events/' . $eventId;
            $message = $status === 'going'
                ? __('notifications.event_rsvp_going', ['name' => $userName, 'title' => $eventTitle])
                : __('notifications.event_rsvp_interested', ['name' => $userName, 'title' => $eventTitle]);

            Notification::create([
                'user_id' => $organizerId,
                'tenant_id' => $tenantId,
                'message' => $message,
                'link' => $path,
                'type' => 'event_rsvp',
                'created_at' => now(),
            ]);

            // Send email to organizer about the RSVP
            try {
                $organizer = DB::table('users')
                    ->where('id', $organizerId)
                    ->where('tenant_id', $tenantId)
                    ->select(['id as user_id', 'email', 'name', 'first_name'])
                    ->first();

                if ($organizer) {
                    $safeTitle = htmlspecialchars($eventTitle, ENT_QUOTES, 'UTF-8');
                    $safeUserName = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');

                    $this->sendEventEmail(
                        $organizer,
                        __('emails.events.rsvp_subject', ['name' => $safeUserName, 'status_label' => $statusLabel, 'title' => $safeTitle]),
                        $message,
                        $path,
                        'event_rsvp',
                        $this->buildRsvpEmailHtml($event, $user, $status, $organizer)
                    );
                }
            } catch (\Throwable $e) {
                Log::warning("[EventNotificationService] RSVP email failed for organizer {$organizerId}: " . $e->getMessage());
            }
        } catch (\Throwable $e) {
            Log::error("EventNotificationService::notifyRsvp error: " . $e->getMessage());
        }
    }

    // =========================================================================
    // EMAIL SENDING
    // =========================================================================

    /**
     * Send an event-related email to a user.
     *
     * Respects the user's notification frequency setting. If the user has email
     * notifications set to 'off', no email is sent. For 'instant', the email is
     * sent immediately. For 'daily'/'weekly', it is queued.
     *
     * @param object $user     User object with email, name, first_name, user_id
     * @param string $subject  Email subject line
     * @param string $content  Plain text content (fallback)
     * @param string $link     Relative link to the event
     * @param string $type     Notification type for queue classification
     * @param string|null $htmlBody  Rich HTML email body (optional)
     */
    private function sendEventEmail(object $user, string $subject, string $content, string $link, string $type, ?string $htmlBody = null): void
    {
        if (empty($user->email)) {
            return;
        }

        $userId = (int) ($user->user_id ?? 0);
        if ($userId <= 0) {
            return;
        }

        // Respect user's notification frequency preference
        $frequency = $this->getUserEventEmailFrequency($userId);

        if ($frequency === 'off') {
            return;
        }

        if ($frequency === 'instant') {
            // Send immediately
            try {
                $mailer = Mailer::forCurrentTenant();
                $body = $htmlBody ?? $this->buildDefaultEventEmailHtml($subject, $content, $link, $user);
                $mailer->send($user->email, $subject, $body);
            } catch (\Throwable $e) {
                Log::warning("[EventNotificationService] Instant email send failed: " . $e->getMessage());
            }

            // Also send web push
            try {
                WebPushService::sendToUserStatic($userId, $subject, $content, $link);
            } catch (\Throwable $e) {
                Log::debug("[EventNotificationService] WebPush failed: " . $e->getMessage());
            }
        } else {
            // Queue for daily/weekly digest
            try {
                DB::table('notification_queue')->insert([
                    'user_id'         => $userId,
                    'activity_type'   => $type,
                    'content_snippet' => substr($content, 0, 250),
                    'link'            => $link,
                    'frequency'       => $frequency,
                    'email_body'      => $htmlBody,
                    'created_at'      => now(),
                    'status'          => 'pending',
                ]);
            } catch (\Throwable $e) {
                Log::warning("[EventNotificationService] Email queue insert failed: " . $e->getMessage());
            }
        }
    }

    /**
     * Get the user's email notification frequency for events.
     *
     * Checks notification_settings for an 'event' context, falls back to global,
     * then to the tenant default.
     */
    private function getUserEventEmailFrequency(int $userId): string
    {
        // Check event-specific setting
        $frequency = DB::table('notification_settings')
            ->where('user_id', $userId)
            ->where('context_type', 'event')
            ->value('frequency');

        if ($frequency !== null) {
            return $frequency;
        }

        // Fall back to global setting
        $frequency = DB::table('notification_settings')
            ->where('user_id', $userId)
            ->where('context_type', 'global')
            ->where('context_id', 0)
            ->value('frequency');

        if ($frequency !== null) {
            return $frequency;
        }

        // Fall back to tenant default
        try {
            $tenant = TenantContext::get();
            $config = json_decode($tenant['configuration'] ?? '{}', true);
            return $config['notifications']['default_frequency'] ?? 'daily';
        } catch (\Throwable $e) {
            return 'daily';
        }
    }

    // =========================================================================
    // REMINDER NOTIFICATION (in-app)
    // =========================================================================

    /**
     * Create an in-app reminder notification for an event attendee.
     *
     * @return string The notification message text (used for email)
     */
    private function createReminderNotification(int $userId, object $event, string $reminderType): string
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
            $message = __('notifications.event_reminder_24h', ['title' => $title, 'when' => $when, 'location' => $locationText]);
        } else {
            $message = __('notifications.event_reminder_1h', ['title' => $title, 'when' => $when, 'location' => $locationText]);
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

        return $message;
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

    // =========================================================================
    // HTML EMAIL BUILDERS
    // =========================================================================

    /**
     * Build a default event email when no specific template is provided.
     */
    private function buildDefaultEventEmailHtml(string $subject, string $content, string $link, object $user): string
    {
        $recipientName = htmlspecialchars($user->first_name ?? $user->name ?? 'there', ENT_QUOTES, 'UTF-8');
        $safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
        $safeContent = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
        $tenantName = htmlspecialchars(TenantContext::getSetting('site_name', 'Project NEXUS'), ENT_QUOTES, 'UTF-8');
        $baseUrl = TenantContext::getFrontendUrl();
        $basePath = TenantContext::getSlugPrefix();
        $fullUrl = $baseUrl . $basePath . $link;
        $greeting = htmlspecialchars(__('emails.common.greeting', ['name' => $recipientName]), ENT_QUOTES, 'UTF-8');
        $viewEventLabel = htmlspecialchars(__('emails.events.view_event'), ENT_QUOTES, 'UTF-8');

        return <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #6366f1, #8b5cf6); padding: 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 22px;">{$safeSubject}</h1>
        <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0;">{$tenantName}</p>
    </div>
    <div style="background: #f8fafc; padding: 24px; border-radius: 0 0 16px 16px; border: 1px solid #e2e8f0; border-top: none;">
        <p style="color: #1e293b; font-size: 16px; line-height: 1.6;">{$greeting}</p>
        <p style="color: #1e293b; font-size: 16px; line-height: 1.6;">{$safeContent}</p>
        <div style="text-align: center; margin-top: 24px;">
            <a href="{$fullUrl}" style="display: inline-block; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-weight: 600;">{$viewEventLabel}</a>
        </div>
    </div>
</div>
HTML;
    }

    /**
     * Build rich HTML email for RSVP notification (sent to organizer).
     */
    private function buildRsvpEmailHtml(object $event, object $rsvpUser, string $status, object $organizer): string
    {
        $organizerName = htmlspecialchars($organizer->first_name ?? $organizer->name ?? 'there', ENT_QUOTES, 'UTF-8');
        $rsvpUserName = htmlspecialchars($rsvpUser->name ?? trim(($rsvpUser->first_name ?? '') . ' ' . ($rsvpUser->last_name ?? '')), ENT_QUOTES, 'UTF-8');
        $eventTitle = htmlspecialchars($event->title, ENT_QUOTES, 'UTF-8');
        $statusLabel = $status === 'going'
            ? __('emails.events.rsvp_status_going')
            : __('emails.events.rsvp_status_interested');
        $statusColor = $status === 'going' ? '#10b981' : '#f59e0b';
        $statusBadge = $status === 'going'
            ? __('emails.events.rsvp_badge_going')
            : __('emails.events.rsvp_badge_interested');
        $tenantName = htmlspecialchars(TenantContext::getSetting('site_name', 'Project NEXUS'), ENT_QUOTES, 'UTF-8');
        $baseUrl = TenantContext::getFrontendUrl();
        $basePath = TenantContext::getSlugPrefix();
        $eventUrl = $baseUrl . $basePath . '/events/' . $event->id;
        $greeting = htmlspecialchars(__('emails.common.greeting', ['name' => $organizerName]), ENT_QUOTES, 'UTF-8');
        $rsvpHeading = htmlspecialchars(__('emails.events.new_rsvp_heading'), ENT_QUOTES, 'UTF-8');
        $rsvpBody = __('emails.events.rsvp_body', ['name' => $rsvpUserName, 'status_label' => $statusLabel]);
        $viewEventLabel = htmlspecialchars(__('emails.events.view_event'), ENT_QUOTES, 'UTF-8');

        return <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #6366f1, #8b5cf6); padding: 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 22px;">{$rsvpHeading}</h1>
        <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0;">{$tenantName}</p>
    </div>
    <div style="background: #f8fafc; padding: 24px; border-radius: 0 0 16px 16px; border: 1px solid #e2e8f0; border-top: none;">
        <p style="color: #1e293b; font-size: 16px; line-height: 1.6;">{$greeting}</p>
        <p style="color: #1e293b; font-size: 16px; line-height: 1.6;">
            {$rsvpBody}
        </p>
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin: 16px 0;">
            <h2 style="color: #1e293b; margin: 0 0 8px; font-size: 18px;">{$eventTitle}</h2>
            <span style="display: inline-block; background: {$statusColor}; color: white; padding: 4px 12px; border-radius: 16px; font-size: 13px; font-weight: 600;">{$statusBadge}</span>
        </div>
        <div style="text-align: center; margin-top: 24px;">
            <a href="{$eventUrl}" style="display: inline-block; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-weight: 600;">{$viewEventLabel}</a>
        </div>
    </div>
</div>
HTML;
    }

    /**
     * Build rich HTML email for event cancellation.
     */
    private function buildCancellationEmailHtml(object $event, ?string $reason, object $user): string
    {
        $recipientName = htmlspecialchars($user->first_name ?? $user->name ?? 'there', ENT_QUOTES, 'UTF-8');
        $eventTitle = htmlspecialchars($event->title, ENT_QUOTES, 'UTF-8');
        $tenantName = htmlspecialchars(TenantContext::getSetting('site_name', 'Project NEXUS'), ENT_QUOTES, 'UTF-8');
        $baseUrl = TenantContext::getFrontendUrl();
        $basePath = TenantContext::getSlugPrefix();
        $eventsUrl = $baseUrl . $basePath . '/events';

        $reasonLabel = htmlspecialchars(__('emails.events.cancelled_reason_label'), ENT_QUOTES, 'UTF-8');
        $reasonHtml = '';
        if (!empty($reason)) {
            $safeReason = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
            $reasonHtml = <<<HTML
        <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px 16px; margin: 16px 0;">
            <p style="color: #991b1b; margin: 0; font-size: 14px;"><strong>{$reasonLabel}</strong> {$safeReason}</p>
        </div>
HTML;
        }

        $dateHtml = '';
        if (!empty($event->start_time)) {
            $when = date('l, M j \a\t g:i A', strtotime($event->start_time));
            $scheduledFor = htmlspecialchars(__('emails.events.cancelled_scheduled_for', ['when' => $when]), ENT_QUOTES, 'UTF-8');
            $dateHtml = "<p style=\"color: #64748b; margin: 4px 0 0; font-size: 14px;\">{$scheduledFor}</p>";
        }

        $cancelledHeading = htmlspecialchars(__('emails.events.cancelled_heading'), ENT_QUOTES, 'UTF-8');
        $greeting = htmlspecialchars(__('emails.common.greeting', ['name' => $recipientName]), ENT_QUOTES, 'UTF-8');
        $cancelledBody = htmlspecialchars(__('emails.events.cancelled_body'), ENT_QUOTES, 'UTF-8');
        $browseEventsLabel = htmlspecialchars(__('emails.events.browse_events'), ENT_QUOTES, 'UTF-8');

        return <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #ef4444, #dc2626); padding: 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 22px;">{$cancelledHeading}</h1>
        <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0;">{$tenantName}</p>
    </div>
    <div style="background: #f8fafc; padding: 24px; border-radius: 0 0 16px 16px; border: 1px solid #e2e8f0; border-top: none;">
        <p style="color: #1e293b; font-size: 16px; line-height: 1.6;">{$greeting}</p>
        <p style="color: #1e293b; font-size: 16px; line-height: 1.6;">{$cancelledBody}</p>
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin: 16px 0;">
            <h2 style="color: #1e293b; margin: 0 0 4px; font-size: 18px;">{$eventTitle}</h2>
            {$dateHtml}
        </div>
        {$reasonHtml}
        <div style="text-align: center; margin-top: 24px;">
            <a href="{$eventsUrl}" style="display: inline-block; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-weight: 600;">{$browseEventsLabel}</a>
        </div>
    </div>
</div>
HTML;
    }

    /**
     * Build rich HTML email for event update notification.
     */
    private function buildUpdateEmailHtml(object $event, array $changes, object $user): string
    {
        $recipientName = htmlspecialchars($user->first_name ?? $user->name ?? 'there', ENT_QUOTES, 'UTF-8');
        $eventTitle = htmlspecialchars($event->title, ENT_QUOTES, 'UTF-8');
        $tenantName = htmlspecialchars(TenantContext::getSetting('site_name', 'Project NEXUS'), ENT_QUOTES, 'UTF-8');
        $baseUrl = TenantContext::getFrontendUrl();
        $basePath = TenantContext::getSlugPrefix();
        $eventUrl = $baseUrl . $basePath . '/events/' . $event->id;

        $dateTimeLabel = htmlspecialchars(__('emails.events.change_label_date_time'), ENT_QUOTES, 'UTF-8');
        $locationLabel = htmlspecialchars(__('emails.events.change_label_location'), ENT_QUOTES, 'UTF-8');
        $titleLabel = htmlspecialchars(__('emails.events.change_label_title'), ENT_QUOTES, 'UTF-8');

        $changesHtml = '';
        if (isset($changes['start_time'])) {
            $newTime = date('l, M j \a\t g:i A', strtotime($changes['start_time']));
            $changesHtml .= "<li style=\"color: #1e293b; margin-bottom: 8px;\"><strong>{$dateTimeLabel}</strong> {$newTime}</li>";
        }
        if (isset($changes['location'])) {
            $newLocation = htmlspecialchars($changes['location'], ENT_QUOTES, 'UTF-8');
            $changesHtml .= "<li style=\"color: #1e293b; margin-bottom: 8px;\"><strong>{$locationLabel}</strong> {$newLocation}</li>";
        }
        if (isset($changes['title'])) {
            $newTitle = htmlspecialchars($changes['title'], ENT_QUOTES, 'UTF-8');
            $changesHtml .= "<li style=\"color: #1e293b; margin-bottom: 8px;\"><strong>{$titleLabel}</strong> {$newTitle}</li>";
        }

        $updatedHeading = htmlspecialchars(__('emails.events.updated_heading'), ENT_QUOTES, 'UTF-8');
        $greeting = htmlspecialchars(__('emails.common.greeting', ['name' => $recipientName]), ENT_QUOTES, 'UTF-8');
        $updatedBody = __('emails.events.updated_body', ['title' => $eventTitle]);
        $viewUpdatedLabel = htmlspecialchars(__('emails.events.view_updated_event'), ENT_QUOTES, 'UTF-8');

        return <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #f59e0b, #d97706); padding: 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 22px;">{$updatedHeading}</h1>
        <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0;">{$tenantName}</p>
    </div>
    <div style="background: #f8fafc; padding: 24px; border-radius: 0 0 16px 16px; border: 1px solid #e2e8f0; border-top: none;">
        <p style="color: #1e293b; font-size: 16px; line-height: 1.6;">{$greeting}</p>
        <p style="color: #1e293b; font-size: 16px; line-height: 1.6;">{$updatedBody}</p>
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin: 16px 0;">
            <ul style="padding-left: 20px; margin: 0;">{$changesHtml}</ul>
        </div>
        <div style="text-align: center; margin-top: 24px;">
            <a href="{$eventUrl}" style="display: inline-block; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-weight: 600;">{$viewUpdatedLabel}</a>
        </div>
    </div>
</div>
HTML;
    }

    /**
     * Build rich HTML email for event reminder.
     */
    private function buildReminderEmailHtml(object $event, string $reminderType, object $user): string
    {
        $recipientName = htmlspecialchars($user->first_name ?? $user->name ?? 'there', ENT_QUOTES, 'UTF-8');
        $eventTitle = htmlspecialchars($event->title, ENT_QUOTES, 'UTF-8');
        $tenantName = htmlspecialchars(TenantContext::getSetting('site_name', 'Project NEXUS'), ENT_QUOTES, 'UTF-8');
        $baseUrl = TenantContext::getFrontendUrl();
        $basePath = TenantContext::getSlugPrefix();
        $eventUrl = $baseUrl . $basePath . '/events/' . $event->id;

        $when = date('l, M j \a\t g:i A', strtotime($event->start_time));
        $heading = $reminderType === '24h'
            ? htmlspecialchars(__('emails.events.reminder_heading_24h'), ENT_QUOTES, 'UTF-8')
            : htmlspecialchars(__('emails.events.reminder_heading_1h'), ENT_QUOTES, 'UTF-8');
        $gradientColors = $reminderType === '24h' ? '#6366f1, #8b5cf6' : '#f59e0b, #ef4444';
        $timeNote = $reminderType === '24h'
            ? __('emails.events.reminder_time_note_24h')
            : __('emails.events.reminder_time_note_1h');

        $onlineEventLabel = htmlspecialchars(__('emails.events.online_event'), ENT_QUOTES, 'UTF-8');
        $locationHtml = '';
        if (!empty($event->is_online) && !empty($event->online_url)) {
            $locationHtml = "<p style=\"color: #6366f1; margin: 4px 0 0; font-size: 14px;\">{$onlineEventLabel}</p>";
        } elseif (!empty($event->location)) {
            $loc = htmlspecialchars($event->location, ENT_QUOTES, 'UTF-8');
            $locationLabelHtml = htmlspecialchars(__('emails.events.location_label', ['location' => $loc]), ENT_QUOTES, 'UTF-8');
            $locationHtml = "<p style=\"color: #64748b; margin: 4px 0 0; font-size: 14px;\">{$locationLabelHtml}</p>";
        }

        $greeting = htmlspecialchars(__('emails.common.greeting', ['name' => $recipientName]), ENT_QUOTES, 'UTF-8');
        $reminderBody = __('emails.events.reminder_body', ['title' => $eventTitle, 'time_note' => $timeNote]);
        $viewEventLabel = htmlspecialchars(__('emails.events.view_event'), ENT_QUOTES, 'UTF-8');

        return <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, {$gradientColors}); padding: 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 22px;">{$heading}</h1>
        <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0;">{$tenantName}</p>
    </div>
    <div style="background: #f8fafc; padding: 24px; border-radius: 0 0 16px 16px; border: 1px solid #e2e8f0; border-top: none;">
        <p style="color: #1e293b; font-size: 16px; line-height: 1.6;">{$greeting}</p>
        <p style="color: #1e293b; font-size: 16px; line-height: 1.6;">{$reminderBody}</p>
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin: 16px 0;">
            <p style="color: #1e293b; margin: 0; font-size: 15px; font-weight: 600;">{$when}</p>
            {$locationHtml}
        </div>
        <div style="text-align: center; margin-top: 24px;">
            <a href="{$eventUrl}" style="display: inline-block; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-weight: 600;">{$viewEventLabel}</a>
        </div>
    </div>
</div>
HTML;
    }
}
