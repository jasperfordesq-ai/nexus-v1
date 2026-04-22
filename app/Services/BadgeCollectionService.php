<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\I18n\LocaleContext;
use App\Models\BadgeCollection;
use App\Models\BadgeCollectionItem;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\UserCollectionCompletion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * BadgeCollectionService — Eloquent-based service for badge collections.
 *
 * Manages badge collections, progress tracking, and completion rewards.
 * All queries are tenant-scoped via HasTenantScope trait on models.
 */
class BadgeCollectionService
{
    public function __construct(
        private readonly BadgeCollection $collection,
        private readonly GamificationService $gamificationService,
    ) {}

    /**
     * Get all collections with user progress.
     *
     * OPTIMIZED: Uses batch queries instead of N+1 pattern.
     */
    public static function getCollectionsWithProgress(int $userId): array
    {
        $collections = BadgeCollection::query()
            ->orderBy('display_order')
            ->get();

        if ($collections->isEmpty()) {
            return [];
        }

        $collectionIds = $collections->pluck('id')->all();

        // Batch load all collection items
        $allItems = BadgeCollectionItem::whereIn('collection_id', $collectionIds)
            ->orderBy('collection_id')
            ->orderBy('display_order')
            ->get();

        $itemsByCollection = $allItems->groupBy('collection_id');

        // Get all unique badge keys
        $allBadgeKeys = $allItems->pluck('badge_key')->unique()->all();

        // Get user's earned badges
        $earnedKeys = UserBadge::where('user_id', $userId)
            ->pluck('badge_key')
            ->flip()
            ->all();

        // Get user's completed collections
        $completedCollections = UserCollectionCompletion::where('user_id', $userId)
            ->pluck('collection_id')
            ->flip()
            ->all();

        // Build badge definitions map (in-memory)
        $badgeDefsMap = self::getBadgeDefinitionsMap($allBadgeKeys);

        // Assemble collections with progress
        $result = [];
        foreach ($collections as $collection) {
            $row = $collection->toArray();
            $items = $itemsByCollection->get($collection->id, collect());

            $row['badges'] = [];
            $row['earned_count'] = 0;
            $row['total_count'] = $items->count();

            foreach ($items as $item) {
                $badgeDef = $badgeDefsMap[$item->badge_key] ?? null;
                if ($badgeDef) {
                    $isEarned = isset($earnedKeys[$item->badge_key]);
                    $badgeDef['earned'] = $isEarned;
                    $row['badges'][] = $badgeDef;
                    if ($isEarned) {
                        $row['earned_count']++;
                    }
                }
            }

            $row['progress_percent'] = $row['total_count'] > 0
                ? round(($row['earned_count'] / $row['total_count']) * 100)
                : 0;
            $row['is_completed'] = $row['earned_count'] >= $row['total_count'] && $row['total_count'] > 0;
            $row['bonus_claimed'] = isset($completedCollections[$collection->id]);

            $result[] = $row;
        }

        return $result;
    }

    /**
     * Get journey-type collections with ordered step-by-step progress.
     */
    public static function getJourneys(int $userId, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        $journeys = BadgeCollection::where('tenant_id', $tenantId)
            ->where('collection_type', 'journey')
            ->orderBy('display_order')
            ->with(['items' => function ($q) {
                $q->orderBy('display_order');
            }])
            ->get();

        if ($journeys->isEmpty()) {
            return [];
        }

        // Get user's earned badge keys
        $earnedKeys = UserBadge::where('user_id', $userId)
            ->pluck('badge_key')
            ->flip()
            ->all();

        return $journeys->map(function ($journey) use ($earnedKeys) {
            $steps = $journey->items->map(function ($item) use ($earnedKeys) {
                return [
                    'badge_key' => $item->badge_key,
                    'display_order' => $item->display_order,
                    'earned' => isset($earnedKeys[$item->badge_key]),
                ];
            })->values()->toArray();

            $earnedCount = count(array_filter($steps, fn ($s) => $s['earned']));

            return [
                'id' => $journey->id,
                'name' => $journey->name,
                'description' => $journey->description,
                'icon' => $journey->icon,
                'estimated_duration' => $journey->estimated_duration,
                'is_ordered' => $journey->is_ordered,
                'steps' => $steps,
                'earned_count' => $earnedCount,
                'total_steps' => count($steps),
                'completed' => $earnedCount === count($steps) && count($steps) > 0,
                'bonus_xp' => $journey->bonus_xp,
            ];
        })->values()->toArray();
    }

