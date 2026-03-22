<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\JobSavedProfile;
use Illuminate\Support\Facades\Log;

class JobSavedProfileService
{
    /**
     * Get the saved profile for the current user (if it exists).
     */
    public static function get(int $userId): ?array
    {
        $tenantId = TenantContext::getId();

        try {
            $profile = JobSavedProfile::where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->first();

            return $profile?->toArray();
        } catch (\Throwable $e) {
            Log::error('JobSavedProfileService::get failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Save or update the current user's application profile.
     */
    public static function save(int $userId, array $data): array|false
    {
        $tenantId = TenantContext::getId();

        try {
            $profile = JobSavedProfile::updateOrCreate(
                ['tenant_id' => $tenantId, 'user_id' => $userId],
                array_filter([
                    'cv_path'     => $data['cv_path'] ?? null,
                    'cv_filename' => $data['cv_filename'] ?? null,
                    'cv_size'     => isset($data['cv_size']) ? (int) $data['cv_size'] : null,
                    'headline'    => isset($data['headline']) ? trim($data['headline']) : null,
                    'cover_text'  => isset($data['cover_text']) ? trim($data['cover_text']) : null,
                ], fn($v) => $v !== null)
            );

            return $profile->toArray();
        } catch (\Throwable $e) {
            Log::error('JobSavedProfileService::save failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
