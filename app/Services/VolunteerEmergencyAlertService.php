<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * VolunteerEmergencyAlertService — Laravel DI wrapper for legacy \Nexus\Services\VolunteerEmergencyAlertService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class VolunteerEmergencyAlertService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy VolunteerEmergencyAlertService::getErrors().
     */
    public function getErrors(): array
    {
        return \Nexus\Services\VolunteerEmergencyAlertService::getErrors();
    }

    /**
     * Delegates to legacy VolunteerEmergencyAlertService::createAlert().
     */
    public function createAlert(int $createdBy, array $data): ?int
    {
        return \Nexus\Services\VolunteerEmergencyAlertService::createAlert($createdBy, $data);
    }

    /**
     * Delegates to legacy VolunteerEmergencyAlertService::respond().
     */
    public function respond(int $alertId, int $userId, string $response): bool
    {
        return \Nexus\Services\VolunteerEmergencyAlertService::respond($alertId, $userId, $response);
    }

    /**
     * Delegates to legacy VolunteerEmergencyAlertService::getUserAlerts().
     */
    public function getUserAlerts(int $userId): array
    {
        return \Nexus\Services\VolunteerEmergencyAlertService::getUserAlerts($userId);
    }

    /**
     * Delegates to legacy VolunteerEmergencyAlertService::getCoordinatorAlerts().
     */
    public function getCoordinatorAlerts(int $coordinatorId): array
    {
        return \Nexus\Services\VolunteerEmergencyAlertService::getCoordinatorAlerts($coordinatorId);
    }
}
