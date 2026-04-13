<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * GroupRecommendationEngine — ML-powered group discovery system.
 *
 * Uses four recommendation algorithms fused with weighted combination:
 *   1. Collaborative Filtering (40%) — "Users like you joined these groups"
 *   2. Content-Based Matching (25%) — Jaccard similarity on keywords
 *   3. Location-Based (20%) — Haversine distance decay
 *   4. Activity-Based (15%) — Categories user engages with
 *
 * Post-fusion: temporal trend boost + MMR diversity reranking.
 */
class GroupRecommendationEngine
{
    private const WEIGHT_COLLABORATIVE = 0.40;
    private const WEIGHT_CONTENT = 0.25;
    private const WEIGHT_LOCATION = 0.20;
    private const WEIGHT_ACTIVITY = 0.15;

    /**
     * Get personalized group recommendations for a user.
     */
    public function getRecommendations($userId, $limit = 10, $options = []): array
    {
        $tenantId = TenantContext::getId();

        $userGroups = $this->getUserGroups($userId);

        // Cold-start
        if (empty($userGroups)) {
            $connectionGroups = $this->getConnectionGroups($userId, $tenantId);
            if (!empty($connectionGroups)) {
                return $connectionGroups;
            }
            return $this->getPopularGroups($tenantId, $limit, $options);
        }

        $collaborativeScores = $this->collaborativeFiltering($userId, $userGroups, $tenantId);
        $contentScores = $this->contentBasedMatching($userId, $tenantId);
        $locationScores = $this->locationBasedRecommendations($userId, $tenantId);
        $activityScores = $this->activityBasedRecommendations($userId, $tenantId);

        $fusedScores = $this->fuseScores([
            'collaborative' => $collaborativeScores,
            'content' => $contentScores,
            'location' => $locationScores,
            'activity' => $activityScores,
        ], [
            'collaborative' => self::WEIGHT_COLLABORATIVE,
            'content' => self::WEIGHT_CONTENT,
            'location' => self::WEIGHT_LOCATION,
            'activity' => self::WEIGHT_ACTIVITY,
        ]);

        // Temporal trend boost
        $trendBoosts = $this->getTrendBoosts($tenantId);
        foreach ($fusedScores as $groupId => &$score) {
            if (!empty($trendBoosts[$groupId])) {
                $score = min(1.0, $score + $trendBoosts[$groupId] * 0.2);
            }
        }
        unset($score);

        // Exclude already-joined groups
        $excludeIds = array_column($userGroups, 'group_id');
        if (!empty($options['exclude_ids'])) {
            $excludeIds = array_merge($excludeIds, $options['exclude_ids']);
        }

        $recommendations = $this->rankAndFilter($fusedScores, $excludeIds, $tenantId, $limit, $options);

        return $this->hydrateGroupDetails($recommendations, $tenantId);
    }

