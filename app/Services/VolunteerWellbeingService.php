<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * VolunteerWellbeingService — Laravel DI wrapper for legacy \Nexus\Services\VolunteerWellbeingService.
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
    public function getErrors(): array
    {
        return \Nexus\Services\VolunteerWellbeingService::getErrors();
    }

    /**
     * Delegates to legacy VolunteerWellbeingService::detectBurnoutRisk().
     */
    public function detectBurnoutRisk(int $userId): array
    {
        return \Nexus\Services\VolunteerWellbeingService::detectBurnoutRisk($userId);
    }

    /**
     * Delegates to legacy VolunteerWellbeingService::runTenantAssessment().
     */
    public function runTenantAssessment(): array
    {
        return \Nexus\Services\VolunteerWellbeingService::runTenantAssessment();
    }

    /**
     * Delegates to legacy VolunteerWellbeingService::getActiveAlerts().
     */
    public function getActiveAlerts(): array
    {
        return \Nexus\Services\VolunteerWellbeingService::getActiveAlerts();
    }

    /**
     * Delegates to legacy VolunteerWellbeingService::updateAlert().
     */
    public function updateAlert(int $alertId, string $action, ?string $notes = null): bool
    {
        return \Nexus\Services\VolunteerWellbeingService::updateAlert($alertId, $action, $notes);
    }
}
