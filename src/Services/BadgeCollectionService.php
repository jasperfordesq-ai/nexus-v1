<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\UserBadge;
use Nexus\Models\Notification;

class BadgeCollectionService
{
    /**
     * Get all collections with user progress
     *
     * OPTIMIZED: Uses batch queries instead of N+1 pattern
     * Before: 1 + N + (N*M) queries where N=collections, M=badges per collection
     * After: 4 queries total regardless of collection/badge count
     */
    public static function getCollectionsWithProgress($userId)
    {
        $tenantId = TenantContext::getId();

        // Query 1: Get all collections
        $collections = Database::query(
            "SELECT * FROM badge_collections WHERE tenant_id = ? ORDER BY display_order ASC",
            [$tenantId]
        )->fetchAll();

        if (empty($collections)) {
            return [];
        }

        // Query 2: Get ALL collection items in a single query
        $collectionIds = array_column($collections, 'id');
        $placeholders = implode(',', array_fill(0, count($collectionIds), '?'));

        $allItems = Database::query(
            "SELECT collection_id, badge_key, display_order
             FROM badge_collection_items
             WHERE collection_id IN ({$placeholders})
             ORDER BY collection_id, display_order ASC",
            $collectionIds
        )->fetchAll();

        // Group items by collection_id for O(1) lookup
        $itemsByCollection = [];
        $allBadgeKeys = [];
        foreach ($allItems as $item) {
            $itemsByCollection[$item['collection_id']][] = $item;
            $allBadgeKeys[$item['badge_key']] = true;
        }

        // Query 3: Get user's earned badges
        $userBadges = UserBadge::getForUser($userId);
        $earnedKeys = array_flip(array_column($userBadges, 'badge_key'));

        // Query 4: Get user's completed collections
        $completedCollections = Database::query(
            "SELECT collection_id FROM user_collection_completions WHERE user_id = ?",
            [$userId]
        )->fetchAll(\PDO::FETCH_COLUMN);
        $completedMap = array_flip($completedCollections ?: []);

        // Build badge definitions map (in-memory, no DB query - uses cached definitions)
        $badgeDefsMap = self::getBadgeDefinitionsMap(array_keys($allBadgeKeys));

        // Assemble collections with progress using O(1) lookups
        foreach ($collections as &$collection) {
            $collectionId = $collection['id'];
            $items = $itemsByCollection[$collectionId] ?? [];

            $collection['badges'] = [];
            $collection['earned_count'] = 0;
            $collection['total_count'] = count($items);

            foreach ($items as $item) {
                $badgeKey = $item['badge_key'];
                $badgeDef = $badgeDefsMap[$badgeKey] ?? null;

                if ($badgeDef) {
                    $isEarned = isset($earnedKeys[$badgeKey]);
                    $badgeDef['earned'] = $isEarned;
                    $collection['badges'][] = $badgeDef;

                    if ($isEarned) {
                        $collection['earned_count']++;
                    }
                }
            }

            $collection['progress_percent'] = $collection['total_count'] > 0
                ? round(($collection['earned_count'] / $collection['total_count']) * 100)
                : 0;
            $collection['is_completed'] = $collection['earned_count'] >= $collection['total_count'] && $collection['total_count'] > 0;
            $collection['bonus_claimed'] = isset($completedMap[$collectionId]);
        }

        return $collections;
    }

    /**
     * Build a map of badge definitions for fast O(1) lookup
     *
     * @param array $keys Badge keys to include (uses all if empty)
     * @return array Map of badge_key => definition
     */
    private static function getBadgeDefinitionsMap(array $keys = []): array
    {
        $allDefs = GamificationService::getBadgeDefinitions();
        $map = [];
        $keysToFind = empty($keys) ? null : array_flip($keys);

        foreach ($allDefs as $def) {
            if ($keysToFind === null || isset($keysToFind[$def['key']])) {
                $map[$def['key']] = $def;
            }
        }

        return $map;
    }

