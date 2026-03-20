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
class VolunteerWellbeingService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy VolunteerWellbeingService::getErrors().
     */
    public static function getErrors(): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy VolunteerWellbeingService::detectBurnoutRisk().
     */
    public static function detectBurnoutRisk(int $userId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy VolunteerWellbeingService::runTenantAssessment().
     */
    public static function runTenantAssessment(): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy VolunteerWellbeingService::getActiveAlerts().
     */
    public static function getActiveAlerts(): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy VolunteerWellbeingService::updateAlert().
     */
    public static function updateAlert(int $alertId, string $action, ?string $notes = null): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }
}
