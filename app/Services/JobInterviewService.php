<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\JobApplication;
use App\Models\JobInterview;
use App\Models\Notification;
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
                Notification::createNotification(
                    (int) $application->user_id,
                    "Interview requested for {$jobTitle}",
                    "/jobs/{$application->vacancy_id}",
                    'job_application'
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
                    Notification::createNotification(
                        (int) $posterId,
                        "Interview accepted for {$jobTitle}",
                        "/jobs/{$interview->vacancy_id}/applications",
                        'job_application_status'
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
                    Notification::createNotification(
                        (int) $posterId,
                        "Interview declined for {$jobTitle}",
                        "/jobs/{$interview->vacancy_id}/applications",
                        'job_application_status'
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
                    Notification::createNotification(
                        (int) $candidateId,
                        "Interview cancelled for {$jobTitle}",
                        "/jobs/{$interview->vacancy_id}",
                        'job_application_status'
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
}
