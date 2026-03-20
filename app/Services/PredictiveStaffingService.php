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
class PredictiveStaffingService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy PredictiveStaffingService::predictShortages().
     */
    public function predictShortages(int $tenantId, int $nextDays = 14): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy PredictiveStaffingService::alertCoordinators().
     */
    public function alertCoordinators(int $tenantId, array $criticalPredictions): void
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
    }

    /**
     * Delegates to legacy PredictiveStaffingService::getDashboardSummary().
     */
    public function getDashboardSummary(int $tenantId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }
}
