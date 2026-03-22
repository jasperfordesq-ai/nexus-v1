<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\JobReferral;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class JobReferralService
{
    /**
     * Create a shareable referral token for a vacancy.
     * If the user has already created one, return the existing token.
     */
    public static function getOrCreate(int $vacancyId, ?int $referrerUserId): array
    {
        $tenantId = TenantContext::getId();

        try {
            // Try to find existing token for this referrer+vacancy
            if ($referrerUserId) {
                $existing = JobReferral::where('tenant_id', $tenantId)
                    ->where('vacancy_id', $vacancyId)
                    ->where('referrer_user_id', $referrerUserId)
                    ->where('applied', false)
                    ->first();

                if ($existing) {
                    return $existing->toArray();
                }
            }

            $token = Str::random(32);
            $referral = JobReferral::create([
                'tenant_id'        => $tenantId,
                'vacancy_id'       => $vacancyId,
                'referrer_user_id' => $referrerUserId,
                'ref_token'        => $token,
                'applied'          => false,
                'created_at'       => now(),
            ]);

            return $referral->toArray();
        } catch (\Throwable $e) {
            Log::error('JobReferralService::getOrCreate failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Mark a referral as applied when a user applies via a ref token.
     */
    public static function markApplied(string $refToken, int $appliedUserId): void
    {
        $tenantId = TenantContext::getId();

        try {
            JobReferral::where('tenant_id', $tenantId)
                ->where('ref_token', $refToken)
                ->where('applied', false)
                ->update([
                    'applied'          => true,
                    'referred_user_id' => $appliedUserId,
                    'applied_at'       => now(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('JobReferralService::markApplied failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get referral stats for a vacancy (employer view).
     */
    public static function getStats(int $vacancyId): array
    {
        $tenantId = TenantContext::getId();

        try {
            $total   = JobReferral::where('tenant_id', $tenantId)->where('vacancy_id', $vacancyId)->count();
            $applied = JobReferral::where('tenant_id', $tenantId)->where('vacancy_id', $vacancyId)->where('applied', true)->count();

            return ['total_shares' => $total, 'converted_applications' => $applied];
        } catch (\Throwable $e) {
            Log::error('JobReferralService::getStats failed', ['error' => $e->getMessage()]);
            return ['total_shares' => 0, 'converted_applications' => 0];
        }
    }
}
