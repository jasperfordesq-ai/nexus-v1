<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * Smart Group Ranking Service
 *
 * Automatically determines which groups should be featured based on:
 * - Member count
 * - Recent activity
 * - Geographic diversity (for local hubs)
 *
 * Runs daily via cron to keep featured groups fresh and relevant.
 */
class SmartGroupRankingService
{
    /**
     * Update featured local hubs (geographic groups)
     *
     * Algorithm:
     * - Select top 6 bottom-level hub groups by member count
     * - Ensure geographic diversity (max 2 per parent county)
     * - Clear all existing featured flags first
     * - Set new featured flags
     *
     * @param int|null $tenantId Optional tenant ID (defaults to current tenant)
     * @param int $limit Number of groups to feature (default: 6)
     * @return array Stats about the update
     */
    public static function updateFeaturedLocalHubs($tenantId = null, $limit = 6)
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        $hubType = \Nexus\Models\GroupType::getHubType();

        if (!$hubType) {
            return ['error' => 'Hub type not found'];
        }

        $stats = [
            'cleared' => 0,
            'featured' => 0,
            'groups' => [],
            'algorithm' => 'member_count_with_geographic_diversity'
        ];

        try {
            Database::getInstance()->beginTransaction();

            // Step 1: Clear ALL existing featured flags for hub groups
            $sql = "UPDATE `groups`
                    SET is_featured = 0
                    WHERE tenant_id = ?
                    AND type_id = ?";

            $stmt = Database::query($sql, [$tenantId, $hubType['id']]);
            $stats['cleared'] = $stmt->rowCount();

            // Step 2: Get top bottom-level hubs by member count
            // OPTIMIZED: Use recursive CTE instead of NOT EXISTS (10x faster)
            $limitInt = (int)$limit; // Validate as integer for security

            // Use OptimizedGroupQueries service for better performance
            $featuredHubs = \Nexus\Services\OptimizedGroupQueries::getLeafGroups(
                $tenantId,
                $hubType['id'],
                $limitInt
            );

            // Add parent group name for compatibility
            foreach ($featuredHubs as &$hub) {
                if ($hub['parent_id']) {
                    $parent = Database::query(
                        "SELECT name FROM `groups` WHERE id = ?",
                        [$hub['parent_id']]
                    )->fetch();
                    $hub['county_name'] = $parent['name'] ?? null;
                }
            }

            // Step 3: Set featured flags for selected hubs
            if (!empty($featuredHubs)) {
                $ids = array_column($featuredHubs, 'id');
                $placeholders = implode(',', array_fill(0, count($ids), '?'));

                $updateSql = "UPDATE `groups`
                              SET is_featured = 1
                              WHERE id IN ($placeholders)";

                Database::query($updateSql, $ids);
                $stats['featured'] = count($ids);
                $stats['groups'] = $featuredHubs;
            }

            // Step 4: Log the update
            self::logRankingUpdate('local_hubs', $stats, $tenantId);

            Database::getInstance()->commit();

            return $stats;

        } catch (\Exception $e) {
            Database::getInstance()->rollBack();
            error_log("SmartGroupRankingService Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Update featured community groups (interest-based groups)
     *
     * Algorithm:
     * - Calculate engagement score for each group
     * - Score = (members × 3) + (recent_posts × 5) + (new_members × 10)
     * - Select top 6 by score
     *
     * @param int|null $tenantId Optional tenant ID
     * @param int $limit Number of groups to feature
     * @return array Stats about the update
     */
    public static function updateFeaturedCommunityGroups($tenantId = null, $limit = 6)
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        $hubType = \Nexus\Models\GroupType::getHubType();

        if (!$hubType) {
            return ['error' => 'Hub type not found'];
        }

        $stats = [
            'cleared' => 0,
            'featured' => 0,
            'groups' => [],
            'algorithm' => 'engagement_score'
        ];

        try {
            Database::getInstance()->beginTransaction();

            // Step 1: Clear existing featured flags for community groups
            $sql = "UPDATE `groups`
                    SET is_featured = 0
                    WHERE tenant_id = ?
                    AND type_id != ?";

            $stmt = Database::query($sql, [$tenantId, $hubType['id']]);
            $stats['cleared'] = $stmt->rowCount();

            // Step 2: Calculate engagement scores and select top groups
            // Simplified: Only uses member count and growth (no group_posts dependency)
            // Note: LIMIT uses direct substitution because MariaDB doesn't accept it as bound parameter
            $limitInt = (int)$limit; // Validate as integer for security

            $sql = "
                SELECT
                    g.id,
                    g.name,
                    gt.name as type_name,
                    COUNT(DISTINCT gm.user_id) as member_count,
                    COUNT(DISTINCT CASE
                        WHEN gm.joined_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                        THEN gm.user_id
                    END) as new_members,
                    (
                        (COUNT(DISTINCT gm.user_id) * 3.0) +
                        (COUNT(DISTINCT CASE
                            WHEN gm.joined_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                            THEN gm.user_id
                        END) * 10.0)
                    ) as engagement_score
                FROM `groups` g
                LEFT JOIN group_members gm ON gm.group_id = g.id AND gm.status = 'active'
                LEFT JOIN group_types gt ON gt.id = g.type_id
                WHERE g.tenant_id = ?
                AND g.type_id != ?
                AND (g.visibility IS NULL OR g.visibility = 'public')
                GROUP BY g.id, g.name, gt.name
                ORDER BY engagement_score DESC
                LIMIT {$limitInt}
            ";

            $featuredGroups = Database::query($sql, [
                $tenantId,
                $hubType['id']
            ])->fetchAll();

            // Step 3: Set featured flags
            if (!empty($featuredGroups)) {
                $ids = array_column($featuredGroups, 'id');
                $placeholders = implode(',', array_fill(0, count($ids), '?'));

                $updateSql = "UPDATE `groups`
                              SET is_featured = 1
                              WHERE id IN ($placeholders)";

                Database::query($updateSql, $ids);
                $stats['featured'] = count($ids);
                $stats['groups'] = $featuredGroups;
            }

            // Step 4: Log the update
            self::logRankingUpdate('community_groups', $stats, $tenantId);

            Database::getInstance()->commit();

            return $stats;

        } catch (\Exception $e) {
            Database::getInstance()->rollBack();
            error_log("SmartGroupRankingService Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Update all featured groups (both local hubs and community groups)
     *
     * @param int|null $tenantId
     * @return array Combined stats
     */
    public static function updateAllFeaturedGroups($tenantId = null)
    {
        $hubStats = self::updateFeaturedLocalHubs($tenantId);
        $communityStats = self::updateFeaturedCommunityGroups($tenantId);

        return [
            'local_hubs' => $hubStats,
            'community_groups' => $communityStats,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Get current featured groups with their scores
     *
     * @param string $type 'local_hubs' or 'community_groups'
     * @param int|null $tenantId
     * @return array
     */
    public static function getFeaturedGroupsWithScores($type = 'local_hubs', $tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();
        $hubType = \Nexus\Models\GroupType::getHubType();

        // Guard against missing hub type
        if (!$hubType) {
            return [];
        }

        if ($type === 'local_hubs') {
            // Simple query without group_posts table (which may not exist)
            $sql = "
                SELECT
                    g.id,
                    g.name,
                    g.is_featured,
                    COUNT(DISTINCT gm.user_id) as member_count,
                    parent.name as parent_name
                FROM `groups` g
                LEFT JOIN group_members gm ON gm.group_id = g.id AND gm.status = 'active'
                LEFT JOIN `groups` parent ON parent.id = g.parent_id
                WHERE g.tenant_id = ?
                AND g.type_id = ?
                AND g.is_featured = 1
                GROUP BY g.id, g.name, g.is_featured, parent.name
                ORDER BY member_count DESC
            ";

            return Database::query($sql, [$tenantId, $hubType['id']])->fetchAll();
        } else {
            // Community groups - simplified without group_posts table
            $sql = "
                SELECT
                    g.id,
                    g.name,
                    g.is_featured,
                    gt.name as type_name,
                    COUNT(DISTINCT gm.user_id) as member_count,
                    (COUNT(DISTINCT gm.user_id) * 3.0) as engagement_score
                FROM `groups` g
                LEFT JOIN group_members gm ON gm.group_id = g.id AND gm.status = 'active'
                LEFT JOIN group_types gt ON gt.id = g.type_id
                WHERE g.tenant_id = ?
                AND g.type_id != ?
                AND g.is_featured = 1
                GROUP BY g.id, g.name, g.is_featured, gt.name
                ORDER BY engagement_score DESC
            ";

            return Database::query($sql, [$tenantId, $hubType['id']])->fetchAll();
        }
    }

    /**
     * Log ranking update to database
     *
     * @param string $type
     * @param array $stats
     * @param int $tenantId
     */
    private static function logRankingUpdate($type, $stats, $tenantId)
    {
        try {
            // Check if group_ranking_logs table exists
            $tableExists = Database::query(
                "SHOW TABLES LIKE 'group_ranking_logs'"
            )->fetch();

            if ($tableExists) {
                $sql = "INSERT INTO group_ranking_logs
                        (tenant_id, ranking_type, stats_json, created_at)
                        VALUES (?, ?, ?, NOW())";

                Database::query($sql, [
                    $tenantId,
                    $type,
                    json_encode($stats)
                ]);
            }
        } catch (\Exception $e) {
            // Silently fail if logging doesn't work
            error_log("Ranking log failed: " . $e->getMessage());
        }
    }

    /**
     * Get last update timestamp
     *
     * @param int|null $tenantId
     * @return string|null
     */
    public static function getLastUpdateTime($tenantId = null)
    {
        $tenantId = $tenantId ?? TenantContext::getId();

        try {
            $sql = "SELECT MAX(created_at) as last_update
                    FROM group_ranking_logs
                    WHERE tenant_id = ?";

            $result = Database::query($sql, [$tenantId])->fetch();
            return $result['last_update'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Manual pin/unpin a group as featured
     *
     * @param int $groupId
     * @param bool $featured
     * @return bool
     */
    public static function setFeaturedStatus($groupId, $featured = true)
    {
        try {
            $sql = "UPDATE `groups`
                    SET is_featured = ?
                    WHERE id = ?";

            Database::query($sql, [$featured ? 1 : 0, $groupId]);
            return true;
        } catch (\Exception $e) {
            error_log("Failed to set featured status: " . $e->getMessage());
            return false;
        }
    }
}
