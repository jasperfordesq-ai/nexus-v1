<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FederationFeatureService — Manages feature toggles for the federation system
 * at both system and tenant level.
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

    /** In-process caches */
    private ?array $systemControlCache = null;
    private array $tenantFeatureCache = [];
    private array $whitelistCache = [];

    public function __construct(
        private readonly FederationAuditService $auditService,
    ) {}

    // =========================================================================
    // SYSTEM-LEVEL CONTROLS
    // =========================================================================

    /**
     * Get all system-level federation controls.
     */
    public function getSystemControls(): array
    {
        if ($this->systemControlCache !== null) {
            return $this->systemControlCache;
        }

        try {
            $result = DB::table('federation_system_control')->where('id', 1)->first();

            if (!$result) {
                $this->initializeSystemDefaults();
                $result = DB::table('federation_system_control')->where('id', 1)->first();
            }

            $this->systemControlCache = $result ? (array) $result : $this->getSystemDefaults();
            return $this->systemControlCache;
        } catch (\Exception $e) {
            Log::error('FederationFeatureService: Failed to get system controls - ' . $e->getMessage());
            return $this->getSystemDefaults();
        }
    }

    /**
     * Check if federation is globally enabled (master switch).
     */
    public function isGloballyEnabled(): bool
    {
        $controls = $this->getSystemControls();

        if (!empty($controls['emergency_lockdown_active'])) {
            return false;
        }

        return !empty($controls['federation_enabled']);
    }

    /**
     * Check if whitelist mode is active.
     */
    public function isWhitelistModeActive(): bool
    {
        $controls = $this->getSystemControls();
        return !empty($controls['whitelist_mode_enabled']);
    }

    /**
     * Check if a tenant is whitelisted for federation.
     */
    public function isTenantWhitelisted(int $tenantId): bool
    {
        if (!$this->isWhitelistModeActive()) {
            return true;
        }

        if (isset($this->whitelistCache[$tenantId])) {
            return $this->whitelistCache[$tenantId];
        }

        try {
            $result = DB::table('federation_tenant_whitelist')
                ->where('tenant_id', $tenantId)
                ->exists();

            $this->whitelistCache[$tenantId] = $result;
            return $result;
        } catch (\Exception $e) {
            Log::error('FederationFeatureService: Failed to check whitelist - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a system-level feature is enabled.
     */
    public function isSystemFeatureEnabled(string $feature): bool
    {
        $controls = $this->getSystemControls();

        $columnMap = [
            self::SYSTEM_PROFILES_ENABLED => 'cross_tenant_profiles_enabled',
            self::SYSTEM_MESSAGING_ENABLED => 'cross_tenant_messaging_enabled',
            self::SYSTEM_TRANSACTIONS_ENABLED => 'cross_tenant_transactions_enabled',
            self::SYSTEM_LISTINGS_ENABLED => 'cross_tenant_listings_enabled',
            self::SYSTEM_EVENTS_ENABLED => 'cross_tenant_events_enabled',
            self::SYSTEM_GROUPS_ENABLED => 'cross_tenant_groups_enabled',
        ];

        $column = $columnMap[$feature] ?? null;
        if (!$column) {
            return false;
        }

        return !empty($controls[$column]);
    }

    /**
     * Get maximum allowed federation level.
     */
    public function getMaxFederationLevel(): int
    {
        $controls = $this->getSystemControls();
        return (int) ($controls['max_federation_level'] ?? 0);
    }

    // =========================================================================
    // TENANT-LEVEL CONTROLS
    // =========================================================================

    /**
     * Check if federation is enabled for a specific tenant.
     */
    public function isTenantFederationEnabled(?int $tenantId = null): bool
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        try {
            $exists = DB::table('tenants')->where('id', $tenantId)->exists();
            if (!$exists) {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }

        if (!$this->isGloballyEnabled()) {
            return false;
        }

        if (!$this->isTenantWhitelisted($tenantId)) {
            return false;
        }

        return $this->isTenantFeatureEnabled(self::TENANT_FEDERATION_ENABLED, $tenantId);
    }

    /**
     * Check if a tenant-level feature is enabled.
     */
    public function isTenantFeatureEnabled(string $feature, ?int $tenantId = null): bool
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        // Check parent feature first (for non-main features)
        if ($feature !== self::TENANT_FEDERATION_ENABLED) {
            if (!$this->isTenantFeatureEnabled(self::TENANT_FEDERATION_ENABLED, $tenantId)) {
                return false;
            }
        }

        $cacheKey = "{$tenantId}:{$feature}";
        if (isset($this->tenantFeatureCache[$cacheKey])) {
            return $this->tenantFeatureCache[$cacheKey];
        }

        try {
            $result = DB::table('federation_tenant_features')
                ->where('tenant_id', $tenantId)
                ->where('feature_key', $feature)
                ->first();

            if ($result) {
                $enabled = (bool) $result->is_enabled;
            } else {
                $enabled = $this->getTenantFeatureDefault($feature);
            }

            $this->tenantFeatureCache[$cacheKey] = $enabled;
            return $enabled;
        } catch (\Exception $e) {
            Log::error('FederationFeatureService: Failed to check tenant feature - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Enable a federation feature for a tenant.
     */
    public function enableTenantFeature(string $feature, ?int $tenantId = null): bool
    {
        return $this->setTenantFeature($feature, true, $tenantId);
    }

    /**
     * Disable a federation feature for a tenant.
     */
    public function disableTenantFeature(string $feature, ?int $tenantId = null): bool
    {
        return $this->setTenantFeature($feature, false, $tenantId);
    }

    /**
     * Get all tenant features with their states.
     */
    public function getAllTenantFeatures(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        $definitions = $this->getTenantFeatureDefinitions();
        $features = [];

        try {
            $results = DB::table('federation_tenant_features')
                ->where('tenant_id', $tenantId)
                ->get();

            $stored = [];
            foreach ($results as $row) {
                $stored[$row->feature_key] = (bool) $row->is_enabled;
            }

            foreach ($definitions as $key => $definition) {
                $features[$key] = [
                    'enabled' => $stored[$key] ?? $definition['default'],
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                    'category' => $definition['category'],
                    'requires_system' => $definition['requires_system'] ?? null,
                ];

                if (!empty($definition['requires_system'])) {
                    $features[$key]['system_enabled'] = $this->isSystemFeatureEnabled($definition['requires_system']);
                    if (!$features[$key]['system_enabled']) {
                        $features[$key]['blocked_reason'] = 'Disabled at system level';
                    }
                }
            }

            return $features;
        } catch (\Exception $e) {
            Log::error('FederationFeatureService: Failed to get tenant features - ' . $e->getMessage());
            return [];
        }
    }

    // =========================================================================
    // COMPREHENSIVE CHECK
    // =========================================================================

    /**
     * Check if a specific federation operation is allowed.
     *
     * @param string $operation One of: profiles, messaging, transactions, listings, events, groups
     */
    public function isOperationAllowed(string $operation, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        try {
            $exists = DB::table('tenants')->where('id', $tenantId)->exists();
            if (!$exists) {
                return ['allowed' => false, 'reason' => 'Tenant does not exist', 'level' => 'invalid'];
            }
        } catch (\Exception $e) {
            return ['allowed' => false, 'reason' => 'Unable to verify tenant', 'level' => 'error'];
        }

        $controls = $this->getSystemControls();
        if (!empty($controls['emergency_lockdown_active'])) {
            return ['allowed' => false, 'reason' => 'Federation is in emergency lockdown', 'level' => 'emergency'];
        }

        if (empty($controls['federation_enabled'])) {
            return ['allowed' => false, 'reason' => 'Federation is globally disabled', 'level' => 'system'];
        }

        if (!$this->isTenantWhitelisted($tenantId)) {
            return ['allowed' => false, 'reason' => 'Tenant not approved for federation', 'level' => 'whitelist'];
        }

        $systemFeatureMap = [
            'profiles' => self::SYSTEM_PROFILES_ENABLED,
            'messaging' => self::SYSTEM_MESSAGING_ENABLED,
            'transactions' => self::SYSTEM_TRANSACTIONS_ENABLED,
            'listings' => self::SYSTEM_LISTINGS_ENABLED,
            'events' => self::SYSTEM_EVENTS_ENABLED,
            'groups' => self::SYSTEM_GROUPS_ENABLED,
        ];
        $systemFeature = $systemFeatureMap[$operation] ?? null;
        if ($systemFeature && !$this->isSystemFeatureEnabled($systemFeature)) {
            return ['allowed' => false, 'reason' => "Cross-tenant {$operation} is disabled at system level", 'level' => 'system_feature'];
        }

        if (!$this->isTenantFeatureEnabled(self::TENANT_FEDERATION_ENABLED, $tenantId)) {
            return ['allowed' => false, 'reason' => 'Federation is disabled for this tenant', 'level' => 'tenant'];
        }

        $tenantFeatureMap = [
            'profiles' => self::TENANT_PROFILES_ENABLED,
            'messaging' => self::TENANT_MESSAGING_ENABLED,
            'transactions' => self::TENANT_TRANSACTIONS_ENABLED,
            'listings' => self::TENANT_LISTINGS_ENABLED,
            'events' => self::TENANT_EVENTS_ENABLED,
            'groups' => self::TENANT_GROUPS_ENABLED,
        ];
        $tenantFeature = $tenantFeatureMap[$operation] ?? null;
        if ($tenantFeature && !$this->isTenantFeatureEnabled($tenantFeature, $tenantId)) {
            return ['allowed' => false, 'reason' => "Cross-tenant {$operation} is disabled for this tenant", 'level' => 'tenant_feature'];
        }

        return ['allowed' => true, 'reason' => null];
    }

    // =========================================================================
    // EMERGENCY CONTROLS
    // =========================================================================

    /**
     * Trigger emergency lockdown.
     */
    public function triggerEmergencyLockdown(int $adminId, string $reason): bool
    {
        try {
            DB::table('federation_system_control')->where('id', 1)->update([
                'emergency_lockdown_active' => 1,
                'emergency_lockdown_reason' => $reason,
                'emergency_lockdown_at' => now(),
                'emergency_lockdown_by' => $adminId,
                'updated_at' => now(),
                'updated_by' => $adminId,
            ]);

            $this->clearCache();

            $this->auditService->log(
                'emergency_lockdown_triggered',
                null, null, $adminId,
                ['reason' => $reason],
                'critical'
            );

            return true;
        } catch (\Exception $e) {
            Log::error('FederationFeatureService: Failed to trigger emergency lockdown - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Lift emergency lockdown.
     */
    public function liftEmergencyLockdown(int $adminId): bool
    {
        try {
            DB::table('federation_system_control')->where('id', 1)->update([
                'emergency_lockdown_active' => 0,
                'updated_at' => now(),
                'updated_by' => $adminId,
            ]);

            $this->clearCache();

            $this->auditService->log(
                'emergency_lockdown_lifted',
                null, null, $adminId,
                [],
                'critical'
            );

            return true;
        } catch (\Exception $e) {
            Log::error('FederationFeatureService: Failed to lift emergency lockdown - ' . $e->getMessage());
            return false;
        }
    }

    // =========================================================================
    // WHITELIST MANAGEMENT
    // =========================================================================

    /**
     * Add tenant to whitelist.
     */
    public function addToWhitelist(int $tenantId, int $adminId, ?string $notes = null): bool
    {
        try {
            DB::statement(
                "INSERT INTO federation_tenant_whitelist (tenant_id, approved_at, approved_by, notes)
                 VALUES (?, NOW(), ?, ?)
                 ON DUPLICATE KEY UPDATE approved_at = NOW(), approved_by = ?, notes = ?",
                [$tenantId, $adminId, $notes, $adminId, $notes]
            );

            unset($this->whitelistCache[$tenantId]);

            $this->auditService->log('tenant_whitelisted', $tenantId, null, $adminId, ['notes' => $notes]);

            return true;
        } catch (\Exception $e) {
            Log::error('FederationFeatureService: Failed to add to whitelist - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove tenant from whitelist.
     */
    public function removeFromWhitelist(int $tenantId, int $adminId): bool
    {
        try {
            DB::table('federation_tenant_whitelist')->where('tenant_id', $tenantId)->delete();

            unset($this->whitelistCache[$tenantId]);

            $this->auditService->log('tenant_removed_from_whitelist', $tenantId, null, $adminId, []);

            return true;
        } catch (\Exception $e) {
            Log::error('FederationFeatureService: Failed to remove from whitelist - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all whitelisted tenants.
     */
    public function getWhitelistedTenants(): array
    {
        try {
            return DB::table('federation_tenant_whitelist as fw')
                ->join('tenants as t', 'fw.tenant_id', '=', 't.id')
                ->leftJoin('users as u', 'fw.approved_by', '=', 'u.id')
                ->select(
                    'fw.*',
                    't.name as tenant_name',
                    't.domain as tenant_domain',
                    DB::raw("CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as approved_by_name")
                )
                ->orderByDesc('fw.approved_at')
                ->get()
                ->map(fn ($row) => (array) $row)
                ->all();
        } catch (\Exception $e) {
            Log::error('FederationFeatureService: Failed to get whitelisted tenants - ' . $e->getMessage());
            return [];
        }
    }

    // =========================================================================
    // CACHE MANAGEMENT
    // =========================================================================

    /**
     * Clear all in-process caches.
     */
    public function clearCache(): void
    {
        $this->systemControlCache = null;
        $this->tenantFeatureCache = [];
        $this->whitelistCache = [];
    }

    // =========================================================================
    // DEFINITIONS & DEFAULTS
    // =========================================================================

    /**
     * Get tenant feature definitions.
     */
    public function getTenantFeatureDefinitions(): array
    {
        return [
            self::TENANT_FEDERATION_ENABLED => [
                'label' => 'Federation Enabled',
                'description' => 'Enable federation features for this tenant',
                'category' => 'core',
                'default' => true,
            ],
            self::TENANT_APPEAR_IN_DIRECTORY => [
                'label' => 'Appear in Directory',
                'description' => 'Show this tenant in the federation directory',
                'category' => 'core',
                'default' => true,
            ],
            self::TENANT_AUTO_ACCEPT_HIERARCHY => [
                'label' => 'Auto-Accept Hierarchy',
                'description' => 'Automatically accept federation requests from parent/child tenants',
                'category' => 'core',
                'default' => false,
            ],
            self::TENANT_PROFILES_ENABLED => [
                'label' => 'Cross-Tenant Profiles',
                'description' => 'Allow member profiles to be visible to partner timebanks',
                'category' => 'features',
                'default' => true,
                'requires_system' => self::SYSTEM_PROFILES_ENABLED,
            ],
            self::TENANT_MESSAGING_ENABLED => [
                'label' => 'Cross-Tenant Messaging',
                'description' => 'Allow members to message users from partner timebanks',
                'category' => 'features',
                'default' => true,
                'requires_system' => self::SYSTEM_MESSAGING_ENABLED,
            ],
            self::TENANT_TRANSACTIONS_ENABLED => [
                'label' => 'Cross-Tenant Transactions',
                'description' => 'Allow time credit exchanges with partner timebanks',
                'category' => 'features',
                'default' => true,
                'requires_system' => self::SYSTEM_TRANSACTIONS_ENABLED,
            ],
            self::TENANT_LISTINGS_ENABLED => [
                'label' => 'Cross-Tenant Listings',
                'description' => 'Allow listings to be visible to partner timebanks',
                'category' => 'features',
                'default' => true,
                'requires_system' => self::SYSTEM_LISTINGS_ENABLED,
            ],
            self::TENANT_EVENTS_ENABLED => [
                'label' => 'Cross-Tenant Events',
                'description' => 'Allow events to be visible to partner timebanks',
                'category' => 'features',
                'default' => true,
                'requires_system' => self::SYSTEM_EVENTS_ENABLED,
            ],
            self::TENANT_GROUPS_ENABLED => [
                'label' => 'Cross-Tenant Groups',
                'description' => 'Allow groups to accept members from partner timebanks',
                'category' => 'features',
                'default' => true,
                'requires_system' => self::SYSTEM_GROUPS_ENABLED,
            ],
        ];
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function setTenantFeature(string $feature, bool $enabled, ?int $tenantId = null): bool
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        try {
            DB::statement(
                "REPLACE INTO federation_tenant_features
                 (tenant_id, feature_key, is_enabled, updated_at, updated_by)
                 VALUES (?, ?, ?, NOW(), ?)",
                [$tenantId, $feature, $enabled ? 1 : 0, auth()->id()]
            );

            $cacheKey = "{$tenantId}:{$feature}";
            unset($this->tenantFeatureCache[$cacheKey]);

            $this->auditService->log(
                'tenant_feature_changed',
                $tenantId, null, auth()->id(),
                ['feature' => $feature, 'enabled' => $enabled]
            );

            return true;
        } catch (\Exception $e) {
            Log::error('FederationFeatureService: Failed to set tenant feature - ' . $e->getMessage());
            return false;
        }
    }

    private function getSystemDefaults(): array
    {
        return [
            'federation_enabled' => 1,
            'whitelist_mode_enabled' => 0,
            'emergency_lockdown_active' => 0,
            'max_federation_level' => 4,
            'cross_tenant_profiles_enabled' => 1,
            'cross_tenant_messaging_enabled' => 1,
            'cross_tenant_transactions_enabled' => 1,
            'cross_tenant_listings_enabled' => 1,
            'cross_tenant_events_enabled' => 1,
            'cross_tenant_groups_enabled' => 1,
        ];
    }

    private function getTenantFeatureDefault(string $feature): bool
    {
        $definitions = $this->getTenantFeatureDefinitions();
        return $definitions[$feature]['default'] ?? false;
    }

    private function initializeSystemDefaults(): void
    {
        try {
            DB::statement(
                "INSERT INTO federation_system_control (
                    id, federation_enabled, whitelist_mode_enabled, emergency_lockdown_active,
                    max_federation_level, cross_tenant_profiles_enabled, cross_tenant_messaging_enabled,
                    cross_tenant_transactions_enabled, cross_tenant_listings_enabled,
                    cross_tenant_events_enabled, cross_tenant_groups_enabled, created_at
                ) VALUES (1, 1, 0, 0, 4, 1, 1, 1, 1, 1, 1, NOW())
                ON DUPLICATE KEY UPDATE id = id"
            );
        } catch (\Exception $e) {
            Log::error('FederationFeatureService: Failed to initialize system defaults - ' . $e->getMessage());
        }
    }
}
