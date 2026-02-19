<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * MemberRankingService - CommunityRank Algorithm
 *
 * Intelligent ranking for members/users based on:
 *
 * 1. ACTIVITY SCORE
 *    - Recent logins
 *    - Posts created
 *    - Comments made
 *    - Transactions completed
 *
 * 2. CONTRIBUTION SCORE
 *    - Hours given vs received (giver ratio)
 *    - Listings created (offers/requests)
 *    - Events organized
 *    - Group participation
 *
 * 3. REPUTATION SCORE
 *    - Transaction ratings
 *    - Endorsements received
 *    - Verified status
 *    - Account age (trust factor)
 *
 * 4. CONNECTIVITY SCORE
 *    - Mutual connections with viewer
 *    - Shared groups
 *    - Past interactions
 *
 * 5. PROXIMITY SCORE
 *    - Distance from viewer (if location enabled)
 *
 * 6. COMPLEMENTARY SKILLS SCORE (Unique to CommunityRank)
 *    - Do they offer what viewer needs?
 *    - Do they need what viewer offers?
 *
 * Final Score = Activity × Contribution × Reputation × Connectivity × Proximity × Complementary
 */
class MemberRankingService
{
    // =========================================================================
    // DEFAULT CONFIGURATION
    // =========================================================================

    // Activity weights
    const ACTIVITY_LOGIN_WEIGHT = 1;
    const ACTIVITY_POST_WEIGHT = 3;
    const ACTIVITY_COMMENT_WEIGHT = 2;
    const ACTIVITY_TRANSACTION_WEIGHT = 5;
    const ACTIVITY_LOOKBACK_DAYS = 30;
    const ACTIVITY_MINIMUM = 0.1;

    // Contribution weights
    const CONTRIBUTION_LISTING_WEIGHT = 2;
    const CONTRIBUTION_EVENT_WEIGHT = 5;
    const CONTRIBUTION_GROUP_WEIGHT = 3;
    const CONTRIBUTION_GIVER_BONUS = 1.5;       // Bonus for net givers

    // Reputation parameters
    const REPUTATION_MIN_TRANSACTIONS = 3;      // Min transactions for full reputation
    const REPUTATION_VERIFIED_BOOST = 1.5;
    const REPUTATION_ACCOUNT_AGE_FULL_DAYS = 90;
    const REPUTATION_MINIMUM = 0.3;

    // Connectivity weights
    const CONNECTIVITY_MUTUAL_CONNECTION = 1.5;
    const CONNECTIVITY_SHARED_GROUP = 1.2;
    const CONNECTIVITY_PAST_INTERACTION = 1.3;

    // Complementary skills parameters
    const COMPLEMENTARY_ENABLED = true;
    const COMPLEMENTARY_MATCH_BOOST = 1.8;
    const COMPLEMENTARY_MUTUAL_BOOST = 2.5;     // Both can help each other

    // Cached configuration
    private static ?array $config = null;

    /**
     * Get configuration from tenant settings
     */
    public static function getConfig(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $defaults = [
            'enabled' => true,
            // Activity
            'activity_login_weight' => self::ACTIVITY_LOGIN_WEIGHT,
            'activity_post_weight' => self::ACTIVITY_POST_WEIGHT,
            'activity_comment_weight' => self::ACTIVITY_COMMENT_WEIGHT,
            'activity_transaction_weight' => self::ACTIVITY_TRANSACTION_WEIGHT,
            'activity_lookback_days' => self::ACTIVITY_LOOKBACK_DAYS,
            'activity_minimum' => self::ACTIVITY_MINIMUM,
            // Contribution
            'contribution_listing_weight' => self::CONTRIBUTION_LISTING_WEIGHT,
            'contribution_event_weight' => self::CONTRIBUTION_EVENT_WEIGHT,
            'contribution_group_weight' => self::CONTRIBUTION_GROUP_WEIGHT,
            'contribution_giver_bonus' => self::CONTRIBUTION_GIVER_BONUS,
            // Reputation
            'reputation_min_transactions' => self::REPUTATION_MIN_TRANSACTIONS,
            'reputation_verified_boost' => self::REPUTATION_VERIFIED_BOOST,
            'reputation_account_age_days' => self::REPUTATION_ACCOUNT_AGE_FULL_DAYS,
            'reputation_minimum' => self::REPUTATION_MINIMUM,
            // Connectivity
            'connectivity_mutual_connection' => self::CONNECTIVITY_MUTUAL_CONNECTION,
            'connectivity_shared_group' => self::CONNECTIVITY_SHARED_GROUP,
            'connectivity_past_interaction' => self::CONNECTIVITY_PAST_INTERACTION,
            // Complementary
            'complementary_enabled' => self::COMPLEMENTARY_ENABLED,
            'complementary_match_boost' => self::COMPLEMENTARY_MATCH_BOOST,
            'complementary_mutual_boost' => self::COMPLEMENTARY_MUTUAL_BOOST,
            // Geo
            'geo_enabled' => true,
            'geo_full_radius_km' => 30,
            'geo_decay_per_km' => 0.004,
        ];

        try {
            $tenantId = TenantContext::getId();
            $configJson = Database::query(
                "SELECT configuration FROM tenants WHERE id = ?",
                [$tenantId]
            )->fetchColumn();

            if ($configJson) {
                $configArr = json_decode($configJson, true);
                if (is_array($configArr) && isset($configArr['algorithms']['members'])) {
                    self::$config = array_merge($defaults, $configArr['algorithms']['members']);
                    return self::$config;
                }
            }
        } catch (\Exception $e) {
            // Silently fall back to defaults
        }

        self::$config = $defaults;
        return self::$config;
    }

