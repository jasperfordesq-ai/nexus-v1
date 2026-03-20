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
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy GroupReportingService::generateCustomReport().
     */
    public function generateCustomReport($groupId, $startDate, $endDate)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy GroupReportingService::sendAllWeeklyDigests().
     */
    public function sendAllWeeklyDigests()
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }
}
