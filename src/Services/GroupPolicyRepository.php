<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * GroupPolicyRepository
 *
 * Repository for storing and managing tenant-specific group policies.
 * Provides a flexible way to define custom rules and policies per tenant.
 */
class GroupPolicyRepository
{
    // Policy categories
    const CATEGORY_CREATION = 'creation';
    const CATEGORY_MEMBERSHIP = 'membership';
    const CATEGORY_CONTENT = 'content';
    const CATEGORY_MODERATION = 'moderation';
    const CATEGORY_NOTIFICATIONS = 'notifications';
    const CATEGORY_FEATURES = 'features';

    // Policy types
    const TYPE_BOOLEAN = 'boolean';
    const TYPE_NUMBER = 'number';
    const TYPE_STRING = 'string';
    const TYPE_JSON = 'json';
    const TYPE_LIST = 'list';

    /**
     * Create or update a policy
     *
     * @param string $key Policy key
     * @param mixed $value Policy value
     * @param string $category Policy category
     * @param string $type Value type
     * @param string|null $description Policy description
     * @param int|null $tenantId Tenant ID
     * @return bool Success
     */
    public static function setPolicy($key, $value, $category = self::CATEGORY_FEATURES, $type = self::TYPE_STRING, $description = null, $tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        self::ensureTableExists();

        $encodedValue = self::encodeValue($value, $type);

        try {
            Database::query(
                "REPLACE INTO group_policies
                 (tenant_id, policy_key, policy_value, category, value_type, description, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())",
                [$tenantId, $key, $encodedValue, $category, $type, $description]
            );
            return true;
        } catch (\Exception $e) {
            error_log("GroupPolicyRepository: Failed to set policy - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get a policy value
     *
     * @param string $key Policy key
     * @param mixed $default Default value if not found
     * @param int|null $tenantId Tenant ID
     * @return mixed Policy value
     */
    public static function getPolicy($key, $default = null, $tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        self::ensureTableExists();

        try {
            $result = Database::query(
                "SELECT policy_value, value_type FROM group_policies
                 WHERE tenant_id = ? AND policy_key = ?",
                [$tenantId, $key]
            )->fetch();

            if ($result) {
                return self::decodeValue($result['policy_value'], $result['value_type']);
            }

            return $default;
        } catch (\Exception $e) {
            error_log("GroupPolicyRepository: Failed to get policy - " . $e->getMessage());
            return $default;
        }
    }

    /**
     * Get all policies for a category
     *
     * @param string $category Policy category
     * @param int|null $tenantId Tenant ID
     * @return array Policies
     */
    public static function getPoliciesByCategory($category, $tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        self::ensureTableExists();

        try {
            $results = Database::query(
                "SELECT policy_key, policy_value, value_type, description
                 FROM group_policies
                 WHERE tenant_id = ? AND category = ?
                 ORDER BY policy_key",
                [$tenantId, $category]
            )->fetchAll();

            $policies = [];
            foreach ($results as $row) {
                $policies[$row['policy_key']] = [
                    'value' => self::decodeValue($row['policy_value'], $row['value_type']),
                    'description' => $row['description'],
                    'type' => $row['value_type'],
                ];
            }

            return $policies;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get all policies for tenant
     *
     * @param int|null $tenantId Tenant ID
     * @return array All policies grouped by category
     */
    public static function getAllPolicies($tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        self::ensureTableExists();

        try {
            $results = Database::query(
                "SELECT policy_key, policy_value, value_type, category, description
                 FROM group_policies
                 WHERE tenant_id = ?
                 ORDER BY category, policy_key",
                [$tenantId]
            )->fetchAll();

            $policies = [];
            foreach ($results as $row) {
                $category = $row['category'];
                if (!isset($policies[$category])) {
                    $policies[$category] = [];
                }

                $policies[$category][$row['policy_key']] = [
                    'value' => self::decodeValue($row['policy_value'], $row['value_type']),
                    'description' => $row['description'],
                    'type' => $row['value_type'],
                ];
            }

            return $policies;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Delete a policy
     *
     * @param string $key Policy key
     * @param int|null $tenantId Tenant ID
     * @return bool Success
     */
    public static function deletePolicy($key, $tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        try {
            Database::query(
                "DELETE FROM group_policies WHERE tenant_id = ? AND policy_key = ?",
                [$tenantId, $key]
            );
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if a policy exists
     *
     * @param string $key Policy key
     * @param int|null $tenantId Tenant ID
     * @return bool
     */
    public static function hasPolicy($key, $tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        try {
            $result = Database::query(
                "SELECT 1 FROM group_policies WHERE tenant_id = ? AND policy_key = ?",
                [$tenantId, $key]
            )->fetch();

            return (bool) $result;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Bulk set policies
     *
     * @param array $policies Array of [key => ['value' => val, 'category' => cat, 'type' => type, 'description' => desc]]
     * @param int|null $tenantId Tenant ID
     * @return bool Success
     */
    public static function bulkSetPolicies(array $policies, $tenantId = null)
    {
        $success = true;
        foreach ($policies as $key => $config) {
            $value = $config['value'] ?? null;
            $category = $config['category'] ?? self::CATEGORY_FEATURES;
            $type = $config['type'] ?? self::TYPE_STRING;
            $description = $config['description'] ?? null;

            if (!self::setPolicy($key, $value, $category, $type, $description, $tenantId)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Import policies from array
     *
     * @param array $policiesData Policies data
     * @param int|null $tenantId Tenant ID
     * @param bool $overwrite Overwrite existing policies
     * @return array ['success' => int, 'failed' => int, 'skipped' => int]
     */
    public static function importPolicies(array $policiesData, $tenantId = null, $overwrite = false)
    {
        $stats = ['success' => 0, 'failed' => 0, 'skipped' => 0];

        foreach ($policiesData as $key => $config) {
            // Skip if exists and not overwriting
            if (!$overwrite && self::hasPolicy($key, $tenantId)) {
                $stats['skipped']++;
                continue;
            }

            $value = $config['value'] ?? null;
            $category = $config['category'] ?? self::CATEGORY_FEATURES;
            $type = $config['type'] ?? self::TYPE_STRING;
            $description = $config['description'] ?? null;

            if (self::setPolicy($key, $value, $category, $type, $description, $tenantId)) {
                $stats['success']++;
            } else {
                $stats['failed']++;
            }
        }

        return $stats;
    }

    /**
     * Export policies to array
     *
     * @param int|null $tenantId Tenant ID
     * @return array Policies data
     */
    public static function exportPolicies($tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        try {
            $results = Database::query(
                "SELECT policy_key, policy_value, value_type, category, description
                 FROM group_policies
                 WHERE tenant_id = ?",
                [$tenantId]
            )->fetchAll();

            $export = [];
            foreach ($results as $row) {
                $export[$row['policy_key']] = [
                    'value' => self::decodeValue($row['policy_value'], $row['value_type']),
                    'category' => $row['category'],
                    'type' => $row['value_type'],
                    'description' => $row['description'],
                ];
            }

            return $export;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Clone policies from one tenant to another
     *
     * @param int $sourceTenantId Source tenant ID
     * @param int $targetTenantId Target tenant ID
     * @param bool $overwrite Overwrite existing policies
     * @return array Stats
     */
    public static function clonePolicies($sourceTenantId, $targetTenantId, $overwrite = false)
    {
        $policies = self::exportPolicies($sourceTenantId);
        return self::importPolicies($policies, $targetTenantId, $overwrite);
    }

    /**
     * Get policy metadata
     *
     * @param string $key Policy key
     * @param int|null $tenantId Tenant ID
     * @return array|null Metadata
     */
    public static function getPolicyMetadata($key, $tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        try {
            $result = Database::query(
                "SELECT policy_key, category, value_type, description, updated_at
                 FROM group_policies
                 WHERE tenant_id = ? AND policy_key = ?",
                [$tenantId, $key]
            )->fetch();

            return $result ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Search policies
     *
     * @param string $search Search term
     * @param int|null $tenantId Tenant ID
     * @return array Matching policies
     */
    public static function searchPolicies($search, $tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        $searchTerm = "%{$search}%";

        try {
            $results = Database::query(
                "SELECT policy_key, policy_value, value_type, category, description
                 FROM group_policies
                 WHERE tenant_id = ?
                 AND (policy_key LIKE ? OR description LIKE ?)
                 ORDER BY policy_key",
                [$tenantId, $searchTerm, $searchTerm]
            )->fetchAll();

            $policies = [];
            foreach ($results as $row) {
                $policies[$row['policy_key']] = [
                    'value' => self::decodeValue($row['policy_value'], $row['value_type']),
                    'category' => $row['category'],
                    'type' => $row['value_type'],
                    'description' => $row['description'],
                ];
            }

            return $policies;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get policy count by category
     *
     * @param int|null $tenantId Tenant ID
     * @return array Category counts
     */
    public static function getPolicyCounts($tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        try {
            $results = Database::query(
                "SELECT category, COUNT(*) as count
                 FROM group_policies
                 WHERE tenant_id = ?
                 GROUP BY category",
                [$tenantId]
            )->fetchAll();

            $counts = [];
            foreach ($results as $row) {
                $counts[$row['category']] = (int) $row['count'];
            }

            return $counts;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Validate policy value against type
     *
     * @param mixed $value Value to validate
     * @param string $type Expected type
     * @return bool Valid
     */
    public static function validatePolicyValue($value, $type)
    {
        switch ($type) {
            case self::TYPE_BOOLEAN:
                return is_bool($value);
            case self::TYPE_NUMBER:
                return is_numeric($value);
            case self::TYPE_STRING:
                return is_string($value);
            case self::TYPE_JSON:
            case self::TYPE_LIST:
                return is_array($value) || is_object($value);
            default:
                return true;
        }
    }

    /**
     * Encode value for storage
     *
     * @param mixed $value Value
     * @param string $type Type
     * @return string Encoded value
     */
    private static function encodeValue($value, $type)
    {
        switch ($type) {
            case self::TYPE_BOOLEAN:
                return $value ? '1' : '0';
            case self::TYPE_NUMBER:
                return (string) $value;
            case self::TYPE_JSON:
            case self::TYPE_LIST:
                return json_encode($value);
            case self::TYPE_STRING:
            default:
                return (string) $value;
        }
    }

    /**
     * Decode value from storage
     *
     * @param string $value Stored value
     * @param string $type Type
     * @return mixed Decoded value
     */
    private static function decodeValue($value, $type)
    {
        switch ($type) {
            case self::TYPE_BOOLEAN:
                return $value === '1';
            case self::TYPE_NUMBER:
                return strpos($value, '.') !== false ? (float) $value : (int) $value;
            case self::TYPE_JSON:
            case self::TYPE_LIST:
                return json_decode($value, true);
            case self::TYPE_STRING:
            default:
                return $value;
        }
    }

    /**
     * Get default policies template
     *
     * @return array Default policies
     */
    public static function getDefaultPoliciesTemplate()
    {
        return [
            'banned_words' => [
                'value' => [],
                'category' => self::CATEGORY_CONTENT,
                'type' => self::TYPE_LIST,
                'description' => 'List of banned words in group names and descriptions',
            ],
            'require_approval_for_hubs' => [
                'value' => true,
                'category' => self::CATEGORY_CREATION,
                'type' => self::TYPE_BOOLEAN,
                'description' => 'Require admin approval for hub group creation',
            ],
            'max_pending_join_requests' => [
                'value' => 50,
                'category' => self::CATEGORY_MEMBERSHIP,
                'type' => self::TYPE_NUMBER,
                'description' => 'Maximum pending join requests per group',
            ],
            'auto_archive_inactive_days' => [
                'value' => 180,
                'category' => self::CATEGORY_MODERATION,
                'type' => self::TYPE_NUMBER,
                'description' => 'Auto-archive groups with no activity for this many days',
            ],
            'notification_frequency' => [
                'value' => 'daily',
                'category' => self::CATEGORY_NOTIFICATIONS,
                'type' => self::TYPE_STRING,
                'description' => 'Default notification frequency (instant, daily, weekly)',
            ],
            'allow_anonymous_viewing' => [
                'value' => true,
                'category' => self::CATEGORY_FEATURES,
                'type' => self::TYPE_BOOLEAN,
                'description' => 'Allow non-members to view public groups',
            ],
            'enable_group_analytics' => [
                'value' => true,
                'category' => self::CATEGORY_FEATURES,
                'type' => self::TYPE_BOOLEAN,
                'description' => 'Enable analytics dashboard for group owners',
            ],
            'member_kick_cooldown_hours' => [
                'value' => 24,
                'category' => self::CATEGORY_MODERATION,
                'type' => self::TYPE_NUMBER,
                'description' => 'Hours before a kicked member can rejoin',
            ],
        ];
    }

    /**
     * Ensure policies table exists
     */
    private static function ensureTableExists()
    {
        static $checked = false;
        if ($checked) return;

        try {
            Database::query("SELECT 1 FROM group_policies LIMIT 1");
        } catch (\Exception $e) {
            Database::query("
                CREATE TABLE IF NOT EXISTS group_policies (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT NOT NULL,
                    policy_key VARCHAR(100) NOT NULL,
                    policy_value TEXT NOT NULL,
                    category VARCHAR(50) NOT NULL,
                    value_type VARCHAR(20) NOT NULL,
                    description TEXT NULL,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_tenant_policy (tenant_id, policy_key),
                    INDEX idx_tenant_category (tenant_id, category),
                    INDEX idx_tenant (tenant_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        $checked = true;
    }
}