    /**
     * Check if CommunityRank is enabled
     */
    public static function isEnabled(): bool
    {
        $config = self::getConfig();
        return !empty($config['enabled']);
    }

    /**
     * Clear cached config
     */
    public static function clearCache(): void
    {
        self::$config = null;
    }

    // =========================================================================
    // MAIN PUBLIC METHODS
    // =========================================================================

    /**
     * Rank a list of members for a specific viewer
     *
     * @param array $members Array of member data
     * @param int|null $viewerId Viewing user ID (null for anonymous)
     * @param array $options Additional options
     * @return array Ranked members with scores
     */
    public static function rankMembers(
        array $members,
        ?int $viewerId = null,
        array $options = []
    ): array {
        if (!self::isEnabled() || empty($members)) {
            return $members;
        }

        // Get viewer data if logged in
        $viewerCoords = ['lat' => null, 'lon' => null];
        $viewerListings = [];
        $viewerGroups = [];

        if ($viewerId) {
            $viewerCoords = RankingService::getViewerCoordinates($viewerId);
            $viewerListings = self::getUserListingTypes($viewerId);
            $viewerGroups = self::getUserGroups($viewerId);
        }

        // PERFORMANCE: Batch load all member groups and listings in 2 queries instead of N+1
        $memberIds = array_column($members, 'id');
        $allMemberGroups = self::getBatchUserGroups($memberIds);
        $allMemberListings = self::getBatchUserListings($memberIds);

        // Batch load all interactions with viewer in 1 query
        $viewerInteractions = $viewerId ? self::getBatchViewerInteractions($viewerId, $memberIds) : [];

        $rankedMembers = [];

        foreach ($members as $member) {
            // Skip the viewer themselves
            if ($viewerId && $member['id'] == $viewerId) {
                continue;
            }

            $memberId = $member['id'];
            $memberGroups = $allMemberGroups[$memberId] ?? [];
            $memberListings = $allMemberListings[$memberId] ?? [];
            $hasInteraction = isset($viewerInteractions[$memberId]);

            $scores = self::calculateMemberScoresOptimized(
                $member,
                $viewerId,
                $viewerCoords,
                $viewerListings,
                $viewerGroups,
                $memberGroups,
                $memberListings,
                $hasInteraction
            );

            // Calculate final score
            $finalScore =
                $scores['activity'] *
                $scores['contribution'] *
                $scores['reputation'] *
                $scores['connectivity'] *
                $scores['proximity'] *
                $scores['complementary'];

            $member['_community_rank'] = $finalScore;
            $member['_score_breakdown'] = $scores;
            $rankedMembers[] = $member;
        }

        // Sort by score descending
        usort($rankedMembers, function ($a, $b) {
            return $b['_community_rank'] <=> $a['_community_rank'];
        });

        return $rankedMembers;
    }

