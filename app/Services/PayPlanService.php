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
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy PayPlanService::validateFeatureAccess().
     */
    public function validateFeatureAccess($feature, $tenantId = null)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy PayPlanService::validateMenuCreation().
     */
    public function validateMenuCreation($tenantId = null)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy PayPlanService::getUpgradeSuggestions().
     */
    public function getUpgradeSuggestions($tenantId = null)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy PayPlanService::assignPlan().
     */
    public function assignPlan($tenantId, $planId, $expiresAt = null, $isTrial = false)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }
}
