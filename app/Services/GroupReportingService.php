<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * GroupReportingService — Laravel DI wrapper for legacy \Nexus\Services\GroupReportingService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class GroupReportingService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy GroupReportingService::generateWeeklyDigest().
     */
    public function generateWeeklyDigest($groupId, $ownerId)
    {
        return \Nexus\Services\GroupReportingService::generateWeeklyDigest($groupId, $ownerId);
    }

    /**
     * Delegates to legacy GroupReportingService::generateCustomReport().
     */
    public function generateCustomReport($groupId, $startDate, $endDate)
    {
        return \Nexus\Services\GroupReportingService::generateCustomReport($groupId, $startDate, $endDate);
    }

    /**
     * Delegates to legacy GroupReportingService::sendAllWeeklyDigests().
     */
    public function sendAllWeeklyDigests()
    {
        return \Nexus\Services\GroupReportingService::sendAllWeeklyDigests();
    }
}
