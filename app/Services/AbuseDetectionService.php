<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * AbuseDetectionService — Laravel DI wrapper for legacy \Nexus\Services\AbuseDetectionService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class AbuseDetectionService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy AbuseDetectionService::runAllChecks().
     */
    public function runAllChecks()
    {
        if (!class_exists('\Nexus\Services\AbuseDetectionService')) { return null; }
        return \Nexus\Services\AbuseDetectionService::runAllChecks();
    }

    /**
     * Delegates to legacy AbuseDetectionService::checkLargeTransfers().
     */
    public function checkLargeTransfers($threshold = null)
    {
        if (!class_exists('\Nexus\Services\AbuseDetectionService')) { return null; }
        return \Nexus\Services\AbuseDetectionService::checkLargeTransfers($threshold);
    }

    /**
     * Delegates to legacy AbuseDetectionService::checkHighVelocity().
     */
    public function checkHighVelocity($threshold = null)
    {
        if (!class_exists('\Nexus\Services\AbuseDetectionService')) { return null; }
        return \Nexus\Services\AbuseDetectionService::checkHighVelocity($threshold);
    }

    /**
     * Delegates to legacy AbuseDetectionService::checkCircularTransfers().
     */
    public function checkCircularTransfers($windowHours = null)
    {
        if (!class_exists('\Nexus\Services\AbuseDetectionService')) { return null; }
        return \Nexus\Services\AbuseDetectionService::checkCircularTransfers($windowHours);
    }

    /**
     * Delegates to legacy AbuseDetectionService::checkInactiveHighBalances().
     */
    public function checkInactiveHighBalances()
    {
        if (!class_exists('\Nexus\Services\AbuseDetectionService')) { return null; }
        return \Nexus\Services\AbuseDetectionService::checkInactiveHighBalances();
    }

    /**
     * Delegates to legacy AbuseDetectionService::updateAlertStatus().
     */
    public function updateAlertStatus(int $id, string $status, ?int $resolvedBy = null, ?string $notes = null): bool
    {
        if (!class_exists('\Nexus\Services\AbuseDetectionService')) { return false; }
        return \Nexus\Services\AbuseDetectionService::updateAlertStatus($id, $status, $resolvedBy, $notes);
    }
}
