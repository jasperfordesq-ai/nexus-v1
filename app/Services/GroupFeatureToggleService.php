<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * GroupFeatureToggleService — Laravel DI wrapper for legacy \Nexus\Services\GroupFeatureToggleService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class GroupFeatureToggleService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy GroupFeatureToggleService::isEnabled().
     */
    public function isEnabled($feature, $tenantId = null)
    {
        return \Nexus\Services\GroupFeatureToggleService::isEnabled($feature, $tenantId);
    }

    /**
     * Delegates to legacy GroupFeatureToggleService::enable().
     */
    public function enable($feature, $tenantId = null)
    {
        return \Nexus\Services\GroupFeatureToggleService::enable($feature, $tenantId);
    }

    /**
     * Delegates to legacy GroupFeatureToggleService::disable().
     */
    public function disable($feature, $tenantId = null)
    {
        return \Nexus\Services\GroupFeatureToggleService::disable($feature, $tenantId);
    }

    /**
     * Delegates to legacy GroupFeatureToggleService::getAllFeatures().
     */
    public function getAllFeatures($tenantId = null)
    {
        return \Nexus\Services\GroupFeatureToggleService::getAllFeatures($tenantId);
    }

    /**
     * Delegates to legacy GroupFeatureToggleService::bulkSet().
     */
    public function bulkSet(array $features, $tenantId = null)
    {
        return \Nexus\Services\GroupFeatureToggleService::bulkSet($features, $tenantId);
    }
}
