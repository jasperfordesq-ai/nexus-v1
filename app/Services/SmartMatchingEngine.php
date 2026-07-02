<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Services\Matching\CandidateRetriever;
use App\Services\SafeguardingTriggerService;
use App\Services\VettingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SmartMatchingEngine — Multi-dimensional matching algorithm.
 *
 * Candidate retrieval goes through {@see CandidateRetriever}, which enforces
 * the HARD gates (geo/service_type, missing-coords degraded mode, dormancy,
 * dismissals) before any scoring happens. Physical listings beyond the
 * effective max distance are never scored; remote_only/hybrid listings are
 * distance-exempt.
 *
 * 6-signal scoring pipeline (weighted sum, 0–100):
 *   1. Category Match (25%) — same/related category
 *   2. Skill Complementarity (20%) — Jaccard + proficiency weighting
 *   3. Proximity (25%) — Haversine with piecewise linear decay,
 *      service_type-aware (remote_only fixed 0.9)
 *   4. Temporal Relevance (10%) — exponential freshness decay
 *   5. Reciprocity Potential (15%) — mutual exchange opportunity
 *   6. Quality Signals (5%) — description, images, ratings
 *
 * Post-scoring boosts (bounded additive on the 0–100 scale): semantic
 * embedding +8, KNN member recs +6.
 */
class SmartMatchingEngine
{
    // Scoring weights (sum = 1.0)
    private const WEIGHT_CATEGORY = 0.25;
    private const WEIGHT_SKILL = 0.20;
    private const WEIGHT_PROXIMITY = 0.25;
    private const WEIGHT_FRESHNESS = 0.10;
    private const WEIGHT_RECIPROCITY = 0.15;
    private const WEIGHT_QUALITY = 0.05;

    // Proximity tiers (km)
    private const PROXIMITY_WALKING = 5;
    private const PROXIMITY_LOCAL = 15;
    private const PROXIMITY_CITY = 30;
    private const PROXIMITY_REGIONAL = 50;
    private const PROXIMITY_MAX = 100;

    // Freshness
    private const FRESHNESS_FULL_HOURS = 24;
    private const FRESHNESS_HALF_LIFE_DAYS = 14;
    private const FRESHNESS_MINIMUM = 0.3;

    // Quality
    private const QUALITY_MIN_DESCRIPTION = 50;
    private const QUALITY_RATING_THRESHOLD = 4.0;

    // In-process caches
    private array $userDataCache = [];
    private ?array $configCache = null;
    private array $categoryCache = [];

    public function __construct(
        private readonly EmbeddingService $embeddingService,
        private readonly VettingService $vettingService,
        private readonly CandidateRetriever $candidateRetriever,
    ) {}

    /**
     * Get configuration from tenant settings.
     */
    public function getConfig(): array
    {
        if ($this->configCache !== null) {
            return $this->configCache;
        }

        $defaults = [
            'enabled' => true,
            'max_distance_km' => 50,
            'min_match_score' => 40,
            'hot_match_threshold' => 80,
            'weights' => [
                'category' => self::WEIGHT_CATEGORY,
                'skill' => self::WEIGHT_SKILL,
                'proximity' => self::WEIGHT_PROXIMITY,
                'freshness' => self::WEIGHT_FRESHNESS,
                'reciprocity' => self::WEIGHT_RECIPROCITY,
                'quality' => self::WEIGHT_QUALITY,
            ],
            'proximity' => [
                'walking_km' => self::PROXIMITY_WALKING,
                'local_km' => self::PROXIMITY_LOCAL,
                'city_km' => self::PROXIMITY_CITY,
                'regional_km' => self::PROXIMITY_REGIONAL,
                'max_km' => self::PROXIMITY_MAX,
            ],
            'gates' => [
                // Hard exclusion of physical listings beyond the effective max
                // distance (remote_only/hybrid are always exempt).
                'geo_hard_gate' => true,
                // Searcher without coordinates: 'remote_only' (default) limits
                // them to distance-independent listings + a set-location prompt;
                // 'tenant_wide' restores legacy nationwide reach.
                'missing_coords_mode' => 'remote_only',
                // Exclude listings whose owner hasn't been active in N days.
                'dormancy_days' => 90,
                // Reserved for the owner-level dismissal suppression gate.
                'owner_dismissal_threshold' => 3,
            ],
        ];

        $tenantId = TenantContext::getId();

        try {
            $configJson = DB::table('tenants')->where('id', $tenantId)->value('configuration');
            if ($configJson) {
                $configArr = json_decode($configJson, true);
                if (is_array($configArr) && isset($configArr['algorithms']['smart_matching'])) {
                    $tenantCfg = $configArr['algorithms']['smart_matching'];
                    $merged = array_merge($defaults, $tenantCfg);
                    // Nested blocks merge key-by-key so a tenant that has only
                    // saved e.g. weights doesn't wipe the gates defaults (and a
                    // partial gates block keeps default values for missing keys).
                    foreach (['weights', 'proximity', 'gates'] as $block) {
                        $merged[$block] = array_merge(
                            $defaults[$block],
                            is_array($tenantCfg[$block] ?? null) ? $tenantCfg[$block] : []
                        );
                    }
                    $this->configCache = $merged;
                    return $this->configCache;
                }
            }
        } catch (\Exception $e) {
            Log::error('[SmartMatchingEngine] getConfig DB fetch failed: ' . $e->getMessage());
        }

        $this->configCache = $defaults;
        return $this->configCache;
    }

