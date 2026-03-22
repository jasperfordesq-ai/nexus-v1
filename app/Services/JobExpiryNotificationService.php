<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\JobVacancy;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * JobExpiryNotificationService — notifies job posters when their vacancy
 * is expiring soon (7 days) or has expired.
 *
 * Designed to be called from a scheduled command / cron job.
 */
class JobExpiryNotificationService
{
    /**
     * Send notifications for all vacancies expiring in the next 7 days
     * that haven't been notified yet. Run once daily via scheduler.
     */
    public static function notifyExpiringSoon(): int
    {
        $notified = 0;

        try {
            $vacancies = JobVacancy::with(['creator:id,first_name,last_name,email'])
                ->where('status', 'open')
                ->whereNotNull('deadline')
                ->whereBetween('deadline', [now(), now()->addDays(7)])
                ->whereNull('expired_at') // not yet expired
                ->get();

            foreach ($vacancies as $vacancy) {
                try {
                    $daysLeft = (int) now()->diffInDays($vacancy->deadline);

                    // In-app notification
                    if ($vacancy->user_id) {
                        Notification::createNotification(
                            (int) $vacancy->user_id,
                            "Your job \"{$vacancy->title}\" expires in {$daysLeft} day(s). Renew it to keep it visible.",
                            "/jobs/{$vacancy->id}",
                            'job_application'
                        );
                    }

                    // Email notification
                    if ($vacancy->creator && $vacancy->creator->email) {
                        self::sendExpiryEmail($vacancy->creator, $vacancy, $daysLeft);
                    }

                    $notified++;
                } catch (\Throwable $e) {
                    Log::warning('JobExpiryNotificationService: vacancy failed', ['vacancy_id' => $vacancy->id, 'error' => $e->getMessage()]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('JobExpiryNotificationService::notifyExpiringSoon failed', ['error' => $e->getMessage()]);
        }

        return $notified;
    }

    private static function sendExpiryEmail(User $user, JobVacancy $vacancy, int $daysLeft): void
    {
        $name      = htmlspecialchars($user->first_name ?? 'there');
        $title     = htmlspecialchars($vacancy->title);
        $appUrl    = config('app.url', 'https://app.project-nexus.ie');
        $renewUrl  = "{$appUrl}/jobs/{$vacancy->id}";
        $deadline  = $vacancy->deadline?->format('d M Y') ?? 'soon';

        $html = <<<HTML
        <!DOCTYPE html><html><body style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f9fafb;margin:0;padding:32px 16px;">
        <table width="600" style="background:#fff;border-radius:12px;padding:32px;margin:0 auto;">
          <tr><td>
            <h2 style="color:#4f46e5;margin-top:0;">Your job listing is expiring soon</h2>
            <p>Hi {$name},</p>
            <p>Your job <strong>"{$title}"</strong> expires on <strong>{$deadline}</strong> ({$daysLeft} day(s) from now).</p>
            <p>Renew it to keep attracting candidates:</p>
            <a href="{$renewUrl}" style="display:inline-block;padding:10px 20px;background:#4f46e5;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;">View &amp; Renew Job</a>
            <p style="margin-top:24px;font-size:12px;color:#9ca3af;">You're receiving this because you posted a job on Project NEXUS.</p>
          </td></tr>
        </table>
        </body></html>
        HTML;

        Mail::html($html, function ($message) use ($user, $title) {
            $message->to($user->email, $user->first_name . ' ' . ($user->last_name ?? ''))
                    ->subject("Your job \"{$title}\" is expiring soon");
        });
    }
}
