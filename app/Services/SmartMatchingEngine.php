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
 * SmartMatchingEngine — Multi-dimensional matching algorithm.
 *
 * 6-signal scoring pipeline:
 *   1. Category Match (25%) — same/related category
 *   2. Skill Complementarity (20%) — Jaccard + proficiency weighting
 *   3. Proximity (25%) — Haversine with piecewise linear decay
 *   4. Temporal Relevance (10%) — exponential freshness decay
 *   5. Reciprocity Potential (15%) — mutual exchange opportunity
 *   6. Quality Signals (5%) — description, images, ratings
 *
 * Post-scoring boosts: semantic embedding, KNN member recs, ML feedback.
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
    private ?bool $userBlocksTableExistsCache = null;
    private array $categoryCache = [];

    public function __construct(
        private readonly EmbeddingService $embeddingService,
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
        ];

        $tenantId = TenantContext::getId();

        try {
            $configJson = DB::table('tenants')->where('id', $tenantId)->value('configuration');
            if ($configJson) {
                $configArr = json_decode($configJson, true);
                if (is_array($configArr) && isset($configArr['algorithms']['smart_matching'])) {
                    $this->configCache = array_merge($defaults, $configArr['algorithms']['smart_matching']);
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
        $this->userBlocksTableExistsCache = null;
        $this->categoryCache = [];
    }

    // =========================================================================
    // MAIN PUBLIC METHODS
    // =========================================================================

    /**
     * Find matches for a user based on their listings.
     */
    public function findMatchesForUser(int $userId, array $options = []): array
    {
        $tenantId = TenantContext::getId();
        $config = $this->getConfig();

        $preferences = $this->getUserPreferences($userId);
        $maxDistance = $options['max_distance'] ?? $preferences['max_distance_km'] ?? $config['max_distance_km'];
        $minScore = $options['min_score'] ?? $preferences['min_match_score'] ?? $config['min_match_score'];
        $limit = $options['limit'] ?? 20;
        $categoryFilter = $options['categories'] ?? $preferences['categories'] ?? null;

        $userData = $this->getUserData($userId);
        if (!$userData) {
            return [];
        }

        $userListings = $this->getUserListings($userId);
        if (empty($userListings)) {
            return $this->getColdStartMatches($userId, $userData, $maxDistance, $limit);
        }

        $matches = [];
        $seenIds = [];

        foreach ($userListings as $myListing) {
            $targetType = ($myListing['type'] === 'offer') ? 'request' : 'offer';

            $candidates = $this->getCandidateListings(
                $tenantId, $userId, $targetType, $myListing['category_id'],
                $categoryFilter, $userData['latitude'], $userData['longitude'], $maxDistance
            );

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

        // Semantic embedding boost
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
                        $match['match_score'] = min(1.0, $match['match_score'] * 1.3);
                    }
                }
                unset($match);
            }
        }

        // KNN member recommendation boost
        $knnKey = "recs_members_{$tenantId}_{$userId}";
        $knnRecs = Cache::get($knnKey);
        if ($knnRecs !== null && !empty($knnRecs)) {
            $knnSet = array_flip($knnRecs);
            foreach ($matches as &$match) {
                if (isset($knnSet[$match['id'] ?? 0])) {
                    $match['match_score'] = min(1.0, ($match['match_score'] ?? 0) * 1.4);
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

        // 3. Proximity
        $distance = $this->calculateDistance(
            $userData['latitude'] ?? 0, $userData['longitude'] ?? 0,
            $candidateListing['latitude'] ?? $candidateListing['author_latitude'] ?? 0,
            $candidateListing['longitude'] ?? $candidateListing['author_longitude'] ?? 0
        );
        $scores['proximity'] = $this->calculateProximityScore($distance);
        if ($distance <= self::PROXIMITY_WALKING) {
            $reasons[] = sprintf("Very close: %.1f km away", $distance);
        } elseif ($distance <= self::PROXIMITY_LOCAL) {
            $reasons[] = sprintf("Nearby: %.1f km away", $distance);
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
            'distance' => round($distance, 1),
            'type' => $matchType,
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

    private function userBlocksTableExists(): bool
    {
        if ($this->userBlocksTableExistsCache !== null) {
            return $this->userBlocksTableExistsCache;
        }

        try {
            DB::selectOne("SELECT 1 FROM user_blocks LIMIT 1");
            $this->userBlocksTableExistsCache = true;
        } catch (\Exception $e) {
            $this->userBlocksTableExistsCache = false;
        }

        return $this->userBlocksTableExistsCache;
    }

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

    private function getCandidateListings(
        int $tenantId, int $excludeUserId, string $targetType, ?int $categoryId,
        ?array $categoryFilter, ?float $userLat, ?float $userLon, float $maxDistance
    ): array {
        $params = [$tenantId, $targetType, $excludeUserId];

        $sql = "SELECT l.*,
                       u.first_name, u.last_name, u.avatar_url, u.location as author_location,
                       u.latitude as author_latitude, u.longitude as author_longitude,
                       u.is_verified as author_verified,
                       TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) as user_name,
                       (SELECT AVG(rating) FROM reviews WHERE receiver_id = u.id AND tenant_id = u.tenant_id) as author_rating,
                       c.name as category_name, c.color as category_color";

        if ($userLat && $userLon) {
            $sql .= ",
                (6371 * acos(
                    cos(radians(?)) * cos(radians(COALESCE(l.latitude, u.latitude, 0))) *
                    cos(radians(COALESCE(l.longitude, u.longitude, 0)) - radians(?)) +
                    sin(radians(?)) * sin(radians(COALESCE(l.latitude, u.latitude, 0)))
                )) as distance_km";
            $params = array_merge([$userLat, $userLon, $userLat], $params);
        }

        $sql .= " FROM listings l
                  JOIN users u ON l.user_id = u.id
                  LEFT JOIN categories c ON l.category_id = c.id
                  WHERE l.tenant_id = ? AND l.type = ? AND l.status = 'active' AND l.user_id != ?
                  AND u.status NOT IN ('banned', 'suspended')";

        if ($this->userBlocksTableExists()) {
            $sql .= "
                  AND l.user_id NOT IN (SELECT blocked_user_id FROM user_blocks WHERE user_id = ?)
                  AND l.user_id NOT IN (SELECT user_id FROM user_blocks WHERE blocked_user_id = ?)";
            $params[] = $excludeUserId;
            $params[] = $excludeUserId;
        }

        if ($categoryId) {
            $sql .= " AND l.category_id = ?";
            $params[] = $categoryId;
        } elseif ($categoryFilter && !empty($categoryFilter)) {
            $placeholders = implode(',', array_fill(0, count($categoryFilter), '?'));
            $sql .= " AND l.category_id IN ($placeholders)";
            $params = array_merge($params, $categoryFilter);
        }

        if ($userLat && $userLon) {
            $sql .= " HAVING distance_km <= ?";
            $params[] = $maxDistance;
            $sql .= " ORDER BY distance_km ASC";
        } else {
            $sql .= " ORDER BY l.created_at DESC";
        }

        $sql .= " LIMIT 50";

        return array_map(fn ($row) => (array) $row, DB::select($sql, $params));
    }

    private function getColdStartMatches(int $userId, array $userData, float $maxDistance, int $limit): array
    {
        $tenantId = TenantContext::getId();
        $params = [$tenantId, $userId];

        $sql = "SELECT l.*,
                       u.first_name, u.last_name, u.avatar_url, u.location as author_location,
                       u.latitude as author_latitude, u.longitude as author_longitude,
                       TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) as user_name,
                       c.name as category_name, c.color as category_color";

        if ($userData['latitude'] && $userData['longitude']) {
            $sql .= ",
                (6371 * acos(
                    cos(radians(?)) * cos(radians(COALESCE(l.latitude, u.latitude, 0))) *
                    cos(radians(COALESCE(l.longitude, u.longitude, 0)) - radians(?)) +
                    sin(radians(?)) * sin(radians(COALESCE(l.latitude, u.latitude, 0)))
                )) as distance_km";
            $params = array_merge([$userData['latitude'], $userData['longitude'], $userData['latitude']], $params);
        }

        $sql .= " FROM listings l
                  JOIN users u ON l.user_id = u.id
                  LEFT JOIN categories c ON l.category_id = c.id
                  WHERE l.tenant_id = ? AND l.status = 'active' AND l.user_id != ?
                  AND u.status NOT IN ('banned', 'suspended')";

        if ($this->userBlocksTableExists()) {
            $sql .= "
                  AND l.user_id NOT IN (SELECT blocked_user_id FROM user_blocks WHERE user_id = ?)
                  AND l.user_id NOT IN (SELECT user_id FROM user_blocks WHERE blocked_user_id = ?)";
            $params[] = $userId;
            $params[] = $userId;
        }

        if ($userData['latitude'] && $userData['longitude']) {
            $sql .= " HAVING distance_km <= ?";
            $params[] = $maxDistance;
            $sql .= " ORDER BY distance_km ASC, l.created_at DESC";
        } else {
            $sql .= " ORDER BY l.created_at DESC";
        }

        $sql .= " LIMIT ?";
        $params[] = $limit;

        $results = array_map(fn ($row) => (array) $row, DB::select($sql, $params));

        foreach ($results as &$listing) {
            $coldScore = 35;
            $descLen = strlen($listing['description'] ?? '');
            if ($descLen >= self::QUALITY_MIN_DESCRIPTION) $coldScore += 10;
            if ($descLen >= self::QUALITY_MIN_DESCRIPTION * 2) $coldScore += 5;
            if (!empty($listing['image_url'])) $coldScore += 10;
            if (!empty($listing['author_verified']) || !empty($listing['is_verified'])) $coldScore += 5;

            $listing['match_score'] = min(65, $coldScore);
            $listing['match_reasons'] = ['Nearby listing that might interest you'];
            $listing['match_type'] = 'cold_start';
            $listing['distance_km'] = $listing['distance_km'] ?? null;
        }

        return $results;
    }
}
