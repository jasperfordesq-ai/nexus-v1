<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * GroupConfigurationService — typed config wrapper around group_policies table.
 *
 * Provides strongly-typed configuration keys with sensible defaults for
 * tenant-scoped group settings. Wraps the low-level GroupPolicyRepository
 * with a simpler get/set interface and 1-hour cache.
 */
class GroupConfigurationService
{
    // =========================================================================
    // Core configuration keys
    // =========================================================================

    public const CONFIG_ALLOW_USER_GROUP_CREATION = 'allow_user_group_creation';
    public const CONFIG_REQUIRE_GROUP_APPROVAL = 'require_group_approval';
    public const CONFIG_MAX_GROUPS_PER_USER = 'max_groups_per_user';
    public const CONFIG_MAX_MEMBERS_PER_GROUP = 'max_members_per_group';
    public const CONFIG_ALLOW_PRIVATE_GROUPS = 'allow_private_groups';
    public const CONFIG_ENABLE_DISCUSSIONS = 'enable_discussions';
    public const CONFIG_ENABLE_FEEDBACK = 'enable_feedback';
    public const CONFIG_ENABLE_ACHIEVEMENTS = 'enable_achievements';
    public const CONFIG_DEFAULT_VISIBILITY = 'default_visibility';
    public const CONFIG_MODERATION_ENABLED = 'moderation_enabled';

    // =========================================================================
    // Content filter keys
    // =========================================================================

    public const CONFIG_CONTENT_FILTER_ENABLED = 'content_filter_enabled';
    public const CONFIG_PROFANITY_FILTER_ENABLED = 'profanity_filter_enabled';
    public const CONFIG_MIN_DESCRIPTION_LENGTH = 'min_description_length';
    public const CONFIG_MAX_DESCRIPTION_LENGTH = 'max_description_length';

    // =========================================================================
    // Tab visibility keys — control which tabs appear in group detail view
    // =========================================================================

    public const CONFIG_TAB_FEED = 'tab_feed';
    public const CONFIG_TAB_DISCUSSION = 'tab_discussion';
    public const CONFIG_TAB_MEMBERS = 'tab_members';
    public const CONFIG_TAB_EVENTS = 'tab_events';
    public const CONFIG_TAB_FILES = 'tab_files';
    public const CONFIG_TAB_ANNOUNCEMENTS = 'tab_announcements';
    public const CONFIG_TAB_QA = 'tab_qa';
    public const CONFIG_TAB_WIKI = 'tab_wiki';
    public const CONFIG_TAB_MEDIA = 'tab_media';
    public const CONFIG_TAB_CHATROOMS = 'tab_chatrooms';
    public const CONFIG_TAB_TASKS = 'tab_tasks';
    public const CONFIG_TAB_CHALLENGES = 'tab_challenges';
    public const CONFIG_TAB_ANALYTICS = 'tab_analytics';
    public const CONFIG_TAB_SUBGROUPS = 'tab_subgroups';

    // =========================================================================
    // Default values
    // =========================================================================