    /**
     * Check and award collection completion
     *
     * OPTIMIZED: Batch queries instead of per-collection queries
     */
    public static function checkCollectionCompletion($userId)
    {
        $tenantId = TenantContext::getId();
        $completedCollections = [];

        // Query 1: Get all collections
        $collections = Database::query(
            "SELECT * FROM badge_collections WHERE tenant_id = ?",
            [$tenantId]
        )->fetchAll();

        if (empty($collections)) {
            return [];
        }

        // Query 2: Get user's earned badges (uses O(1) lookup)
        $userBadges = UserBadge::getForUser($userId);
        $earnedKeys = array_flip(array_column($userBadges, 'badge_key'));

        // Query 3: Get ALL already-completed collections for this user in one query
        $alreadyCompleted = Database::query(
            "SELECT collection_id FROM user_collection_completions WHERE user_id = ?",
            [$userId]
        )->fetchAll(\PDO::FETCH_COLUMN);
        $completedMap = array_flip($alreadyCompleted ?: []);

        // Query 4: Get ALL badge collection items in one batch
        $collectionIds = array_column($collections, 'id');
        $placeholders = implode(',', array_fill(0, count($collectionIds), '?'));

        $allItems = Database::query(
            "SELECT collection_id, badge_key FROM badge_collection_items WHERE collection_id IN ({$placeholders})",
            $collectionIds
        )->fetchAll();

        // Group badges by collection for O(1) lookup
        $badgesByCollection = [];
        foreach ($allItems as $item) {
            $badgesByCollection[$item['collection_id']][] = $item['badge_key'];
        }

        // Check each collection using pre-fetched data
        foreach ($collections as $collection) {
            $collectionId = $collection['id'];

            // Skip if already completed
            if (isset($completedMap[$collectionId])) {
                continue;
            }

            // Get badges for this collection from pre-fetched map
            $badges = $badgesByCollection[$collectionId] ?? [];

            if (empty($badges)) {
                continue;
            }

            // Check if all badges earned (O(1) lookup per badge)
            $allEarned = true;
            foreach ($badges as $badgeKey) {
                if (!isset($earnedKeys[$badgeKey])) {
                    $allEarned = false;
                    break;
                }
            }

            if ($allEarned) {
                // Award collection completion
                self::awardCollectionCompletion($userId, $collection);
                $completedCollections[] = $collection;
            }
        }

        return $completedCollections;
    }