    /**
     * Check and award collection completion.
     */
    public static function checkCollectionCompletion(int $userId): array
    {
        $collections = BadgeCollection::query()->get();

        if ($collections->isEmpty()) {
            return [];
        }

        // Get user's earned badges
        $earnedKeys = UserBadge::where('user_id', $userId)
            ->pluck('badge_key')
            ->flip()
            ->all();

        // Get already-completed collections
        $completedMap = UserCollectionCompletion::where('user_id', $userId)
            ->pluck('collection_id')
            ->flip()
            ->all();

        // Batch load all collection items
        $collectionIds = $collections->pluck('id')->all();
        $allItems = BadgeCollectionItem::whereIn('collection_id', $collectionIds)->get();
        $badgesByCollection = $allItems->groupBy('collection_id');

        $completedCollections = [];

        foreach ($collections as $collection) {
            if (isset($completedMap[$collection->id])) {
                continue;
            }

            $badges = $badgesByCollection->get($collection->id, collect());
            if ($badges->isEmpty()) {
                continue;
            }

            $allEarned = true;
            foreach ($badges as $item) {
                if (! isset($earnedKeys[$item->badge_key])) {
                    $allEarned = false;
                    break;
                }
            }

            if ($allEarned) {
                self::awardCollectionCompletion($userId, $collection);
                $completedCollections[] = $collection->toArray();
            }
        }

        return $completedCollections;
    }

    /**
     * Create a new collection (admin).
     */
    public static function create(array $data): ?int
    {
        $collection = new BadgeCollection([
            'collection_key' => $data['collection_key'],
            'name'           => $data['name'],
            'description'    => $data['description'] ?? '',
            'icon'           => $data['icon'] ?? null,
            'bonus_xp'       => $data['bonus_xp'] ?? 100,
            'bonus_badge_key' => $data['bonus_badge_key'] ?? null,
            'display_order'  => $data['display_order'] ?? 0,
        ]);
        $collection->save();

        return $collection->id;
    }

    /**
     * Add badge to collection.
     */
    public static function addBadgeToCollection(int $collectionId, string $badgeKey, int $order = 0): void
    {
        BadgeCollectionItem::firstOrCreate(
            ['collection_id' => $collectionId, 'badge_key' => $badgeKey],
            ['display_order' => $order]
        );
    }

    /**
     * Remove badge from collection (with tenant check via collection).
     */
    public static function removeBadgeFromCollection(int $collectionId, string $badgeKey): void
    {
        // Verify the collection belongs to current tenant
        $collection = BadgeCollection::query()->find($collectionId);
        if (! $collection) {
            return;
        }

        BadgeCollectionItem::where('collection_id', $collectionId)
            ->where('badge_key', $badgeKey)
            ->delete();
    }

    /**
     * Build a map of badge definitions for fast O(1) lookup.
     */
    private static function getBadgeDefinitionsMap(array $keys = []): array
    {
        $allDefs = GamificationService::getStaticBadgeDefinitions();
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
     * Award collection completion bonus.
     */
    private static function awardCollectionCompletion(int $userId, BadgeCollection $collection): void
    {
        try {
            DB::transaction(function () use ($userId, $collection) {
                UserCollectionCompletion::create([
                    'user_id'       => $userId,
                    'collection_id' => $collection->id,
                    'bonus_claimed' => true,
                ]);

                if ($collection->bonus_xp > 0) {
                    GamificationService::awardXP(
                        $userId,
                        $collection->bonus_xp,
                        'collection_complete',
                        "Collection: {$collection->name}"
                    );
                }

                if (! empty($collection->bonus_badge_key)) {
                    GamificationService::awardBadgeByKey($userId, $collection->bonus_badge_key);
                }
            });

            // Render the achievement bell in the RECIPIENT's preferred language.
            $recipient = User::query()
                ->withoutGlobalScopes()
                ->select(['id', 'preferred_language'])
                ->find($userId);

            LocaleContext::withLocale($recipient, function () use ($userId, $collection) {
                Notification::create([
                    'user_id' => $userId,
                    'type'    => 'achievement',
                    'message' => __('svc_notifications.badge_collection.collection_complete', ['name' => $collection->name, 'xp' => $collection->bonus_xp, 'icon' => $collection->icon]),
                    'link'    => '/achievements',
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Failed to award collection completion: ' . $e->getMessage());
        }
    }
}
