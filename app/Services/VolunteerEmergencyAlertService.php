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
        if (!class_exists('\Nexus\Services\VolunteerEmergencyAlertService')) { return []; }
        return \Nexus\Services\VolunteerEmergencyAlertService::getErrors();
    }

    /**
     * Delegates to legacy VolunteerEmergencyAlertService::createAlert().
     */
    public function createAlert(int $createdBy, array $data): ?int
    {
        if (!class_exists('\Nexus\Services\VolunteerEmergencyAlertService')) { return null; }
        return \Nexus\Services\VolunteerEmergencyAlertService::createAlert($createdBy, $data);
    }

    /**
     * Delegates to legacy VolunteerEmergencyAlertService::respond().
     */
    public function respond(int $alertId, int $userId, string $response): bool
    {
        if (!class_exists('\Nexus\Services\VolunteerEmergencyAlertService')) { return false; }
        return \Nexus\Services\VolunteerEmergencyAlertService::respond($alertId, $userId, $response);
    }

    /**
     * Delegates to legacy VolunteerEmergencyAlertService::getUserAlerts().
     */
    public function getUserAlerts(int $userId): array
    {
        if (!class_exists('\Nexus\Services\VolunteerEmergencyAlertService')) { return []; }
        return \Nexus\Services\VolunteerEmergencyAlertService::getUserAlerts($userId);
    }

    /**
     * Delegates to legacy VolunteerEmergencyAlertService::getCoordinatorAlerts().
     */
    public function getCoordinatorAlerts(int $coordinatorId): array
    {
        if (!class_exists('\Nexus\Services\VolunteerEmergencyAlertService')) { return []; }
        return \Nexus\Services\VolunteerEmergencyAlertService::getCoordinatorAlerts($coordinatorId);
    }

    /**
     * Cancel an active emergency alert.
     *
     * @param int $alertId  Alert ID
     * @param int $userId   User cancelling (must be the creator)
     * @param int $tenantId Tenant ID
     * @return bool Success
     */
    public function cancelAlert(int $alertId, int $userId, int $tenantId): bool
    {
        $this->errors = [];

        $alert = \Illuminate\Support\Facades\DB::selectOne(
            "SELECT * FROM vol_emergency_alerts WHERE id = ? AND created_by = ? AND status = 'active' AND tenant_id = ?",
            [$alertId, $userId, $tenantId]
        );

        if (!$alert) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'Alert not found or cannot be cancelled'];
            return false;
        }

        try {
            \Illuminate\Support\Facades\DB::update(
                "UPDATE vol_emergency_alerts SET status = 'cancelled' WHERE id = ? AND tenant_id = ?",
                [$alertId, $tenantId]
            );
            return true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('VolunteerEmergencyAlertService::cancelAlert error: ' . $e->getMessage());
            $this->errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to cancel alert'];
            return false;
        }
    }

    /** @var array */
    private array $errors = [];

    /**
     * Get errors from the last cancelAlert() call.
     *
     * @return array
     */
    public function getCancelErrors(): array
    {
        return $this->errors;
    }
}
