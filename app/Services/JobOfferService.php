<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\JobApplication;
use App\Models\JobOffer;
use App\Models\Notification;
use App\Services\WebhookDispatchService;
use Illuminate\Support\Facades\Log;

/**
 * JobOfferService — Manages job offers sent by employers to candidates.
 *
 * All queries are tenant-scoped via TenantContext::getId().
 */
class JobOfferService
{
    /**
     * Create an offer (employer sends offer to applicant).
     *
     * @param int   $applicationId   The application ID.
     * @param int   $employerUserId  The job poster's user ID.
     * @param array $data            salary_offered, salary_currency, salary_type, start_date, message, expires_at
     * @return array|false           The created offer as array, or false on failure.
     */
    public static function create(int $applicationId, int $employerUserId, array $data): array|false
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

            // Only the job poster can create an offer
            if ((int) $application->vacancy->user_id !== $employerUserId) {
                return false;
            }

            // Enforce one offer per application (UNIQUE constraint on application_id)
            $existing = JobOffer::where('application_id', $applicationId)->exists();
            if ($existing) {
                return false;
            }

            $offer = JobOffer::create([
                'tenant_id'      => $tenantId,
                'vacancy_id'     => (int) $application->vacancy_id,
                'application_id' => $applicationId,
                'salary_offered' => isset($data['salary_offered']) ? (float) $data['salary_offered'] : null,
                'salary_currency' => $data['salary_currency'] ?? 'EUR',
                'salary_type'    => $data['salary_type'] ?? null,
                'start_date'     => $data['start_date'] ?? null,
                'message'        => isset($data['message']) ? trim($data['message']) : null,
                'status'         => 'pending',
                'expires_at'     => $data['expires_at'] ?? null,
            ]);

            // Notify the candidate
            try {
                $jobTitle = $application->vacancy->title ?? 'a job';
                Notification::createNotification(
                    (int) $application->user_id,
                    "You have received a job offer for {$jobTitle}",
                    "/jobs/{$application->vacancy_id}",
                    'job_application_status'
                );
            } catch (\Throwable $e) {
                Log::warning('JobOfferService::create notification failed: ' . $e->getMessage());
            }