    /**
     * Award collection completion bonus
     */
    private static function awardCollectionCompletion($userId, $collection)
    {
        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            // Record completion
            Database::query(
                "INSERT INTO user_collection_completions (user_id, collection_id, bonus_claimed) VALUES (?, ?, 1)",
                [$userId, $collection['id']]
            );

            // Award bonus XP
            if ($collection['bonus_xp'] > 0) {
                GamificationService::awardXP(
                    $userId,
                    $collection['bonus_xp'],
                    'collection_complete',
                    "Collection: {$collection['name']}"
                );
            }

            // Award bonus badge if specified
            if (!empty($collection['bonus_badge_key'])) {
                GamificationService::awardBadgeByKey($userId, $collection['bonus_badge_key']);
            }

            $db->commit();

            // Send notification AFTER transaction completes (async operation)
            $basePath = TenantContext::getBasePath();
            Notification::create(
                $userId,
                "Collection Complete! You finished '{$collection['name']}' and earned {$collection['bonus_xp']} bonus XP! {$collection['icon']}",
                "{$basePath}/achievements/badges",
                'achievement'
            );
        } catch (\Exception $e) {
            $db->rollBack();
            error_log("Failed to award collection completion: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a new collection (admin)
     */
    public static function create($data)
    {
        $tenantId = TenantContext::getId();

        Database::query(
            "INSERT INTO badge_collections (tenant_id, collection_key, name, description, icon, bonus_xp, bonus_badge_key, display_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $tenantId,
                $data['collection_key'],
                $data['name'],
                $data['description'] ?? '',
                $data['icon'] ?? null,
                $data['bonus_xp'] ?? 100,
                $data['bonus_badge_key'] ?? null,
                $data['display_order'] ?? 0
            ]
        );

        return Database::getInstance()->lastInsertId();
    }

    /**
     * Add badge to collection
     */
    public static function addBadgeToCollection($collectionId, $badgeKey, $order = 0)
    {
        Database::query(
            "INSERT IGNORE INTO badge_collection_items (collection_id, badge_key, display_order) VALUES (?, ?, ?)",
            [$collectionId, $badgeKey, $order]
        );
    }

    /**
     * Remove badge from collection
     */
    public static function removeBadgeFromCollection($collectionId, $badgeKey)
    {
        Database::query(
            "DELETE FROM badge_collection_items WHERE collection_id = ? AND badge_key = ?",
            [$collectionId, $badgeKey]
        );
    }

    /**
     * Get collection by ID (with tenant scoping for security)
     */
    public static function getById($id)
    {
        $tenantId = TenantContext::getId();
        $collection = Database::query(
            "SELECT * FROM badge_collections WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if ($collection) {
            $collection['badges'] = Database::query(
                "SELECT badge_key, display_order FROM badge_collection_items WHERE collection_id = ? ORDER BY display_order",
                [$id]
            )->fetchAll();
        }

        return $collection;
    }

    /**
     * Initialize default collections with badge mappings
     */
    public static function initializeDefaultCollections($tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        $collections = [
            [
                'key' => 'getting_started',
                'name' => 'Getting Started',
                'description' => 'Complete your first activities in each area',
                'icon' => '🌟',
                'bonus_xp' => 100,
                'badges' => ['offer_1', 'request_1', 'earn_1', 'spend_1', 'connect_1', 'msg_1']
            ],
            [
                'key' => 'timebank_master',
                'name' => 'Timebank Master',
                'description' => 'Master the art of time credits',
                'icon' => '⏰',
                'bonus_xp' => 200,
                'badges' => ['earn_10', 'earn_50', 'spend_10', 'transaction_10', 'transaction_50']
            ],
            [
                'key' => 'social_butterfly',
                'name' => 'Social Butterfly',
                'description' => 'Build your community connections',
                'icon' => '🦋',
                'bonus_xp' => 150,
                'badges' => ['connect_10', 'connect_25', 'msg_50', 'review_10']
            ],
            [
                'key' => 'volunteer_hero',
                'name' => 'Volunteer Hero',
                'description' => 'Dedicate your time to helping others',
                'icon' => '🦸',
                'bonus_xp' => 250,
                'badges' => ['vol_1h', 'vol_10h', 'vol_50h', 'vol_100h']
            ],
            [
                'key' => 'event_enthusiast',
                'name' => 'Event Enthusiast',
                'description' => 'Participate in community events',
                'icon' => '🎉',
                'bonus_xp' => 150,
                'badges' => ['event_attend_1', 'event_attend_10', 'event_host_1']
            ],
        ];

        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            foreach ($collections as $col) {
                // Check if exists
                $existing = Database::query(
                    "SELECT id FROM badge_collections WHERE tenant_id = ? AND collection_key = ?",
                    [$tenantId, $col['key']]
                )->fetch();

                if (!$existing) {
                    Database::query(
                        "INSERT INTO badge_collections (tenant_id, collection_key, name, description, icon, bonus_xp)
                         VALUES (?, ?, ?, ?, ?, ?)",
                        [$tenantId, $col['key'], $col['name'], $col['description'], $col['icon'], $col['bonus_xp']]
                    );
                    $collectionId = $db->lastInsertId();

                    // Add badges
                    $order = 0;
                    foreach ($col['badges'] as $badgeKey) {
                        Database::query(
                            "INSERT INTO badge_collection_items (collection_id, badge_key, display_order) VALUES (?, ?, ?)",
                            [$collectionId, $badgeKey, $order++]
                        );
                    }
                }
            }

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            error_log("Failed to initialize default collections: " . $e->getMessage());
            throw $e;
        }
    }
}
