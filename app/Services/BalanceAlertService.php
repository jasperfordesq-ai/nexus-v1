<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * BalanceAlertService — Laravel DI wrapper for legacy \Nexus\Services\BalanceAlertService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class BalanceAlertService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy BalanceAlertService::checkAllBalances().
     */
    public function checkAllBalances()
    {
        if (!class_exists('\Nexus\Services\BalanceAlertService')) { return null; }
        return \Nexus\Services\BalanceAlertService::checkAllBalances();
    }

    /**
     * Delegates to legacy BalanceAlertService::checkBalance().
     */
    public function checkBalance($organizationId, $balance = null, $orgName = null)
    {
        if (!class_exists('\Nexus\Services\BalanceAlertService')) { return null; }
        return \Nexus\Services\BalanceAlertService::checkBalance($organizationId, $balance, $orgName);
    }

    /**
     * Delegates to legacy BalanceAlertService::getThresholds().
     */
    public function getThresholds($organizationId)
    {
        if (!class_exists('\Nexus\Services\BalanceAlertService')) { return null; }
        return \Nexus\Services\BalanceAlertService::getThresholds($organizationId);
    }

    /**
     * Delegates to legacy BalanceAlertService::areAlertsEnabled().
     */
    public function areAlertsEnabled($organizationId)
    {
        if (!class_exists('\Nexus\Services\BalanceAlertService')) { return null; }
        return \Nexus\Services\BalanceAlertService::areAlertsEnabled($organizationId);
    }

    /**
     * Delegates to legacy BalanceAlertService::setThresholds().
     */
    public function setThresholds($organizationId, $lowThreshold, $criticalThreshold)
    {
        if (!class_exists('\Nexus\Services\BalanceAlertService')) { return null; }
        return \Nexus\Services\BalanceAlertService::setThresholds($organizationId, $lowThreshold, $criticalThreshold);
    }
}
