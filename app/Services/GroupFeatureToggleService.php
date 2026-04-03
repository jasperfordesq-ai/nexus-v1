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
 * GroupFeatureToggleService — tenant-aware group feature gating.
 *
 * Controls which group-related features are enabled for each tenant.
 * Features default to enabled (true) when no row exists in the
 * `group_feature_toggles` table.
 *
 * Table schema:
 *   id, tenant_id, feature_key, is_enabled, category, description
 *
 * Cache: 5-minute TTL per tenant, key `group_features:{tenantId}`.
 */
class GroupFeatureToggleService
{
    // ── Cache ───────────────────────────────────────────────────────────
    private const CACHE_TTL_SECONDS = 300; // 5 minutes
    private const CACHE_PREFIX = 'group_features:';

    // ── Main module ────────────────────────────────────────────────────
    const FEATURE_GROUPS_MODULE = 'groups_module';

    // ── Core features ──────────────────────────────────────────────────
    const FEATURE_GROUP_CREATION = 'group_creation';
    const FEATURE_HUB_GROUPS = 'hub_groups';
    const FEATURE_REGULAR_GROUPS = 'regular_groups';
    const FEATURE_PRIVATE_GROUPS = 'private_groups';
    const FEATURE_SUB_GROUPS = 'sub_groups';

    // ── Interaction features ───────────────────────────────────────────
    const FEATURE_DISCUSSIONS = 'discussions';
    const FEATURE_FEEDBACK = 'feedback';
    const FEATURE_MEMBER_INVITES = 'member_invites';
    const FEATURE_JOIN_REQUESTS = 'join_requests';

    // ── Gamification ───────────────────────────────────────────────────
    const FEATURE_ACHIEVEMENTS = 'achievements';
    const FEATURE_BADGES = 'badges';
    const FEATURE_LEADERBOARD = 'leaderboard';

    // ── Advanced ───────────────────────────────────────────────────────
    const FEATURE_ANALYTICS = 'analytics';
    const FEATURE_MODERATION = 'moderation';
    const FEATURE_APPROVAL_WORKFLOW = 'approval_workflow';

    // ── Feature definitions (static metadata) ──────────────────────────
    private const DEFINITIONS = [
        self::FEATURE_GROUPS_MODULE => [
            'label'    => 'Groups Module',
            'category' => 'core',
        ],
        self::FEATURE_GROUP_CREATION => [
            'label'    => 'Group Creation',
            'category' => 'core',
        ],
        self::FEATURE_HUB_GROUPS => [
            'label'    => 'Hub Groups',
            'category' => 'core',
        ],
        self::FEATURE_REGULAR_GROUPS => [
            'label'    => 'Regular Groups',
            'category' => 'core',
        ],
        self::FEATURE_PRIVATE_GROUPS => [
            'label'    => 'Private Groups',
            'category' => 'core',
        ],
        self::FEATURE_SUB_GROUPS => [
            'label'    => 'Sub Groups',
            'category' => 'core',
        ],
        self::FEATURE_DISCUSSIONS => [
            'label'    => 'Discussions',
            'category' => 'content',
        ],
        self::FEATURE_FEEDBACK => [
            'label'    => 'Feedback',
            'category' => 'content',
        ],
        self::FEATURE_MEMBER_INVITES => [
            'label'    => 'Member Invites',
            'category' => 'content',
        ],
        self::FEATURE_JOIN_REQUESTS => [
            'label'    => 'Join Requests',
            'category' => 'content',
        ],
        self::FEATURE_ACHIEVEMENTS => [
            'label'    => 'Achievements',
            'category' => 'gamification',
        ],
        self::FEATURE_BADGES => [
            'label'    => 'Badges',
            'category' => 'gamification',
        ],
        self::FEATURE_LEADERBOARD => [
            'label'    => 'Leaderboard',
            'category' => 'gamification',
        ],
        self::FEATURE_ANALYTICS => [
            'label'    => 'Analytics',
            'category' => 'advanced',
        ],
        self::FEATURE_MODERATION => [
            'label'    => 'Moderation',
            'category' => 'moderation',
        ],
        self::FEATURE_APPROVAL_WORKFLOW => [
            'label'    => 'Approval Workflow',
            'category' => 'advanced',
        ],
    ];

