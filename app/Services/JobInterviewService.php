<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\EmailTemplateBuilder;
use App\Core\Mailer;
use App\Core\TenantContext;
use App\Models\JobApplication;
use App\Models\JobInterview;
use App\Models\Notification;
use App\Models\Tenant;
use App\Services\RealtimeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * JobInterviewService — Manages interview scheduling between employers and candidates.
 *
 * All queries are tenant-scoped via TenantContext::getId().
 */
class JobInterviewService
{
    /**
     * Propose an interview (employer proposes to candidate).
     *
     * @param int   $applicationId   The application ID.
     * @param int   $proposedByUserId The user proposing the interview (employer/job poster).
     * @param array $data             interview_type, scheduled_at, duration_mins, location_notes
     * @return array|false            The created interview as array, or false on failure.
     */
    public static function propose(int $applicationId, int $proposedByUserId, array $data): array|false
    {
        $tenantId = TenantContext::getId();

        try {
            $application = JobApplication::with(['vacancy'])->find($applicationId);

            if (!$application || !$application->vacancy) {
                return false;
            }

            // Scope check — vacancy must belong to this tenant
            if ((int) $application->vacancy->tenant_id !== $tenantId) {
                return false;
            }

            // Only the job poster can propose an interview
            if ((int) $application->vacancy->user_id !== $proposedByUserId) {
                return false;
            }

            if (empty($data['scheduled_at'])) {
                return false;
            }

            $interview = JobInterview::create([
                'tenant_id'      => $tenantId,
                'vacancy_id'     => (int) $application->vacancy_id,
                'application_id' => $applicationId,
                'proposed_by'    => $proposedByUserId,
                'interview_type' => $data['interview_type'] ?? 'video',
                'scheduled_at'   => $data['scheduled_at'],
                'duration_mins'  => isset($data['duration_mins']) ? (int) $data['duration_mins'] : 60,
                'location_notes' => $data['location_notes'] ?? null,
                'status'         => 'proposed',
            ]);

            // Notify the candidate
            try {
                $jobTitle = $application->vacancy->title ?? 'a job';
                $candidateId = (int) $application->user_id;
                $interviewMsg = __('emails_misc.jobs.interview_requested', ['title' => $jobTitle]);
                Notification::createNotification(
                    $candidateId,
                    $interviewMsg,
                    "/jobs/{$application->vacancy_id}",
                    'job_application'
                );
                RealtimeService::broadcastAndPush($candidateId, $interviewMsg, [
                    'type'      => 'job_interview_proposed',
                    'job_id'    => (int) $application->vacancy_id,
                    'job_title' => $jobTitle,
                    'message'   => $interviewMsg,
                    'url'       => "/jobs/{$application->vacancy_id}",
                ]);
                static::sendInterviewEmail(
                    $candidateId,
                    'emails_misc.jobs.interview_email_subject_proposed',
                    'emails_misc.jobs.interview_requested',
                    ['title' => $jobTitle],
                    "/jobs/{$application->vacancy_id}"
                );
            } catch (\Throwable $e) {
                Log::warning('JobInterviewService::propose notification failed: ' . $e->getMessage());
            }

            return $interview->toArray();
        } catch (\Throwable $e) {
            Log::error('JobInterviewService::propose failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Accept an interview (candidate accepts).
     *
     * @param int         $interviewId The interview ID.
     * @param int         $userId      The candidate's user ID.
     * @param string|null $notes       Optional candidate notes.
     * @return bool
     */
    public static function accept(int $interviewId, int $userId, ?string $notes = null): bool
    {
        $tenantId = TenantContext::getId();

        try {
            $interview = JobInterview::with(['application.vacancy'])->find($interviewId);

            if (!$interview || (int) $interview->tenant_id !== $tenantId) {
                return false;
            }

            // Only the applicant can accept
            if (!$interview->application || (int) $interview->application->user_id !== $userId) {
                return false;
            }

            if ($interview->status !== 'proposed') {
                return false;
            }

            $interview->update([
                'status'          => 'accepted',
                'candidate_notes' => $notes ? trim($notes) : null,
            ]);

            // Notify the job poster
            try {
                $jobTitle = $interview->application->vacancy->title ?? 'a job';
                $posterId = $interview->application->vacancy->user_id ?? null;
                if ($posterId) {
                    $acceptMsg = __('emails_misc.jobs.interview_accepted', ['title' => $jobTitle]);
                    Notification::createNotification(
                        (int) $posterId,
                        $acceptMsg,
                        "/jobs/{$interview->vacancy_id}/applications",
                        'job_application_status'
                    );
                    RealtimeService::broadcastAndPush((int) $posterId, $acceptMsg, [
                        'type'      => 'job_interview_accepted',
                        'job_id'    => (int) $interview->vacancy_id,
                        'job_title' => $jobTitle,
                        'message'   => $acceptMsg,
                        'url'       => "/jobs/{$interview->vacancy_id}/applications",
                    ]);
                    static::sendInterviewEmail(
                        (int) $posterId,
                        'emails_misc.jobs.interview_email_subject_accepted',
                        'emails_misc.jobs.interview_accepted',
                        ['title' => $jobTitle],
                        "/jobs/{$interview->vacancy_id}/applications"
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('JobInterviewService::accept notification failed: ' . $e->getMessage());
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('JobInterviewService::accept failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Decline an interview (candidate declines).
     *
     * @param int         $interviewId The interview ID.
     * @param int         $userId      The candidate's user ID.
     * @param string|null $notes       Optional candidate notes.
     * @return bool
     */
    public static function decline(int $interviewId, int $userId, ?string $notes = null): bool
    {
        $tenantId = TenantContext::getId();

        try {
            $interview = JobInterview::with(['application.vacancy'])->find($interviewId);

            if (!$interview || (int) $interview->tenant_id !== $tenantId) {
                return false;
            }

            // Only the applicant can decline
            if (!$interview->application || (int) $interview->application->user_id !== $userId) {
                return false;
            }

            if ($interview->status !== 'proposed') {
                return false;
            }

            $interview->update([
                'status'          => 'declined',
                'candidate_notes' => $notes ? trim($notes) : null,
            ]);

            // Notify the job poster
            try {
                $jobTitle = $interview->application->vacancy->title ?? 'a job';
                $posterId = $interview->application->vacancy->user_id ?? null;
                if ($posterId) {
                    $declineMsg = __('emails_misc.jobs.interview_declined', ['title' => $jobTitle]);
                    Notification::createNotification(
                        (int) $posterId,
                        $declineMsg,
                        "/jobs/{$interview->vacancy_id}/applications",
                        'job_application_status'
                    );
                    RealtimeService::broadcastAndPush((int) $posterId, $declineMsg, [
                        'type'      => 'job_interview_declined',
                        'job_id'    => (int) $interview->vacancy_id,
                        'job_title' => $jobTitle,
                        'message'   => $declineMsg,
                        'url'       => "/jobs/{$interview->vacancy_id}/applications",
                    ]);
                    static::sendInterviewEmail(
                        (int) $posterId,
                        'emails_misc.jobs.interview_email_subject_declined',
                        'emails_misc.jobs.interview_declined',
                        ['title' => $jobTitle],
                        "/jobs/{$interview->vacancy_id}/applications"
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('JobInterviewService::decline notification failed: ' . $e->getMessage());
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('JobInterviewService::decline failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get interviews for a vacancy (employer view).
     *
     * @param int $vacancyId
     * @return array
     */
    public static function getForVacancy(int $vacancyId): array
    {
        $tenantId = TenantContext::getId();

        try {
            return JobInterview::with(['application.applicant:id,first_name,last_name,avatar_url'])
                ->where('tenant_id', $tenantId)
                ->where('vacancy_id', $vacancyId)
                ->orderByDesc('scheduled_at')
                ->get()
                ->toArray();
        } catch (\Throwable $e) {
            Log::error('JobInterviewService::getForVacancy failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get interviews for the current user (candidate view).
     *
     * @param int $userId
     * @return array
     */
    public static function getForUser(int $userId): array
    {
        $tenantId = TenantContext::getId();

        try {
            return JobInterview::with(['vacancy:id,title,user_id'])
                ->where('tenant_id', $tenantId)
                ->whereHas('application', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                })
                ->orderByDesc('scheduled_at')
                ->get()
                ->toArray();
        } catch (\Throwable $e) {
            Log::error('JobInterviewService::getForUser failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Cancel an interview (employer cancels).
     *
     * @param int $interviewId
     * @param int $userId The employer's user ID.
     * @return bool
     */
    public static function cancel(int $interviewId, int $userId): bool
    {
        $tenantId = TenantContext::getId();

        try {
            $interview = JobInterview::with(['application.vacancy'])->find($interviewId);

            if (!$interview || (int) $interview->tenant_id !== $tenantId) {
                return false;
            }

            // Only the job poster (proposed_by) can cancel
            if ((int) $interview->proposed_by !== $userId) {
                return false;
            }

            if (in_array($interview->status, ['completed', 'cancelled'], true)) {
                return false;
            }

            $interview->update(['status' => 'cancelled']);

            // Notify the candidate
            try {
                $jobTitle = $interview->application->vacancy->title ?? 'a job';
                $candidateId = $interview->application->user_id ?? null;
                if ($candidateId) {
                    $cancelMsg = __('emails_misc.jobs.interview_cancelled', ['title' => $jobTitle]);
                    Notification::createNotification(
                        (int) $candidateId,
                        $cancelMsg,
                        "/jobs/{$interview->vacancy_id}",
                        'job_application_status'
                    );
                    RealtimeService::broadcastAndPush((int) $candidateId, $cancelMsg, [
                        'type'      => 'job_interview_cancelled',
                        'job_id'    => (int) $interview->vacancy_id,
                        'job_title' => $jobTitle,
                        'message'   => $cancelMsg,
                        'url'       => "/jobs/{$interview->vacancy_id}",
                    ]);
                    static::sendInterviewEmail(
                        (int) $candidateId,
                        'emails_misc.jobs.interview_email_subject_cancelled',
                        'emails_misc.jobs.interview_cancelled',
                        ['title' => $jobTitle],
                        "/jobs/{$interview->vacancy_id}"
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('JobInterviewService::cancel notification failed: ' . $e->getMessage());
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('JobInterviewService::cancel failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Send interview reminders for upcoming interviews across all tenants.
     *
     * Sends reminders at two windows: 24 hours before and 1 hour before.
     * Uses a `reminder_sent_at` check to avoid duplicate reminders.
     * Called from the Laravel scheduler (bootstrap/app.php).
     *
     * @return array{reminders_sent: int, errors: int}
     */
    public static function sendReminders(): array
    {
        $sent = 0;
        $errors = 0;

        try {
            $now = now();

            // Find interviews scheduled in the next 24h that haven't been reminded
            $upcoming = JobInterview::with(['application.applicant:id,first_name,last_name', 'vacancy:id,title,user_id'])
                ->whereIn('status', ['proposed', 'accepted'])
                ->where('scheduled_at', '>', $now)
                ->where('scheduled_at', '<=', $now->copy()->addHours(24))
                ->whereNull('reminder_sent_at')
                ->get();

            foreach ($upcoming as $interview) {
                try {
                    // Set tenant context for this interview
                    TenantContext::setById((int) $interview->tenant_id);

                    $jobTitle = $interview->vacancy->title ?? 'a job';
                    $scheduledAt = $interview->scheduled_at->format('M j, g:i A');
                    $hoursUntil = (int) $now->diffInHours($interview->scheduled_at);

                    $timeLabel = $hoursUntil <= 1 ? __('emails_misc.jobs.interview_in_1_hour') : __('emails_misc.jobs.interview_in_hours', ['hours' => $hoursUntil]);
                    $message = __('emails_misc.jobs.interview_reminder', ['title' => $jobTitle, 'time_label' => $timeLabel, 'scheduled_at' => $scheduledAt]);

                    // Notify the candidate
                    $candidateId = $interview->application->user_id ?? null;
                    if ($candidateId) {
                        Notification::createNotification(
                            (int) $candidateId,
                            $message,
                            "/jobs/{$interview->vacancy_id}",
                            'job_interview_proposed'
                        );
                        RealtimeService::broadcastAndPush((int) $candidateId, __('emails_misc.jobs.interview_reminder_push_title'), [
                            'type'      => 'job_interview_reminder',
                            'job_id'    => (int) $interview->vacancy_id,
                            'job_title' => $jobTitle,
                            'message'   => $message,
                            'url'       => "/jobs/{$interview->vacancy_id}",
                        ]);
                        static::sendInterviewEmail(
                            (int) $candidateId,
                            'emails_misc.jobs.interview_email_subject_reminder',
                            'emails_misc.jobs.interview_reminder',
                            ['title' => $jobTitle, 'time_label' => $timeLabel, 'scheduled_at' => $scheduledAt],
                            "/jobs/{$interview->vacancy_id}"
                        );
                    }

                    // Notify the employer/interviewer
                    $posterId = $interview->vacancy->user_id ?? null;
                    if ($posterId) {
                        Notification::createNotification(
                            (int) $posterId,
                            $message,
                            "/jobs/{$interview->vacancy_id}/applications",
                            'job_interview_proposed'
                        );
                        RealtimeService::broadcastAndPush((int) $posterId, __('emails_misc.jobs.interview_reminder_push_title'), [
                            'type'      => 'job_interview_reminder',
                            'job_id'    => (int) $interview->vacancy_id,
                            'job_title' => $jobTitle,
                            'message'   => $message,
                            'url'       => "/jobs/{$interview->vacancy_id}/applications",
                        ]);
                        static::sendInterviewEmail(
                            (int) $posterId,
                            'emails_misc.jobs.interview_email_subject_reminder',
                            'emails_misc.jobs.interview_reminder',
                            ['title' => $jobTitle, 'time_label' => $timeLabel, 'scheduled_at' => $scheduledAt],
                            "/jobs/{$interview->vacancy_id}/applications"
                        );
                    }

                    // Mark as reminded to prevent duplicate sends
                    $interview->update(['reminder_sent_at' => $now]);
                    $sent++;
                } catch (\Throwable $e) {
                    $errors++;
                    Log::warning('JobInterviewService::sendReminders failed for interview ' . $interview->id, [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('JobInterviewService::sendReminders failed', ['error' => $e->getMessage()]);
        }

        return ['reminders_sent' => $sent, 'errors' => $errors];
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Send an interview lifecycle email to a user.
     *
     * @param int    $userId     Recipient user ID
     * @param string $subjectKey Translation key for the email subject
     * @param string $messageKey Translation key for the notification message (reused as body)
     * @param array  $params     Translation params (must include 'title')
     * @param string $jobLink    URL to include as CTA
     */
    private static function sendInterviewEmail(int $userId, string $subjectKey, string $messageKey, array $params, string $jobLink): void
    {
        try {
            $tenantId = TenantContext::getId();
            $user     = DB::table('users')->where('id', $userId)->where('tenant_id', $tenantId)->select(['email', 'first_name', 'name'])->first();

            if (!$user || empty($user->email)) {
                return;
            }

            $firstName  = $user->first_name ?? $user->name ?? __('emails.common.fallback_name');
            $bodyText   = __($messageKey, $params);
            $subject    = __($subjectKey, $params);
            $fullUrl    = TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix() . $jobLink;

            $html = EmailTemplateBuilder::make()
                ->title(__('emails_misc.jobs.interview_email_title'))
                ->previewText($bodyText)
                ->greeting($firstName)
                ->paragraph($bodyText)
                ->button(__('emails_misc.jobs.interview_email_cta'), $fullUrl)
                ->render();

            if (!Mailer::forCurrentTenant()->send($user->email, $subject, $html)) {
                Log::warning('[JobInterviewService] Interview email failed', ['user_id' => $userId, 'subject_key' => $subjectKey]);
            }
        } catch (\Throwable $e) {
            Log::warning('[JobInterviewService] sendInterviewEmail error: ' . $e->getMessage());
        }
    }
}
