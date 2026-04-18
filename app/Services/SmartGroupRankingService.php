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
 * SmartGroupRankingService — ranks groups by activity and manages featured status.
 *
 * Native Laravel implementation (replaces legacy wrapper).
 *
 * Scoring algorithm:
 *   score = (member_count * 3) + (recent_posts * 2) + (recent_events * 5) + (recent_discussions * 2)
 *   "recent" = last 30 days
 *
 * Groups with `parent_id IS NULL` and `type_id` matching a "local_hub" type are
 * considered local hubs. Community groups are those with a type matching "community".
 * If no type distinction exists, all top-level groups are ranked together.
 */
class SmartGroupRankingService
{
    public function __construct()
    {
    }

    /**
     * Update featured status for local hub groups.
     *
     * Scores all eligible local hub groups, marks the top N as featured,
     * and clears featured status from the rest.
     *
     * @param int|null $tenantId Tenant ID (defaults to current tenant)
     * @param int $limit Max number of featured groups
     * @return array{featured: int, cleared: int, scores: array}
     */
    public static function updateFeaturedLocalHubs($tenantId = null, $limit = 6)
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        return static::updateFeaturedByType($tenantId, 'local_hubs', $limit);
    }

    /**
     * Update featured status for community groups.
     *
     * @param int|null $tenantId Tenant ID (defaults to current tenant)
     * @param int $limit Max number of featured groups
     * @return array{featured: int, cleared: int, scores: array}
     */
    public static function updateFeaturedCommunityGroups($tenantId = null, $limit = 6)
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        return static::updateFeaturedByType($tenantId, 'community', $limit);
    }

    /**
     * Update all featured groups (both local hubs and community).
     *
     * @param int|null $tenantId Tenant ID (defaults to current tenant)
     * @return array{local_hubs: array, community: array}
     */
    public static function updateAllFeaturedGroups($tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        $localHubs = static::updateFeaturedLocalHubs($tenantId);
        $community = static::updateFeaturedCommunityGroups($tenantId);

        // Store last update time
        Cache::put("featured_groups_updated:{$tenantId}", now()->toIso8601String(), 86400 * 7);

        return [
            'local_hubs' => $localHubs,
            'community' => $community,
        ];
    }

    /**
     * Get featured groups with their computed scores.
     *
     * @param string $type 'local_hubs' or 'community'
     * @param int|null $tenantId Tenant ID (defaults to current tenant)
     * @return array
     */
    public static function getFeaturedGroupsWithScores($type = 'local_hubs', $tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        try {
            $groups = DB::select(
                "SELECT g.id, g.name, g.description, g.image_url, g.location,
                        g.is_featured, g.cached_member_count,
                        g.latitude, g.longitude, g.created_at
                 FROM `groups` g
                 WHERE g.tenant_id = ? AND g.is_featured = 1
                 ORDER BY g.cached_member_count DESC",
                [$tenantId]
            );

            $scored = [];
            foreach ($groups as $group) {
                $score = static::computeGroupScore($group->id, $tenantId, (int) ($group->cached_member_count ?? 0));
                $scored[] = [
                    'id' => (int) $group->id,
                    'name' => $group->name,
                    'description' => $group->description ?? '',
                    'image_url' => $group->image_url,
                    'location' => $group->location,
                    'latitude' => $group->latitude ? (float) $group->latitude : null,
                    'longitude' => $group->longitude ? (float) $group->longitude : null,
                    'is_featured' => true,
                    'member_count' => (int) ($group->cached_member_count ?? 0),
                    'score' => $score,
                    'created_at' => $group->created_at,
                ];
            }

            // Sort by score descending
            usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

            return $scored;
        } catch (\Throwable $e) {
            Log::error('SmartGroupRankingService::getFeaturedGroupsWithScores failed', [
                'type' => $type,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get the last time featured groups were updated for a tenant.
     *
     * @param int|null $tenantId Tenant ID (defaults to current tenant)
     * @return string|null ISO 8601 timestamp
     */
    public static function getLastUpdateTime($tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        return Cache::get("featured_groups_updated:{$tenantId}");
    }

    // =========================================================================
    // Internal ranking logic
    // =========================================================================

    /**
     * Update featured groups of a given type.
     *
     * @param int $tenantId
     * @param string $type 'local_hubs' or 'community'
     * @param int $limit
     * @return array{featured: int, cleared: int, scores: array}
     */
    private static function updateFeaturedByType(int $tenantId, string $type, int $limit): array
    {
        try {
            // Target leaf groups only (bottom of hierarchy — no children).
            // Featuring top-level containers would be misleading; members join leaf groups.
            $typeCondition = 'AND NOT EXISTS (SELECT 1 FROM `groups` child WHERE child.parent_id = g.id AND child.tenant_id = g.tenant_id)';

            $groups = DB::select(
                "SELECT g.id, g.cached_member_count
                 FROM `groups` g
                 WHERE g.tenant_id = ? AND (g.is_active = 1 OR g.is_active IS NULL)
                 {$typeCondition}
                 ORDER BY g.cached_member_count DESC",
                [$tenantId]
            );

            // Score each group
            $scores = [];
            foreach ($groups as $group) {
                $score = static::computeGroupScore($group->id, $tenantId, (int) ($group->cached_member_count ?? 0));
                $scores[] = [
                    'group_id' => (int) $group->id,
                    'score' => $score,
                ];
            }

            // Sort by score descending
            usort($scores, fn($a, $b) => $b['score'] <=> $a['score']);

            // Top N become featured
            $toFeature = array_slice($scores, 0, $limit);
            $toClear = array_slice($scores, $limit);

            $featuredIds = array_column($toFeature, 'group_id');
            $clearedIds = array_column($toClear, 'group_id');

            // Update featured status
            if (!empty($featuredIds)) {
                $placeholders = implode(',', array_fill(0, count($featuredIds), '?'));
                DB::update(
                    "UPDATE `groups` SET is_featured = 1 WHERE id IN ({$placeholders}) AND tenant_id = ?",
                    array_merge($featuredIds, [$tenantId])
                );
            }

            if (!empty($clearedIds)) {
                $placeholders = implode(',', array_fill(0, count($clearedIds), '?'));
                DB::update(
                    "UPDATE `groups` SET is_featured = 0 WHERE id IN ({$placeholders}) AND tenant_id = ?",
                    array_merge($clearedIds, [$tenantId])
                );
            }

            // Also clear featured from any groups not in our scored set (orphans)
            $allScoredIds = array_column($scores, 'group_id');
            if (!empty($allScoredIds)) {
                $placeholders = implode(',', array_fill(0, count($allScoredIds), '?'));
                DB::update(
                    "UPDATE `groups` SET is_featured = 0
                     WHERE tenant_id = ? AND is_featured = 1 AND id NOT IN ({$placeholders})",
                    array_merge([$tenantId], $allScoredIds)
                );
            }

            return [
                'featured' => count($featuredIds),
                'cleared' => count($clearedIds),
                'scores' => array_slice($scores, 0, 20), // Return top 20 for debugging
            ];
        } catch (\Throwable $e) {
            Log::error('SmartGroupRankingService::updateFeaturedByType failed', [
                'type' => $type,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            return ['featured' => 0, 'cleared' => 0, 'scores' => []];
        }
    }

    /**
     * Compute activity score for a single group.
     *
     * Score formula:
     *   (member_count * 3) + (recent_posts * 2) + (recent_events * 5) + (recent_discussions * 2)
     *
     * @param int $groupId
     * @param int $tenantId
     * @param int $memberCount Pre-fetched cached member count
     * @return float
     */
    private static function computeGroupScore(int $groupId, int $tenantId, int $memberCount): float
    {
        $thirtyDaysAgo = now()->subDays(30)->toDateTimeString();

        // Recent posts count
        $recentPosts = 0;
        try {
            $row = DB::selectOne(
                "SELECT COUNT(*) as cnt FROM group_posts WHERE group_id = ? AND created_at >= ?",
                [$groupId, $thirtyDaysAgo]
            );
            $recentPosts = (int) ($row->cnt ?? 0);
        } catch (\Throwable $e) {
            // Table may not exist
        }

        // Recent events count
        $recentEvents = 0;
        try {
            $row = DB::selectOne(
                "SELECT COUNT(*) as cnt FROM events WHERE group_id = ? AND created_at >= ?",
                [$groupId, $thirtyDaysAgo]
            );
            $recentEvents = (int) ($row->cnt ?? 0);
        } catch (\Throwable $e) {
            // Table may not exist
        }

        // Recent discussions count
        $recentDiscussions = 0;
        try {
            $row = DB::selectOne(
                "SELECT COUNT(*) as cnt FROM group_discussions WHERE group_id = ? AND created_at >= ?",
                [$groupId, $thirtyDaysAgo]
            );
            $recentDiscussions = (int) ($row->cnt ?? 0);
        } catch (\Throwable $e) {
            // Table may not exist
        }

        return ($memberCount * 3.0)
             + ($recentPosts * 2.0)
             + ($recentEvents * 5.0)
             + ($recentDiscussions * 2.0);
    }
}