    // ────────────────────────────────────────────────────────────────────
    // Public API
    // ────────────────────────────────────────────────────────────────────

    /**
     * Check whether a group feature is enabled for the current tenant.
     *
     * Returns true (enabled by default) when no row exists in the database.
     */
    public static function isEnabled(string $feature): bool
    {
        $features = self::loadCachedFeatures();

        // No row in database → feature is enabled by default
        if (!array_key_exists($feature, $features)) {
            return true;
        }

        return (bool) $features[$feature];
    }

    /**
     * Return the static definition for a feature.
     *
     * @return array{key: string, label: string, category: string}
     */
    public static function getFeatureDefinition(string $feature): array
    {
        $def = self::DEFINITIONS[$feature] ?? [
            'label'    => ucwords(str_replace('_', ' ', $feature)),
            'category' => 'core',
        ];

        return [
            'key'      => $feature,
            'label'    => $def['label'],
            'category' => $def['category'],
        ];
    }

    /**
     * Enable a group feature for the current tenant (upsert).
     */
    public static function enableFeature(string $feature): bool
    {
        return self::upsertFeature($feature, true);
    }

    /**
     * Disable a group feature for the current tenant (upsert).
     */
    public static function disableFeature(string $feature): bool
    {
        return self::upsertFeature($feature, false);
    }

    /**
     * Return every known feature with its enabled status for the current tenant.
     *
     * @return array<int, array{key: string, label: string, category: string, is_enabled: bool}>
     */
    public static function getAllFeatures(): array
    {
        $features = self::loadCachedFeatures();
        $result = [];

        foreach (self::DEFINITIONS as $key => $def) {
            $result[] = [
                'key'        => $key,
                'label'      => $def['label'],
                'category'   => $def['category'],
                'is_enabled' => array_key_exists($key, $features)
                    ? (bool) $features[$key]
                    : true, // default enabled
            ];
        }

        return $result;
    }

    // ────────────────────────────────────────────────────────────────────
    // Internals
    // ────────────────────────────────────────────────────────────────────

    /**
     * Load all feature toggle rows for the current tenant, cached for 5 minutes.
     *
     * @return array<string, bool> feature_key => is_enabled
     */
    private static function loadCachedFeatures(): array
    {
        $tenantId = TenantContext::getId();
        $cacheKey = self::CACHE_PREFIX . $tenantId;

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($tenantId) {
            return self::loadFeaturesFromDb($tenantId);
        });
    }

    /**
     * Query the database for all group feature toggles of a tenant.
     *
     * @return array<string, bool>
     */
    private static function loadFeaturesFromDb(int $tenantId): array
    {
        try {
            $rows = DB::table('group_feature_toggles')
                ->where('tenant_id', $tenantId)
                ->get(['feature_key', 'is_enabled']);

            $map = [];
            foreach ($rows as $row) {
                $map[$row->feature_key] = (bool) $row->is_enabled;
            }

            return $map;
        } catch (\Throwable $e) {
            Log::warning('GroupFeatureToggleService: failed to load features', [
                'tenant_id' => $tenantId,
                'error'     => $e->getMessage(),
            ]);

            // On failure, return empty array so every feature defaults to enabled
            return [];
        }
    }

    /**
     * Upsert a feature toggle row and bust the cache.
     */
    private static function upsertFeature(string $feature, bool $enabled): bool
    {
        $tenantId   = TenantContext::getId();
        $definition = self::getFeatureDefinition($feature);

        try {
            DB::table('group_feature_toggles')->updateOrInsert(
                [
                    'tenant_id'   => $tenantId,
                    'feature_key' => $feature,
                ],
                [
                    'is_enabled'  => $enabled ? 1 : 0,
                    'category'    => $definition['category'],
                    'description' => $definition['label'],
                ]
            );

            // Bust cache so the change is visible immediately
            Cache::forget(self::CACHE_PREFIX . $tenantId);

            return true;
        } catch (\Throwable $e) {
            Log::error('GroupFeatureToggleService: failed to upsert feature', [
                'tenant_id' => $tenantId,
                'feature'   => $feature,
                'enabled'   => $enabled,
                'error'     => $e->getMessage(),
            ]);

            return false;
        }
    }
}
