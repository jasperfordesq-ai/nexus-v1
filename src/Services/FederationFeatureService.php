<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * FederationFeatureService
 *
 * Manages feature toggles for the federation system at both system and tenant level.
 * ALL features default to OFF (disabled) for safety.
 *
 * This follows the GroupFeatureToggleService pattern but adds:
 * - System-level controls (global kill switch)
 * - Tenant whitelist mode
 * - Emergency lockdown capability
 */
class FederationFeatureService
{
    // =========================================================================
    // SYSTEM-LEVEL FEATURES (Super Admin controlled)
    // =========================================================================

    const SYSTEM_FEDERATION_ENABLED = 'system_federation_enabled';
    const SYSTEM_WHITELIST_MODE = 'system_whitelist_mode';
    const SYSTEM_EMERGENCY_LOCKDOWN = 'system_emergency_lockdown';
    const SYSTEM_MAX_FEDERATION_LEVEL = 'system_max_federation_level';

    // System feature-level kill switches
    const SYSTEM_PROFILES_ENABLED = 'system_cross_tenant_profiles';
    const SYSTEM_MESSAGING_ENABLED = 'system_cross_tenant_messaging';
    const SYSTEM_TRANSACTIONS_ENABLED = 'system_cross_tenant_transactions';
    const SYSTEM_LISTINGS_ENABLED = 'system_cross_tenant_listings';
    const SYSTEM_EVENTS_ENABLED = 'system_cross_tenant_events';
    const SYSTEM_GROUPS_ENABLED = 'system_cross_tenant_groups';

    // =========================================================================
    // TENANT-LEVEL FEATURES (Tenant Admin controlled)
    // =========================================================================

    const TENANT_FEDERATION_ENABLED = 'tenant_federation_enabled';
    const TENANT_APPEAR_IN_DIRECTORY = 'tenant_appear_in_directory';
    const TENANT_AUTO_ACCEPT_HIERARCHY = 'tenant_auto_accept_hierarchy';

    // Tenant feature toggles
    const TENANT_PROFILES_ENABLED = 'tenant_profiles_enabled';
    const TENANT_MESSAGING_ENABLED = 'tenant_messaging_enabled';
    const TENANT_TRANSACTIONS_ENABLED = 'tenant_transactions_enabled';
    const TENANT_LISTINGS_ENABLED = 'tenant_listings_enabled';
    const TENANT_EVENTS_ENABLED = 'tenant_events_enabled';
    const TENANT_GROUPS_ENABLED = 'tenant_groups_enabled';

    // Cache for performance
    private static ?array $systemControlCache = null;
    private static array $tenantFeatureCache = [];
    private static array $whitelistCache = [];

    // =========================================================================
    // SYSTEM-LEVEL CONTROLS
    // =========================================================================

    /**
     * Get all system-level federation controls
     * This is cached for performance - call clearCache() after changes
     */
    public static function getSystemControls(): array
    {
        if (self::$systemControlCache !== null) {
            return self::$systemControlCache;
        }

        self::ensureSystemTableExists();

        try {
            $result = Database::query(
                "SELECT * FROM federation_system_control WHERE id = 1"
            )->fetch(\PDO::FETCH_ASSOC);

            if (!$result) {
                // Initialize with safe defaults (everything OFF)
                self::initializeSystemDefaults();
                $result = Database::query(
                    "SELECT * FROM federation_system_control WHERE id = 1"
                )->fetch(\PDO::FETCH_ASSOC);
            }

            self::$systemControlCache = $result ?: self::getSystemDefaults();
            return self::$systemControlCache;

        } catch (\Exception $e) {
            error_log("FederationFeatureService: Failed to get system controls - " . $e->getMessage());
            return self::getSystemDefaults();
        }
    }

    /**
     * Check if federation is globally enabled (master switch)
     */
    public static function isGloballyEnabled(): bool
    {
        $controls = self::getSystemControls();

        // Emergency lockdown overrides everything
        if (!empty($controls['emergency_lockdown_active'])) {
            return false;
        }

        return !empty($controls['federation_enabled']);
    }

