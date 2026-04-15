<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\Mailer;
use App\Core\TenantContext;
use App\Models\JobVacancy;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
     *
     * Iterates over all active tenants to ensure TenantContext is set
     * correctly for each batch of notifications (required for tenant-scoped
     * Notification::createNotification).
     */
    public static function notifyExpiringSoon(): int
    {
        $notified = 0;

        try {
            // Iterate tenants so TenantContext is set for notifications
            $tenants = DB::select("SELECT id FROM tenants WHERE is_active = 1");

            foreach ($tenants as $tenant) {
                TenantContext::setById($tenant->id);

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
                                __('notifications.job_expiring_soon', ['title' => $vacancy->title, 'days' => $daysLeft]),
                                "/jobs/{$vacancy->id}",
                                'job_expiry'
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
            }
        } catch (\Throwable $e) {
            Log::error('JobExpiryNotificationService::notifyExpiringSoon failed', ['error' => $e->getMessage()]);
        }

        return $notified;
    }

    private static function sendExpiryEmail(User $user, object $vacancy, int $daysLeft): void
    {
        $name      = htmlspecialchars($user->first_name ?? 'there');
        $title     = htmlspecialchars($vacancy->title);
        $renewUrl  = TenantContext::getFrontendUrl("/jobs/{$vacancy->id}");
        $deadline  = $vacancy->deadline?->format('d M Y') ?? 'soon';

        $greeting = __('emails_misc.jobs.expiry_greeting', ['name' => $name]);
        $heading = __('notifications.job_expiry_email_heading');
        $bodyText = __('notifications.job_expiry_email_body', ['title' => $title, 'deadline' => $deadline, 'days' => $daysLeft]);
        $ctaText = __('notifications.job_expiry_email_cta');
        $buttonText = __('notifications.job_expiry_email_button');
        $footerText = __('notifications.job_expiry_email_footer');

        $html = <<<HTML
        <!DOCTYPE html><html><body style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f9fafb;margin:0;padding:32px 16px;">
        <table width="600" style="background:#fff;border-radius:12px;padding:32px;margin:0 auto;">
          <tr><td>
            <h2 style="color:#4f46e5;margin-top:0;">{$heading}</h2>
            <p>{$greeting}</p>
            <p>{$bodyText}</p>
            <p>{$ctaText}</p>
            <a href="{$renewUrl}" style="display:inline-block;padding:10px 20px;background:#4f46e5;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;">{$buttonText}</a>
            <p style="margin-top:24px;font-size:12px;color:#9ca3af;">{$footerText}</p>
          </td></tr>
        </table>
        </body></html>
        HTML;

        $subject = __('notifications.job_expiry_email_subject', ['title' => $title]);
        $mailer = Mailer::forCurrentTenant();
        if (!$mailer->send($user->email, $subject, $html)) {
            Log::warning('JobExpiryNotificationService: failed to send expiry email', ['user_id' => $user->id, 'vacancy_id' => $vacancy->id]);
        }
    }
}