    /**
     * Clear cached data.
     */
    public function clearCache(): void
    {
        $this->configCache = null;
        $this->userDataCache = [];
        $this->categoryCache = [];
    }

    /**
     * Warm the persistent match cache (the `match_cache` table) for active users
     * in the current tenant.
     *
     * This is the SOLE writer of `match_cache`, which backs the matching
     * analytics, new-match notifications and the matches dashboards. The static
     * legacy version was dropped in the Laravel-migration refactor (commit
     * 7bc2a1629), which left the scheduled "warm-match-cache" task calling an
     * undefined method every 30 minutes (a per-tenant fatal in the scheduler
     * logs) and the cache permanently unpopulated. Restored here as an instance
     * method on the DI service.
     *
     * For up to $limit active users (who have at least one active listing and no
     * fresh cache entry) it computes matches via findMatchesForUser() and upserts
     * them into match_cache with a 7-day TTL. Tenant-scoped via TenantContext.
     *
     * @return array{processed:int, cached:int}
     */
    public function warmUpCache(int $limit = 50): array
    {
        $results = ['processed' => 0, 'cached' => 0];
        $tenantId = TenantContext::getId();
        if (!$tenantId) {
            return $results;
        }

        // This service is a container singleton (AppServiceProvider), so the
        // per-tenant scheduler loop reuses ONE instance. Reset the in-process
        // caches (configCache is not tenant-keyed; userDataCache is per-user) so
        // each tenant warms with its own config instead of the previous tenant's.
        $this->clearCache();

        // Active users with an active listing whose cache is missing or expired.
        // $limit is an internal int (never user input) — inlined because PDO
        // cannot reliably bind a LIMIT placeholder under emulated prepares.
        $users = DB::select(
            "SELECT DISTINCT u.id
             FROM users u
             INNER JOIN listings l
                 ON u.id = l.user_id AND l.status = 'active' AND l.tenant_id = u.tenant_id
             LEFT JOIN match_cache mc
                 ON u.id = mc.user_id AND mc.tenant_id = ?
             WHERE u.tenant_id = ?
               AND u.status = 'active'
               AND (mc.id IS NULL OR mc.expires_at < NOW())
             ORDER BY u.last_login_at DESC
             LIMIT " . max(1, (int) $limit),
            [$tenantId, $tenantId]
        );

        foreach ($users as $row) {
            $userId = (int) $row->id;
            $results['processed']++;

            try {
                $matches = $this->findMatchesForUser($userId, ['limit' => 20]);
            } catch (\Throwable $e) {
                Log::warning('SmartMatchingEngine::warmUpCache findMatches failed', [
                    'user_id' => $userId, 'tenant_id' => $tenantId, 'error' => $e->getMessage(),
                ]);
                continue;
            }

            foreach ($matches as $match) {
                $listingId = (int) ($match['id'] ?? 0);
                if ($listingId <= 0) {
                    continue;
                }
                // findMatchesForUser scores are 0–100, same scale as
                // match_cache.match_score. (A former min(1.0, …) * 100 clamp
                // here wrote every cached entry as exactly 100.00, feeding
                // garbage to analytics and hot-match notifications.)
                $score = round(min(100.0, max(0.0, (float) ($match['match_score'] ?? 0))), 2);
                $distance = (isset($match['distance_km']) && is_numeric($match['distance_km'])
                    && $match['distance_km'] >= 0 && $match['distance_km'] < 999999)
                    ? round((float) $match['distance_km'], 2)
                    : null;
                $type = in_array($match['match_type'] ?? 'one_way', ['one_way', 'potential', 'mutual', 'cold_start'], true)
                    ? ($match['match_type'] ?? 'one_way')
                    : 'one_way';
                $reasons = json_encode($match['match_reasons'] ?? [], JSON_UNESCAPED_UNICODE) ?: '[]';

                try {
                    // ON DUPLICATE KEY UPDATE deliberately leaves `status` untouched
                    // so a user's interaction state (viewed/contacted/…) survives a re-warm.
                    DB::insert(
                        "INSERT INTO match_cache
                            (user_id, listing_id, tenant_id, match_score, distance_km, match_type, match_reasons, status, created_at, expires_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, 'new', NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY))
                         ON DUPLICATE KEY UPDATE
                            match_score = VALUES(match_score),
                            distance_km = VALUES(distance_km),
                            match_type = VALUES(match_type),
                            match_reasons = VALUES(match_reasons),
                            expires_at = DATE_ADD(NOW(), INTERVAL 7 DAY)",
                        [$userId, $listingId, $tenantId, $score, $distance, $type, $reasons]
                    );
                    $results['cached']++;
                } catch (\Throwable $e) {
                    Log::warning('SmartMatchingEngine::warmUpCache cache write failed', [
                        'user_id' => $userId, 'listing_id' => $listingId, 'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $results;
    }

    // =========================================================================
    // MAIN PUBLIC METHODS
    // =========================================================================

    /**
     * Find matches for a user based on their listings.
     *
     * @param array|null $meta Filled with run metadata for the API layer:
     *   needs_location (searcher has no coords), degraded (+reason — remote-only
     *   degraded mode active), has_active_listings (false → cold-start path).
     */
    public function findMatchesForUser(int $userId, array $options = [], ?array &$meta = null): array
    {
        $tenantId = TenantContext::getId();
        $config = $this->getConfig();

        $preferences = $this->getUserPreferences($userId);
        // Effective ceiling: the user's preference can tighten the tenant max
        // but never exceed it.
        $tenantMax = (float) $config['max_distance_km'];
        $userMax = isset($preferences['max_distance_km']) ? (float) $preferences['max_distance_km'] : null;
        $maxDistance = (float) ($options['max_distance'] ?? ($userMax !== null && $userMax > 0 ? min($userMax, $tenantMax) : $tenantMax));
        $minScore = $options['min_score'] ?? $preferences['min_match_score'] ?? $config['min_match_score'];
        $limit = $options['limit'] ?? 20;
        $categoryFilter = $options['categories'] ?? $preferences['categories'] ?? null;

        $userData = $this->getUserData($userId);
        if (!$userData) {
            $meta = [
                'needs_location' => false,
                'degraded' => false,
                'degraded_reason' => null,
                'has_active_listings' => false,
            ];
            return [];
        }

        $userLat = $this->coordOrNull($userData['latitude'] ?? null);
        $userLon = $this->coordOrNull($userData['longitude'] ?? null);
        $hasCoords = $userLat !== null && $userLon !== null;
        $gates = $config['gates'];
        $degraded = !$hasCoords
            && (bool) ($gates['geo_hard_gate'] ?? true)
            && (($gates['missing_coords_mode'] ?? 'remote_only') !== 'tenant_wide');

        $meta = [
            'needs_location' => !$hasCoords,
            'degraded' => $degraded,
            'degraded_reason' => !$hasCoords ? 'no_coordinates' : null,
            'has_active_listings' => true,
        ];

        $userListings = $this->getUserListings($userId);
        if (empty($userListings)) {
            $meta['has_active_listings'] = false;
            return $this->getColdStartMatches($userId, $userData, $maxDistance, $limit);
        }

        $matches = [];
        $seenIds = [];

        // PERF: Batch candidate lookup. Previous code fired one query per user listing
        // (N+1 over $userListings). Now we group by targetType and issue at most 2 queries
        // (one for 'offer' candidates, one for 'request' candidates), filtered by the
        // union of all relevant category_ids. We then dispatch candidates back to each
        // source listing by matching category_id in PHP.
        $byTargetType = ['offer' => [], 'request' => []]; // targetType => list of category_ids
        $myListingsByTarget = ['offer' => [], 'request' => []]; // targetType => list of myListings
        foreach ($userListings as $myListing) {
            $targetType = ($myListing['type'] === 'offer') ? 'request' : 'offer';
            if (!empty($myListing['category_id'])) {
                $byTargetType[$targetType][] = (int) $myListing['category_id'];
            }
            $myListingsByTarget[$targetType][] = $myListing;
        }

        $candidatesByTargetType = ['offer' => [], 'request' => []];
        foreach ($byTargetType as $targetType => $categoryIds) {
            if (empty($myListingsByTarget[$targetType])) {
                continue;
            }
            $uniqueCatIds = array_values(array_unique($categoryIds));
            $candidatesByTargetType[$targetType] = $this->candidateRetriever->retrieveBatch(
                $tenantId, $userId, $targetType, $uniqueCatIds,
                $categoryFilter, $userLat, $userLon, $maxDistance, $gates
            );
        }

        // Safeguarding: strip candidates whose owners require vetting types the
        // searcher does not hold. Staff roles bypass this at discovery — they
        // still need the full pool for coordination and assignment purposes.
        // National Vetting Bureau Acts 2012–2016 / DBS / PVG / AccessNI.
        foreach ($candidatesByTargetType as $targetType => $list) {
            if (!empty($list)) {
                $candidatesByTargetType[$targetType] = $this->filterCandidatesByVettingRequirements(
                    $list,
                    $userId
                );
            }
        }

        foreach ($userListings as $myListing) {
            $targetType = ($myListing['type'] === 'offer') ? 'request' : 'offer';
            $myCatId = $myListing['category_id'] ?? null;

            // Dispatch: candidates whose category_id matches this listing, OR all if myCatId null.
            $candidates = [];
            foreach ($candidatesByTargetType[$targetType] as $cand) {
                if ($myCatId === null || (int) ($cand['category_id'] ?? 0) === (int) $myCatId) {
                    $candidates[] = $cand;
                }
            }

            foreach ($candidates as $candidate) {
                if (in_array($candidate['id'], $seenIds)) {
                    continue;
                }

                $matchResult = $this->calculateMatchScore($userData, $userListings, $myListing, $candidate);

                if ($matchResult['score'] >= $minScore) {
                    $candidate['match_score'] = $matchResult['score'];
                    $candidate['match_reasons'] = $matchResult['reasons'];
                    $candidate['match_breakdown'] = $matchResult['breakdown'];
                    $candidate['distance_km'] = $matchResult['distance'];
                    $candidate['matched_listing'] = $myListing['title'];
                    $candidate['match_type'] = $matchResult['type'];

                    $matches[] = $candidate;
                    $seenIds[] = $candidate['id'];
                }
            }
        }

        // Semantic embedding boost — bounded additive on the 0–100 scale.
        // (The former min(1.0, score × 1.3) treated the score as 0–1 and
        // collapsed every boosted match to 1/100, sinking the BEST matches.)
        if (!empty($matches)) {
            $userListingIds = array_column($userListings, 'id');
            $firstListingId = $userListingIds[0] ?? null;

            if ($firstListingId) {
                $semanticSimilar = $this->embeddingService->findSimilar(
                    (int) $firstListingId, 'listing', $tenantId, 50
                );
                $semanticSet = array_flip($semanticSimilar);

                foreach ($matches as &$match) {
                    if (isset($semanticSet[$match['id'] ?? 0])) {
                        $match['match_score'] = min(100.0, (float) $match['match_score'] + 8);
                        $match['match_reasons'][] = 'Similar to your listing';
                    }
                }
                unset($match);
            }
        }

        // KNN member recommendation boost — bounded additive (same scale fix).
        // recs_members_* holds recommended MEMBER ids, so key on the listing's
        // owner (the old code compared listing ids against member ids).
        $knnKey = "recs_members_{$tenantId}_{$userId}";
        $knnRecs = Cache::get($knnKey);
        if ($knnRecs !== null && !empty($knnRecs)) {
            $knnSet = array_flip($knnRecs);
            foreach ($matches as &$match) {
                if (isset($knnSet[$match['user_id'] ?? 0])) {
                    $match['match_score'] = min(100.0, (float) ($match['match_score'] ?? 0) + 6);
                }
            }
            unset($match);
        }

        usort($matches, fn ($a, $b) => $b['match_score'] <=> $a['match_score']);

        return array_slice($matches, 0, $limit);
    }

    /**
     * Get "hot" matches (high score + close proximity).
     */
    public function getHotMatches(int $userId, int $limit = 5): array
    {
        $config = $this->getConfig();
        $hotThreshold = $config['hot_match_threshold'];

        $matches = $this->findMatchesForUser($userId, [
            'max_distance' => self::PROXIMITY_LOCAL,
            'min_score' => $hotThreshold,
            'limit' => $limit,
        ]);

        return array_filter($matches, fn ($m) => $m['match_score'] >= $hotThreshold);
    }

    /**
     * Get mutual matches (both parties can benefit).
     */
    public function getMutualMatches(int $userId, int $limit = 10): array
    {
        $matches = $this->findMatchesForUser($userId, ['limit' => 50]);
        $mutual = array_filter($matches, fn ($m) => $m['match_type'] === 'mutual');
        usort($mutual, fn ($a, $b) => $b['match_score'] <=> $a['match_score']);

        return array_slice($mutual, 0, $limit);
    }

    // =========================================================================
    // SCORING
    // =========================================================================

    /**
     * Calculate match score between user and a candidate listing.
     */
    public function calculateMatchScore(
        array $userData,
        array $userListings,
        array $myListing,
        array $candidateListing
    ): array {
        $config = $this->getConfig();
        $weights = $config['weights'];

        $scores = [
            'category' => 0, 'skill' => 0, 'proximity' => 0,
            'freshness' => 0, 'reciprocity' => 0, 'quality' => 0,
        ];
        $reasons = [];

        // 1. Category
        $scores['category'] = $this->calculateCategoryScore($myListing, $candidateListing);
        if ($scores['category'] >= 0.8) {
            $reasons[] = "Same category: " . ($candidateListing['category_name'] ?? 'General');
        }

        // 2. Skill
        $scores['skill'] = $this->calculateSkillScore($userData, $myListing, $candidateListing);
        if ($scores['skill'] >= 0.5) {
            $reasons[] = "Skills match your expertise";
        }

        // 3. Proximity — service_type aware. Remote listings score a fixed 0.9
        // (distance is irrelevant to them); unknown distance is neutral for
        // hybrid and a heavy penalty for physical listings.
        $serviceType = $candidateListing['service_type'] ?? 'physical_only';
        $distance = $this->calculateDistance(
            $userData['latitude'] ?? 0, $userData['longitude'] ?? 0,
            $candidateListing['latitude'] ?? $candidateListing['author_latitude'] ?? 0,
            $candidateListing['longitude'] ?? $candidateListing['author_longitude'] ?? 0
        );
        $hasDistance = $distance < PHP_FLOAT_MAX;

        if ($serviceType === 'remote_only') {
            $scores['proximity'] = 0.9;
            $reasons[] = "Can be done remotely";
        } elseif (!$hasDistance) {
            $scores['proximity'] = ($serviceType === 'hybrid') ? 0.5 : 0.05;
        } else {
            $scores['proximity'] = $this->calculateProximityScore($distance);
            if ($distance <= self::PROXIMITY_WALKING) {
                $reasons[] = sprintf("Very close: %.1f km away", $distance);
            } elseif ($distance <= self::PROXIMITY_LOCAL) {
                $reasons[] = sprintf("Nearby: %.1f km away", $distance);
            }
        }

        // 4. Freshness
        $scores['freshness'] = $this->calculateFreshnessScore($candidateListing['created_at'] ?? null);
        if ($scores['freshness'] >= 0.9) {
            $reasons[] = "Posted recently";
        }

        // 5. Reciprocity
        $reciprocityResult = $this->calculateReciprocityScore($userListings, $candidateListing);
        $scores['reciprocity'] = $reciprocityResult['score'];
        $matchType = $reciprocityResult['type'];
        if ($matchType === 'mutual') {
            $reasons[] = "Mutual exchange possible!";
        }

        // 6. Quality
        $scores['quality'] = $this->calculateQualityScore($candidateListing);
        if ($scores['quality'] >= 0.8) {
            $reasons[] = "Highly rated member";
        }

        // Weighted final score (0-100)
        $finalScore = 0;
        foreach ($scores as $key => $value) {
            $finalScore += $value * $weights[$key];
        }
        $finalScore = round($finalScore * 100, 1);

        return [
            'score' => $finalScore,
            'reasons' => $reasons,
            'breakdown' => $scores,
            // NULL when unresolvable — the old PHP_FLOAT_MAX leaked a 1.8e308
            // "distance" into the API and cache layers.
            'distance' => $hasDistance ? round($distance, 1) : null,
            'type' => $matchType,
            'service_type' => $serviceType,
        ];
    }

    // =========================================================================
    // SCORING COMPONENT METHODS
    // =========================================================================

    private function fetchCategory(int $categoryId): ?array
    {
        if (isset($this->categoryCache[$categoryId])) {
            return $this->categoryCache[$categoryId];
        }

        try {
            $row = DB::table('categories')
                ->where('id', $categoryId)
                ->where('tenant_id', TenantContext::getId())
                ->select('name', 'parent_id')
                ->first();

            if ($row) {
                $this->categoryCache[$categoryId] = [
                    'name' => (string) $row->name,
                    'parent_id' => isset($row->parent_id) ? (int) $row->parent_id : null,
                ];
                return $this->categoryCache[$categoryId];
            }
        } catch (\Exception $e) {
            // DB unavailable
        }

        return null;
    }

    private function calculateCategoryScore(array $myListing, array $candidate): float
    {
        $myId = $myListing['category_id'] ?? null;
        $candidateId = $candidate['category_id'] ?? null;

        if ($myId && $myId === $candidateId) {
            return 1.0;
        }
        if (!$myId || !$candidateId) {
            return 0.15;
        }

        $myCat = $this->fetchCategory((int) $myId);
        $candidateCat = $this->fetchCategory((int) $candidateId);

        if ($myCat !== null && $candidateCat !== null &&
            $myCat['parent_id'] !== null && $myCat['parent_id'] === $candidateCat['parent_id']) {
            return 0.7;
        }

        if ($myCat !== null && $candidateCat !== null) {
            similar_text($myCat['name'], $candidateCat['name'], $pct);
            return (float) max(0.15, $pct / 100 * 0.8);
        }

        return 0.15;
    }

    private function stemWord(string $word): string
    {
        $len = strlen($word);
        if ($len > 6 && substr($word, -3) === 'ing') return substr($word, 0, $len - 3);
        if ($len > 5 && substr($word, -2) === 'ed') return substr($word, 0, $len - 2);
        if ($len > 5 && substr($word, -2) === 'er') return substr($word, 0, $len - 2);
        if ($len > 4 && substr($word, -2) === 'es') return substr($word, 0, $len - 2);
        if ($len > 4 && substr($word, -1) === 's' && substr($word, -2) !== 'ss') return substr($word, 0, $len - 1);
        return $word;
    }

    private function calculateSkillScore(array $userData, array $myListing, array $candidate): float
    {
        $proficiencyKeys = $userData['skills_proficiency_keys'] ?? null;
        $userSkills = $proficiencyKeys !== null
            ? $proficiencyKeys
            : $this->extractKeywords($userData['skills'] ?? '');

        $myKeywords = $this->extractKeywords($myListing['title'] . ' ' . ($myListing['description'] ?? ''));
        $candidateKeywords = $this->extractKeywords($candidate['title'] . ' ' . ($candidate['description'] ?? ''));

        $allUserKeywords = array_unique(array_merge($userSkills, $myKeywords));

        if (empty($allUserKeywords) || empty($candidateKeywords)) {
            return 0.4;
        }

        $matches = array_intersect($allUserKeywords, $candidateKeywords);
        $union = count(array_unique(array_merge($allUserKeywords, $candidateKeywords)));
        $jaccard = $union > 0 ? count($matches) / $union : 0;

        $skillsWeighted = $userData['skills_weighted'] ?? [];
        if (!empty($skillsWeighted) && !empty($matches)) {
            $totalWeight = 0.0;
            foreach ($matches as $m) {
                $totalWeight += $skillsWeighted[$m] ?? 1.0;
            }
            $avgWeight = $totalWeight / count($matches);
            $jaccard *= min(1.4, $avgWeight);
        }

        return min(1.0, $jaccard * 1.5);
    }

    private function calculateProximityScore(float $distanceKm): float
    {
        $config = $this->getConfig();
        $prox = $config['proximity'];

        if ($distanceKm <= $prox['walking_km']) return 1.0;
        if ($distanceKm <= $prox['local_km']) {
            $ratio = ($distanceKm - $prox['walking_km']) / ($prox['local_km'] - $prox['walking_km']);
            return 1.0 - ($ratio * 0.1);
        }
        if ($distanceKm <= $prox['city_km']) {
            $ratio = ($distanceKm - $prox['local_km']) / ($prox['city_km'] - $prox['local_km']);
            return 0.9 - ($ratio * 0.2);
        }
        if ($distanceKm <= $prox['regional_km']) {
            $ratio = ($distanceKm - $prox['city_km']) / ($prox['regional_km'] - $prox['city_km']);
            return 0.7 - ($ratio * 0.2);
        }
        if ($distanceKm <= $prox['max_km']) {
            $ratio = ($distanceKm - $prox['regional_km']) / ($prox['max_km'] - $prox['regional_km']);
            return 0.5 - ($ratio * 0.4);
        }

        return max(0.05, 0.1 * ($prox['max_km'] / $distanceKm));
    }

    private function calculateFreshnessScore(?string $createdAt): float
    {
        if (!$createdAt) return 0.5;

        $created = strtotime($createdAt);
        $ageHours = (time() - $created) / 3600;

        if ($ageHours <= self::FRESHNESS_FULL_HOURS) return 1.0;

        $halfLifeHours = self::FRESHNESS_HALF_LIFE_DAYS * 24;
        $decayFactor = pow(0.5, ($ageHours - self::FRESHNESS_FULL_HOURS) / $halfLifeHours);

        return max(self::FRESHNESS_MINIMUM, $decayFactor);
    }

    private function calculateReciprocityScore(array $userListings, array $candidate): array
    {
        $candidateOwnerId = $candidate['user_id'];
        $tenantId = TenantContext::getId();

        $candidateListings = DB::select(
            "SELECT type, category_id, title FROM listings
             WHERE user_id = ? AND tenant_id = ? AND status = 'active'",
            [$candidateOwnerId, $tenantId]
        );
        $candidateListings = array_map(fn ($r) => (array) $r, $candidateListings);

        if (empty($candidateListings)) {
            return ['score' => 0.3, 'type' => 'one_way'];
        }

        $userOfferCats = array_column(array_filter($userListings, fn ($l) => $l['type'] === 'offer'), 'category_id');
        $userRequestCats = array_column(array_filter($userListings, fn ($l) => $l['type'] === 'request'), 'category_id');
        $candOfferCats = array_column(array_filter($candidateListings, fn ($l) => $l['type'] === 'offer'), 'category_id');
        $candRequestCats = array_column(array_filter($candidateListings, fn ($l) => $l['type'] === 'request'), 'category_id');

        $candidateNeedsUserOffer = !empty(array_intersect($userOfferCats, $candRequestCats));
        $userNeedsCandidateOffer = !empty(array_intersect($candOfferCats, $userRequestCats));

        if ($candidateNeedsUserOffer && $userNeedsCandidateOffer) {
            return ['score' => 1.0, 'type' => 'mutual'];
        }
        if ($candidateNeedsUserOffer || $userNeedsCandidateOffer) {
            return ['score' => 0.7, 'type' => 'potential'];
        }

        return ['score' => 0.4, 'type' => 'one_way'];
    }

    private function calculateQualityScore(array $candidate): float
    {
        $score = 0.5;
        $descLength = strlen($candidate['description'] ?? '');
        if ($descLength >= self::QUALITY_MIN_DESCRIPTION) $score += 0.1;
        if ($descLength >= self::QUALITY_MIN_DESCRIPTION * 2) $score += 0.1;
        if (!empty($candidate['image_url'])) $score += 0.1;
        if (!empty($candidate['author_verified']) || !empty($candidate['is_verified'])) $score += 0.1;
        $rating = $candidate['author_rating'] ?? $candidate['avg_rating'] ?? 0;
        if ($rating >= self::QUALITY_RATING_THRESHOLD) $score += 0.1;

        return min(1.0, $score);
    }

    // =========================================================================
    // UTILITY METHODS
    // =========================================================================

    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        if (!$lat1 || !$lon1 || !$lat2 || !$lon2) return PHP_FLOAT_MAX;

        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Extract keywords from text with Porter stemming.
     */
    public function extractKeywords(string $text): array
    {
        $text = strtolower($text);
        $stopWords = [
            'the','a','an','and','or','but','in','on','at','to','for','of','with','by','from',
            'is','are','was','were','be','been','being','have','has','had','do','does','did',
            'will','would','could','should','may','might','must','shall','can','need','i','you',
            'he','she','it','we','they','my','your','his','her','its','our','their','this',
            'that','these','those','am','help','looking','need','want','offer','request',
        ];

        preg_match_all('/\b[a-z]{3,}\b/', $text, $matches);
        $words = $matches[0] ?? [];

        static $twoCharDomainTerms = [
            'ai','ml','ux','ui','go','vr','ar','it','hr','pr','qa','db','uk','eu','us','r',
        ];
        preg_match_all('/\b[a-z]{1,2}\b/', $text, $shortMatches);
        foreach ($shortMatches[0] ?? [] as $short) {
            if (in_array($short, $twoCharDomainTerms, true)) {
                $words[] = $short;
            }
        }

        $keywords = array_diff($words, $stopWords);
        $keywords = array_map([$this, 'stemWord'], $keywords);
        $keywords = array_unique($keywords);

        return array_values($keywords);
    }

    // =========================================================================
    // DATA LOADING
    // =========================================================================

    private function getUserData(int $userId): ?array
    {
        if (isset($this->userDataCache[$userId])) {
            return $this->userDataCache[$userId];
        }

        $tenantId = TenantContext::getId();

        $rows = DB::select(
            "SELECT u.*,
                    COALESCE(u.latitude, 0) as latitude,
                    COALESCE(u.longitude, 0) as longitude,
                    (SELECT AVG(rating) FROM reviews WHERE receiver_id = u.id AND tenant_id = u.tenant_id) as avg_rating,
                    (SELECT COUNT(*) FROM transactions WHERE (sender_id = u.id OR receiver_id = u.id) AND tenant_id = u.tenant_id AND status = 'completed') as transaction_count
             FROM users u
             WHERE u.id = ? AND u.tenant_id = ?",
            [$userId, $tenantId]
        );

        $user = !empty($rows) ? (array) $rows[0] : null;

        if ($user) {
            // Enrich with proficiency-weighted skills
            try {
                $weighted = \App\Services\SkillTaxonomyService::getProficiencyWeightedSkills($userId, $tenantId);
                $user['skills_weighted'] = $weighted;
                if (!empty($weighted)) {
                    $user['skills_proficiency_keys'] = array_keys($weighted);
                }
            } catch (\Throwable $e) {
                // SkillTaxonomyService error — continue without weighted skills
            }

            $this->userDataCache[$userId] = $user;
        }

        return $user;
    }

    private function getUserListings(int $userId): array
    {
        $tenantId = TenantContext::getId();

        return array_map(
            fn ($row) => (array) $row,
            DB::select(
                "SELECT l.*, c.name as category_name
                 FROM listings l
                 LEFT JOIN categories c ON l.category_id = c.id
                 WHERE l.user_id = ? AND l.tenant_id = ? AND l.status = 'active'
                 ORDER BY l.created_at DESC LIMIT 10",
                [$userId, $tenantId]
            )
        );
    }

    private function getUserPreferences(int $userId): array
    {
        $tenantId = TenantContext::getId();
        $config = $this->getConfig();

        try {
            $rows = DB::select(
                "SELECT * FROM match_preferences WHERE user_id = ? AND tenant_id = ?",
                [$userId, $tenantId]
            );

            if (!empty($rows)) {
                $prefs = (array) $rows[0];
                $prefs['categories'] = $prefs['categories'] ? json_decode($prefs['categories'], true) : null;
                return $prefs;
            }
        } catch (\Exception $e) {
            // Table might not exist
        }

        return [
            'max_distance_km' => $config['max_distance_km'],
            'min_match_score' => $config['min_match_score'],
            'notification_frequency' => 'fortnightly',
            'categories' => null,
        ];
    }

    private function getColdStartMatches(int $userId, array $userData, float $maxDistance, int $limit): array
    {
        $tenantId = TenantContext::getId();
        $config = $this->getConfig();

        // Same hard gates as the main path — the old cold-start SQL fell back
        // to newest-listings-tenant-wide for users without coordinates and
        // labelled every result "nearby".
        $results = $this->candidateRetriever->retrieveColdStart(
            $tenantId,
            $userId,
            $this->coordOrNull($userData['latitude'] ?? null),
            $this->coordOrNull($userData['longitude'] ?? null),
            $maxDistance,
            $config['gates'],
            $limit
        );

        // Safeguarding filter — same rule as the main match path. Applied after
        // LIMIT here (cold-start users typically request small result sets, so
        // we accept the occasional short result rather than expanding LIMIT).
        $results = $this->filterCandidatesByVettingRequirements($results, $userId);

        $localKm = (float) ($config['proximity']['local_km'] ?? self::PROXIMITY_LOCAL);

        foreach ($results as &$listing) {
            $coldScore = 35;
            $descLen = strlen($listing['description'] ?? '');
            if ($descLen >= self::QUALITY_MIN_DESCRIPTION) $coldScore += 10;
            if ($descLen >= self::QUALITY_MIN_DESCRIPTION * 2) $coldScore += 5;
            if (!empty($listing['image_url'])) $coldScore += 10;
            if (!empty($listing['author_verified']) || !empty($listing['is_verified'])) $coldScore += 5;

            $distance = isset($listing['distance_km']) && is_numeric($listing['distance_km'])
                ? (float) $listing['distance_km'] : null;

            // Honest reasons: only claim "nearby" when the distance is known.
            if (($listing['service_type'] ?? 'physical_only') === 'remote_only') {
                $reason = 'Remote-friendly listing you might like';
            } elseif ($distance !== null && $distance <= $localKm) {
                $reason = 'Nearby listing that might interest you';
            } else {
                $reason = 'Recently posted in your community';
            }

            $listing['match_score'] = min(65, $coldScore);
            $listing['match_reasons'] = [$reason];
            $listing['match_type'] = 'cold_start';
            $listing['distance_km'] = $distance;
        }

        return $results;
    }

    /**
     * Normalise a stored coordinate: NULL/0 (the COALESCE sentinel for missing
     * coords) becomes null so the retriever can apply the degraded mode.
     */
    private function coordOrNull(mixed $value): ?float
    {
        $f = (float) ($value ?? 0);
        return $f != 0.0 ? $f : null;
    }

    // =========================================================================
    // SAFEGUARDING FILTER
    // =========================================================================

    /**
     * Remove candidates whose owners require safeguarding vetting types the
     * searcher does not hold. Used by both the main match path and cold-start.
     *
     * Staff roles (admin, tenant_admin, broker, super_admin) bypass this —
     * they need the full pool for coordinator assignments and safeguarding
     * oversight. Discovery bypass only; the downstream messaging, match
     * submission and group exchange gates still enforce vetting for staff.
     *
     * Fail-open on error: if the filter itself errors, returns the candidate
     * list unchanged. Downstream gates (MessageService, MatchApprovalWorkflow,
     * GroupExchangeService) remain fail-closed, so a flagged user still can't
     * be interacted with even if discovery briefly leaks them during a blip.
     *
     * @param array<int, array> $candidates
     * @param int $searcherId The user ID performing the discovery/match request
     * @return array<int, array>
     */
    public function filterCandidatesByVettingRequirements(array $candidates, int $searcherId): array
    {
        if (empty($candidates) || $searcherId <= 0) {
            return $candidates;
        }

        // Staff bypass — admins/brokers see the full pool for coordination.
        try {
            if ($this->vettingService->isSafeguardingStaff($searcherId)) {
                return $candidates;
            }
        } catch (\Throwable $e) {
            // Staff lookup failure falls through to apply the filter — safer
            // default (treat as non-staff).
            Log::debug('[SmartMatchingEngine] staff bypass lookup failed', [
                'error' => $e->getMessage(),
                'searcher_id' => $searcherId,
            ]);
        }

        try {
            // Collect unique candidate owner IDs.
            $ownerIds = [];
            foreach ($candidates as $cand) {
                $owner = (int) ($cand['user_id'] ?? 0);
                if ($owner > 0 && $owner !== $searcherId) {
                    $ownerIds[$owner] = true;
                }
            }
            if (empty($ownerIds)) {
                return $candidates;
            }

            $requiredByUser = SafeguardingTriggerService::getRequiredVettingTypesForUsers(
                array_keys($ownerIds),
                TenantContext::getId()
            );

            // Fast path: if nobody in this batch requires vetting, skip filtering.
            $anyRequiresVetting = false;
            foreach ($requiredByUser as $types) {
                if (!empty($types)) {
                    $anyRequiresVetting = true;
                    break;
                }
            }
            if (!$anyRequiresVetting) {
                return $candidates;
            }

            // Cache searcher vetting checks by type-set signature so we don't
            // re-query for each candidate when many share the same requirements.
            $vettingCheckCache = [];
            $filtered = [];

            foreach ($candidates as $candidate) {
                $owner = (int) ($candidate['user_id'] ?? 0);
                $requiredTypes = $requiredByUser[$owner] ?? [];

                if (empty($requiredTypes)) {
                    $filtered[] = $candidate;
                    continue;
                }

                $sortedTypes = $requiredTypes;
                sort($sortedTypes);
                $cacheKey = implode('|', $sortedTypes);

                if (!array_key_exists($cacheKey, $vettingCheckCache)) {
                    $vettingCheckCache[$cacheKey] = $this->vettingService
                        ->userHasAllValidVettings($searcherId, $sortedTypes);
                }

                if ($vettingCheckCache[$cacheKey]) {
                    $filtered[] = $candidate;
                }
                // else: silently drop the candidate from the searcher's view
            }

            return $filtered;
        } catch (\Throwable $e) {
            Log::warning('[SmartMatchingEngine] safeguarding filter failed (returning unfiltered)', [
                'error' => $e->getMessage(),
                'searcher_id' => $searcherId,
                'candidate_count' => count($candidates),
            ]);
            return $candidates;
        }
    }
}