    /**
     * Check if whitelist mode is active
     */
    public static function isWhitelistModeActive(): bool
    {
        $controls = self::getSystemControls();
        return !empty($controls['whitelist_mode_enabled']);
    }

    /**
     * Check if a tenant is whitelisted for federation
     */
    public static function isTenantWhitelisted(int $tenantId): bool
    {
        if (!self::isWhitelistModeActive()) {
            return true; // If whitelist mode is off, all tenants are allowed
        }

        if (isset(self::$whitelistCache[$tenantId])) {
            return self::$whitelistCache[$tenantId];
        }

        self::ensureWhitelistTableExists();

        try {
            $result = Database::query(
                "SELECT 1 FROM federation_tenant_whitelist WHERE tenant_id = ?",
                [$tenantId]
            )->fetch();

            self::$whitelistCache[$tenantId] = (bool) $result;
            return self::$whitelistCache[$tenantId];

        } catch (\Exception $e) {
            error_log("FederationFeatureService: Failed to check whitelist - " . $e->getMessage());
            return false; // Fail safe - deny access
        }
    }

    /**
     * Check if a system-level feature is enabled
     */
    public static function isSystemFeatureEnabled(string $feature): bool
    {
        $controls = self::getSystemControls();

        // Map feature constant to database column
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
     * Get maximum allowed federation level
     */
    public static function getMaxFederationLevel(): int
    {
        $controls = self::getSystemControls();
        return (int) ($controls['max_federation_level'] ?? 0);
    }

    // =========================================================================
    // TENANT-LEVEL CONTROLS
    // =========================================================================

    /**
     * Check if federation is enabled for a specific tenant
     */
    public static function isTenantFederationEnabled(?int $tenantId = null): bool
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        // First check system-level
        if (!self::isGloballyEnabled()) {
            return false;
        }

        // Check whitelist
        if (!self::isTenantWhitelisted($tenantId)) {
            return false;
        }

        // Check tenant-level setting
        return self::isTenantFeatureEnabled(self::TENANT_FEDERATION_ENABLED, $tenantId);
    }

    /**
     * Check if a tenant-level feature is enabled
     */
    public static function isTenantFeatureEnabled(string $feature, ?int $tenantId = null): bool
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        // First check if tenant federation is enabled (for non-main features)
        if ($feature !== self::TENANT_FEDERATION_ENABLED) {
            if (!self::isTenantFeatureEnabled(self::TENANT_FEDERATION_ENABLED, $tenantId)) {
                return false;
            }
        }

        $cacheKey = "{$tenantId}:{$feature}";
        if (isset(self::$tenantFeatureCache[$cacheKey])) {
            return self::$tenantFeatureCache[$cacheKey];
        }

        self::ensureTenantTableExists();

