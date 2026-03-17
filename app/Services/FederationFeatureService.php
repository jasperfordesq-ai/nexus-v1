<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * FederationFeatureService — Laravel DI wrapper for legacy \Nexus\Services\FederationFeatureService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class FederationFeatureService
{
    /** Feature key for tenant-level federation toggle. */
    public const TENANT_FEDERATION_ENABLED = 'tenant_federation_enabled';

    public function __construct()
    {
    }

    /**
     * Delegates to legacy FederationFeatureService::getSystemControls().
     */
    public function getSystemControls(): array
    {
        return \Nexus\Services\FederationFeatureService::getSystemControls();
    }

    /**
     * Delegates to legacy FederationFeatureService::isGloballyEnabled().
     */
    public function isGloballyEnabled(): bool
    {
        return \Nexus\Services\FederationFeatureService::isGloballyEnabled();
    }

    /**
     * Delegates to legacy FederationFeatureService::isWhitelistModeActive().
     */
    public function isWhitelistModeActive(): bool
    {
        return \Nexus\Services\FederationFeatureService::isWhitelistModeActive();
    }

    /**
     * Delegates to legacy FederationFeatureService::isTenantWhitelisted().
     */
    public function isTenantWhitelisted(int $tenantId): bool
    {
        return \Nexus\Services\FederationFeatureService::isTenantWhitelisted($tenantId);
    }

    /**
     * Delegates to legacy FederationFeatureService::isSystemFeatureEnabled().
     */
    public function isSystemFeatureEnabled(string $feature): bool
    {
        return \Nexus\Services\FederationFeatureService::isSystemFeatureEnabled($feature);
    }

    /**
     * Enable a federation feature for a tenant.
     */
    public function enableTenantFeature(string $feature, ?int $tenantId = null): bool
    {
        return \Nexus\Services\FederationFeatureService::enableTenantFeature($feature, $tenantId);
    }

    /**
     * Disable a federation feature for a tenant.
     */
    public function disableTenantFeature(string $feature, ?int $tenantId = null): bool
    {
        return \Nexus\Services\FederationFeatureService::disableTenantFeature($feature, $tenantId);
    }
}
