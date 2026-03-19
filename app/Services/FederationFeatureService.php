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
    /** System-level constants */
    public const SYSTEM_FEDERATION_ENABLED = 'system_federation_enabled';
    public const SYSTEM_WHITELIST_MODE = 'system_whitelist_mode';
    public const SYSTEM_EMERGENCY_LOCKDOWN = 'system_emergency_lockdown';
    public const SYSTEM_MAX_FEDERATION_LEVEL = 'system_max_federation_level';
    public const SYSTEM_PROFILES_ENABLED = 'system_cross_tenant_profiles';
    public const SYSTEM_MESSAGING_ENABLED = 'system_cross_tenant_messaging';
    public const SYSTEM_TRANSACTIONS_ENABLED = 'system_cross_tenant_transactions';
    public const SYSTEM_LISTINGS_ENABLED = 'system_cross_tenant_listings';
    public const SYSTEM_EVENTS_ENABLED = 'system_cross_tenant_events';
    public const SYSTEM_GROUPS_ENABLED = 'system_cross_tenant_groups';

    /** Tenant-level constants */
    public const TENANT_FEDERATION_ENABLED = 'tenant_federation_enabled';
    public const TENANT_APPEAR_IN_DIRECTORY = 'tenant_appear_in_directory';
    public const TENANT_AUTO_ACCEPT_HIERARCHY = 'tenant_auto_accept_hierarchy';
    public const TENANT_PROFILES_ENABLED = 'tenant_profiles_enabled';
    public const TENANT_MESSAGING_ENABLED = 'tenant_messaging_enabled';
    public const TENANT_TRANSACTIONS_ENABLED = 'tenant_transactions_enabled';
    public const TENANT_LISTINGS_ENABLED = 'tenant_listings_enabled';
    public const TENANT_EVENTS_ENABLED = 'tenant_events_enabled';
    public const TENANT_GROUPS_ENABLED = 'tenant_groups_enabled';

    public function __construct()
    {
    }

    /**
     * Delegates to legacy FederationFeatureService::getSystemControls().
     */
    public function getSystemControls(): array
    {
        if (!class_exists('\Nexus\Services\FederationFeatureService')) {
            return [];
        }
        return \Nexus\Services\FederationFeatureService::getSystemControls();
    }

    /**
     * Delegates to legacy FederationFeatureService::isGloballyEnabled().
     */
    public function isGloballyEnabled(): bool
    {
        if (!class_exists('\Nexus\Services\FederationFeatureService')) {
            return false;
        }
        return \Nexus\Services\FederationFeatureService::isGloballyEnabled();
    }

    /**
     * Delegates to legacy FederationFeatureService::isWhitelistModeActive().
     */
    public function isWhitelistModeActive(): bool
    {
        if (!class_exists('\Nexus\Services\FederationFeatureService')) {
            return false;
        }
        return \Nexus\Services\FederationFeatureService::isWhitelistModeActive();
    }

    /**
     * Delegates to legacy FederationFeatureService::isTenantWhitelisted().
     */
    public function isTenantWhitelisted(int $tenantId): bool
    {
        if (!class_exists('\Nexus\Services\FederationFeatureService')) {
            return false;
        }
        return \Nexus\Services\FederationFeatureService::isTenantWhitelisted($tenantId);
    }

    /**
     * Delegates to legacy FederationFeatureService::isSystemFeatureEnabled().
     */
    public function isSystemFeatureEnabled(string $feature): bool
    {
        if (!class_exists('\Nexus\Services\FederationFeatureService')) {
            return false;
        }
        return \Nexus\Services\FederationFeatureService::isSystemFeatureEnabled($feature);
    }

    /**
     * Get max federation level.
     */
    public function getMaxFederationLevel(): int
    {
        if (!class_exists('\Nexus\Services\FederationFeatureService')) {
            return 0;
        }
        return \Nexus\Services\FederationFeatureService::getMaxFederationLevel();
    }

    /**
     * Check if a tenant has federation enabled.
     */
    public function isTenantFederationEnabled(?int $tenantId = null): bool
    {
        if (!class_exists('\Nexus\Services\FederationFeatureService')) {
            return false;
        }
        return \Nexus\Services\FederationFeatureService::isTenantFederationEnabled($tenantId);
    }

    /**
     * Check if a specific tenant feature is enabled.
     */
    public function isTenantFeatureEnabled(string $feature, ?int $tenantId = null): bool
    {
        if (!class_exists('\Nexus\Services\FederationFeatureService')) {
            return false;
        }
        return \Nexus\Services\FederationFeatureService::isTenantFeatureEnabled($feature, $tenantId);
    }

    /**
     * Enable a federation feature for a tenant.
     */
    public function enableTenantFeature(string $feature, ?int $tenantId = null): bool
    {
        if (!class_exists('\Nexus\Services\FederationFeatureService')) {
            return false;
        }
        return \Nexus\Services\FederationFeatureService::enableTenantFeature($feature, $tenantId);
    }

    /**
     * Disable a federation feature for a tenant.
     */
    public function disableTenantFeature(string $feature, ?int $tenantId = null): bool
    {
        if (!class_exists('\Nexus\Services\FederationFeatureService')) {
            return false;
        }
        return \Nexus\Services\FederationFeatureService::disableTenantFeature($feature, $tenantId);
    }

    /**
     * Get all tenant features.
     */
    public function getAllTenantFeatures(?int $tenantId = null): array
    {
        if (!class_exists('\Nexus\Services\FederationFeatureService')) {
            return [];
        }
        return \Nexus\Services\FederationFeatureService::getAllTenantFeatures($tenantId);
    }

    /**
     * Check if an operation is allowed.
     */
    public function isOperationAllowed(string $operation, ?int $tenantId = null): array
    {
        if (!class_exists('\Nexus\Services\FederationFeatureService')) {
            return ['allowed' => false, 'reason' => 'Legacy service unavailable'];
        }
        return \Nexus\Services\FederationFeatureService::isOperationAllowed($operation, $tenantId);
    }

    /**
     * Trigger emergency lockdown.
     */
    public function triggerEmergencyLockdown(int $adminId, string $reason): bool
    {
        if (!class_exists('\Nexus\Services\FederationFeatureService')) {
            return false;
        }
        return \Nexus\Services\FederationFeatureService::triggerEmergencyLockdown($adminId, $reason);
    }

    /**
     * Lift emergency lockdown.
     */
    public function liftEmergencyLockdown(int $adminId): bool
    {
        if (!class_exists('\Nexus\Services\FederationFeatureService')) {
            return false;
        }
        return \Nexus\Services\FederationFeatureService::liftEmergencyLockdown($adminId);
    }

    /**
     * Add a tenant to the whitelist.
     */
    public function addToWhitelist(int $tenantId, int $adminId, ?string $notes = null): bool
    {
        if (!class_exists('\Nexus\Services\FederationFeatureService')) {
            return false;
        }
        return \Nexus\Services\FederationFeatureService::addToWhitelist($tenantId, $adminId, $notes);
    }

    /**
     * Remove a tenant from the whitelist.
     */
    public function removeFromWhitelist(int $tenantId, int $adminId): bool
    {
        if (!class_exists('\Nexus\Services\FederationFeatureService')) {
            return false;
        }
        return \Nexus\Services\FederationFeatureService::removeFromWhitelist($tenantId, $adminId);
    }

    /**
     * Get all whitelisted tenants.
     */
    public function getWhitelistedTenants(): array
    {
        if (!class_exists('\Nexus\Services\FederationFeatureService')) {
            return [];
        }
        return \Nexus\Services\FederationFeatureService::getWhitelistedTenants();
    }

    /**
     * Clear feature cache.
     */
    public function clearCache(): void
    {
        if (!class_exists('\Nexus\Services\FederationFeatureService')) {
            return;
        }
        \Nexus\Services\FederationFeatureService::clearCache();
    }

    /**
     * Get tenant feature definitions.
     */
    public function getTenantFeatureDefinitions(): array
    {
        if (!class_exists('\Nexus\Services\FederationFeatureService')) {
            return [];
        }
        return \Nexus\Services\FederationFeatureService::getTenantFeatureDefinitions();
    }
}