        try {
            $result = Database::query(
                "SELECT is_enabled FROM federation_tenant_features
                 WHERE tenant_id = ? AND feature_key = ?",
                [$tenantId, $feature]
            )->fetch();

            if ($result) {
                $enabled = (bool) $result['is_enabled'];
            } else {
                // Default to OFF for all features
                $enabled = self::getTenantFeatureDefault($feature);
            }

            self::$tenantFeatureCache[$cacheKey] = $enabled;
            return $enabled;

        } catch (\Exception $e) {
            error_log("FederationFeatureService: Failed to check tenant feature - " . $e->getMessage());
            return false; // Fail safe
        }
    }

    /**
     * Enable a tenant feature
     */
    public static function enableTenantFeature(string $feature, ?int $tenantId = null): bool
    {
        return self::setTenantFeature($feature, true, $tenantId);
    }

    /**
     * Disable a tenant feature
     */
    public static function disableTenantFeature(string $feature, ?int $tenantId = null): bool
    {
        return self::setTenantFeature($feature, false, $tenantId);
    }

    /**
     * Set tenant feature state
     */
    private static function setTenantFeature(string $feature, bool $enabled, ?int $tenantId = null): bool
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        self::ensureTenantTableExists();

        try {
            Database::query(
                "REPLACE INTO federation_tenant_features
                 (tenant_id, feature_key, is_enabled, updated_at, updated_by)
                 VALUES (?, ?, ?, NOW(), ?)",
                [$tenantId, $feature, $enabled ? 1 : 0, $_SESSION['user_id'] ?? null]
            );

            // Clear cache
            $cacheKey = "{$tenantId}:{$feature}";
            unset(self::$tenantFeatureCache[$cacheKey]);

            // Audit log
            FederationAuditService::log(
                'tenant_feature_changed',
                $tenantId,
                null,
                $_SESSION['user_id'] ?? null,
                [
                    'feature' => $feature,
                    'enabled' => $enabled,
                ]
            );

            return true;

        } catch (\Exception $e) {
            error_log("FederationFeatureService: Failed to set tenant feature - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all tenant features with their states
     */
    public static function getAllTenantFeatures(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        self::ensureTenantTableExists();

        $definitions = self::getTenantFeatureDefinitions();
        $features = [];

        try {
            $results = Database::query(
                "SELECT feature_key, is_enabled FROM federation_tenant_features WHERE tenant_id = ?",
                [$tenantId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            $stored = [];
            foreach ($results as $row) {
                $stored[$row['feature_key']] = (bool) $row['is_enabled'];
            }

            foreach ($definitions as $key => $definition) {
                $features[$key] = [
                    'enabled' => $stored[$key] ?? $definition['default'],
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                    'category' => $definition['category'],
                    'requires_system' => $definition['requires_system'] ?? null,
                ];

                // Check if system-level blocks this feature
                if (!empty($definition['requires_system'])) {
                    $features[$key]['system_enabled'] = self::isSystemFeatureEnabled($definition['requires_system']);
                    if (!$features[$key]['system_enabled']) {
                        $features[$key]['blocked_reason'] = 'Disabled at system level';
                    }
                }
            }

            return $features;

        } catch (\Exception $e) {
            error_log("FederationFeatureService: Failed to get tenant features - " . $e->getMessage());
            return [];
        }
    }

    // =========================================================================
    // COMPREHENSIVE CHECK
    // =========================================================================

    /**
     * Check if a specific federation operation is allowed
     * This is the main method to use before any federation operation
     *
     * @param string $operation One of: profiles, messaging, transactions, listings, events, groups
     * @param int|null $tenantId Tenant to check (defaults to current)
     * @return array ['allowed' => bool, 'reason' => string|null]
     */
    public static function isOperationAllowed(string $operation, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        // 1. Check emergency lockdown
        $controls = self::getSystemControls();
        if (!empty($controls['emergency_lockdown_active'])) {
            return [
                'allowed' => false,
                'reason' => 'Federation is in emergency lockdown',
                'level' => 'emergency'
            ];
        }

        // 2. Check global enable
        if (empty($controls['federation_enabled'])) {
            return [
                'allowed' => false,
                'reason' => 'Federation is globally disabled',
                'level' => 'system'
            ];
        }

        // 3. Check whitelist
        if (!self::isTenantWhitelisted($tenantId)) {
            return [
                'allowed' => false,
                'reason' => 'Tenant not approved for federation',
                'level' => 'whitelist'
            ];
        }

        // 4. Check system-level feature
        $systemFeatureMap = [
            'profiles' => self::SYSTEM_PROFILES_ENABLED,
            'messaging' => self::SYSTEM_MESSAGING_ENABLED,
            'transactions' => self::SYSTEM_TRANSACTIONS_ENABLED,
            'listings' => self::SYSTEM_LISTINGS_ENABLED,
            'events' => self::SYSTEM_EVENTS_ENABLED,
            'groups' => self::SYSTEM_GROUPS_ENABLED,
        ];

        $systemFeature = $systemFeatureMap[$operation] ?? null;
        if ($systemFeature && !self::isSystemFeatureEnabled($systemFeature)) {
            return [
                'allowed' => false,
                'reason' => "Cross-tenant {$operation} is disabled at system level",
                'level' => 'system_feature'
            ];
        }

        // 5. Check tenant-level enable
        if (!self::isTenantFeatureEnabled(self::TENANT_FEDERATION_ENABLED, $tenantId)) {
            return [
                'allowed' => false,
                'reason' => 'Federation is disabled for this tenant',
                'level' => 'tenant'
            ];
        }

        // 6. Check tenant-level feature
        $tenantFeatureMap = [
            'profiles' => self::TENANT_PROFILES_ENABLED,
            'messaging' => self::TENANT_MESSAGING_ENABLED,
            'transactions' => self::TENANT_TRANSACTIONS_ENABLED,
            'listings' => self::TENANT_LISTINGS_ENABLED,
            'events' => self::TENANT_EVENTS_ENABLED,
            'groups' => self::TENANT_GROUPS_ENABLED,
        ];

        $tenantFeature = $tenantFeatureMap[$operation] ?? null;
        if ($tenantFeature && !self::isTenantFeatureEnabled($tenantFeature, $tenantId)) {
            return [
                'allowed' => false,
                'reason' => "Cross-tenant {$operation} is disabled for this tenant",
                'level' => 'tenant_feature'
            ];
        }

        return ['allowed' => true, 'reason' => null];
    }

    // =========================================================================
    // EMERGENCY CONTROLS
    // =========================================================================

    /**
     * Trigger emergency lockdown (Super Admin only)
     */
    public static function triggerEmergencyLockdown(int $adminId, string $reason): bool
    {
        self::ensureSystemTableExists();

        try {
            Database::query(
                "UPDATE federation_system_control SET
                 emergency_lockdown_active = 1,
                 emergency_lockdown_reason = ?,
                 emergency_lockdown_at = NOW(),
                 emergency_lockdown_by = ?,
                 updated_at = NOW(),
                 updated_by = ?
                 WHERE id = 1",
                [$reason, $adminId, $adminId]
            );

            // Clear all caches
            self::clearCache();

            // Audit log
            FederationAuditService::log(
                'emergency_lockdown_triggered',
                null,
                null,
                $adminId,
                ['reason' => $reason],
                'critical'
            );

            return true;

        } catch (\Exception $e) {
            error_log("FederationFeatureService: Failed to trigger emergency lockdown - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Lift emergency lockdown (Super Admin only)
     */
    public static function liftEmergencyLockdown(int $adminId): bool
    {
        self::ensureSystemTableExists();

        try {
            Database::query(
                "UPDATE federation_system_control SET
                 emergency_lockdown_active = 0,
                 updated_at = NOW(),
                 updated_by = ?
                 WHERE id = 1",
                [$adminId]
            );

            // Clear all caches
            self::clearCache();

            // Audit log
            FederationAuditService::log(
                'emergency_lockdown_lifted',
                null,
                null,
                $adminId,
                [],
                'critical'
            );

            return true;

        } catch (\Exception $e) {
            error_log("FederationFeatureService: Failed to lift emergency lockdown - " . $e->getMessage());
            return false;
        }
    }

    // =========================================================================
    // WHITELIST MANAGEMENT
    // =========================================================================

    /**
     * Add tenant to whitelist
     */
    public static function addToWhitelist(int $tenantId, int $adminId, ?string $notes = null): bool
    {
        self::ensureWhitelistTableExists();

        try {
            Database::query(
                "INSERT INTO federation_tenant_whitelist (tenant_id, approved_at, approved_by, notes)
                 VALUES (?, NOW(), ?, ?)
                 ON DUPLICATE KEY UPDATE approved_at = NOW(), approved_by = ?, notes = ?",
                [$tenantId, $adminId, $notes, $adminId, $notes]
            );

            unset(self::$whitelistCache[$tenantId]);

            FederationAuditService::log(
                'tenant_whitelisted',
                $tenantId,
                null,
                $adminId,
                ['notes' => $notes]
            );

            return true;

        } catch (\Exception $e) {
            error_log("FederationFeatureService: Failed to add to whitelist - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove tenant from whitelist
     */
    public static function removeFromWhitelist(int $tenantId, int $adminId): bool
    {
        self::ensureWhitelistTableExists();

        try {
            Database::query(
                "DELETE FROM federation_tenant_whitelist WHERE tenant_id = ?",
                [$tenantId]
            );

            unset(self::$whitelistCache[$tenantId]);

            FederationAuditService::log(
                'tenant_removed_from_whitelist',
                $tenantId,
                null,
                $adminId,
                []
            );

            return true;

        } catch (\Exception $e) {
            error_log("FederationFeatureService: Failed to remove from whitelist - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all whitelisted tenants
     */
    public static function getWhitelistedTenants(): array
    {
        self::ensureWhitelistTableExists();

        try {
            return Database::query(
                "SELECT fw.*, t.name as tenant_name, t.domain as tenant_domain,
                        u.name as approved_by_name
                 FROM federation_tenant_whitelist fw
                 JOIN tenants t ON fw.tenant_id = t.id
                 LEFT JOIN users u ON fw.approved_by = u.id
                 ORDER BY fw.approved_at DESC"
            )->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            error_log("FederationFeatureService: Failed to get whitelisted tenants - " . $e->getMessage());
            return [];
        }
    }

    // =========================================================================
    // CACHE MANAGEMENT
    // =========================================================================

    /**
     * Clear all caches
     */
    public static function clearCache(): void
    {
        self::$systemControlCache = null;
        self::$tenantFeatureCache = [];
        self::$whitelistCache = [];
    }

    // =========================================================================
    // DEFINITIONS & DEFAULTS
    // =========================================================================

    /**
     * Get safe system defaults (everything OFF)
     */
    private static function getSystemDefaults(): array
    {
        return [
            'federation_enabled' => 0,
            'whitelist_mode_enabled' => 1,
            'emergency_lockdown_active' => 0,
            'max_federation_level' => 0,
            'cross_tenant_profiles_enabled' => 0,
            'cross_tenant_messaging_enabled' => 0,
            'cross_tenant_transactions_enabled' => 0,
            'cross_tenant_listings_enabled' => 0,
            'cross_tenant_events_enabled' => 0,
            'cross_tenant_groups_enabled' => 0,
        ];
    }

    /**
     * Get tenant feature definitions
     */
    public static function getTenantFeatureDefinitions(): array
    {
        return [
            self::TENANT_FEDERATION_ENABLED => [
                'label' => 'Federation Enabled',
                'description' => 'Enable federation features for this tenant',
                'category' => 'core',
                'default' => false,
            ],
            self::TENANT_APPEAR_IN_DIRECTORY => [
                'label' => 'Appear in Directory',
                'description' => 'Show this tenant in the federation directory',
                'category' => 'core',
                'default' => false,
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
                'default' => false,
                'requires_system' => self::SYSTEM_PROFILES_ENABLED,
            ],
            self::TENANT_MESSAGING_ENABLED => [
                'label' => 'Cross-Tenant Messaging',
                'description' => 'Allow members to message users from partner timebanks',
                'category' => 'features',
                'default' => false,
                'requires_system' => self::SYSTEM_MESSAGING_ENABLED,
            ],
            self::TENANT_TRANSACTIONS_ENABLED => [
                'label' => 'Cross-Tenant Transactions',
                'description' => 'Allow time credit exchanges with partner timebanks',
                'category' => 'features',
                'default' => false,
                'requires_system' => self::SYSTEM_TRANSACTIONS_ENABLED,
            ],
            self::TENANT_LISTINGS_ENABLED => [
                'label' => 'Cross-Tenant Listings',
                'description' => 'Allow listings to be visible to partner timebanks',
                'category' => 'features',
                'default' => false,
                'requires_system' => self::SYSTEM_LISTINGS_ENABLED,
            ],
            self::TENANT_EVENTS_ENABLED => [
                'label' => 'Cross-Tenant Events',
                'description' => 'Allow events to be visible to partner timebanks',
                'category' => 'features',
                'default' => false,
                'requires_system' => self::SYSTEM_EVENTS_ENABLED,
            ],
            self::TENANT_GROUPS_ENABLED => [
                'label' => 'Cross-Tenant Groups',
                'description' => 'Allow groups to accept members from partner timebanks',
                'category' => 'features',
                'default' => false,
                'requires_system' => self::SYSTEM_GROUPS_ENABLED,
            ],
        ];
    }

    /**
     * Get default value for tenant feature
     */
    private static function getTenantFeatureDefault(string $feature): bool
    {
        $definitions = self::getTenantFeatureDefinitions();
        return $definitions[$feature]['default'] ?? false;
    }

    // =========================================================================
    // TABLE CREATION
    // =========================================================================

    /**
     * Initialize system defaults
     */
    private static function initializeSystemDefaults(): void
    {
        try {
            Database::query(
                "INSERT INTO federation_system_control (
                    id, federation_enabled, whitelist_mode_enabled, emergency_lockdown_active,
                    max_federation_level, cross_tenant_profiles_enabled, cross_tenant_messaging_enabled,
                    cross_tenant_transactions_enabled, cross_tenant_listings_enabled,
                    cross_tenant_events_enabled, cross_tenant_groups_enabled, created_at
                ) VALUES (1, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, NOW())
                ON DUPLICATE KEY UPDATE id = id"
            );
        } catch (\Exception $e) {
            error_log("FederationFeatureService: Failed to initialize system defaults - " . $e->getMessage());
        }
    }

    /**
     * Ensure system control table exists
     */
    private static function ensureSystemTableExists(): void
    {
        static $checked = false;
        if ($checked) return;

        try {
            Database::query("SELECT 1 FROM federation_system_control LIMIT 1");
        } catch (\Exception $e) {
            Database::query("
                CREATE TABLE IF NOT EXISTS federation_system_control (
                    id INT UNSIGNED NOT NULL DEFAULT 1,

                    -- Master controls
                    federation_enabled TINYINT(1) NOT NULL DEFAULT 0
                        COMMENT 'Master switch: 0 = ALL federation disabled',
                    whitelist_mode_enabled TINYINT(1) NOT NULL DEFAULT 1
                        COMMENT 'Only whitelisted tenants can use federation',
                    max_federation_level TINYINT UNSIGNED NOT NULL DEFAULT 0
                        COMMENT 'Maximum federation level any tenant can use (0-4)',

                    -- Feature kill switches
                    cross_tenant_profiles_enabled TINYINT(1) NOT NULL DEFAULT 0,
                    cross_tenant_messaging_enabled TINYINT(1) NOT NULL DEFAULT 0,
                    cross_tenant_transactions_enabled TINYINT(1) NOT NULL DEFAULT 0,
                    cross_tenant_listings_enabled TINYINT(1) NOT NULL DEFAULT 0,
                    cross_tenant_events_enabled TINYINT(1) NOT NULL DEFAULT 0,
                    cross_tenant_groups_enabled TINYINT(1) NOT NULL DEFAULT 0,

                    -- Emergency lockdown
                    emergency_lockdown_active TINYINT(1) NOT NULL DEFAULT 0,
                    emergency_lockdown_reason TEXT NULL,
                    emergency_lockdown_at TIMESTAMP NULL,
                    emergency_lockdown_by INT UNSIGNED NULL,

                    -- Audit
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
                    updated_by INT UNSIGNED NULL,

                    PRIMARY KEY (id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        $checked = true;
    }

    /**
     * Ensure tenant features table exists
     */
    private static function ensureTenantTableExists(): void
    {
        static $checked = false;
        if ($checked) return;

        try {
            Database::query("SELECT 1 FROM federation_tenant_features LIMIT 1");
        } catch (\Exception $e) {
            Database::query("
                CREATE TABLE IF NOT EXISTS federation_tenant_features (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT UNSIGNED NOT NULL,
                    feature_key VARCHAR(100) NOT NULL,
                    is_enabled TINYINT(1) NOT NULL DEFAULT 0,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    updated_by INT UNSIGNED NULL,

                    UNIQUE KEY unique_tenant_feature (tenant_id, feature_key),
                    INDEX idx_tenant (tenant_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        $checked = true;
    }

    /**
     * Ensure whitelist table exists
     */
    private static function ensureWhitelistTableExists(): void
    {
        static $checked = false;
        if ($checked) return;

        try {
            Database::query("SELECT 1 FROM federation_tenant_whitelist LIMIT 1");
        } catch (\Exception $e) {
            Database::query("
                CREATE TABLE IF NOT EXISTS federation_tenant_whitelist (
                    tenant_id INT UNSIGNED PRIMARY KEY,
                    approved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    approved_by INT UNSIGNED NOT NULL,
                    notes VARCHAR(500) NULL,

                    INDEX idx_approved_at (approved_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        $checked = true;
    }
}