    public const DEFAULTS = [
        self::CONFIG_ALLOW_USER_GROUP_CREATION => true,
        self::CONFIG_REQUIRE_GROUP_APPROVAL    => false,
        self::CONFIG_MAX_GROUPS_PER_USER       => 10,
        self::CONFIG_MAX_MEMBERS_PER_GROUP     => 500,
        self::CONFIG_ALLOW_PRIVATE_GROUPS      => true,
        self::CONFIG_ENABLE_DISCUSSIONS         => true,
        self::CONFIG_ENABLE_FEEDBACK            => true,
        self::CONFIG_ENABLE_ACHIEVEMENTS        => true,
        self::CONFIG_DEFAULT_VISIBILITY         => 'public',
        self::CONFIG_MODERATION_ENABLED         => true,
        self::CONFIG_CONTENT_FILTER_ENABLED     => false,
        self::CONFIG_PROFANITY_FILTER_ENABLED   => false,
        self::CONFIG_MIN_DESCRIPTION_LENGTH     => 10,
        self::CONFIG_MAX_DESCRIPTION_LENGTH     => 5000,
        // Tab visibility (all enabled by default)
        self::CONFIG_TAB_FEED           => true,
        self::CONFIG_TAB_DISCUSSION     => true,
        self::CONFIG_TAB_MEMBERS        => true,
        self::CONFIG_TAB_EVENTS         => true,
        self::CONFIG_TAB_FILES          => true,
        self::CONFIG_TAB_ANNOUNCEMENTS  => true,
        self::CONFIG_TAB_QA             => true,
        self::CONFIG_TAB_WIKI           => true,
        self::CONFIG_TAB_MEDIA          => true,
        self::CONFIG_TAB_CHATROOMS      => true,
        self::CONFIG_TAB_TASKS          => true,
        self::CONFIG_TAB_CHALLENGES     => true,
        self::CONFIG_TAB_ANALYTICS      => true,
        self::CONFIG_TAB_SUBGROUPS      => true,
    ];

    /** Cache TTL in seconds (1 hour). */
    private const CACHE_TTL = 3600;

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Get a configuration value for the current tenant.
     *
     * Looks up the group_policies table by policy_key. JSON-encoded values
     * are decoded automatically. Falls back to DEFAULTS if no row exists.
     *
     * @param string $key   One of the CONFIG_* constants
     * @param mixed  $default Override for the built-in default (null = use DEFAULTS)
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $tenantId = TenantContext::getId();
        $allStored = self::getStoredValues($tenantId);

        if (array_key_exists($key, $allStored)) {
            return $allStored[$key];
        }

        // Explicit $default passed by caller takes precedence over DEFAULTS
        if ($default !== null) {
            return $default;
        }

