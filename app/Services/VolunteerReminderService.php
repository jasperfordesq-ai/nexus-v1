<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\EmailTemplateBuilder;
use App\Core\Mailer;
use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * VolunteerReminderService — manages reminder settings and sends shift/credential
 * reminders to volunteers.
 *
 * Backed by `vol_reminder_settings` and `vol_reminders_sent` tables.
 * All queries are tenant-scoped via TenantContext.
 */
class VolunteerReminderService
{
    public function __construct()
    {
    }

    /**
     * Send reminders for an opportunity's upcoming shifts.
     *
     * Finds confirmed volunteers for shifts belonging to the given opportunity
     * that are within the configured pre-shift reminder window, and records
     * the reminder as sent.
     *
     * @return int  Number of reminders sent
     */
    public static function sendReminders(int $tenantId, int $opportunityId): int
    {
        // Get the pre_shift reminder setting for this tenant
        $setting = DB::table('vol_reminder_settings')
            ->where('tenant_id', $tenantId)
            ->where('reminder_type', 'pre_shift')
            ->where('enabled', true)
            ->first();

        if (!$setting) {
            return 0;
        }

        $hoursBefore = (int) ($setting->hours_before ?? 24);

        // Find shifts for this opportunity that start within the reminder window
        $shifts = DB::table('vol_shifts')
            ->where('opportunity_id', $opportunityId)
            ->where('tenant_id', $tenantId)
            ->where('start_time', '>', now())
            ->where('start_time', '<=', now()->addHours($hoursBefore))
            ->get();

        if ($shifts->isEmpty()) {
            return 0;
        }

        $sentCount = 0;

        foreach ($shifts as $shift) {
            // Get confirmed volunteers for this shift
            $signups = DB::table('vol_shift_signups')
                ->where('shift_id', $shift->id)
                ->where('tenant_id', $tenantId)
                ->where('status', 'confirmed')
                ->pluck('user_id')
                ->all();

            // Fetch the opportunity title for this shift once per shift
            $opportunity = DB::table('vol_opportunities')
                ->where('id', $shift->opportunity_id)
                ->first(['title', 'location']);

            $opportunityTitle = htmlspecialchars($opportunity->title ?? '', ENT_QUOTES, 'UTF-8');
            $opportunityLocation = htmlspecialchars($opportunity->location ?? '', ENT_QUOTES, 'UTF-8');

            foreach ($signups as $userId) {
                // Check if reminder was already sent for this shift+user combination
                $alreadySent = DB::table('vol_reminders_sent')
                    ->where('tenant_id', $tenantId)
                    ->where('user_id', $userId)
                    ->where('reminder_type', 'pre_shift')
                    ->where('reference_id', $shift->id)
                    ->exists();

                if ($alreadySent) {
                    continue;
                }

                // Record the reminder as sent
                try {
                    // Determine which channels are enabled
                    $channels = [];
                    if ($setting->push_enabled ?? true) {
                        $channels[] = 'push';
                    }
                    if ($setting->email_enabled ?? true) {
                        $channels[] = 'email';
                    }
                    if ($setting->sms_enabled ?? false) {
                        $channels[] = 'sms';
                    }

                    if (empty($channels)) {
                        $channels = ['push']; // default fallback
                    }

                    foreach ($channels as $channel) {
                        DB::table('vol_reminders_sent')->insert([
                            'tenant_id' => $tenantId,
                            'user_id' => $userId,
                            'reminder_type' => 'pre_shift',
                            'reference_id' => (int) $shift->id,
                            'channel' => $channel,
                            'sent_at' => now(),
                        ]);
                    }

                    // Send email if the email channel is enabled
                    if (in_array('email', $channels, true)) {
                        $user = DB::table('users')
                            ->where('id', $userId)
                            ->where('tenant_id', $tenantId)
                            ->first(['email', 'first_name', 'name']);

                        if ($user && !empty($user->email)) {
                            try {
                                $firstName = $user->first_name ?? $user->name ?? 'there';
                                $shiftTime = $shift->start_time
                                    ? date('D, d M Y H:i', strtotime($shift->start_time))
                                    : '';
                                $shiftUrl = TenantContext::getFrontendUrl()
                                    . TenantContext::getSlugPrefix()
                                    . '/volunteering/opportunities/' . $shift->opportunity_id;
                                $communityName = TenantContext::getName();

                                $infoCard = [__('emails_volunteer.shift_reminder.label_opportunity') => $opportunityTitle];
                                if ($shiftTime) {
                                    $infoCard[__('emails_volunteer.shift_reminder.label_when')] = $shiftTime;
                                }
                                if ($opportunityLocation) {
                                    $infoCard[__('emails_volunteer.shift_reminder.label_location')] = $opportunityLocation;
                                }

                                $html = EmailTemplateBuilder::make()
                                    ->theme('info')
                                    ->title(__('emails_volunteer.shift_reminder.title'))
                                    ->previewText(__('emails_volunteer.shift_reminder.preview', ['title' => $opportunityTitle]))
                                    ->greeting($firstName)
                                    ->paragraph(__('emails_volunteer.shift_reminder.body'))
                                    ->infoCard($infoCard)
                                    ->button(__('emails_volunteer.shift_reminder.cta'), $shiftUrl)
                                    ->render();

                                $subject = __('emails_volunteer.shift_reminder.subject', [
                                    'title'     => $opportunityTitle,
                                    'community' => $communityName,
                                ]);

                                if (!Mailer::forCurrentTenant()->send($user->email, $subject, $html)) {
                                    Log::warning('[VolunteerReminderService] sendReminders email failed', [
                                        'user_id' => $userId,
                                        'shift_id' => $shift->id,
                                    ]);
                                }
                            } catch (\Throwable $e) {
                                Log::warning('[VolunteerReminderService] sendReminders email exception: ' . $e->getMessage(), [
                                    'user_id' => $userId,
                                    'shift_id' => $shift->id,
                                ]);
                            }
                        }
                    }

                    $sentCount++;
                } catch (\Throwable $e) {
                    error_log("VolunteerReminderService::sendReminders error for user {$userId}: " . $e->getMessage());
                }
            }
        }

        return $sentCount;
    }

