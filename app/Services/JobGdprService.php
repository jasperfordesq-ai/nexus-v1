<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\JobApplication;
use App\Models\JobInterview;
use App\Models\JobOffer;
use App\Models\JobAlert;
use App\Models\JobSavedProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * JobGdprService — GDPR data export and erasure for job-related data.
 */
class JobGdprService
{
    /**
     * Export all job-related data for a user as a structured array.
     */
    public static function exportUserData(int $userId): array
    {
        $tenantId = TenantContext::getId();

        try {
            $applications = JobApplication::with(['vacancy:id,title,type,status,created_at'])
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->get()
                ->toArray();

            $interviews = JobInterview::with(['vacancy:id,title'])
                ->where('tenant_id', $tenantId)
                ->whereHas('application', fn($q) => $q->where('user_id', $userId))
                ->get()
                ->toArray();

            $offers = JobOffer::with(['vacancy:id,title'])
                ->where('tenant_id', $tenantId)
                ->whereHas('application', fn($q) => $q->where('user_id', $userId))
                ->get()
                ->toArray();

            $alerts = JobAlert::where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->get(['id','keywords','job_type','commitment','location_text','is_active','created_at'])
                ->toArray();

            $savedProfile = JobSavedProfile::where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->first(['cv_filename', 'cv_size', 'headline', 'cover_text', 'created_at', 'updated_at']);

            return [
                'exported_at'   => now()->toIso8601String(),
                'user_id'       => $userId,
                'tenant_id'     => $tenantId,
                'applications'  => $applications,
                'interviews'    => $interviews,
                'offers'        => $offers,
                'alerts'        => $alerts,
                'saved_profile' => $savedProfile ? $savedProfile->toArray() : null,
            ];
        } catch (\Throwable $e) {
            Log::error('JobGdprService::exportUserData failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Anonymise all job-related data for a user (GDPR right to erasure).
     * Keeps structural data (applications, interviews) but removes PII.
     */
    public static function eraseUserData(int $userId): bool
    {
        $tenantId = TenantContext::getId();

        try {
            DB::transaction(function () use ($userId, $tenantId) {
                // Anonymise applications: clear message, reviewer_notes, cv_path fields
                JobApplication::where('tenant_id', $tenantId)
                    ->where('user_id', $userId)
                    ->update([
                        'message'        => null,
                        'reviewer_notes' => null,
                        'cv_path'        => null,
                        'cv_filename'    => null,
                        'cv_size'        => null,
                    ]);

                // Delete alerts
                JobAlert::where('tenant_id', $tenantId)
                    ->where('user_id', $userId)
                    ->delete();

                // Delete saved profile
                JobSavedProfile::where('tenant_id', $tenantId)
                    ->where('user_id', $userId)
                    ->delete();
            });

            return true;
        } catch (\Throwable $e) {
            Log::error('JobGdprService::eraseUserData failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
