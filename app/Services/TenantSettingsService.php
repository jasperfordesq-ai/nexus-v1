<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * TenantSettingsService — Laravel DI wrapper for legacy \Nexus\Services\TenantSettingsService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class TenantSettingsService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy TenantSettingsService::get().
     */
    public function get(int $tenantId, string $key, $default = null)
    {
        return \Nexus\Services\TenantSettingsService::get($tenantId, $key, $default);
    }

    /**
     * Delegates to legacy TenantSettingsService::getBool().
     */
    public function getBool(int $tenantId, string $key, bool $default = false): bool
    {
        return \Nexus\Services\TenantSettingsService::getBool($tenantId, $key, $default);
    }

    /**
     * Delegates to legacy TenantSettingsService::getAllGeneral().
     */
    public function getAllGeneral(int $tenantId): array
    {
        return \Nexus\Services\TenantSettingsService::getAllGeneral($tenantId);
    }

    /**
     * Delegates to legacy TenantSettingsService::clearCache().
     */
    public function clearCache(): void
    {
        \Nexus\Services\TenantSettingsService::clearCache();
    }

    /**
     * Delegates to legacy TenantSettingsService::isRegistrationOpen().
     */
    public function isRegistrationOpen(int $tenantId): bool
    {
        return \Nexus\Services\TenantSettingsService::isRegistrationOpen($tenantId);
    }
}
