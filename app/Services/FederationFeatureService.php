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
    /** System-level constants — mirrored from legacy */
    public const SYSTEM_FEDERATION_ENABLED = \Nexus\Services\FederationFeatureService::SYSTEM_FEDERATION_ENABLED;
    public const SYSTEM_WHITELIST_MODE = \Nexus\Services\FederationFeatureService::SYSTEM_WHITELIST_MODE;
    public const SYSTEM_EMERGENCY_LOCKDOWN = \Nexus\Services\FederationFeatureService::SYSTEM_EMERGENCY_LOCKDOWN;
    public const SYSTEM_MAX_FEDERATION_LEVEL = \Nexus\Services\FederationFeatureService::SYSTEM_MAX_FEDERATION_LEVEL;
    public const SYSTEM_PROFILES_ENABLED = \Nexus\Services\FederationFeatureService::SYSTEM_PROFILES_ENABLED;
    public const SYSTEM_MESSAGING_ENABLED = \Nexus\Services\FederationFeatureService::SYSTEM_MESSAGING_ENABLED;
    public const SYSTEM_TRANSACTIONS_ENABLED = \Nexus\Services\FederationFeatureService::SYSTEM_TRANSACTIONS_ENABLED;
    public const SYSTEM_LISTINGS_ENABLED = \Nexus\Services\FederationFeatureService::SYSTEM_LISTINGS_ENABLED;
    public const SYSTEM_EVENTS_ENABLED = \Nexus\Services\FederationFeatureService::SYSTEM_EVENTS_ENABLED;
    public const SYSTEM_GROUPS_ENABLED = \Nexus\Services\FederationFeatureService::SYSTEM_GROUPS_ENABLED;

    /** Tenant-level constants — mirrored from legacy */
    public const TENANT_FEDERATION_ENABLED = \Nexus\Services\FederationFeatureService::TENANT_FEDERATION_ENABLED;
    public const TENANT_APPEAR_IN_DIRECTORY = \Nexus\Services\FederationFeatureService::TENANT_APPEAR_IN_DIRECTORY;
    public const TENANT_AUTO_ACCEPT_HIERARCHY = \Nexus\Services\FederationFeatureService::TENANT_AUTO_ACCEPT_HIERARCHY;
    public const TENANT_PROFILES_ENABLED = \Nexus\Services\FederationFeatureService::TENANT_PROFILES_ENABLED;
    public const TENANT_MESSAGING_ENABLED = \Nexus\Services\FederationFeatureService::TENANT_MESSAGING_ENABLED;
    public const TENANT_TRANSACTIONS_ENABLED = \Nexus\Services\FederationFeatureService::TENANT_TRANSACTIONS_ENABLED;
    public const TENANT_LISTINGS_ENABLED = \Nexus\Services\FederationFeatureService::TENANT_LISTINGS_ENABLED;
    public const TENANT_EVENTS_ENABLED = \Nexus\Services\FederationFeatureService::TENANT_EVENTS_ENABLED;
    public const TENANT_GROUPS_ENABLED = \Nexus\Services\FederationFeatureService::TENANT_GROUPS_ENABLED;

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
     * Get max federation level.
     */
    public function getMaxFederationLevel(): int
    {
        return \Nexus\Services\FederationFeatureService::getMaxFederationLevel();
    }

    /**
     * Check if a tenant has federation enabled.
     */
    public function isTenantFederationEnabled(?int $tenantId = null): bool
    {
        return \Nexus\Services\FederationFeatureService::isTenantFederationEnabled($tenantId);
    }

    /**
     * Check if a specific tenant feature is enabled.
     */
    public function isTenantFeatureEnabled(string $feature, ?int $tenantId = null): bool
    {
        return \Nexus\Services\FederationFeatureService::isTenantFeatureEnabled($feature, $tenantId);
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

    /**
     * Get all tenant features.
     */
    public function getAllTenantFeatures(?int $tenantId = null): array
    {
        return \Nexus\Services\FederationFeatureService::getAllTenantFeatures($tenantId);
    }

    /**
     * Check if an operation is allowed.
     */
    public function isOperationAllowed(string $operation, ?int $tenantId = null): array
    {
        return \Nexus\Services\FederationFeatureService::isOperationAllowed($operation, $tenantId);
    }

    /**
     * Trigger emergency lockdown.
     */
    public function triggerEmergencyLockdown(int $adminId, string $reason): bool
    {
        return \Nexus\Services\FederationFeatureService::triggerEmergencyLockdown($adminId, $reason);
    }

    /**
     * Lift emergency lockdown.
     */
    public function liftEmergencyLockdown(int $adminId): bool
    {
        return \Nexus\Services\FederationFeatureService::liftEmergencyLockdown($adminId);
    }

    /**
     * Add a tenant to the whitelist.
     */
    public function addToWhitelist(int $tenantId, int $adminId, ?string $notes = null): bool
    {
        return \Nexus\Services\FederationFeatureService::addToWhitelist($tenantId, $adminId, $notes);
    }

    /**
     * Remove a tenant from the whitelist.
     */
    public function removeFromWhitelist(int $tenantId, int $adminId): bool
    {
        return \Nexus\Services\FederationFeatureService::removeFromWhitelist($tenantId, $adminId);
    }

    /**
     * Get all whitelisted tenants.
     */
    public function getWhitelistedTenants(): array
    {
        return \Nexus\Services\FederationFeatureService::getWhitelistedTenants();
    }

    /**
     * Clear feature cache.
     */
    public function clearCache(): void
    {
        \Nexus\Services\FederationFeatureService::clearCache();
    }

    /**
     * Get tenant feature definitions.
     */
    public function getTenantFeatureDefinitions(): array
    {
        return \Nexus\Services\FederationFeatureService::getTenantFeatureDefinitions();
    }
}
