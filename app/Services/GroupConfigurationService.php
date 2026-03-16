<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * GroupConfigurationService — Laravel DI wrapper for legacy \Nexus\Services\GroupConfigurationService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class GroupConfigurationService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy GroupConfigurationService::get().
     */
    public function get($key, $tenantId = null)
    {
        return \Nexus\Services\GroupConfigurationService::get($key, $tenantId);
    }

    /**
     * Delegates to legacy GroupConfigurationService::set().
     */
    public function set($key, $config_value, $tenantId = null)
    {
        return \Nexus\Services\GroupConfigurationService::set($key, $config_value, $tenantId);
    }

    /**
     * Delegates to legacy GroupConfigurationService::setMultiple().
     */
    public function setMultiple(array $configs, $tenantId = null)
    {
        return \Nexus\Services\GroupConfigurationService::setMultiple($configs, $tenantId);
    }

    /**
     * Delegates to legacy GroupConfigurationService::getAll().
     */
    public function getAll($tenantId = null)
    {
        return \Nexus\Services\GroupConfigurationService::getAll($tenantId);
    }

    /**
     * Delegates to legacy GroupConfigurationService::resetToDefaults().
     */
    public function resetToDefaults($tenantId = null)
    {
        return \Nexus\Services\GroupConfigurationService::resetToDefaults($tenantId);
    }
}
