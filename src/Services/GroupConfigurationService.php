<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * GroupConfigurationService
 *
 * Centralized configuration and settings management for groups module.
 * Provides tenant-aware policies, permissions, and group-level settings.
 */
class GroupConfigurationService
{
    // Configuration keys
    const CONFIG_ALLOW_USER_GROUP_CREATION = 'allow_user_group_creation';
    const CONFIG_REQUIRE_GROUP_APPROVAL = 'require_group_approval';
    const CONFIG_MAX_GROUPS_PER_USER = 'max_groups_per_user';
    const CONFIG_MAX_MEMBERS_PER_GROUP = 'max_members_per_group';
    const CONFIG_ALLOW_PRIVATE_GROUPS = 'allow_private_groups';
    const CONFIG_REQUIRE_LOCATION = 'require_location';
    const CONFIG_ENABLE_DISCUSSIONS = 'enable_discussions';
    const CONFIG_ENABLE_FEEDBACK = 'enable_feedback';
    const CONFIG_ENABLE_ACHIEVEMENTS = 'enable_achievements';
    const CONFIG_DEFAULT_VISIBILITY = 'default_visibility';
    const CONFIG_MODERATION_ENABLED = 'moderation_enabled';
    const CONFIG_AUTO_APPROVE_MEMBERS = 'auto_approve_members';
    const CONFIG_ALLOW_MEMBER_INVITES = 'allow_member_invites';
    const CONFIG_MIN_DESCRIPTION_LENGTH = 'min_description_length';
    const CONFIG_MAX_DESCRIPTION_LENGTH = 'max_description_length';
    const CONFIG_CONTENT_FILTER_ENABLED = 'content_filter_enabled';
    const CONFIG_PROFANITY_FILTER_ENABLED = 'profanity_filter_enabled';
    const CONFIG_IMAGE_REQUIRED = 'image_required';
    const CONFIG_ALLOW_SUB_GROUPS = 'allow_sub_groups';
    const CONFIG_NOTIFICATION_DIGEST_ENABLED = 'notification_digest_enabled';

    // Default configuration config_values
    private static $defaults = [
        self::CONFIG_ALLOW_USER_GROUP_CREATION => true,
        self::CONFIG_REQUIRE_GROUP_APPROVAL => false,
        self::CONFIG_MAX_GROUPS_PER_USER => null, // null = unlimited
        self::CONFIG_MAX_MEMBERS_PER_GROUP => null, // null = unlimited
        self::CONFIG_ALLOW_PRIVATE_GROUPS => true,
        self::CONFIG_REQUIRE_LOCATION => false,
        self::CONFIG_ENABLE_DISCUSSIONS => true,
        self::CONFIG_ENABLE_FEEDBACK => true,
        self::CONFIG_ENABLE_ACHIEVEMENTS => true,
        self::CONFIG_DEFAULT_VISIBILITY => 'public',
        self::CONFIG_MODERATION_ENABLED => false,
        self::CONFIG_AUTO_APPROVE_MEMBERS => true,
        self::CONFIG_ALLOW_MEMBER_INVITES => true,
        self::CONFIG_MIN_DESCRIPTION_LENGTH => 10,
        self::CONFIG_MAX_DESCRIPTION_LENGTH => 5000,
        self::CONFIG_CONTENT_FILTER_ENABLED => false,
        self::CONFIG_PROFANITY_FILTER_ENABLED => false,
        self::CONFIG_IMAGE_REQUIRED => false,
        self::CONFIG_ALLOW_SUB_GROUPS => true,
        self::CONFIG_NOTIFICATION_DIGEST_ENABLED => false,
    ];

    /**
     * Get configuration config_value for tenant
     *
     * @param string $key Configuration key
     * @param int|null $tenantId Tenant ID (defaults to current tenant)
     * @return mixed Configuration config_value
     */
    public static function get($key, $tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        self::ensureTableExists();

        try {
            $result = Database::query(
                "SELECT config_value FROM group_configuration
                 WHERE tenant_id = ? AND config_key = ?",
                [$tenantId, $key]
            )->fetch();

            if ($result) {
                return self::decodeValue($result['config_value']);
            }

            // Return default if not set
            return self::$defaults[$key] ?? null;
        } catch (\Exception $e) {
            error_log("GroupConfigurationService: Failed to get config - " . $e->getMessage());
            return self::$defaults[$key] ?? null;
        }
    }

