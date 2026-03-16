<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * VolunteerCheckInService — Laravel DI wrapper for legacy \Nexus\Services\VolunteerCheckInService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class VolunteerCheckInService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy VolunteerCheckInService::checkIn().
     */
    public function checkIn(int $tenantId, int $opportunityId, int $userId): bool
    {
        return \Nexus\Services\VolunteerCheckInService::checkIn($tenantId, $opportunityId, $userId);
    }

    /**
     * Delegates to legacy VolunteerCheckInService::checkOut().
     */
    public function checkOut(int $tenantId, int $opportunityId, int $userId, ?float $hours = null): bool
    {
        return \Nexus\Services\VolunteerCheckInService::checkOut($tenantId, $opportunityId, $userId, $hours);
    }

    /**
     * Delegates to legacy VolunteerCheckInService::getCheckIns().
     */
    public function getCheckIns(int $tenantId, int $opportunityId): array
    {
        return \Nexus\Services\VolunteerCheckInService::getCheckIns($tenantId, $opportunityId);
    }

    /**
     * Delegates to legacy VolunteerCheckInService::isCheckedIn().
     */
    public function isCheckedIn(int $tenantId, int $opportunityId, int $userId): bool
    {
        return \Nexus\Services\VolunteerCheckInService::isCheckedIn($tenantId, $opportunityId, $userId);
    }
}