    /**
     * Schedule a reminder for a specific opportunity at a given datetime.
     *
     * Records a pre_shift reminder entry with the specified send time.
     * Actual delivery is handled by a scheduled job that polls vol_reminders_sent.
     *
     * @return bool  True if scheduled successfully
     */
    public static function scheduleReminder(int $tenantId, int $opportunityId, string $datetime): bool
    {
        try {
            $sendAt = new \DateTime($datetime);
        } catch (\Throwable $e) {
            Log::warning('[VolunteerReminder] Invalid datetime for scheduleReminder: ' . $e->getMessage());
            return false;
        }

        // Verify the opportunity exists in this tenant
        $opp = DB::table('vol_opportunities as opp')
            ->join('vol_organizations as org', 'opp.organization_id', '=', 'org.id')
            ->where('opp.id', $opportunityId)
            ->where('org.tenant_id', $tenantId)
            ->first();

        if (!$opp) {
            return false;
        }

        try {
            // Record a scheduled reminder entry (reference_id = opportunity_id)
            DB::table('vol_reminders_sent')->insert([
                'tenant_id' => $tenantId,
                'user_id' => 0, // 0 = broadcast to all volunteers for this opportunity
                'reminder_type' => 'pre_shift',
                'reference_id' => $opportunityId,
                'channel' => 'push',
                'sent_at' => $sendAt->format('Y-m-d H:i:s'),
            ]);

            return true;
        } catch (\Throwable $e) {
            error_log("VolunteerReminderService::scheduleReminder error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cancel a scheduled reminder by its ID.
     *
     * @return bool  True if the reminder was found and deleted
     */
    public static function cancelReminder(int $tenantId, int $reminderId): bool
    {
        try {
            $deleted = DB::table('vol_reminders_sent')
                ->where('id', $reminderId)
                ->where('tenant_id', $tenantId)
                ->delete();

            return $deleted > 0;
        } catch (\Throwable $e) {
            error_log("VolunteerReminderService::cancelReminder error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all reminder settings for the current tenant.
     *
     * Returns one row per reminder_type, or synthesises defaults for types
     * that have no row yet.
     *
     * @return array  Keyed list of reminder settings
     */
    public static function getSettings(): array
    {
        $tenantId = TenantContext::getId();

        $rows = DB::table('vol_reminder_settings')
            ->where('tenant_id', $tenantId)
            ->get()
            ->keyBy('reminder_type');

        $defaults = [
            'pre_shift' => [
                'hours_before' => 24,
                'hours_after' => null,
                'days_inactive' => null,
                'days_before_expiry' => null,
            ],
            'post_shift_feedback' => [
                'hours_before' => null,
                'hours_after' => 2,
                'days_inactive' => null,
                'days_before_expiry' => null,
            ],
            'lapsed_volunteer' => [
                'hours_before' => null,
                'hours_after' => null,
                'days_inactive' => 30,
                'days_before_expiry' => null,
            ],
            'credential_expiry' => [
                'hours_before' => null,
                'hours_after' => null,
                'days_inactive' => null,
                'days_before_expiry' => 14,
            ],
            'training_expiry' => [
                'hours_before' => null,
                'hours_after' => null,
                'days_inactive' => null,
                'days_before_expiry' => 14,
            ],
        ];

        $settings = [];

        foreach ($defaults as $type => $typeDefaults) {
            $row = $rows->get($type);

            if ($row) {
                $settings[] = [
                    'id' => (int) $row->id,
                    'reminder_type' => $row->reminder_type,
                    'enabled' => (bool) $row->enabled,
                    'hours_before' => $row->hours_before !== null ? (int) $row->hours_before : null,
                    'hours_after' => $row->hours_after !== null ? (int) $row->hours_after : null,
                    'days_inactive' => $row->days_inactive !== null ? (int) $row->days_inactive : null,
                    'days_before_expiry' => $row->days_before_expiry !== null ? (int) $row->days_before_expiry : null,
                    'email_template' => $row->email_template,
                    'push_enabled' => (bool) $row->push_enabled,
                    'email_enabled' => (bool) $row->email_enabled,
                    'sms_enabled' => (bool) $row->sms_enabled,
                    'updated_at' => $row->updated_at,
                ];
            } else {
                // Synthesise a default entry (not yet persisted)
                $settings[] = [
                    'id' => null,
                    'reminder_type' => $type,
                    'enabled' => true,
                    'hours_before' => $typeDefaults['hours_before'],
                    'hours_after' => $typeDefaults['hours_after'],
                    'days_inactive' => $typeDefaults['days_inactive'],
                    'days_before_expiry' => $typeDefaults['days_before_expiry'],
                    'email_template' => null,
                    'push_enabled' => true,
                    'email_enabled' => true,
                    'sms_enabled' => false,
                    'updated_at' => null,
                ];
            }
        }

        return $settings;
    }

    // ─── Cron-callable stubs ───────────────────────────────────────────
    // These methods are called by CronJobRunner but the actual sending logic
    // is not yet implemented. They log a warning and return 0 so the cron
    // runner doesn't crash.

    /**
     * Send pre-shift reminders to volunteers with upcoming shifts.
     *
     * Iterates all active tenants, reads their pre_shift reminder setting to
     * determine the reminder window, then emails each confirmed volunteer whose
     * shift falls within that window and hasn't been reminded yet.
     *
     * @return int Total number of reminders sent across all tenants
     */
    public static function sendPreShiftReminders(): int
    {
        $totalSent = 0;

        // Collect all distinct tenant IDs that have reminder settings enabled,
        // plus any tenants that have upcoming shifts (fallback 24h window).
        $tenantIds = DB::table('vol_reminder_settings')
            ->where('reminder_type', 'pre_shift')
            ->where('enabled', true)
            ->pluck('tenant_id')
            ->unique()
            ->all();

        // Also include tenants that have shifts but no explicit setting (use 24h default).
        $tenantsWithShifts = DB::table('vol_shifts')
            ->where('start_time', '>', now())
            ->where('start_time', '<=', now()->addHours(24))
            ->pluck('tenant_id')
            ->unique()
            ->all();

        $allTenantIds = array_unique(array_merge($tenantIds, $tenantsWithShifts));

        foreach ($allTenantIds as $tenantId) {
            try {
                // Get pre_shift reminder setting for this tenant (or use 24h default)
                $setting = DB::table('vol_reminder_settings')
                    ->where('tenant_id', $tenantId)
                    ->where('reminder_type', 'pre_shift')
                    ->where('enabled', true)
                    ->first();

                // Skip tenant if setting explicitly disabled
                if ($setting !== null && !(bool) $setting->enabled) {
                    continue;
                }

                $hoursBefore  = (int) ($setting->hours_before ?? 24);
                $emailEnabled = (bool) ($setting->email_enabled ?? true);
                $pushEnabled  = (bool) ($setting->push_enabled ?? true);
                $smsEnabled   = (bool) ($setting->sms_enabled ?? false);

                // Find shifts within the reminder window for this tenant
                $shifts = DB::table('vol_shifts')
                    ->where('tenant_id', $tenantId)
                    ->where('start_time', '>', now())
                    ->where('start_time', '<=', now()->addHours($hoursBefore))
                    ->get();

                foreach ($shifts as $shift) {
                    // Fetch opportunity once per shift
                    $opportunity = DB::table('vol_opportunities')
                        ->where('id', $shift->opportunity_id)
                        ->first(['title', 'location']);

                    $opportunityTitle    = htmlspecialchars($opportunity->title ?? '', ENT_QUOTES, 'UTF-8');
                    $opportunityLocation = htmlspecialchars($opportunity->location ?? '', ENT_QUOTES, 'UTF-8');

                    // Get confirmed volunteers for this shift
                    $signups = DB::table('vol_shift_signups')
                        ->where('shift_id', $shift->id)
                        ->where('tenant_id', $tenantId)
                        ->where('status', 'confirmed')
                        ->pluck('user_id')
                        ->all();

                    foreach ($signups as $userId) {
                        // Skip if already reminded
                        $alreadySent = DB::table('vol_reminders_sent')
                            ->where('tenant_id', $tenantId)
                            ->where('user_id', $userId)
                            ->where('reminder_type', 'pre_shift')
                            ->where('reference_id', $shift->id)
                            ->exists();

                        if ($alreadySent) {
                            continue;
                        }

                        try {
                            // Build channel list
                            $channels = [];
                            if ($pushEnabled) {
                                $channels[] = 'push';
                            }
                            if ($emailEnabled) {
                                $channels[] = 'email';
                            }
                            if ($smsEnabled) {
                                $channels[] = 'sms';
                            }
                            if (empty($channels)) {
                                $channels = ['push'];
                            }

                            // Record reminder attempt for each channel
                            foreach ($channels as $channel) {
                                DB::table('vol_reminders_sent')->insert([
                                    'tenant_id'     => $tenantId,
                                    'user_id'       => $userId,
                                    'reminder_type' => 'pre_shift',
                                    'reference_id'  => (int) $shift->id,
                                    'channel'       => $channel,
                                    'sent_at'       => now(),
                                ]);
                            }

                            // Send email
                            if ($emailEnabled) {
                                $user = DB::table('users')
                                    ->where('id', $userId)
                                    ->where('tenant_id', $tenantId)
                                    ->first(['email', 'first_name', 'name']);

                                if ($user && !empty($user->email)) {
                                    // Set tenant context so Mailer and translations use the right tenant
                                    TenantContext::setId($tenantId);

                                    $firstName    = $user->first_name ?? $user->name ?? 'there';
                                    $shiftTime    = $shift->start_time
                                        ? date('D, d M Y H:i', strtotime($shift->start_time))
                                        : '';
                                    $shiftUrl     = TenantContext::getFrontendUrl()
                                        . TenantContext::getSlugPrefix()
                                        . '/volunteering/opportunities/' . $shift->opportunity_id;
                                    $communityName = TenantContext::getName();

                                    $infoCard = [__('emails_volunteer.shift_starting_soon.title') => $opportunityTitle];
                                    if ($shiftTime) {
                                        $infoCard[__('emails_volunteer.shift_reminder.label_when')] = $shiftTime;
                                    }
                                    if ($opportunityLocation) {
                                        $infoCard[__('emails_volunteer.shift_reminder.label_location')] = $opportunityLocation;
                                    }

                                    $html = EmailTemplateBuilder::make()
                                        ->theme('info')
                                        ->title(__('emails_volunteer.shift_starting_soon.title'))
                                        ->previewText(__('emails_volunteer.shift_starting_soon.preview', ['title' => $opportunityTitle]))
                                        ->greeting($firstName)
                                        ->paragraph(__('emails_volunteer.shift_starting_soon.body'))
                                        ->infoCard($infoCard)
                                        ->button(__('emails_volunteer.shift_starting_soon.cta'), $shiftUrl)
                                        ->render();

                                    $subject = __('emails_volunteer.shift_starting_soon.subject', ['title' => $opportunityTitle]);

                                    if (!Mailer::forCurrentTenant()->send($user->email, $subject, $html)) {
                                        Log::warning('[VolunteerReminderService] sendPreShiftReminders email failed', [
                                            'tenant_id' => $tenantId,
                                            'user_id'   => $userId,
                                            'shift_id'  => $shift->id,
                                        ]);
                                    }
                                }
                            }

                            $totalSent++;
                        } catch (\Throwable $e) {
                            Log::warning('[VolunteerReminderService] sendPreShiftReminders error for user ' . $userId . ': ' . $e->getMessage());
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[VolunteerReminderService] sendPreShiftReminders tenant error for tenant ' . $tenantId . ': ' . $e->getMessage());
            }
        }

        return $totalSent;
    }

    /**
     * Send post-shift feedback requests to volunteers after their shift ends.
     *
     * For each tenant with a post_shift_feedback setting enabled, finds completed
     * shifts (end_time in the past, within the hours_after window) and emails each
     * confirmed volunteer asking them to log their hours — unless already sent.
     *
     * @return int Total number of feedback requests sent across all tenants
     */
    public static function sendPostShiftFeedback(): int
    {
        $totalSent = 0;

        // Collect tenants that have post_shift_feedback enabled
        $settingsByTenant = DB::table('vol_reminder_settings')
            ->where('reminder_type', 'post_shift_feedback')
            ->where('enabled', true)
            ->get()
            ->keyBy('tenant_id');

        // Also cover tenants with recently-ended shifts but no explicit setting (default 2h window)
        $tenantsWithEndedShifts = DB::table('vol_shifts')
            ->where('end_time', '<', now())
            ->where('end_time', '>=', now()->subHours(2))
            ->pluck('tenant_id')
            ->unique()
            ->all();

        $allTenantIds = array_unique(array_merge(
            $settingsByTenant->keys()->all(),
            $tenantsWithEndedShifts
        ));

        foreach ($allTenantIds as $tenantId) {
            try {
                $setting      = $settingsByTenant->get($tenantId);
                $hoursAfter   = (int) ($setting->hours_after ?? 2);
                $emailEnabled = (bool) ($setting->email_enabled ?? true);
                $pushEnabled  = (bool) ($setting->push_enabled ?? true);
                $smsEnabled   = (bool) ($setting->sms_enabled ?? false);

                // Find shifts that ended within the feedback window
                $shifts = DB::table('vol_shifts')
                    ->where('tenant_id', $tenantId)
                    ->where('end_time', '<', now())
                    ->where('end_time', '>=', now()->subHours($hoursAfter))
                    ->get();

                foreach ($shifts as $shift) {
                    $opportunity = DB::table('vol_opportunities')
                        ->where('id', $shift->opportunity_id)
                        ->first(['title']);

                    $opportunityTitle = htmlspecialchars($opportunity->title ?? '', ENT_QUOTES, 'UTF-8');

                    // Get confirmed volunteers
                    $signups = DB::table('vol_shift_signups')
                        ->where('shift_id', $shift->id)
                        ->where('tenant_id', $tenantId)
                        ->where('status', 'confirmed')
                        ->pluck('user_id')
                        ->all();

                    foreach ($signups as $userId) {
                        // Skip if post-shift feedback already sent for this shift+user
                        $alreadySent = DB::table('vol_reminders_sent')
                            ->where('tenant_id', $tenantId)
                            ->where('user_id', $userId)
                            ->where('reminder_type', 'post_shift_feedback')
                            ->where('reference_id', $shift->id)
                            ->exists();

                        if ($alreadySent) {
                            continue;
                        }

                        try {
                            // Build channel list
                            $channels = [];
                            if ($pushEnabled) {
                                $channels[] = 'push';
                            }
                            if ($emailEnabled) {
                                $channels[] = 'email';
                            }
                            if ($smsEnabled) {
                                $channels[] = 'sms';
                            }
                            if (empty($channels)) {
                                $channels = ['push'];
                            }

                            // Record feedback request for each channel
                            foreach ($channels as $channel) {
                                DB::table('vol_reminders_sent')->insert([
                                    'tenant_id'     => $tenantId,
                                    'user_id'       => $userId,
                                    'reminder_type' => 'post_shift_feedback',
                                    'reference_id'  => (int) $shift->id,
                                    'channel'       => $channel,
                                    'sent_at'       => now(),
                                ]);
                            }

                            // Send email
                            if ($emailEnabled) {
                                $user = DB::table('users')
                                    ->where('id', $userId)
                                    ->where('tenant_id', $tenantId)
                                    ->first(['email', 'first_name', 'name']);

                                if ($user && !empty($user->email)) {
                                    TenantContext::setId($tenantId);

                                    $firstName  = $user->first_name ?? $user->name ?? 'there';
                                    $logHoursUrl = TenantContext::getFrontendUrl()
                                        . TenantContext::getSlugPrefix()
                                        . '/volunteering/opportunities/' . $shift->opportunity_id;

                                    $html = EmailTemplateBuilder::make()
                                        ->theme('success')
                                        ->title(__('emails_volunteer.post_shift_feedback.title'))
                                        ->previewText(__('emails_volunteer.post_shift_feedback.preview', ['title' => $opportunityTitle]))
                                        ->greeting($firstName)
                                        ->paragraph(__('emails_volunteer.post_shift_feedback.body', ['title' => $opportunityTitle]))
                                        ->paragraph(__('emails_volunteer.post_shift_feedback.log_hours_prompt'))
                                        ->button(__('emails_volunteer.post_shift_feedback.cta'), $logHoursUrl)
                                        ->render();

                                    $subject = __('emails_volunteer.post_shift_feedback.subject', ['title' => $opportunityTitle]);

                                    if (!Mailer::forCurrentTenant()->send($user->email, $subject, $html)) {
                                        Log::warning('[VolunteerReminderService] sendPostShiftFeedback email failed', [
                                            'tenant_id' => $tenantId,
                                            'user_id'   => $userId,
                                            'shift_id'  => $shift->id,
                                        ]);
                                    }
                                }
                            }

                            $totalSent++;
                        } catch (\Throwable $e) {
                            Log::warning('[VolunteerReminderService] sendPostShiftFeedback error for user ' . $userId . ': ' . $e->getMessage());
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[VolunteerReminderService] sendPostShiftFeedback tenant error for tenant ' . $tenantId . ': ' . $e->getMessage());
            }
        }

        return $totalSent;
    }

    /**
     * Nudge volunteers who have been inactive/lapsed.
     * Stub — not yet implemented; logs a warning and returns 0.
     *
     * @return int Number of nudges sent
     */
    public static function nudgeLapsedVolunteers(): int
    {
        \Illuminate\Support\Facades\Log::warning(
            '[VolunteerReminderService] nudgeLapsedVolunteers() is not yet implemented — returning 0.'
        );
        return 0;
    }

    /**
     * Send warnings to volunteers whose credentials are about to expire.
     * Stub — not yet implemented; logs a warning and returns 0.
     *
     * @return int Number of warnings sent
     */
    public static function sendCredentialExpiryWarnings(): int
    {
        \Illuminate\Support\Facades\Log::warning(
            '[VolunteerReminderService] sendCredentialExpiryWarnings() is not yet implemented — returning 0.'
        );
        return 0;
    }

    /**
     * Send warnings to volunteers whose training certifications are about to expire.
     * Stub — not yet implemented; logs a warning and returns 0.
     *
     * @return int Number of warnings sent
     */
    public static function sendTrainingExpiryWarnings(): int
    {
        \Illuminate\Support\Facades\Log::warning(
            '[VolunteerReminderService] sendTrainingExpiryWarnings() is not yet implemented — returning 0.'
        );
        return 0;
    }

    /**
     * Create or update a reminder setting for the current tenant.
     *
     * Uses UPSERT on (tenant_id, reminder_type).
     *
     * @param string $type  One of: pre_shift, post_shift_feedback, lapsed_volunteer, credential_expiry, training_expiry
     * @param array  $data  Setting fields to update
     * @return bool  True on success
     */
    public static function updateSetting(string $type, array $data): bool
    {
        $tenantId = TenantContext::getId();

        $allowedTypes = ['pre_shift', 'post_shift_feedback', 'lapsed_volunteer', 'credential_expiry', 'training_expiry'];
        if (!in_array($type, $allowedTypes, true)) {
            return false;
        }

        $values = [
            'tenant_id' => $tenantId,
            'reminder_type' => $type,
            'enabled' => isset($data['enabled']) ? (bool) $data['enabled'] : true,
            'hours_before' => isset($data['hours_before']) ? (int) $data['hours_before'] : null,
            'hours_after' => isset($data['hours_after']) ? (int) $data['hours_after'] : null,
            'days_inactive' => isset($data['days_inactive']) ? (int) $data['days_inactive'] : null,
            'days_before_expiry' => isset($data['days_before_expiry']) ? (int) $data['days_before_expiry'] : null,
            'email_template' => isset($data['email_template']) ? trim((string) $data['email_template']) : null,
            'push_enabled' => isset($data['push_enabled']) ? (bool) $data['push_enabled'] : true,
            'email_enabled' => isset($data['email_enabled']) ? (bool) $data['email_enabled'] : true,
            'sms_enabled' => isset($data['sms_enabled']) ? (bool) $data['sms_enabled'] : false,
            'updated_at' => now(),
        ];

        try {
            $existing = DB::table('vol_reminder_settings')
                ->where('tenant_id', $tenantId)
                ->where('reminder_type', $type)
                ->first();

            if ($existing) {
                DB::table('vol_reminder_settings')
                    ->where('id', $existing->id)
                    ->update($values);
            } else {
                $values['created_at'] = now();
                DB::table('vol_reminder_settings')->insert($values);
            }

            return true;
        } catch (\Throwable $e) {
            error_log("VolunteerReminderService::updateSetting error: " . $e->getMessage());
            return false;
        }
    }
}
