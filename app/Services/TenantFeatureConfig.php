<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * TenantFeatureConfig — Laravel DI wrapper for legacy \Nexus\Services\TenantFeatureConfig.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class TenantFeatureConfig
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy TenantFeatureConfig::mergeFeatures().
     */
    public function mergeFeatures(?array $dbFeatures): array
    {
        return \Nexus\Services\TenantFeatureConfig::mergeFeatures($dbFeatures);
    }

    /**
     * Delegates to legacy TenantFeatureConfig::mergeModules().
     */
    public function mergeModules(?array $dbModules): array
    {
        return \Nexus\Services\TenantFeatureConfig::mergeModules($dbModules);
    }
}