            return $offer->toArray();
        } catch (\Throwable $e) {
            Log::error('JobOfferService::create failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Accept an offer (candidate accepts).
     *
     * On accept: update application status to 'accepted', update vacancy status to 'filled'.
     *
     * @param int $offerId         The offer ID.
     * @param int $candidateUserId The candidate's user ID.
     * @return bool
     */
    public static function accept(int $offerId, int $candidateUserId): bool
    {
        $tenantId = TenantContext::getId();

        try {
            $offer = JobOffer::with(['application.vacancy'])->find($offerId);

            if (!$offer || (int) $offer->tenant_id !== $tenantId) {
                return false;
            }

            // Only the applicant can accept the offer
            if (!$offer->application || (int) $offer->application->user_id !== $candidateUserId) {
                return false;
            }

            if ($offer->status !== 'pending') {
                return false;
            }

            $offer->update([
                'status'       => 'accepted',
                'responded_at' => now(),
            ]);

            // Update application status to accepted
            $offer->application->update([
                'status' => 'accepted',
                'stage'  => 'accepted',
            ]);

            // Update vacancy status to filled
            if ($offer->application->vacancy) {
                $offer->application->vacancy->update(['status' => 'filled']);
            }

            // Notify the job poster
            try {
                $jobTitle = $offer->application->vacancy->title ?? 'a job';
                $posterId = $offer->application->vacancy->user_id ?? null;
                if ($posterId) {
                    Notification::createNotification(
                        (int) $posterId,
                        "Offer accepted for {$jobTitle}",
                        "/jobs/{$offer->vacancy_id}/applications",
                        'job_application_status'
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('JobOfferService::accept notification failed: ' . $e->getMessage());
            }

            // Dispatch webhook
            try {
                WebhookDispatchService::dispatch('job.offer.accepted', [
                    'offer_id'       => $offer->id,
                    'vacancy_id'     => $offer->vacancy_id,
                    'application_id' => $offer->application_id,
                    'user_id'        => $candidateUserId,
                    'tenant_id'      => $tenantId,
                ]);
            } catch (\Throwable $e) {
                Log::warning('JobOfferService::accept webhook dispatch failed: ' . $e->getMessage());
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('JobOfferService::accept failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Reject an offer (candidate rejects).
     *
     * @param int $offerId         The offer ID.
     * @param int $candidateUserId The candidate's user ID.
     * @return bool
     */
    public static function reject(int $offerId, int $candidateUserId): bool
    {
        $tenantId = TenantContext::getId();

        try {
            $offer = JobOffer::with(['application.vacancy'])->find($offerId);

            if (!$offer || (int) $offer->tenant_id !== $tenantId) {
                return false;
            }

            // Only the applicant can reject the offer
            if (!$offer->application || (int) $offer->application->user_id !== $candidateUserId) {
                return false;
            }

            if ($offer->status !== 'pending') {
                return false;
            }

            $offer->update([
                'status'       => 'rejected',
                'responded_at' => now(),
            ]);

            // Notify the job poster
            try {
                $jobTitle = $offer->application->vacancy->title ?? 'a job';
                $posterId = $offer->application->vacancy->user_id ?? null;
                if ($posterId) {
                    Notification::createNotification(
                        (int) $posterId,
                        "Offer rejected for {$jobTitle}",
                        "/jobs/{$offer->vacancy_id}/applications",
                        'job_application_status'
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('JobOfferService::reject notification failed: ' . $e->getMessage());
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('JobOfferService::reject failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Withdraw an offer (employer withdraws).
     *
     * @param int $offerId        The offer ID.
     * @param int $employerUserId The employer's user ID.
     * @return bool
     */
    public static function withdraw(int $offerId, int $employerUserId): bool
    {
        $tenantId = TenantContext::getId();

        try {
            $offer = JobOffer::with(['application.vacancy'])->find($offerId);

            if (!$offer || (int) $offer->tenant_id !== $tenantId) {
                return false;
            }

            // Only the job poster can withdraw the offer
            if (!$offer->application || !$offer->application->vacancy ||
                (int) $offer->application->vacancy->user_id !== $employerUserId) {
                return false;
            }

            if (!in_array($offer->status, ['pending'], true)) {
                return false;
            }

            $offer->update(['status' => 'withdrawn']);

            // Notify the candidate
            try {
                $jobTitle = $offer->application->vacancy->title ?? 'a job';
                $candidateId = $offer->application->user_id ?? null;
                if ($candidateId) {
                    Notification::createNotification(
                        (int) $candidateId,
                        "Job offer withdrawn for {$jobTitle}",
                        "/jobs/{$offer->vacancy_id}",
                        'job_application_status'
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('JobOfferService::withdraw notification failed: ' . $e->getMessage());
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('JobOfferService::withdraw failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get the offer for a specific application.
     *
     * @param int $applicationId
     * @param int $userId        Must be either the applicant or the job poster.
     * @return array|null
     */
    public static function getForApplication(int $applicationId, int $userId): ?array
    {
        $tenantId = TenantContext::getId();

        try {
            $offer = JobOffer::with(['application.vacancy'])
                ->where('tenant_id', $tenantId)
                ->where('application_id', $applicationId)
                ->first();

            if (!$offer) {
                return null;
            }

            // Access control: must be applicant or job poster
            $isApplicant = $offer->application && (int) $offer->application->user_id === $userId;
            $isPoster = $offer->application && $offer->application->vacancy &&
                        (int) $offer->application->vacancy->user_id === $userId;

            if (!$isApplicant && !$isPoster) {
                return null;
            }

            return $offer->toArray();
        } catch (\Throwable $e) {
            Log::error('JobOfferService::getForApplication failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get all pending offers for the current user (candidate view).
     *
     * @param int $userId
     * @return array
     */
    public static function getForUser(int $userId): array
    {
        $tenantId = TenantContext::getId();

        try {
            return JobOffer::with(['vacancy:id,title,user_id', 'application:id,user_id,vacancy_id,status'])
                ->where('tenant_id', $tenantId)
                ->whereHas('application', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                })
                ->orderByDesc('created_at')
                ->get()
                ->toArray();
        } catch (\Throwable $e) {
            Log::error('JobOfferService::getForUser failed', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
