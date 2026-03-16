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
        return \Nexus\Services\AbuseDetectionService::runAllChecks();
    }

    /**
     * Delegates to legacy AbuseDetectionService::checkLargeTransfers().
     */
    public function checkLargeTransfers($threshold = null)
    {
        return \Nexus\Services\AbuseDetectionService::checkLargeTransfers($threshold);
    }

    /**
     * Delegates to legacy AbuseDetectionService::checkHighVelocity().
     */
    public function checkHighVelocity($threshold = null)
    {
        return \Nexus\Services\AbuseDetectionService::checkHighVelocity($threshold);
    }

    /**
     * Delegates to legacy AbuseDetectionService::checkCircularTransfers().
     */
    public function checkCircularTransfers($windowHours = null)
    {
        return \Nexus\Services\AbuseDetectionService::checkCircularTransfers($windowHours);
    }

    /**
     * Delegates to legacy AbuseDetectionService::checkInactiveHighBalances().
     */
    public function checkInactiveHighBalances()
    {
        return \Nexus\Services\AbuseDetectionService::checkInactiveHighBalances();
    }
}