    /**
     * Set configuration config_value for tenant
     *
     * @param string $key Configuration key
     * @param mixed $config_value Configuration config_value
     * @param int|null $tenantId Tenant ID (defaults to current tenant)
     * @return bool Success
     */
    public static function set($key, $config_value, $tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        self::ensureTableExists();

        $encodedValue = self::encodeValue($config_value);

        try {
            // Use REPLACE to insert or update
            Database::query(
                "REPLACE INTO group_configuration (tenant_id, config_key, config_value, updated_at)
                 VALUES (?, ?, ?, NOW())",
                [$tenantId, $key, $encodedValue]
            );

            return true;
        } catch (\Exception $e) {
            error_log("GroupConfigurationService: Failed to set config - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Set multiple configuration config_values at once
     *
     * @param array $configs Key-config_value pairs
     * @param int|null $tenantId Tenant ID (defaults to current tenant)
     * @return bool Success
     */
    public static function setMultiple(array $configs, $tenantId = null)
    {
        $success = true;
        foreach ($configs as $key => $config_value) {
            if (!self::set($key, $config_value, $tenantId)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Get all configuration config_values for tenant
     *
     * @param int|null $tenantId Tenant ID (defaults to current tenant)
     * @return array Configuration key-config_value pairs
     */
    public static function getAll($tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        self::ensureTableExists();

        try {
            $results = Database::query(
                "SELECT config_key, config_value FROM group_configuration WHERE tenant_id = ?",
                [$tenantId]
            )->fetchAll();

            $config = self::$defaults; // Start with defaults

            // Override with tenant-specific config_values
            foreach ($results as $row) {
                $config[$row['config_key']] = self::decodeValue($row['config_value']);
            }

            return $config;
        } catch (\Exception $e) {
            error_log("GroupConfigurationService: Failed to get all config - " . $e->getMessage());
            return self::$defaults;
        }
    }

    /**
     * Reset configuration to defaults
     *
     * @param int|null $tenantId Tenant ID (defaults to current tenant)
     * @return bool Success
     */
    public static function resetToDefaults($tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        try {
            Database::query(
                "DELETE FROM group_configuration WHERE tenant_id = ?",
                [$tenantId]
            );
            return true;
        } catch (\Exception $e) {
            error_log("GroupConfigurationService: Failed to reset config - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if user can create groups
     *
     * @param int $userId User ID
     * @return array ['allowed' => bool, 'reason' => string|null]
     */
    public static function canUserCreateGroup($userId)
    {
        // Check if group creation is enabled
        if (!self::get(self::CONFIG_ALLOW_USER_GROUP_CREATION)) {
            return [
                'allowed' => false,
                'reason' => 'Group creation is disabled by administrator'
            ];
        }

        // Check max groups per user limit
        $maxGroups = self::get(self::CONFIG_MAX_GROUPS_PER_USER);
        if ($maxGroups !== null) {
            $userGroupCount = self::getUserGroupCount($userId);
            if ($userGroupCount >= $maxGroups) {
                return [
                    'allowed' => false,
                    'reason' => "You have reached the maximum number of groups ($maxGroups)"
                ];
            }
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Get count of groups created by user
     *
     * @param int $userId User ID
     * @return int Group count
     */
    private static function getUserGroupCount($userId)
    {
        $tenantId = TenantContext::getId();

        try {
            return (int) Database::query(
                "SELECT COUNT(*) FROM groups
                 WHERE tenant_id = ? AND owner_id = ?",
                [$tenantId, $userId]
            )->fetchColumn();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Check if group can accept new members
     *
     * @param int $groupId Group ID
     * @return array ['allowed' => bool, 'reason' => string|null]
     */
    public static function canGroupAcceptMembers($groupId)
    {
        $maxMembers = self::get(self::CONFIG_MAX_MEMBERS_PER_GROUP);

        if ($maxMembers !== null) {
            $memberCount = self::getGroupMemberCount($groupId);
            if ($memberCount >= $maxMembers) {
                return [
                    'allowed' => false,
                    'reason' => "Group has reached maximum member capacity ($maxMembers)"
                ];
            }
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Get count of active members in group
     *
     * @param int $groupId Group ID
     * @return int Member count
     */
    private static function getGroupMemberCount($groupId)
    {
        try {
            return (int) Database::query(
                "SELECT COUNT(*) FROM group_members
                 WHERE group_id = ? AND status = 'active'",
                [$groupId]
            )->fetchColumn();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Validate group data against configuration rules
     *
     * @param array $data Group data
     * @return array ['valid' => bool, 'errors' => array]
     */
    public static function validateGroupData(array $data)
    {
        $errors = [];

        // Validate description length
        if (isset($data['description'])) {
            $minLength = self::get(self::CONFIG_MIN_DESCRIPTION_LENGTH);
            $maxLength = self::get(self::CONFIG_MAX_DESCRIPTION_LENGTH);

            $descLength = strlen(trim($data['description']));

            if ($descLength < $minLength) {
                $errors['description'] = "Description must be at least $minLength characters";
            }

            if ($descLength > $maxLength) {
                $errors['description'] = "Description cannot exceed $maxLength characters";
            }
        }

        // Validate location if required
        if (self::get(self::CONFIG_REQUIRE_LOCATION)) {
            if (empty($data['location'])) {
                $errors['location'] = 'Location is required';
            }
        }

        // Validate image if required
        if (self::get(self::CONFIG_IMAGE_REQUIRED)) {
            if (empty($data['image_url'])) {
                $errors['image'] = 'Group image is required';
            }
        }

        // Validate visibility
        if (isset($data['visibility']) && $data['visibility'] === 'private') {
            if (!self::get(self::CONFIG_ALLOW_PRIVATE_GROUPS)) {
                $errors['visibility'] = 'Private groups are not allowed';
            }
        }

        // Content filtering
        if (self::get(self::CONFIG_CONTENT_FILTER_ENABLED)) {
            if (isset($data['name'])) {
                if (self::containsInappropriateContent($data['name'])) {
                    $errors['name'] = 'Group name contains inappropriate content';
                }
            }

            if (isset($data['description'])) {
                if (self::containsInappropriateContent($data['description'])) {
                    $errors['description'] = 'Description contains inappropriate content';
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Simple content filter (can be extended with more sophisticated filtering)
     *
     * @param string $content Content to check
     * @return bool True if inappropriate content found
     */
    private static function containsInappropriateContent($content)
    {
        if (!self::get(self::CONFIG_PROFANITY_FILTER_ENABLED)) {
            return false;
        }

        // Basic profanity list (extend as needed)
        $profanityList = [
            'spam', 'scam', 'viagra', 'casino', 'porn',
            // Add more terms as needed
        ];

        $contentLower = strtolower($content);
        foreach ($profanityList as $word) {
            if (strpos($contentLower, $word) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get configuration schema with descriptions
     *
     * @return array Configuration schema
     */
    public static function getConfigSchema()
    {
        return [
            self::CONFIG_ALLOW_USER_GROUP_CREATION => [
                'type' => 'boolean',
                'label' => 'Allow User Group Creation',
                'description' => 'Allow regular users to create groups',
                'default' => true,
            ],
            self::CONFIG_REQUIRE_GROUP_APPROVAL => [
                'type' => 'boolean',
                'label' => 'Require Group Approval',
                'description' => 'New groups must be approved by admin before becoming active',
                'default' => false,
            ],
            self::CONFIG_MAX_GROUPS_PER_USER => [
                'type' => 'number',
                'label' => 'Max Groups Per User',
                'description' => 'Maximum number of groups a user can create (null = unlimited)',
                'default' => null,
            ],
            self::CONFIG_MAX_MEMBERS_PER_GROUP => [
                'type' => 'number',
                'label' => 'Max Members Per Group',
                'description' => 'Maximum members allowed in a group (null = unlimited)',
                'default' => null,
            ],
            self::CONFIG_ALLOW_PRIVATE_GROUPS => [
                'type' => 'boolean',
                'label' => 'Allow Private Groups',
                'description' => 'Allow users to create private groups',
                'default' => true,
            ],
            self::CONFIG_REQUIRE_LOCATION => [
                'type' => 'boolean',
                'label' => 'Require Location',
                'description' => 'Groups must have a location specified',
                'default' => false,
            ],
            self::CONFIG_ENABLE_DISCUSSIONS => [
                'type' => 'boolean',
                'label' => 'Enable Discussions',
                'description' => 'Enable group discussion feature',
                'default' => true,
            ],
            self::CONFIG_ENABLE_FEEDBACK => [
                'type' => 'boolean',
                'label' => 'Enable Feedback',
                'description' => 'Enable group member feedback/ratings',
                'default' => true,
            ],
            self::CONFIG_ENABLE_ACHIEVEMENTS => [
                'type' => 'boolean',
                'label' => 'Enable Achievements',
                'description' => 'Enable group achievement system',
                'default' => true,
            ],
            self::CONFIG_DEFAULT_VISIBILITY => [
                'type' => 'select',
                'options' => ['public', 'private'],
                'label' => 'Default Visibility',
                'description' => 'Default visibility for new groups',
                'default' => 'public',
            ],
            self::CONFIG_MODERATION_ENABLED => [
                'type' => 'boolean',
                'label' => 'Content Moderation',
                'description' => 'Enable content moderation for group posts',
                'default' => false,
            ],
            self::CONFIG_AUTO_APPROVE_MEMBERS => [
                'type' => 'boolean',
                'label' => 'Auto-Approve Members',
                'description' => 'Automatically approve member join requests',
                'default' => true,
            ],
            self::CONFIG_ALLOW_MEMBER_INVITES => [
                'type' => 'boolean',
                'label' => 'Allow Member Invites',
                'description' => 'Allow group members to invite others',
                'default' => true,
            ],
            self::CONFIG_MIN_DESCRIPTION_LENGTH => [
                'type' => 'number',
                'label' => 'Min Description Length',
                'description' => 'Minimum characters required for group description',
                'default' => 10,
            ],
            self::CONFIG_MAX_DESCRIPTION_LENGTH => [
                'type' => 'number',
                'label' => 'Max Description Length',
                'description' => 'Maximum characters allowed for group description',
                'default' => 5000,
            ],
            self::CONFIG_CONTENT_FILTER_ENABLED => [
                'type' => 'boolean',
                'label' => 'Content Filter',
                'description' => 'Enable content filtering for inappropriate content',
                'default' => false,
            ],
            self::CONFIG_PROFANITY_FILTER_ENABLED => [
                'type' => 'boolean',
                'label' => 'Profanity Filter',
                'description' => 'Enable profanity filtering',
                'default' => false,
            ],
            self::CONFIG_IMAGE_REQUIRED => [
                'type' => 'boolean',
                'label' => 'Require Group Image',
                'description' => 'Groups must have an image uploaded',
                'default' => false,
            ],
            self::CONFIG_ALLOW_SUB_GROUPS => [
                'type' => 'boolean',
                'label' => 'Allow Sub-Groups',
                'description' => 'Allow groups to have sub-groups',
                'default' => true,
            ],
            self::CONFIG_NOTIFICATION_DIGEST_ENABLED => [
                'type' => 'boolean',
                'label' => 'Notification Digest',
                'description' => 'Send digest emails for group activity',
                'default' => false,
            ],
        ];
    }

    /**
     * Encode config_value for storage
     *
     * @param mixed $config_value
     * @return string
     */
    private static function encodeValue($config_value)
    {
        if (is_bool($config_value)) {
            return $config_value ? '1' : '0';
        }
        if (is_null($config_value)) {
            return 'null';
        }
        if (is_array($config_value) || is_object($config_value)) {
            return json_encode($config_value);
        }
        return (string) $config_value;
    }

    /**
     * Decode config_value from storage
     *
     * @param string $config_value
     * @return mixed
     */
    private static function decodeValue($config_value)
    {
        if ($config_value === 'null') {
            return null;
        }
        if ($config_value === '1') {
            return true;
        }
        if ($config_value === '0') {
            return false;
        }
        if (is_numeric($config_value)) {
            return strpos($config_value, '.') !== false ? (float) $config_value : (int) $config_value;
        }

        // Try to decode JSON
        $decoded = json_decode($config_value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return $config_value;
    }

    /**
     * Ensure the configuration table exists
     */
    private static function ensureTableExists()
    {
        static $checked = false;
        if ($checked) return;

        try {
            Database::query("SELECT 1 FROM group_configuration LIMIT 1");
        } catch (\Exception $e) {
            Database::query("
                CREATE TABLE IF NOT EXISTS group_configuration (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT NOT NULL,
                    config_key VARCHAR(100) NOT NULL,
                    config_value TEXT NULL,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_tenant_config (tenant_id, config_key),
                    INDEX idx_tenant (tenant_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        $checked = true;
    }
}
