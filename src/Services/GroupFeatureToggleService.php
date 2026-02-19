<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * GroupFeatureToggleService
 *
 * Manages feature toggles for the groups module at tenant level.
 * Allows enabling/disabling the entire module or specific features per tenant.
 */
class GroupFeatureToggleService
{
    // Main module toggle
    const FEATURE_GROUPS_MODULE = 'groups_module';

    // Core features
    const FEATURE_GROUP_CREATION = 'group_creation';
    const FEATURE_HUB_GROUPS = 'hub_groups';
    const FEATURE_REGULAR_GROUPS = 'regular_groups';
    const FEATURE_PRIVATE_GROUPS = 'private_groups';
    const FEATURE_SUB_GROUPS = 'sub_groups';

    // Interaction features
    const FEATURE_DISCUSSIONS = 'discussions';
    const FEATURE_FEEDBACK = 'feedback';
    const FEATURE_MEMBER_INVITES = 'member_invites';
    const FEATURE_JOIN_REQUESTS = 'join_requests';

    // Gamification features
    const FEATURE_ACHIEVEMENTS = 'achievements';
    const FEATURE_BADGES = 'badges';
    const FEATURE_LEADERBOARD = 'leaderboard';

    // Advanced features
    const FEATURE_ANALYTICS = 'analytics';
    const FEATURE_MODERATION = 'moderation';
    const FEATURE_APPROVAL_WORKFLOW = 'approval_workflow';
    const FEATURE_EXPORT = 'export';
    const FEATURE_AUDIT_LOG = 'audit_log';

    // UI features
    const FEATURE_DISCOVERY = 'discovery';
    const FEATURE_SEARCH = 'search';
    const FEATURE_FILTERS = 'filters';
    const FEATURE_MAPS = 'maps';

    /**
     * Check if a feature is is_enabled for the current tenant
     *
     * @param string $feature Feature constant
     * @param int|null $tenantId Tenant ID (defaults to current)
     * @return bool Enabled status
     */
    public static function isEnabled($feature, $tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        // Check if entire groups module is disabled
        if ($feature !== self::FEATURE_GROUPS_MODULE) {
            if (!self::isEnabled(self::FEATURE_GROUPS_MODULE, $tenantId)) {
                return false;
            }
        }

        self::ensureTableExists();

        try {
            $result = Database::query(
                "SELECT is_enabled FROM group_feature_toggles
                 WHERE tenant_id = ? AND feature_key = ?",
                [$tenantId, $feature]
            )->fetch();

            if ($result) {
                return (bool) $result['is_enabled'];
            }

            // Return default value if not set
            return self::getDefaultValue($feature);
        } catch (\Exception $e) {
            error_log("GroupFeatureToggleService: Failed to check feature - " . $e->getMessage());
            return self::getDefaultValue($feature);
        }
    }

    /**
     * Enable a feature for a tenant
     *
     * @param string $feature Feature constant
     * @param int|null $tenantId Tenant ID (defaults to current)
     * @return bool Success
     */
    public static function enable($feature, $tenantId = null)
    {
        return self::setFeature($feature, true, $tenantId);
    }

    /**
     * Disable a feature for a tenant
     *
     * @param string $feature Feature constant
     * @param int|null $tenantId Tenant ID (defaults to current)
     * @return bool Success
     */
    public static function disable($feature, $tenantId = null)
    {
        return self::setFeature($feature, false, $tenantId);
    }

