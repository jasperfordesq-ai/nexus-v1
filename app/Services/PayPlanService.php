<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * PayPlanService — Laravel DI wrapper for legacy \Nexus\Services\PayPlanService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class PayPlanService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy PayPlanService::validateLayoutAccess().
     */
    public function validateLayoutAccess($layout, $tenantId = null)
    {
        return \Nexus\Services\PayPlanService::validateLayoutAccess($layout, $tenantId);
    }

    /**
     * Delegates to legacy PayPlanService::validateFeatureAccess().
     */
    public function validateFeatureAccess($feature, $tenantId = null)
    {
        return \Nexus\Services\PayPlanService::validateFeatureAccess($feature, $tenantId);
    }

    /**
     * Delegates to legacy PayPlanService::validateMenuCreation().
     */
    public function validateMenuCreation($tenantId = null)
    {
        return \Nexus\Services\PayPlanService::validateMenuCreation($tenantId);
    }

    /**
     * Delegates to legacy PayPlanService::getUpgradeSuggestions().
     */
    public function getUpgradeSuggestions($tenantId = null)
    {
        return \Nexus\Services\PayPlanService::getUpgradeSuggestions($tenantId);
    }

    /**
     * Delegates to legacy PayPlanService::assignPlan().
     */
    public function assignPlan($tenantId, $planId, $expiresAt = null, $isTrial = false)
    {
        return \Nexus\Services\PayPlanService::assignPlan($tenantId, $planId, $expiresAt, $isTrial);
    }
}
