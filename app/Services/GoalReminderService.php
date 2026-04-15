<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\EmailTemplateBuilder;
use App\Core\Mailer;
use App\Core\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * GoalReminderService — Eloquent-based service for goal reminders.
 *
 * Replaces the legacy DI wrapper that delegated to
 */
class GoalReminderService
{
    /**
     * Get reminder settings for a goal.
     */
    public function getReminder(int $goalId, int $userId): ?array
    {
        $row = DB::table('goal_reminders')
            ->where('goal_id', $goalId)
            ->where('user_id', $userId)
            ->first();

        return $row ? (array) $row : null;
    }

    /**
     * Set or update a reminder.
     */
    public function setReminder(int $goalId, int $userId, array $data): array
    {
        $existing = DB::table('goal_reminders')
            ->where('goal_id', $goalId)
            ->where('user_id', $userId)
            ->first();

        $values = [
            'frequency'  => $data['frequency'] ?? 'weekly',
            'enabled'    => $data['enabled'] ?? true,
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('goal_reminders')
                ->where('id', $existing->id)
                ->update($values);
            $id = $existing->id;
        } else {
            $id = DB::table('goal_reminders')->insertGetId(array_merge($values, [
                'goal_id'    => $goalId,
                'user_id'    => $userId,
                'created_at' => now(),
            ]));
        }

        return (array) DB::table('goal_reminders')->where('id', $id)->where('tenant_id', TenantContext::getId())->first();
    }

    // ─── Cron-callable ────────────────────────────────────────────────

    /**
     * Send due goal reminders for the current tenant.
     * Called statically by CronJobRunner; TenantContext is already set.
     *
     * @return int Number of reminders sent
     */
    public static function sendDueReminders(): int
    {
        $tenantId = TenantContext::getId();

        $reminders = DB::table('goal_reminders as gr')
            ->join('goals as g', function ($join) {
                $join->on('gr.goal_id', '=', 'g.id')
                     ->whereColumn('g.tenant_id', '=', 'gr.tenant_id');
            })
            ->join('users as u', function ($join) {
                $join->on('gr.user_id', '=', 'u.id')
                     ->whereColumn('u.tenant_id', '=', 'gr.tenant_id');
            })
            ->where('gr.tenant_id', $tenantId)
            ->where('gr.enabled', 1)
            ->whereNotNull('gr.next_reminder_at')
            ->where('gr.next_reminder_at', '<=', now())
            ->where('g.status', 'active')
            ->select([
                'gr.id', 'gr.goal_id', 'gr.user_id', 'gr.frequency',
                'g.title as goal_title',
                'u.email', 'u.name as user_name', 'u.first_name',
            ])
            ->get();

        $sent = 0;

        foreach ($reminders as $reminder) {
            try {
                $goalTitle = htmlspecialchars($reminder->goal_title ?? 'your goal', ENT_QUOTES, 'UTF-8');
                $firstName = $reminder->first_name ?? $reminder->user_name ?? 'there';
                $link = '/goals/' . $reminder->goal_id;

                // In-app notification
                DB::insert(
                    "INSERT INTO notifications (user_id, tenant_id, message, link, type, created_at) VALUES (?, ?, ?, ?, 'goal_reminder', NOW())",
                    [$reminder->user_id, $tenantId, __('svc_notifications.goal.reminder_bell', ['title' => $goalTitle]), $link]
                );

                // Email notification
                if (!empty($reminder->email)) {
                    $goalUrl = TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix() . $link;

                    $html = EmailTemplateBuilder::make()
                        ->title(__('emails_misc.goals.reminder_title'))
                        ->previewText(__('emails_misc.goals.reminder_preview', ['title' => $goalTitle]))
                        ->greeting($firstName)
                        ->paragraph(__('emails_misc.goals.reminder_body'))
                        ->highlight($goalTitle)
                        ->button(__('emails_misc.goals.reminder_cta'), $goalUrl)
                        ->render();

                    $subject = __('emails_misc.goals.reminder_subject', ['title' => $goalTitle]);
                    if (!Mailer::forCurrentTenant()->send($reminder->email, $subject, $html)) {
                        Log::warning('[GoalReminderService] Email failed', ['user_id' => $reminder->user_id, 'reminder_id' => $reminder->id]);
                    }
                }

                // Advance next_reminder_at
                DB::table('goal_reminders')
                    ->where('id', $reminder->id)
                    ->update([
                        'last_sent_at'    => now(),
                        'next_reminder_at' => static::nextReminderAt($reminder->frequency),
                        'updated_at'      => now(),
                    ]);

                $sent++;
            } catch (\Throwable $e) {
                Log::warning('[GoalReminderService] Failed reminder id=' . $reminder->id . ': ' . $e->getMessage());
            }
        }

        return $sent;
    }

    private static function nextReminderAt(string $frequency): Carbon
    {
        return match ($frequency) {
            'daily'     => now()->addDay(),
            'biweekly'  => now()->addWeeks(2),
            'monthly'   => now()->addMonth(),
            default     => now()->addWeek(),
        };
    }

    /**
     * Delete a reminder.
     */
    public function deleteReminder(int $goalId, int $userId): bool
    {
        return (bool) DB::table('goal_reminders')
            ->where('goal_id', $goalId)
            ->where('user_id', $userId)
            ->delete();
    }
}