    /**
     * Set feature toggle state
     *
     * @param string $feature Feature constant
     * @param bool $is_enabled Enabled state
     * @param int|null $tenantId Tenant ID
     * @return bool Success
     */
    private static function setFeature($feature, $is_enabled, $tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        self::ensureTableExists();

        try {
            Database::query(
                "REPLACE INTO group_feature_toggles (tenant_id, feature_key, is_enabled, updated_at)
                 VALUES (?, ?, ?, NOW())",
                [$tenantId, $feature, $is_enabled ? 1 : 0]
            );

            // Log the change
            GroupAuditService::log(
                'feature_toggle_changed',
                null,
                $_SESSION['user_id'] ?? null,
                [
                    'feature' => $feature,
                    'is_enabled' => $is_enabled,
                ]
            );

            return true;
        } catch (\Exception $e) {
            error_log("GroupFeatureToggleService: Failed to set feature - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all feature toggles for a tenant
     *
     * @param int|null $tenantId Tenant ID (defaults to current)
     * @return array Feature toggles with their states
     */
    public static function getAllFeatures($tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        self::ensureTableExists();

        $features = self::getFeatureDefinitions();
        $featureStates = [];

        try {
            $results = Database::query(
                "SELECT feature_key, is_enabled FROM group_feature_toggles WHERE tenant_id = ?",
                [$tenantId]
            )->fetchAll();

            $toggles = [];
            foreach ($results as $row) {
                $toggles[$row['feature_key']] = (bool) $row['is_enabled'];
            }

            // Merge with defaults
            foreach ($features as $key => $definition) {
                $featureStates[$key] = [
                    'is_enabled' => $toggles[$key] ?? $definition['default'],
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                    'category' => $definition['category'],
                    'dependencies' => $definition['dependencies'] ?? [],
                ];
            }

            return $featureStates;
        } catch (\Exception $e) {
            error_log("GroupFeatureToggleService: Failed to get features - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Bulk enable/disable features
     *
     * @param array $features Array of feature_key => is_enabled pairs
     * @param int|null $tenantId Tenant ID
     * @return bool Success
     */
    public static function bulkSet(array $features, $tenantId = null)
    {
        $success = true;
        foreach ($features as $feature => $is_enabled) {
            if (!self::setFeature($feature, $is_enabled, $tenantId)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Reset all features to defaults
     *
     * @param int|null $tenantId Tenant ID
     * @return bool Success
     */
    public static function resetToDefaults($tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        try {
            Database::query(
                "DELETE FROM group_feature_toggles WHERE tenant_id = ?",
                [$tenantId]
            );
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if user can access groups based on feature toggles
     *
     * @param int|null $userId User ID
     * @return array ['allowed' => bool, 'reason' => string|null]
     */
    public static function canAccessGroups($userId = null)
    {
        // Check main module
        if (!self::isEnabled(self::FEATURE_GROUPS_MODULE)) {
            return [
                'allowed' => false,
                'reason' => 'Groups feature is not is_enabled for this community'
            ];
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Check if user can create groups
     *
     * @param int $userId User ID
     * @param bool $isHub Is hub group
     * @return array ['allowed' => bool, 'reason' => string|null]
     */
    public static function canCreateGroup($userId, $isHub = false)
    {
        // Check module
        $access = self::canAccessGroups($userId);
        if (!$access['allowed']) {
            return $access;
        }

        // Check group creation
        if (!self::isEnabled(self::FEATURE_GROUP_CREATION)) {
            return [
                'allowed' => false,
                'reason' => 'Group creation is disabled'
            ];
        }

        // Check hub/regular groups
        if ($isHub) {
            if (!self::isEnabled(self::FEATURE_HUB_GROUPS)) {
                return [
                    'allowed' => false,
                    'reason' => 'Hub groups are disabled'
                ];
            }
        } else {
            if (!self::isEnabled(self::FEATURE_REGULAR_GROUPS)) {
                return [
                    'allowed' => false,
                    'reason' => 'Regular groups are disabled'
                ];
            }
        }

        // Check permissions
        return GroupConfigurationService::canUserCreateGroup($userId);
    }

    /**
     * Get feature definition by key
     *
     * @param string $feature Feature key
     * @return array|null Feature definition
     */
    public static function getFeatureDefinition($feature)
    {
        $definitions = self::getFeatureDefinitions();
        return $definitions[$feature] ?? null;
    }

    /**
     * Get all feature definitions with metadata
     *
     * @return array Feature definitions
     */
    public static function getFeatureDefinitions()
    {
        return [
            // Main module
            self::FEATURE_GROUPS_MODULE => [
                'label' => 'Groups Module',
                'description' => 'Enable/disable the entire groups module',
                'category' => 'core',
                'default' => true,
                'dependencies' => [],
            ],

            // Core features
            self::FEATURE_GROUP_CREATION => [
                'label' => 'Group Creation',
                'description' => 'Allow users to create new groups',
                'category' => 'core',
                'default' => true,
                'dependencies' => [self::FEATURE_GROUPS_MODULE],
            ],
            self::FEATURE_HUB_GROUPS => [
                'label' => 'Hub Groups',
                'description' => 'Enable geographic hub groups',
                'category' => 'core',
                'default' => true,
                'dependencies' => [self::FEATURE_GROUPS_MODULE],
            ],
            self::FEATURE_REGULAR_GROUPS => [
                'label' => 'Regular Groups',
                'description' => 'Enable regular interest-based groups',
                'category' => 'core',
                'default' => true,
                'dependencies' => [self::FEATURE_GROUPS_MODULE],
            ],
            self::FEATURE_PRIVATE_GROUPS => [
                'label' => 'Private Groups',
                'description' => 'Allow private/invite-only groups',
                'category' => 'core',
                'default' => true,
                'dependencies' => [self::FEATURE_GROUPS_MODULE],
            ],
            self::FEATURE_SUB_GROUPS => [
                'label' => 'Sub-Groups',
                'description' => 'Allow nested sub-groups',
                'category' => 'core',
                'default' => true,
                'dependencies' => [self::FEATURE_GROUPS_MODULE],
            ],

            // Interaction features
            self::FEATURE_DISCUSSIONS => [
                'label' => 'Discussions',
                'description' => 'Enable group discussions and posts',
                'category' => 'interaction',
                'default' => true,
                'dependencies' => [self::FEATURE_GROUPS_MODULE],
            ],
            self::FEATURE_FEEDBACK => [
                'label' => 'Feedback & Ratings',
                'description' => 'Allow members to rate and review groups',
                'category' => 'interaction',
                'default' => true,
                'dependencies' => [self::FEATURE_GROUPS_MODULE],
            ],
            self::FEATURE_MEMBER_INVITES => [
                'label' => 'Member Invites',
                'description' => 'Allow members to invite others to groups',
                'category' => 'interaction',
                'default' => true,
                'dependencies' => [self::FEATURE_GROUPS_MODULE],
            ],
            self::FEATURE_JOIN_REQUESTS => [
                'label' => 'Join Requests',
                'description' => 'Allow users to request to join groups',
                'category' => 'interaction',
                'default' => true,
                'dependencies' => [self::FEATURE_GROUPS_MODULE],
            ],

            // Gamification features
            self::FEATURE_ACHIEVEMENTS => [
                'label' => 'Group Achievements',
                'description' => 'Enable achievement system for groups',
                'category' => 'gamification',
                'default' => true,
                'dependencies' => [self::FEATURE_GROUPS_MODULE],
            ],
            self::FEATURE_BADGES => [
                'label' => 'Group Badges',
                'description' => 'Enable badge system for groups',
                'category' => 'gamification',
                'default' => false,
                'dependencies' => [self::FEATURE_GROUPS_MODULE],
            ],
            self::FEATURE_LEADERBOARD => [
                'label' => 'Group Leaderboard',
                'description' => 'Show leaderboard of top groups',
                'category' => 'gamification',
                'default' => false,
                'dependencies' => [self::FEATURE_GROUPS_MODULE],
            ],

            // Advanced features
            self::FEATURE_ANALYTICS => [
                'label' => 'Analytics',
                'description' => 'Enable analytics dashboard for group owners',
                'category' => 'advanced',
                'default' => true,
                'dependencies' => [self::FEATURE_GROUPS_MODULE],
            ],
            self::FEATURE_MODERATION => [
                'label' => 'Content Moderation',
                'description' => 'Enable content moderation tools',
                'category' => 'advanced',
                'default' => true,
                'dependencies' => [self::FEATURE_GROUPS_MODULE],
            ],
            self::FEATURE_APPROVAL_WORKFLOW => [
                'label' => 'Approval Workflow',
                'description' => 'Require admin approval for new groups',
                'category' => 'advanced',
                'default' => false,
                'dependencies' => [self::FEATURE_GROUPS_MODULE],
            ],
            self::FEATURE_EXPORT => [
                'label' => 'Data Export',
                'description' => 'Allow exporting group data',
                'category' => 'advanced',
                'default' => true,
                'dependencies' => [self::FEATURE_GROUPS_MODULE],
            ],
            self::FEATURE_AUDIT_LOG => [
                'label' => 'Audit Logging',
                'description' => 'Track all group actions in audit log',
                'category' => 'advanced',
                'default' => true,
                'dependencies' => [self::FEATURE_GROUPS_MODULE],
            ],

            // UI features
            self::FEATURE_DISCOVERY => [
                'label' => 'Group Discovery',
                'description' => 'Show group discovery/browse page',
                'category' => 'ui',
                'default' => true,
                'dependencies' => [self::FEATURE_GROUPS_MODULE],
            ],
            self::FEATURE_SEARCH => [
                'label' => 'Group Search',
                'description' => 'Enable search functionality for groups',
                'category' => 'ui',
                'default' => true,
                'dependencies' => [self::FEATURE_GROUPS_MODULE, self::FEATURE_DISCOVERY],
            ],
            self::FEATURE_FILTERS => [
                'label' => 'Advanced Filters',
                'description' => 'Enable filtering by type, location, etc.',
                'category' => 'ui',
                'default' => true,
                'dependencies' => [self::FEATURE_GROUPS_MODULE, self::FEATURE_DISCOVERY],
            ],
            self::FEATURE_MAPS => [
                'label' => 'Map View',
                'description' => 'Show groups on a map',
                'category' => 'ui',
                'default' => false,
                'dependencies' => [self::FEATURE_GROUPS_MODULE, self::FEATURE_DISCOVERY],
            ],
        ];
    }

    /**
     * Get default value for a feature
     *
     * @param string $feature Feature constant
     * @return bool Default value
     */
    private static function getDefaultValue($feature)
    {
        $definition = self::getFeatureDefinition($feature);
        return $definition['default'] ?? true;
    }

    /**
     * Validate feature dependencies
     *
     * @param string $feature Feature to validate
     * @return array ['valid' => bool, 'missing' => array]
     */
    public static function validateDependencies($feature)
    {
        $definition = self::getFeatureDefinition($feature);
        if (!$definition) {
            return ['valid' => false, 'missing' => []];
        }

        $dependencies = $definition['dependencies'] ?? [];
        $missing = [];

        foreach ($dependencies as $dependency) {
            if (!self::isEnabled($dependency)) {
                $missing[] = $dependency;
            }
        }

        return [
            'valid' => empty($missing),
            'missing' => $missing,
        ];
    }

    /**
     * Get features grouped by category
     *
     * @param int|null $tenantId Tenant ID
     * @return array Features grouped by category
     */
    public static function getFeaturesByCategory($tenantId = null)
    {
        $features = self::getAllFeatures($tenantId);
        $grouped = [
            'core' => [],
            'interaction' => [],
            'gamification' => [],
            'advanced' => [],
            'ui' => [],
        ];

        foreach ($features as $key => $feature) {
            $category = $feature['category'];
            $grouped[$category][$key] = $feature;
        }

        return $grouped;
    }

    /**
     * Ensure feature toggles table exists
     */
    private static function ensureTableExists()
    {
        static $checked = false;
        if ($checked) return;

        try {
            Database::query("SELECT 1 FROM group_feature_toggles LIMIT 1");
        } catch (\Exception $e) {
            Database::query("
                CREATE TABLE IF NOT EXISTS group_feature_toggles (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT NOT NULL,
                    feature_key VARCHAR(100) NOT NULL,
                    is_enabled TINYINT(1) DEFAULT 1,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_tenant_feature (tenant_id, feature_key),
                    INDEX idx_tenant (tenant_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        $checked = true;
    }

    /**
     * Get feature statistics
     *
     * @param int|null $tenantId Tenant ID
     * @return array Statistics
     */
    public static function getStatistics($tenantId = null)
    {
        $features = self::getAllFeatures($tenantId);

        $stats = [
            'total_features' => count($features),
            'is_enabled_count' => 0,
            'disabled_count' => 0,
            'by_category' => [
                'core' => ['is_enabled' => 0, 'total' => 0],
                'interaction' => ['is_enabled' => 0, 'total' => 0],
                'gamification' => ['is_enabled' => 0, 'total' => 0],
                'advanced' => ['is_enabled' => 0, 'total' => 0],
                'ui' => ['is_enabled' => 0, 'total' => 0],
            ],
        ];

        foreach ($features as $feature) {
            if ($feature['is_enabled']) {
                $stats['is_enabled_count']++;
                $stats['by_category'][$feature['category']]['is_enabled']++;
            } else {
                $stats['disabled_count']++;
            }
            $stats['by_category'][$feature['category']]['total']++;
        }

        return $stats;
    }
}