    /**
     * Build a ranked members SQL query
     *
     * @param int|null $viewerId Viewing user ID
     * @param array $filters Filter options
     * @return array ['sql' => string, 'params' => array]
     */
    public static function buildRankedQuery(
        ?int $viewerId = null,
        array $filters = []
    ): array {
        $config = self::getConfig();
        $tenantId = TenantContext::getId();

        // Get viewer coordinates
        $viewerCoords = ['lat' => null, 'lon' => null];
        if ($viewerId) {
            $viewerCoords = RankingService::getViewerCoordinates($viewerId);
        }

        // Build score SQL components
        $activitySql = self::getActivityScoreSql();
        $contributionSql = self::getContributionScoreSql();
        $reputationSql = self::getReputationScoreSql();
        $geoSql = self::getGeoScoreSql($viewerCoords['lat'], $viewerCoords['lon']);

        // Total score calculation
        $totalScoreSql = "({$activitySql}) * ({$contributionSql}) * ({$reputationSql}) * ({$geoSql})";

        $sql = "
            SELECT
                u.id, u.tenant_id,
                u.first_name, u.last_name,
                u.email,
                u.avatar_url,
                u.location, u.role,
                u.created_at, u.last_login_at, u.last_active_at,
                u.is_approved, u.is_verified,
                u.profile_type, u.organization_name,
                u.bio, u.skills,

                CASE
                    WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL AND u.organization_name != ''
                    THEN u.organization_name
                    ELSE CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))
                END as display_name,

                -- Listing counts
                (SELECT COUNT(*) FROM listings WHERE user_id = u.id AND status = 'active' AND type = 'offer') as offer_count,
                (SELECT COUNT(*) FROM listings WHERE user_id = u.id AND status = 'active' AND type = 'request') as request_count,

                -- Transaction stats
                (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE sender_id = u.id AND status = 'completed') as hours_given,
                (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE receiver_id = u.id AND status = 'completed') as hours_received,

                -- Score components
                ({$activitySql}) as activity_score,
                ({$contributionSql}) as contribution_score,
                ({$reputationSql}) as reputation_score,
                ({$geoSql}) as geo_score,

                -- Final rank score
                ({$totalScoreSql}) as community_rank

            FROM users u
            WHERE u.tenant_id = ?
              AND u.avatar_url IS NOT NULL
              AND LENGTH(u.avatar_url) > 0
        ";

        $params = [$tenantId];

        // Exclude viewer
        if ($viewerId) {
            $sql .= " AND u.id != ?";
            $params[] = $viewerId;
        }

        // Apply filters
        if (!empty($filters['search'])) {
            $sql .= " AND (
                u.first_name LIKE ? OR
                u.last_name LIKE ? OR
                u.organization_name LIKE ? OR
                u.bio LIKE ? OR
                u.skills LIKE ?
            )";
            $searchTerm = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }

        if (!empty($filters['location'])) {
            $sql .= " AND u.location LIKE ?";
            $params[] = '%' . $filters['location'] . '%';
        }

        if (!empty($filters['has_offers'])) {
            $sql .= " AND EXISTS (SELECT 1 FROM listings WHERE user_id = u.id AND type = 'offer' AND status = 'active')";
        }

        if (!empty($filters['has_requests'])) {
            $sql .= " AND EXISTS (SELECT 1 FROM listings WHERE user_id = u.id AND type = 'request' AND status = 'active')";
        }

        // Order by community_rank
        $sql .= " ORDER BY community_rank DESC, u.last_login_at DESC";

        // Limit and Offset for pagination/infinite scroll
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
            if (!empty($filters['offset'])) {
                $sql .= " OFFSET " . (int)$filters['offset'];
            }
        }

        return [
            'sql' => $sql,
            'params' => $params
        ];
    }

    /**
     * Get suggested members for a user (People You May Know / Suggested Connections)
     *
     * @param int $userId User to get suggestions for
     * @param int $limit Maximum results
     * @return array Suggested members
     */
    public static function getSuggestedMembers(int $userId, int $limit = 10): array
    {
        $tenantId = TenantContext::getId();
        $viewerListings = self::getUserListingTypes($userId);
        $viewerCoords = RankingService::getViewerCoordinates($userId);

        // Get base ranked query
        $query = self::buildRankedQuery($userId, ['limit' => $limit * 3]);
        $members = Database::query($query['sql'], $query['params'])->fetchAll(\PDO::FETCH_ASSOC);

        // Apply complementary skills scoring
        $suggestions = [];
        foreach ($members as $member) {
            $memberListings = self::getUserListingTypes($member['id']);
            $complementaryScore = self::calculateComplementaryScore($viewerListings, $memberListings);

            $member['community_rank'] = ($member['community_rank'] ?? 1) * $complementaryScore;
            $member['_complementary_score'] = $complementaryScore;
            $suggestions[] = $member;
        }

        // Re-sort by updated score
        usort($suggestions, function ($a, $b) {
            return $b['community_rank'] <=> $a['community_rank'];
        });

        return array_slice($suggestions, 0, $limit);
    }

    /**
     * Get active members (for sidebar widget)
     *
     * @param int|null $viewerId Viewing user
     * @param int $limit Maximum results
     * @return array Active members
     */
    public static function getActiveMembers(?int $viewerId, int $limit = 5): array
    {
        $tenantId = TenantContext::getId();

        try {
            // Simple query for recently active members
            // Uses created_at as fallback if last_login_at column doesn't exist yet
            $sql = "
                SELECT
                    u.id,
                    u.first_name,
                    u.last_name,
                    u.organization_name,
                    u.profile_type,
                    u.avatar_url,
                    u.location,
                    u.created_at as last_login_at,
                    CASE
                        WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL
                        THEN u.organization_name
                        ELSE CONCAT(u.first_name, ' ', u.last_name)
                    END as display_name
                FROM users u
                WHERE u.tenant_id = ?
            ";

            $params = [$tenantId];

            if ($viewerId) {
                $sql .= " AND u.id != ?";
                $params[] = $viewerId;
            }

            $sql .= " ORDER BY u.created_at DESC LIMIT ?";
            $params[] = $limit;

            return Database::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    // =========================================================================
    // SCORE CALCULATION METHODS
    // =========================================================================

    /**
     * Calculate all score components for a member
     */
    private static function calculateMemberScores(
        array $member,
        ?int $viewerId,
        array $viewerCoords,
        array $viewerListings,
        array $viewerGroups
    ): array {
        return [
            'activity' => self::calculateActivityScore($member),
            'contribution' => self::calculateContributionScore($member),
            'reputation' => self::calculateReputationScore($member),
            'connectivity' => self::calculateConnectivityScore($member, $viewerId, $viewerGroups),
            'proximity' => self::calculateProximityScore($member, $viewerCoords),
            'complementary' => self::calculateComplementaryScoreForMember($member['id'], $viewerListings),
        ];
    }

    /**
     * Calculate activity score
     */
    private static function calculateActivityScore(array $member): float
    {
        $config = self::getConfig();

        // Check last login
        $lastLogin = $member['last_login_at'] ?? null;
        if (!$lastLogin) {
            return $config['activity_minimum'];
        }

        $daysSinceLogin = RankingService::getDaysSince($lastLogin);

        // Active in last 7 days = full score
        if ($daysSinceLogin <= 7) {
            return 1.0;
        }

        // Decay over 30 days
        if ($daysSinceLogin >= 30) {
            return $config['activity_minimum'];
        }

        // Linear decay
        $decayRate = (1.0 - $config['activity_minimum']) / 23; // 23 days of decay (7-30)
        return 1.0 - (($daysSinceLogin - 7) * $decayRate);
    }

    /**
     * Calculate contribution score
     */
    private static function calculateContributionScore(array $member): float
    {
        $config = self::getConfig();
        $score = 0.5; // Base score

        // Listing count contribution
        $offerCount = (int)($member['offer_count'] ?? 0);
        $requestCount = (int)($member['request_count'] ?? 0);
        $listingBonus = min(0.3, ($offerCount + $requestCount) * 0.05);
        $score += $listingBonus;

        // Hours given vs received (giver bonus)
        $given = (float)($member['hours_given'] ?? 0);
        $received = (float)($member['hours_received'] ?? 0);

        if ($given > 0 && $given > $received) {
            $giverRatio = $given / max(1, $received);
            $giverBonus = min(0.2, ($giverRatio - 1) * 0.1);
            $score += $giverBonus;
        }

        return min(1.0, $score);
    }

    /**
     * Calculate reputation score
     */
    private static function calculateReputationScore(array $member): float
    {
        $config = self::getConfig();
        $score = 0.5; // Base score

        // Account age factor
        $createdAt = $member['created_at'] ?? null;
        if ($createdAt) {
            $daysOld = RankingService::getDaysSince($createdAt);
            $ageFactor = min(0.2, $daysOld / $config['reputation_account_age_days'] * 0.2);
            $score += $ageFactor;
        }

        // Verified boost
        if (!empty($member['is_verified'])) {
            $score += 0.15;
        }

        // Profile completeness
        $profileScore = self::calculateProfileCompletenessBonus($member);
        $score += $profileScore;

        return min(1.0, max($config['reputation_minimum'], $score));
    }

    /**
     * Calculate connectivity score (relationship with viewer)
     */
    private static function calculateConnectivityScore(
        array $member,
        ?int $viewerId,
        array $viewerGroups
    ): float {
        if (!$viewerId) {
            return 1.0;
        }

        $config = self::getConfig();
        $score = 1.0;

        // Check shared groups
        $memberGroups = self::getUserGroups($member['id']);
        $sharedGroups = array_intersect($viewerGroups, $memberGroups);

        if (count($sharedGroups) > 0) {
            $score *= $config['connectivity_shared_group'];
        }

        // Check past interactions (transactions, messages)
        try {
            $hasInteraction = Database::query(
                "SELECT 1 FROM transactions
                 WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
                 LIMIT 1",
                [$viewerId, $member['id'], $member['id'], $viewerId]
            )->fetch();

            if ($hasInteraction) {
                $score *= $config['connectivity_past_interaction'];
            }
        } catch (\Exception $e) {
        }

        return $score;
    }

    /**
     * Calculate proximity score
     */
    private static function calculateProximityScore(array $member, array $viewerCoords): float
    {
        $config = self::getConfig();

        if (!$config['geo_enabled']) {
            return 1.0;
        }

        $memberLat = isset($member['latitude']) ? (float)$member['latitude'] : null;
        $memberLon = isset($member['longitude']) ? (float)$member['longitude'] : null;

        return RankingService::calculateGeoScore(
            $viewerCoords['lat'],
            $viewerCoords['lon'],
            $memberLat,
            $memberLon
        );
    }

    /**
     * Calculate complementary skills score
     */
    private static function calculateComplementaryScoreForMember(int $memberId, array $viewerListings): float
    {
        $memberListings = self::getUserListingTypes($memberId);
        return self::calculateComplementaryScore($viewerListings, $memberListings);
    }

    /**
     * Calculate complementary score between two users' listings
     */
    private static function calculateComplementaryScore(array $viewerListings, array $memberListings): float
    {
        $config = self::getConfig();

        if (!$config['complementary_enabled'] || empty($viewerListings) || empty($memberListings)) {
            return 1.0;
        }

        $viewerOfferCategories = array_column(array_filter($viewerListings, fn($l) => $l['type'] === 'offer'), 'category_id');
        $viewerRequestCategories = array_column(array_filter($viewerListings, fn($l) => $l['type'] === 'request'), 'category_id');

        $memberOfferCategories = array_column(array_filter($memberListings, fn($l) => $l['type'] === 'offer'), 'category_id');
        $memberRequestCategories = array_column(array_filter($memberListings, fn($l) => $l['type'] === 'request'), 'category_id');

        // Check if member offers what viewer requests
        $memberOffersWhatViewerNeeds = count(array_intersect($memberOfferCategories, $viewerRequestCategories)) > 0;

        // Check if viewer offers what member requests
        $viewerOffersWhatMemberNeeds = count(array_intersect($viewerOfferCategories, $memberRequestCategories)) > 0;

        // Mutual match is best
        if ($memberOffersWhatViewerNeeds && $viewerOffersWhatMemberNeeds) {
            return $config['complementary_mutual_boost'];
        }

        // One-way match is still good
        if ($memberOffersWhatViewerNeeds || $viewerOffersWhatMemberNeeds) {
            return $config['complementary_match_boost'];
        }

        return 1.0;
    }

    /**
     * Calculate profile completeness bonus
     */
    private static function calculateProfileCompletenessBonus(array $member): float
    {
        $bonus = 0;
        $fields = ['bio', 'location', 'avatar_url', 'skills'];

        foreach ($fields as $field) {
            if (!empty($member[$field])) {
                $bonus += 0.04; // 4% per field, max 16%
            }
        }

        return $bonus;
    }

    // =========================================================================
    // SQL SCORE METHODS
    // =========================================================================

    /**
     * SQL snippet for activity score
     * Uses created_at for now (last_login_at may not exist yet)
     */
    private static function getActivityScoreSql(): string
    {
        $config = self::getConfig();
        $minimum = (float)$config['activity_minimum'];

        // Use created_at until last_login_at column is added via migration
        return "
            CASE
                WHEN u.created_at IS NULL THEN {$minimum}
                WHEN DATEDIFF(NOW(), u.created_at) <= 7 THEN 1.0
                WHEN DATEDIFF(NOW(), u.created_at) >= 30 THEN {$minimum}
                ELSE 1.0 - ((DATEDIFF(NOW(), u.created_at) - 7) * " . ((1.0 - $minimum) / 23) . ")
            END
        ";
    }

    /**
     * SQL snippet for contribution score
     */
    private static function getContributionScoreSql(): string
    {
        return "
            LEAST(1.0,
                0.5
                + LEAST(0.3, (
                    (SELECT COUNT(*) FROM listings WHERE user_id = u.id AND status = 'active')
                ) * 0.05)
                + CASE
                    WHEN (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE sender_id = u.id AND status = 'completed') >
                         (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE receiver_id = u.id AND status = 'completed')
                    THEN 0.15
                    ELSE 0
                END
            )
        ";
    }

    /**
     * SQL snippet for reputation score
     */
    private static function getReputationScoreSql(): string
    {
        $config = self::getConfig();
        $ageDays = (int)$config['reputation_account_age_days'];
        $minimum = (float)$config['reputation_minimum'];

        return "
            GREATEST({$minimum},
                LEAST(1.0,
                    0.5
                    + LEAST(0.2, DATEDIFF(NOW(), u.created_at) / {$ageDays} * 0.2)
                    + CASE WHEN u.is_verified = 1 THEN 0.15 ELSE 0 END
                    + CASE WHEN COALESCE(u.bio, '') != '' THEN 0.04 ELSE 0 END
                    + CASE WHEN COALESCE(u.location, '') != '' THEN 0.04 ELSE 0 END
                    + CASE WHEN COALESCE(u.avatar_url, '') != '' THEN 0.04 ELSE 0 END
                )
            )
        ";
    }

    /**
     * SQL snippet for geo/proximity score
     */
    private static function getGeoScoreSql(?float $viewerLat, ?float $viewerLon): string
    {
        $config = self::getConfig();

        if (!$config['geo_enabled'] || $viewerLat === null || $viewerLon === null) {
            return '1.0';
        }

        return RankingService::getGeoScoreSql($viewerLat, $viewerLon, 'latitude', 'longitude', 'u');
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Get user's listing types (for complementary matching)
     */
    private static function getUserListingTypes(int $userId): array
    {
        try {
            return Database::query(
                "SELECT id, type, category_id FROM listings WHERE user_id = ? AND status = 'active'",
                [$userId]
            )->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get user's group memberships
     */
    private static function getUserGroups(int $userId): array
    {
        try {
            return Database::query(
                "SELECT group_id FROM group_members WHERE user_id = ?",
                [$userId]
            )->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * BATCH: Get group memberships for multiple users in a single query
     * Returns array keyed by user_id => [group_id, group_id, ...]
     */
    private static function getBatchUserGroups(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        try {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $rows = Database::query(
                "SELECT user_id, group_id FROM group_members WHERE user_id IN ({$placeholders})",
                $userIds
            )->fetchAll(\PDO::FETCH_ASSOC);

            $result = [];
            foreach ($rows as $row) {
                $uid = $row['user_id'];
                if (!isset($result[$uid])) {
                    $result[$uid] = [];
                }
                $result[$uid][] = $row['group_id'];
            }
            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * BATCH: Get listings for multiple users in a single query
     * Returns array keyed by user_id => [{id, type, category_id}, ...]
     */
    private static function getBatchUserListings(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        try {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $rows = Database::query(
                "SELECT user_id, id, type, category_id FROM listings WHERE user_id IN ({$placeholders}) AND status = 'active'",
                $userIds
            )->fetchAll(\PDO::FETCH_ASSOC);

            $result = [];
            foreach ($rows as $row) {
                $uid = $row['user_id'];
                if (!isset($result[$uid])) {
                    $result[$uid] = [];
                }
                $result[$uid][] = [
                    'id' => $row['id'],
                    'type' => $row['type'],
                    'category_id' => $row['category_id']
                ];
            }
            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * BATCH: Get all interactions between viewer and multiple members
     * Returns array keyed by member_id => true (if interaction exists)
     */
    private static function getBatchViewerInteractions(int $viewerId, array $memberIds): array
    {
        if (empty($memberIds)) {
            return [];
        }

        try {
            $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
            // Find all transactions where viewer was sender/receiver with any of these members
            $params = array_merge([$viewerId], $memberIds, [$viewerId], $memberIds);
            $rows = Database::query(
                "SELECT DISTINCT
                    CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END as member_id
                 FROM transactions
                 WHERE (sender_id = ? AND receiver_id IN ({$placeholders}))
                    OR (receiver_id = ? AND sender_id IN ({$placeholders}))",
                array_merge([$viewerId, $viewerId], $memberIds, [$viewerId], $memberIds)
            )->fetchAll(\PDO::FETCH_COLUMN);

            return array_flip($rows); // Convert to keyed array for O(1) lookup
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Optimized score calculation using pre-loaded batch data
     */
    private static function calculateMemberScoresOptimized(
        array $member,
        ?int $viewerId,
        array $viewerCoords,
        array $viewerListings,
        array $viewerGroups,
        array $memberGroups,
        array $memberListings,
        bool $hasInteraction
    ): array {
        return [
            'activity' => self::calculateActivityScore($member),
            'contribution' => self::calculateContributionScore($member),
            'reputation' => self::calculateReputationScore($member),
            'connectivity' => self::calculateConnectivityScoreOptimized($viewerId, $viewerGroups, $memberGroups, $hasInteraction),
            'proximity' => self::calculateProximityScore($member, $viewerCoords),
            'complementary' => self::calculateComplementaryScore($viewerListings, $memberListings),
        ];
    }

    /**
     * Optimized connectivity score using pre-loaded data
     */
    private static function calculateConnectivityScoreOptimized(
        ?int $viewerId,
        array $viewerGroups,
        array $memberGroups,
        bool $hasInteraction
    ): float {
        if (!$viewerId) {
            return 1.0;
        }

        $config = self::getConfig();
        $score = 1.0;

        // Check shared groups (using pre-loaded data)
        $sharedGroups = array_intersect($viewerGroups, $memberGroups);
        if (count($sharedGroups) > 0) {
            $score *= $config['connectivity_shared_group'];
        }

        // Check past interactions (using pre-loaded data)
        if ($hasInteraction) {
            $score *= $config['connectivity_past_interaction'];
        }

        return $score;
    }

    // =========================================================================
    // DEBUG & ANALYTICS
    // =========================================================================

    /**
     * Get score breakdown for a member
     */
    public static function debugMemberScore(int $memberId, ?int $viewerId = null): array
    {
        try {
            $member = Database::query(
                "SELECT u.*,
                    (SELECT COUNT(*) FROM listings WHERE user_id = u.id AND status = 'active' AND type = 'offer') as offer_count,
                    (SELECT COUNT(*) FROM listings WHERE user_id = u.id AND status = 'active' AND type = 'request') as request_count,
                    (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE sender_id = u.id AND status = 'completed') as hours_given,
                    (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE receiver_id = u.id AND status = 'completed') as hours_received
                 FROM users u
                 WHERE u.id = ?",
                [$memberId]
            )->fetch(\PDO::FETCH_ASSOC);

            if (!$member) {
                return ['error' => 'Member not found'];
            }

            $viewerCoords = ['lat' => null, 'lon' => null];
            $viewerListings = [];
            $viewerGroups = [];

            if ($viewerId) {
                $viewerCoords = RankingService::getViewerCoordinates($viewerId);
                $viewerListings = self::getUserListingTypes($viewerId);
                $viewerGroups = self::getUserGroups($viewerId);
            }

            $scores = self::calculateMemberScores(
                $member,
                $viewerId,
                $viewerCoords,
                $viewerListings,
                $viewerGroups
            );

            $finalScore = array_product($scores);

            return [
                'member_id' => $memberId,
                'display_name' => $member['first_name'] . ' ' . $member['last_name'],
                'scores' => $scores,
                'final_score' => $finalScore,
                'config' => self::getConfig()
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
