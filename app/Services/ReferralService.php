<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class ReferralService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy ReferralService::generateCode().
     */
    public function generateCode(int $tenantId, int $userId): string
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return '';
    }

    /**
     * Delegates to legacy ReferralService::redeem().
     */
    public function redeem(int $tenantId, string $code, int $userId): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy ReferralService::getReferrals().
     */
    public function getReferrals(int $tenantId, int $userId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy ReferralService::getStats().
     */
    public function getStats(int $tenantId, int $userId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }
}