    /**
     * Track user interaction with recommendations.
     */
    public function trackInteraction($userId, $groupId, $action): void
    {
        try {
            DB::table('group_recommendation_interactions')->insert([
                'user_id' => $userId,
                'group_id' => $groupId,
                'action' => $action,
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Table may not exist — silent fail
        }
    }

    /**
     * Get recommendation performance metrics (admin analytics).
     */
    public function getPerformanceMetrics($days = 30): array
    {
        try {
            return array_map(
                fn ($row) => (array) $row,
                DB::select(
                    "SELECT action, COUNT(*) as count, COUNT(DISTINCT user_id) as unique_users
                     FROM group_recommendation_interactions
                     WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                     GROUP BY action",
                    [$days]
                )
            );
        } catch (\Exception $e) {
            return [];
        }
    }

    // =========================================================================
    // ALGORITHMS
    // =========================================================================

    /**
     * Collaborative Filtering: Jaccard similarity on group membership sets.
     */
    private function collaborativeFiltering($userId, $userGroups, $tenantId): array
    {
        $userGroupIds = array_column($userGroups, 'group_id');
        if (empty($userGroupIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($userGroupIds), '?'));

        // PERF: Replace per-row correlated subquery (which re-scanned group_members for
        // every candidate user) with aggregation-only Jaccard. The previous query was
        // O(candidates * group_members rows). Now we compute:
        //   - U = |user's groups| (constant, count of $userGroupIds)
        //   - V = |other user's groups| via a single grouped subquery
        //   - X = overlap (COUNT in main aggregation)
        //   - jaccard = X / (U + V - X)
        $userGroupCount = count($userGroupIds);

        $similarUsers = DB::select("
            SELECT
                gm.user_id,
                COUNT(*) as overlap_count,
                user_totals.total_groups as other_total,
                (COUNT(*) * 1.0 / (? + user_totals.total_groups - COUNT(*))) as jaccard_similarity
            FROM group_members gm
            JOIN (
                SELECT user_id, COUNT(DISTINCT group_id) as total_groups
                FROM group_members
                WHERE status = 'active'
                GROUP BY user_id
            ) user_totals ON user_totals.user_id = gm.user_id
            WHERE gm.group_id IN ($placeholders)
            AND gm.user_id != ?
            AND gm.status = 'active'
            AND gm.group_id IN (SELECT g.id FROM `groups` g WHERE g.tenant_id = ?)
            GROUP BY gm.user_id, user_totals.total_groups
            HAVING overlap_count >= 2
            ORDER BY jaccard_similarity DESC, overlap_count DESC
            LIMIT 100
        ", array_merge([$userGroupCount], $userGroupIds, [$userId, $tenantId]));

        if (empty($similarUsers)) {
            return [];
        }

        $similarUserIds = array_column(array_map(fn ($r) => (array) $r, $similarUsers), 'user_id');
        $similarityMap = [];
        foreach ($similarUsers as $user) {
            $similarityMap[$user->user_id] = $user->jaccard_similarity;
        }

        $userPlaceholders = implode(',', array_fill(0, count($similarUserIds), '?'));
        $candidateGroups = DB::select("
            SELECT gm.group_id, gm.user_id, COUNT(*) as recommendation_count
            FROM group_members gm
            WHERE gm.user_id IN ($userPlaceholders)
            AND gm.group_id NOT IN ($placeholders)
            AND gm.status = 'active'
            GROUP BY gm.group_id, gm.user_id
        ", array_merge($similarUserIds, $userGroupIds));

        $scores = [];
        foreach ($candidateGroups as $candidate) {
            $groupId = $candidate->group_id;
            $similarity = $similarityMap[$candidate->user_id] ?? 0;
            $scores[$groupId] = ($scores[$groupId] ?? 0) + $similarity;
        }

        if (!empty($scores)) {
            $maxScore = max($scores);
            if ($maxScore > 0) {
                foreach ($scores as $groupId => $score) {
                    $scores[$groupId] = $score / $maxScore;
                }
            }
        }

        return $scores;
    }

    /**
     * Content-Based Matching: Jaccard on user bio keywords vs group descriptions.
     */
    private function contentBasedMatching($userId, $tenantId): array
    {
        $user = DB::table('users')
            ->where('id', $userId)->where('tenant_id', $tenantId)
            ->select('bio', 'location', 'interests')
            ->first();

        if (!$user || empty($user->bio)) {
            return [];
        }

        $userKeywords = $this->extractKeywords($user->bio . ' ' . ($user->interests ?? ''));
        if (empty($userKeywords)) {
            return [];
        }

        $groups = DB::select(
            "SELECT id, name, description FROM `groups`
             WHERE tenant_id = ? AND status = 'active'
             AND (visibility IS NULL OR visibility = 'public')
             AND description IS NOT NULL",
            [$tenantId]
        );

        $scores = [];
        foreach ($groups as $group) {
            $groupKeywords = $this->extractKeywords($group->name . ' ' . $group->description);
            $intersection = count(array_intersect($userKeywords, $groupKeywords));
            $union = count(array_unique(array_merge($userKeywords, $groupKeywords)));
            if ($union > 0) {
                $scores[$group->id] = $intersection / $union;
            }
        }

        return $scores;
    }

    /**
     * Location-Based: Haversine distance to groups within 50km.
     */
    private function locationBasedRecommendations($userId, $tenantId): array
    {
        $user = DB::table('users')
            ->where('id', $userId)->where('tenant_id', $tenantId)
            ->select('latitude', 'longitude')
            ->first();

        if (!$user || empty($user->latitude) || empty($user->longitude)) {
            return [];
        }

        $groups = DB::select("
            SELECT id, latitude, longitude,
                (6371 * acos(
                    cos(radians(?)) * cos(radians(latitude)) *
                    cos(radians(longitude) - radians(?)) +
                    sin(radians(?)) * sin(radians(latitude))
                )) AS distance_km
            FROM `groups`
            WHERE tenant_id = ?
            AND latitude IS NOT NULL AND longitude IS NOT NULL
            AND (visibility IS NULL OR visibility = 'public')
            HAVING distance_km <= 50
            ORDER BY distance_km ASC LIMIT 50
        ", [$user->latitude, $user->longitude, $user->latitude, $tenantId]);

        $scores = [];
        foreach ($groups as $group) {
            if ($group->distance_km <= 50) {
                $scores[$group->id] = 1 - ($group->distance_km / 50);
            }
        }

        return $scores;
    }

    /**
     * Activity-Based: Groups in categories user engages with.
     */
    private function activityBasedRecommendations($userId, $tenantId): array
    {
        $userActivity = DB::select("
            SELECT c.id as category_id, c.name as category_name, COUNT(*) as activity_count
            FROM listings l
            JOIN categories c ON l.category_id = c.id
            WHERE l.user_id = ? AND l.tenant_id = ?
            AND l.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            GROUP BY c.id, c.name
            ORDER BY activity_count DESC LIMIT 5
        ", [$userId, $tenantId]);

        if (empty($userActivity)) {
            return [];
        }

        $activityKeywords = [];
        foreach ($userActivity as $activity) {
            $keywords = $this->extractKeywords($activity->category_name);
            $activityKeywords = array_merge($activityKeywords, $keywords);
        }
        $activityKeywords = array_unique($activityKeywords);

        $groups = DB::select(
            "SELECT id, name, description FROM `groups`
             WHERE tenant_id = ? AND status = 'active'
             AND (visibility IS NULL OR visibility = 'public')",
            [$tenantId]
        );

        $scores = [];
        foreach ($groups as $group) {
            $groupKeywords = $this->extractKeywords($group->name . ' ' . $group->description);
            $overlap = count(array_intersect($activityKeywords, $groupKeywords));
            if ($overlap > 0) {
                $scores[$group->id] = min(1.0, $overlap / count($activityKeywords));
            }
        }

        return $scores;
    }

    // =========================================================================
    // FUSION, RANKING, HYDRATION
    // =========================================================================

    private function fuseScores($scoreArrays, $weights): array
    {
        $fusedScores = [];
        foreach ($scoreArrays as $algorithm => $scores) {
            $weight = $weights[$algorithm] ?? 0;
            foreach ($scores as $groupId => $score) {
                $fusedScores[$groupId] = ($fusedScores[$groupId] ?? 0) + ($score * $weight);
            }
        }
        return $fusedScores;
    }

    /**
     * Rank and filter with MMR diversity reranking.
     */
    private function rankAndFilter($scores, $excludeIds, $tenantId, $limit, $options): array
    {
        foreach ($excludeIds as $excludeId) {
            unset($scores[$excludeId]);
        }

        arsort($scores);

        if (!empty($options['type_id'])) {
            $validGroupIds = DB::table('groups')
                ->where('tenant_id', $tenantId)
                ->where('type_id', $options['type_id'])
                ->pluck('id')
                ->all();
            $scores = array_intersect_key($scores, array_flip($validGroupIds));
        }

        if (count($scores) <= $limit) {
            return array_slice($scores, 0, $limit, true);
        }

        // Load group types for MMR
        $candidateIds = array_keys($scores);
        $placeholders = implode(',', array_fill(0, count($candidateIds), '?'));
        try {
            $typeRows = DB::select("SELECT id, type_id FROM `groups` WHERE id IN ($placeholders)", $candidateIds);
        } catch (\Throwable $e) {
            return array_slice($scores, 0, $limit, true);
        }

        $groupTypes = [];
        foreach ($typeRows as $row) {
            $groupTypes[(int) $row->id] = (int) ($row->type_id ?? 0);
        }

        // MMR greedy selection (lambda=0.7)
        $lambda = 0.7;
        $selected = [];
        $remaining = $scores;

        while (count($selected) < $limit && !empty($remaining)) {
            $bestId = null;
            $bestMmr = PHP_INT_MIN;

            foreach ($remaining as $groupId => $relevance) {
                $sim = 0.0;
                if (!empty($selected)) {
                    $groupType = $groupTypes[$groupId] ?? 0;
                    $sameTypeCount = 0;
                    foreach (array_keys($selected) as $selId) {
                        if ($groupType > 0 && ($groupTypes[$selId] ?? 0) === $groupType) {
                            $sameTypeCount++;
                        }
                    }
                    $sim = $sameTypeCount / count($selected);
                }

                $mmr = $lambda * $relevance - (1.0 - $lambda) * $sim;
                if ($mmr > $bestMmr) {
                    $bestMmr = $mmr;
                    $bestId = $groupId;
                }
            }

            if ($bestId === null) break;

            $selected[$bestId] = $remaining[$bestId];
            unset($remaining[$bestId]);
        }

        return $selected;
    }

    private function hydrateGroupDetails($scores, $tenantId): array
    {
        if (empty($scores)) {
            return [];
        }

        $groupIds = array_keys($scores);
        $placeholders = implode(',', array_fill(0, count($groupIds), '?'));

        $groups = DB::select("
            SELECT g.*, gt.name as type_name,
                   COUNT(DISTINCT gm.id) as member_count
            FROM `groups` g
            LEFT JOIN group_types gt ON g.type_id = gt.id
            LEFT JOIN group_members gm ON g.id = gm.group_id AND gm.status = 'active'
            WHERE g.tenant_id = ? AND g.status = 'active' AND g.id IN ($placeholders)
            GROUP BY g.id
        ", array_merge([$tenantId], $groupIds));

        $result = [];
        foreach ($groups as $group) {
            $group = (array) $group;
            $group['recommendation_score'] = $scores[$group['id']] ?? 0;
            $group['recommendation_reason'] = $this->generateReason($group, $scores[$group['id']]);
            $result[] = $group;
        }

        usort($result, fn ($a, $b) => $b['recommendation_score'] <=> $a['recommendation_score']);

        return $result;
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function getUserGroups($userId): array
    {
        $tenantId = TenantContext::getId();
        return array_map(
            fn ($row) => (array) $row,
            DB::select(
                "SELECT gm.group_id FROM group_members gm
                 JOIN `groups` g ON gm.group_id = g.id
                 WHERE gm.user_id = ? AND gm.status = 'active' AND g.tenant_id = ?",
                [$userId, $tenantId]
            )
        );
    }

    private function getConnectionGroups(int $userId, int $tenantId, int $limit = 10): array
    {
        try {
            $rows = DB::select(
                "SELECT g.*, gt.name as type_name,
                        COUNT(DISTINCT gm.user_id) as connection_count,
                        COUNT(DISTINCT gm2.id) as member_count
                 FROM connections c
                 JOIN group_members gm ON gm.user_id = CASE
                     WHEN c.requester_id = ? THEN c.addressee_id ELSE c.requester_id END
                 JOIN `groups` g ON g.id = gm.group_id
                 LEFT JOIN group_types gt ON g.type_id = gt.id
                 LEFT JOIN group_members gm2 ON gm2.group_id = g.id AND gm2.status = 'active'
                 WHERE (c.requester_id = ? OR c.addressee_id = ?)
                   AND c.status = 'accepted' AND c.tenant_id = ?
                   AND g.tenant_id = ? AND g.status = 'active'
                   AND (g.visibility IS NULL OR g.visibility = 'public')
                   AND gm.status = 'active'
                   AND g.id NOT IN (
                       SELECT group_id FROM group_members WHERE user_id = ? AND tenant_id = ?
                   )
                 GROUP BY g.id
                 ORDER BY connection_count DESC, member_count DESC LIMIT ?",
                [$userId, $userId, $userId, $tenantId, $tenantId, $userId, $tenantId, $limit]
            );

            $result = [];
            foreach ($rows as $row) {
                $row = (array) $row;
                $count = (int) ($row['connection_count'] ?? 1);
                $row['recommendation_score'] = min(1.0, 0.5 + $count * 0.1);
                $row['recommendation_reason'] = $count === 1
                    ? 'A connection of yours is in this group'
                    : "{$count} of your connections are in this group";
                $result[] = $row;
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error('GroupRecommendationEngine::getConnectionGroups error: ' . $e->getMessage());
            return [];
        }
    }

    private function getPopularGroups($tenantId, $limit, $options): array
    {
        $params = [$tenantId];
        $sql = "SELECT g.*, gt.name as type_name,
                       COUNT(DISTINCT gm.id) as member_count
                FROM `groups` g
                LEFT JOIN group_types gt ON g.type_id = gt.id
                LEFT JOIN group_members gm ON g.id = gm.group_id AND gm.status = 'active'
                WHERE g.tenant_id = ?
                AND (g.visibility IS NULL OR g.visibility = 'public')";

        if (!empty($options['type_id'])) {
            $sql .= " AND g.type_id = ?";
            $params[] = $options['type_id'];
        }

        $sql .= " GROUP BY g.id ORDER BY member_count DESC, g.is_featured DESC LIMIT ?";
        $params[] = $limit;

        $groups = array_map(fn ($r) => (array) $r, DB::select($sql, $params));

        foreach ($groups as &$group) {
            $group['recommendation_score'] = 0.5;
            $group['recommendation_reason'] = 'Popular in your community';
        }

        return $groups;
    }

    private function getTrendBoosts(int $tenantId): array
    {
        try {
            $rows = DB::select("
                SELECT gm.group_id, COUNT(*) as recent_joins
                FROM group_members gm
                JOIN `groups` g ON gm.group_id = g.id
                WHERE g.tenant_id = ? AND g.status = 'active'
                AND gm.status = 'active'
                AND gm.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY gm.group_id HAVING recent_joins >= 2
                ORDER BY recent_joins DESC LIMIT 50
            ", [$tenantId]);
        } catch (\Exception $e) {
            return [];
        }

        if (empty($rows)) return [];

        $maxJoins = max(array_map(fn ($r) => (int) $r->recent_joins, $rows));
        $boosts = [];
        foreach ($rows as $row) {
            $boosts[(int) $row->group_id] = $maxJoins > 0 ? (float) $row->recent_joins / $maxJoins : 0.0;
        }

        return $boosts;
    }

    private function extractKeywords($text): array
    {
        $text = strtolower($text);
        $text = preg_replace('/[^\w\s]/', ' ', $text);
        $words = preg_split('/\s+/', $text);

        $stopWords = ['the','a','an','and','or','but','in','on','at','to','for','of','with','is','are','was','were','be','been','being','have','has','had','do','does','did','will','would','should','could','may','might','must','can','this','that','these','those','i','you','he','she','it','we','they','them','their','what','which','who','when','where','why','how'];

        $keywords = array_filter($words, fn ($word) => strlen($word) >= 3 && !in_array($word, $stopWords));

        return array_unique(array_values($keywords));
    }

    private function generateReason($group, $score): string
    {
        if ($score >= 0.8) return 'Highly recommended based on your interests';
        if ($score >= 0.6) return 'Members like you also joined this group';
        if ($score >= 0.4) return 'Popular in your area';
        return 'Might interest you';
    }
}