        return self::DEFAULTS[$key] ?? null;
    }

    /**
     * Set (upsert) a configuration value for the current tenant.
     *
     * Inserts a new row or updates the existing one in group_policies.
     * Invalidates the tenant cache so subsequent reads reflect the change.
     *
     * @param string $key   One of the CONFIG_* constants
     * @param mixed  $value The value to store
     */
    public static function set(string $key, mixed $value): void
    {
        $tenantId = TenantContext::getId();
        $storedValue = self::encodeValue($value);

        try {
            $existing = DB::selectOne(
                "SELECT id FROM group_policies WHERE tenant_id = ? AND policy_key = ?",
                [$tenantId, $key]
            );

            $valueType = self::detectValueType($value);

            if ($existing) {
                DB::update(
                    "UPDATE group_policies SET policy_value = ?, value_type = ?, updated_at = NOW()
                     WHERE tenant_id = ? AND policy_key = ?",
                    [$storedValue, $valueType, $tenantId, $key]
                );
            } else {
                DB::insert(
                    "INSERT INTO group_policies (tenant_id, policy_key, policy_value, category, value_type, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
                    [$tenantId, $key, $storedValue, self::detectCategory($key), $valueType]
                );
            }

            // Invalidate cache
            Cache::forget("group_config:{$tenantId}");
        } catch (\Throwable $e) {
            Log::error('GroupConfigurationService::set failed', [
                'key'       => $key,
                'tenant_id' => $tenantId,
                'error'     => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get all configuration keys with their effective values.
     *
     * Merges built-in DEFAULTS with stored values so every known key is
     * present in the returned array. Stored values override defaults.
     *
     * @return array<string, mixed>
     */
    public static function getAll(): array
    {
        $tenantId = TenantContext::getId();
        $stored = self::getStoredValues($tenantId);

        return array_merge(self::DEFAULTS, $stored);
    }

    /**
     * Return the built-in default value for a configuration key.
     *
     * @param string $key One of the CONFIG_* constants
     * @return mixed
     */
    public static function getDefault(string $key): mixed
    {
        return self::DEFAULTS[$key] ?? null;
    }

    // =========================================================================
    // Internal helpers
    // =========================================================================

    /**
     * Load all stored config values for a tenant (cached 1 hour).
     *
     * @return array<string, mixed> policy_key => decoded value
     */
    private static function getStoredValues(int $tenantId): array
    {
        $cacheKey = "group_config:{$tenantId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($tenantId) {
            try {
                $rows = DB::select(
                    "SELECT policy_key, policy_value, value_type
                     FROM group_policies
                     WHERE tenant_id = ?",
                    [$tenantId]
                );

                $result = [];
                foreach ($rows as $row) {
                    $result[$row->policy_key] = self::decodeValue($row->policy_value, $row->value_type);
                }

                return $result;
            } catch (\Throwable $e) {
                Log::error('GroupConfigurationService::getStoredValues failed', [
                    'tenant_id' => $tenantId,
                    'error'     => $e->getMessage(),
                ]);
                return [];
            }
        });
    }

    /**
     * Encode a value for storage in the policy_value column.
     */
    private static function encodeValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        return (string) $value;
    }

    /**
     * Decode a stored value based on its value_type.
     */
    private static function decodeValue(string $storedValue, string $type): mixed
    {
        return match ($type) {
            'boolean' => in_array(strtolower($storedValue), ['true', '1', 'yes'], true),
            'number'  => is_numeric($storedValue)
                ? (str_contains($storedValue, '.') ? (float) $storedValue : (int) $storedValue)
                : 0,
            'json'    => json_decode($storedValue, true) ?? $storedValue,
            default   => $storedValue,
        };
    }

    /**
     * Detect the value_type string for storage based on the PHP type.
     */
    private static function detectValueType(mixed $value): string
    {
        if (is_bool($value)) {
            return 'boolean';
        }
        if (is_int($value) || is_float($value)) {
            return 'number';
        }
        if (is_array($value)) {
            return 'json';
        }
        return 'string';
    }

    /**
     * Map a config key to its policy category.
     */
    private static function detectCategory(string $key): string
    {
        return match ($key) {
            self::CONFIG_ALLOW_USER_GROUP_CREATION,
            self::CONFIG_REQUIRE_GROUP_APPROVAL     => 'creation',

            self::CONFIG_MAX_GROUPS_PER_USER,
            self::CONFIG_MAX_MEMBERS_PER_GROUP,
            self::CONFIG_ALLOW_PRIVATE_GROUPS,
            self::CONFIG_DEFAULT_VISIBILITY          => 'membership',

            self::CONFIG_ENABLE_DISCUSSIONS,
            self::CONFIG_ENABLE_FEEDBACK,
            self::CONFIG_ENABLE_ACHIEVEMENTS          => 'features',

            self::CONFIG_MODERATION_ENABLED,
            self::CONFIG_CONTENT_FILTER_ENABLED,
            self::CONFIG_PROFANITY_FILTER_ENABLED     => 'moderation',

            self::CONFIG_MIN_DESCRIPTION_LENGTH,
            self::CONFIG_MAX_DESCRIPTION_LENGTH       => 'content',

            self::CONFIG_TAB_FEED,
            self::CONFIG_TAB_DISCUSSION,
            self::CONFIG_TAB_MEMBERS,
            self::CONFIG_TAB_EVENTS,
            self::CONFIG_TAB_FILES,
            self::CONFIG_TAB_ANNOUNCEMENTS,
            self::CONFIG_TAB_QA,
            self::CONFIG_TAB_WIKI,
            self::CONFIG_TAB_MEDIA,
            self::CONFIG_TAB_CHATROOMS,
            self::CONFIG_TAB_TASKS,
            self::CONFIG_TAB_CHALLENGES,
            self::CONFIG_TAB_ANALYTICS,
            self::CONFIG_TAB_SUBGROUPS                => 'tabs',

            default                                   => 'features',
        };
    }
}
