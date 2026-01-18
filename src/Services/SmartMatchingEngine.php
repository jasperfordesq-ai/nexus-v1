<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * SmartMatchingEngine - Multiverse-Class Matching Algorithm
 *
 * Intelligent matching for offers/requests with multi-dimensional scoring:
 *
 * 1. CATEGORY MATCH (25%)
 *    - Same category = high intent alignment
 *    - Related categories get partial score
 *
 * 2. SKILL COMPLEMENTARITY (20%)
 *    - User skills match listing requirements
 *    - Keyword extraction and matching
 *
 * 3. PROXIMITY SCORE (25%)
 *    - Distance-based using Haversine formula
 *    - Configurable radius with decay
 *
 * 4. TEMPORAL RELEVANCE (10%)
 *    - Freshness of listing
 *    - Availability window matching
 *
 * 5. RECIPROCITY POTENTIAL (15%)
 *    - Mutual exchange opportunity
 *    - Both parties can benefit
 *
 * 6. QUALITY SIGNALS (5%)
 *    - Profile completeness
 *    - Ratings and verification
 *
 * Final Score = Weighted sum × 100 (0-100 scale)
 */
class SmartMatchingEngine
{
    // =========================================================================
    // SCORING WEIGHTS (Must sum to 1.0)
    // =========================================================================
    const WEIGHT_CATEGORY = 0.25;
    const WEIGHT_SKILL = 0.20;
    const WEIGHT_PROXIMITY = 0.25;
    const WEIGHT_FRESHNESS = 0.10;
    const WEIGHT_RECIPROCITY = 0.15;
    const WEIGHT_QUALITY = 0.05;

    // =========================================================================
    // PROXIMITY CONFIGURATION
    // =========================================================================
    const PROXIMITY_WALKING = 5;        // 5km = walking distance (1.0 score)
    const PROXIMITY_LOCAL = 15;         // 15km = local area (0.9 score)
    const PROXIMITY_CITY = 30;          // 30km = same city (0.7 score)
    const PROXIMITY_REGIONAL = 50;      // 50km = regional (0.5 score)
    const PROXIMITY_MAX = 100;          // Beyond 100km = very low score

    // =========================================================================
    // FRESHNESS CONFIGURATION
    // =========================================================================
    const FRESHNESS_FULL_HOURS = 24;    // Full freshness for 24 hours
    const FRESHNESS_HALF_LIFE_DAYS = 14; // Half-life of 14 days
    const FRESHNESS_MINIMUM = 0.3;      // Minimum 30%

    // =========================================================================
    // QUALITY THRESHOLDS
    // =========================================================================
    const QUALITY_MIN_DESCRIPTION = 50;
    const QUALITY_IMAGE_BOOST = 1.2;
    const QUALITY_VERIFIED_BOOST = 1.3;
    const QUALITY_RATING_THRESHOLD = 4.0;

    // =========================================================================
    // CACHE
    // =========================================================================
    private static ?array $userDataCache = [];
    private static ?array $configCache = null;
    private static ?bool $userBlocksTableExistsCache = null;

    /**
     * Get configuration from tenant settings
     */
    public static function getConfig(): array
    {
        if (self::$configCache !== null) {
            return self::$configCache;
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

        try {
            $tenantId = TenantContext::getId();
            $configJson = Database::query(
                "SELECT configuration FROM tenants WHERE id = ?",
                [$tenantId]
            )->fetchColumn();

            if ($configJson) {
                $configArr = json_decode($configJson, true);
                if (is_array($configArr) && isset($configArr['algorithms']['smart_matching'])) {
                    self::$configCache = array_merge($defaults, $configArr['algorithms']['smart_matching']);
                    return self::$configCache;
                }
            }
        } catch (\Exception $e) {
            // Fall back to defaults
        }

        self::$configCache = $defaults;
        return self::$configCache;
    }

    /**
     * Clear cached data
     */
    public static function clearCache(): void
    {
        self::$configCache = null;
        self::$userDataCache = [];
        self::$userBlocksTableExistsCache = null;
    }

    // =========================================================================
    // MAIN PUBLIC METHODS
    // =========================================================================

    /**
     * Find matches for a user based on their listings
     *
     * @param int $userId The user to find matches for
     * @param array $options Optional filters (max_distance, min_score, categories, limit)
     * @return array Array of matches with scores and reasons
     */
    public static function findMatchesForUser(int $userId, array $options = []): array
    {
        $tenantId = TenantContext::getId();
        $config = self::getConfig();

        // Get user preferences (or use defaults/options)
        $preferences = self::getUserPreferences($userId);
        $maxDistance = $options['max_distance'] ?? $preferences['max_distance_km'] ?? $config['max_distance_km'];
        $minScore = $options['min_score'] ?? $preferences['min_match_score'] ?? $config['min_match_score'];
        $limit = $options['limit'] ?? 20;
        $categoryFilter = $options['categories'] ?? $preferences['categories'] ?? null;

        // Get user's data
        $userData = self::getUserData($userId);
        if (!$userData) {
            return [];
        }

        // Get user's active listings to understand what they offer/need
        $userListings = self::getUserListings($userId);
        if (empty($userListings)) {
            // Cold start: User has no listings, show top quality nearby listings
            return self::getColdStartMatches($userId, $userData, $maxDistance, $limit);
        }

        // Build the matching query
        $matches = [];
        $seenIds = [];

        foreach ($userListings as $myListing) {
            // Look for opposite type (if I offer, find requests; if I request, find offers)
            $targetType = ($myListing['type'] === 'offer') ? 'request' : 'offer';

            // Get potential matches
            $candidates = self::getCandidateListings(
                $tenantId,
                $userId,
                $targetType,
                $myListing['category_id'],
                $categoryFilter,
                $userData['latitude'],
                $userData['longitude'],
                $maxDistance
            );

            foreach ($candidates as $candidate) {
                if (in_array($candidate['id'], $seenIds)) {
                    continue;
                }

                // Calculate comprehensive match score
                $matchResult = self::calculateMatchScore(
                    $userData,
                    $userListings,
                    $myListing,
                    $candidate
                );

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

        // Sort by score descending
        usort($matches, fn($a, $b) => $b['match_score'] <=> $a['match_score']);

        // Limit results
        return array_slice($matches, 0, $limit);
    }

    /**
     * Get "hot" matches (high score + close proximity)
     */
    public static function getHotMatches(int $userId, int $limit = 5): array
    {
        $config = self::getConfig();
        $hotThreshold = $config['hot_match_threshold'];

        $matches = self::findMatchesForUser($userId, [
            'max_distance' => self::PROXIMITY_LOCAL, // 15km for hot matches
            'min_score' => $hotThreshold,
            'limit' => $limit
        ]);

        return array_filter($matches, fn($m) => $m['match_score'] >= $hotThreshold);
    }

    /**
     * Get mutual matches (both parties can benefit from each other)
     */
    public static function getMutualMatches(int $userId, int $limit = 10): array
    {
        $matches = self::findMatchesForUser($userId, ['limit' => 50]);

        // Filter to only mutual matches
        $mutual = array_filter($matches, fn($m) => $m['match_type'] === 'mutual');

        // Sort by score
        usort($mutual, fn($a, $b) => $b['match_score'] <=> $a['match_score']);

        return array_slice($mutual, 0, $limit);
    }

    /**
     * Calculate match score between user and a listing
     */
    public static function calculateMatchScore(
        array $userData,
        array $userListings,
        array $myListing,
        array $candidateListing
    ): array {
        $config = self::getConfig();
        $weights = $config['weights'];

        // Initialize scores
        $scores = [
            'category' => 0,
            'skill' => 0,
            'proximity' => 0,
            'freshness' => 0,
            'reciprocity' => 0,
            'quality' => 0,
        ];
        $reasons = [];

        // 1. CATEGORY SCORE
        $scores['category'] = self::calculateCategoryScore($myListing, $candidateListing);
        if ($scores['category'] >= 0.8) {
            $reasons[] = "Same category: " . ($candidateListing['category_name'] ?? 'General');
        }

        // 2. SKILL/KEYWORD SCORE
        $scores['skill'] = self::calculateSkillScore($userData, $myListing, $candidateListing);
        if ($scores['skill'] >= 0.5) {
            $reasons[] = "Skills match your expertise";
        }

        // 3. PROXIMITY SCORE
        $distance = self::calculateDistance(
            $userData['latitude'] ?? 0,
            $userData['longitude'] ?? 0,
            $candidateListing['latitude'] ?? $candidateListing['author_latitude'] ?? 0,
            $candidateListing['longitude'] ?? $candidateListing['author_longitude'] ?? 0
        );
        $scores['proximity'] = self::calculateProximityScore($distance);

        if ($distance <= self::PROXIMITY_WALKING) {
            $reasons[] = sprintf("Very close: %.1f km away", $distance);
        } elseif ($distance <= self::PROXIMITY_LOCAL) {
            $reasons[] = sprintf("Nearby: %.1f km away", $distance);
        }

        // 4. FRESHNESS SCORE
        $scores['freshness'] = self::calculateFreshnessScore($candidateListing['created_at'] ?? null);
        if ($scores['freshness'] >= 0.9) {
            $reasons[] = "Posted recently";
        }

        // 5. RECIPROCITY SCORE
        $reciprocityResult = self::calculateReciprocityScore($userListings, $candidateListing);
        $scores['reciprocity'] = $reciprocityResult['score'];
        $matchType = $reciprocityResult['type'];

        if ($matchType === 'mutual') {
            $reasons[] = "Mutual exchange possible!";
        }

        // 6. QUALITY SCORE
        $scores['quality'] = self::calculateQualityScore($candidateListing);
        if ($scores['quality'] >= 0.8) {
            $reasons[] = "Highly rated member";
        }

        // Calculate weighted final score
        $finalScore = 0;
        foreach ($scores as $key => $value) {
            $finalScore += $value * $weights[$key];
        }

        // Scale to 0-100
        $finalScore = round($finalScore * 100, 1);

        // 7. APPLY ML FEEDBACK BOOST (Historical Learning)
        $mlBoost = 0;
        try {
            $candidateListing['distance_km'] = $distance;
            $mlBoost = MatchLearningService::getHistoricalBoost(
                $userData['id'],
                $candidateListing
            );
            if ($mlBoost != 0) {
                $finalScore = max(0, min(100, $finalScore + $mlBoost));
                $scores['ml_boost'] = $mlBoost;
                if ($mlBoost > 0) {
                    $reasons[] = "Matches your preferences";
                }
            }
        } catch (\Exception $e) {
            // ML service not available, continue without boost
        }

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

    /**
     * Calculate category match score
     */
    private static function calculateCategoryScore(array $myListing, array $candidate): float
    {
        // Exact category match
        if ($myListing['category_id'] && $myListing['category_id'] === $candidate['category_id']) {
            return 1.0;
        }

        // TODO: Add related category matching (parent/child categories)
        // For now, no category match = 0.3 base score
        return 0.3;
    }

    /**
     * Calculate skill/keyword matching score
     */
    private static function calculateSkillScore(array $userData, array $myListing, array $candidate): float
    {
        // Extract keywords from user skills and listing
        $userSkills = self::extractKeywords($userData['skills'] ?? '');
        $myKeywords = self::extractKeywords($myListing['title'] . ' ' . ($myListing['description'] ?? ''));
        $candidateKeywords = self::extractKeywords($candidate['title'] . ' ' . ($candidate['description'] ?? ''));

        // Combine user skills with their listing keywords
        $allUserKeywords = array_unique(array_merge($userSkills, $myKeywords));

        if (empty($allUserKeywords) || empty($candidateKeywords)) {
            return 0.5; // Neutral score if no keywords
        }

        // Calculate intersection
        $matches = array_intersect($allUserKeywords, $candidateKeywords);
        $matchRatio = count($matches) / max(count($candidateKeywords), 1);

        return min(1.0, $matchRatio * 1.5); // Boost slightly, cap at 1.0
    }

    /**
     * Calculate proximity score based on distance
     */
    private static function calculateProximityScore(float $distanceKm): float
    {
        $config = self::getConfig();
        $prox = $config['proximity'];

        if ($distanceKm <= $prox['walking_km']) {
            return 1.0; // Perfect - walking distance
        }

        if ($distanceKm <= $prox['local_km']) {
            // Linear decay from 1.0 to 0.9
            $ratio = ($distanceKm - $prox['walking_km']) / ($prox['local_km'] - $prox['walking_km']);
            return 1.0 - ($ratio * 0.1);
        }

        if ($distanceKm <= $prox['city_km']) {
            // Linear decay from 0.9 to 0.7
            $ratio = ($distanceKm - $prox['local_km']) / ($prox['city_km'] - $prox['local_km']);
            return 0.9 - ($ratio * 0.2);
        }

        if ($distanceKm <= $prox['regional_km']) {
            // Linear decay from 0.7 to 0.5
            $ratio = ($distanceKm - $prox['city_km']) / ($prox['regional_km'] - $prox['city_km']);
            return 0.7 - ($ratio * 0.2);
        }

        if ($distanceKm <= $prox['max_km']) {
            // Linear decay from 0.5 to 0.1
            $ratio = ($distanceKm - $prox['regional_km']) / ($prox['max_km'] - $prox['regional_km']);
            return 0.5 - ($ratio * 0.4);
        }

        // Beyond max distance - very low score but not zero
        return max(0.05, 0.1 * ($prox['max_km'] / $distanceKm));
    }

    /**
     * Calculate freshness score based on listing age
     */
    private static function calculateFreshnessScore(?string $createdAt): float
    {
        if (!$createdAt) {
            return 0.5; // Neutral if no date
        }

        $created = strtotime($createdAt);
        $now = time();
        $ageHours = ($now - $created) / 3600;

        // Full freshness for first 24 hours
        if ($ageHours <= self::FRESHNESS_FULL_HOURS) {
            return 1.0;
        }

        // Exponential decay with half-life
        $halfLifeHours = self::FRESHNESS_HALF_LIFE_DAYS * 24;
        $decayFactor = pow(0.5, ($ageHours - self::FRESHNESS_FULL_HOURS) / $halfLifeHours);

        return max(self::FRESHNESS_MINIMUM, $decayFactor);
    }

    /**
     * Calculate reciprocity score - can both parties benefit?
     */
    private static function calculateReciprocityScore(array $userListings, array $candidate): array
    {
        $candidateOwnerId = $candidate['user_id'];
        $tenantId = TenantContext::getId();

        // Get candidate's listings
        $candidateListings = Database::query(
            "SELECT type, category_id, title FROM listings
             WHERE user_id = ? AND tenant_id = ? AND status = 'active'",
            [$candidateOwnerId, $tenantId]
        )->fetchAll();

        if (empty($candidateListings)) {
            return ['score' => 0.3, 'type' => 'one_way'];
        }

        // Check for mutual benefit
        // User offers → Candidate requests (candidate needs what user offers)
        // Candidate offers → User requests (user needs what candidate offers)

        $userOffers = array_filter($userListings, fn($l) => $l['type'] === 'offer');
        $userRequests = array_filter($userListings, fn($l) => $l['type'] === 'request');
        $candidateOffers = array_filter($candidateListings, fn($l) => $l['type'] === 'offer');
        $candidateRequests = array_filter($candidateListings, fn($l) => $l['type'] === 'request');

        $userOfferCategories = array_column($userOffers, 'category_id');
        $userRequestCategories = array_column($userRequests, 'category_id');
        $candidateOfferCategories = array_column($candidateOffers, 'category_id');
        $candidateRequestCategories = array_column($candidateRequests, 'category_id');

        // Check if candidate needs what user offers
        $candidateNeedsUserOffer = !empty(array_intersect($userOfferCategories, $candidateRequestCategories));

        // Check if user needs what candidate offers
        $userNeedsCandidateOffer = !empty(array_intersect($candidateOfferCategories, $userRequestCategories));

        if ($candidateNeedsUserOffer && $userNeedsCandidateOffer) {
            // Mutual match! Both can help each other
            return ['score' => 1.0, 'type' => 'mutual'];
        }

        if ($candidateNeedsUserOffer || $userNeedsCandidateOffer) {
            // One-way but with potential
            return ['score' => 0.7, 'type' => 'potential'];
        }

        // No direct reciprocity
        return ['score' => 0.4, 'type' => 'one_way'];
    }

    /**
     * Calculate quality score based on listing and owner quality
     */
    private static function calculateQualityScore(array $candidate): float
    {
        $score = 0.5; // Base score

        // Description length
        $descLength = strlen($candidate['description'] ?? '');
        if ($descLength >= self::QUALITY_MIN_DESCRIPTION) {
            $score += 0.1;
        }
        if ($descLength >= self::QUALITY_MIN_DESCRIPTION * 2) {
            $score += 0.1;
        }

        // Has image
        if (!empty($candidate['image_url'])) {
            $score += 0.1;
        }

        // Owner verification
        if (!empty($candidate['author_verified']) || !empty($candidate['is_verified'])) {
            $score += 0.1;
        }

        // Owner rating
        $rating = $candidate['author_rating'] ?? $candidate['avg_rating'] ?? 0;
        if ($rating >= self::QUALITY_RATING_THRESHOLD) {
            $score += 0.1;
        }

        return min(1.0, $score);
    }

    // =========================================================================
    // UTILITY METHODS
    // =========================================================================

    /**
     * Calculate distance using Haversine formula
     */
    private static function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        if (!$lat1 || !$lon1 || !$lat2 || !$lon2) {
            return PHP_FLOAT_MAX; // No coordinates = infinite distance
        }

        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Extract keywords from text
     */
    private static function extractKeywords(string $text): array
    {
        // Convert to lowercase
        $text = strtolower($text);

        // Remove common stop words
        $stopWords = [
            'the',
            'a',
            'an',
            'and',
            'or',
            'but',
            'in',
            'on',
            'at',
            'to',
            'for',
            'of',
            'with',
            'by',
            'from',
            'is',
            'are',
            'was',
            'were',
            'be',
            'been',
            'being',
            'have',
            'has',
            'had',
            'do',
            'does',
            'did',
            'will',
            'would',
            'could',
            'should',
            'may',
            'might',
            'must',
            'shall',
            'can',
            'need',
            'i',
            'you',
            'he',
            'she',
            'it',
            'we',
            'they',
            'my',
            'your',
            'his',
            'her',
            'its',
            'our',
            'their',
            'this',
            'that',
            'these',
            'those',
            'am',
            'help',
            'looking',
            'need',
            'want',
            'offer',
            'request'
        ];

        // Extract words
        preg_match_all('/\b[a-z]{3,}\b/', $text, $matches);
        $words = $matches[0] ?? [];

        // Remove stop words and duplicates
        $keywords = array_diff($words, $stopWords);
        $keywords = array_unique($keywords);

        return array_values($keywords);
    }

    /**
     * Check if user_blocks table exists
     */
    private static function userBlocksTableExists(): bool
    {
        if (self::$userBlocksTableExistsCache !== null) {
            return self::$userBlocksTableExistsCache;
        }

        try {
            Database::query(
                "SELECT 1 FROM user_blocks LIMIT 1"
            )->fetch();
            self::$userBlocksTableExistsCache = true;
        } catch (\Exception $e) {
            // Table doesn't exist or query failed
            self::$userBlocksTableExistsCache = false;
        }

        return self::$userBlocksTableExistsCache;
    }

    /**
     * Get user data with caching
     */
    private static function getUserData(int $userId): ?array
    {
        if (isset(self::$userDataCache[$userId])) {
            return self::$userDataCache[$userId];
        }

        $tenantId = TenantContext::getId();

        $user = Database::query(
            "SELECT u.*,
                    COALESCE(u.latitude, 0) as latitude,
                    COALESCE(u.longitude, 0) as longitude,
                    (SELECT AVG(rating) FROM reviews WHERE receiver_id = u.id) as avg_rating,
                    (SELECT COUNT(*) FROM transactions WHERE (sender_id = u.id OR receiver_id = u.id) AND status = 'completed') as transaction_count
             FROM users u
             WHERE u.id = ? AND u.tenant_id = ?",
            [$userId, $tenantId]
        )->fetch();

        if ($user) {
            self::$userDataCache[$userId] = $user;
        }

        return $user ?: null;
    }

    /**
     * Get user's active listings
     */
    private static function getUserListings(int $userId): array
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT l.*, c.name as category_name
             FROM listings l
             LEFT JOIN categories c ON l.category_id = c.id
             WHERE l.user_id = ? AND l.tenant_id = ? AND l.status = 'active'
             ORDER BY l.created_at DESC
             LIMIT 10",
            [$userId, $tenantId]
        )->fetchAll();
    }

    /**
     * Get user's match preferences
     */
    private static function getUserPreferences(int $userId): array
    {
        $tenantId = TenantContext::getId();
        $config = self::getConfig();

        try {
            $prefs = Database::query(
                "SELECT * FROM match_preferences WHERE user_id = ? AND tenant_id = ?",
                [$userId, $tenantId]
            )->fetch();

            if ($prefs) {
                $prefs['categories'] = $prefs['categories'] ? json_decode($prefs['categories'], true) : null;
                return $prefs;
            }
        } catch (\Exception $e) {
            // Table might not exist yet
        }

        // Return defaults
        return [
            'max_distance_km' => $config['max_distance_km'],
            'min_match_score' => $config['min_match_score'],
            'notification_frequency' => 'daily',
            'categories' => null,
        ];
    }

    /**
     * Get candidate listings for matching
     */
    private static function getCandidateListings(
        int $tenantId,
        int $excludeUserId,
        string $targetType,
        ?int $categoryId,
        ?array $categoryFilter,
        ?float $userLat,
        ?float $userLon,
        float $maxDistance
    ): array {
        $params = [$tenantId, $targetType, $excludeUserId];

        $sql = "SELECT l.*,
                       u.first_name, u.last_name, u.avatar_url, u.location as author_location,
                       u.latitude as author_latitude, u.longitude as author_longitude,
                       u.is_verified as author_verified,
                       (SELECT AVG(rating) FROM reviews WHERE receiver_id = u.id) as author_rating,
                       c.name as category_name, c.color as category_color";

        // Add distance calculation if coordinates available
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
                  WHERE l.tenant_id = ?
                  AND l.type = ?
                  AND l.status = 'active'
                  AND l.user_id != ?";

        // Add user blocking filters only if the table exists
        if (self::userBlocksTableExists()) {
            $sql .= "
                  AND l.user_id NOT IN (
                      SELECT blocked_user_id FROM user_blocks
                      WHERE user_id = ?
                  )
                  AND l.user_id NOT IN (
                      SELECT user_id FROM user_blocks
                      WHERE blocked_user_id = ?
                  )";
            $params[] = $excludeUserId;
            $params[] = $excludeUserId;
        }

        // Category filter
        if ($categoryId) {
            $sql .= " AND l.category_id = ?";
            $params[] = $categoryId;
        } elseif ($categoryFilter && !empty($categoryFilter)) {
            $placeholders = implode(',', array_fill(0, count($categoryFilter), '?'));
            $sql .= " AND l.category_id IN ($placeholders)";
            $params = array_merge($params, $categoryFilter);
        }

        // Distance filter (using HAVING for calculated column)
        if ($userLat && $userLon) {
            $sql .= " HAVING distance_km <= ?";
            $params[] = $maxDistance;
            $sql .= " ORDER BY distance_km ASC";
        } else {
            $sql .= " ORDER BY l.created_at DESC";
        }

        $sql .= " LIMIT 50";

        return Database::query($sql, $params)->fetchAll();
    }

    /**
     * Cold start matches for users with no listings
     */
    private static function getColdStartMatches(int $userId, array $userData, float $maxDistance, int $limit): array
    {
        $tenantId = TenantContext::getId();
        $params = [$tenantId, $userId];

        $sql = "SELECT l.*,
                       u.first_name, u.last_name, u.avatar_url, u.location as author_location,
                       u.latitude as author_latitude, u.longitude as author_longitude,
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
                  WHERE l.tenant_id = ?
                  AND l.status = 'active'
                  AND l.user_id != ?";

        // Add user blocking filters only if the table exists
        if (self::userBlocksTableExists()) {
            $sql .= "
                  AND l.user_id NOT IN (
                      SELECT blocked_user_id FROM user_blocks
                      WHERE user_id = ?
                  )
                  AND l.user_id NOT IN (
                      SELECT user_id FROM user_blocks
                      WHERE blocked_user_id = ?
                  )";
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

        $results = Database::query($sql, $params)->fetchAll();

        // Add basic match info
        foreach ($results as &$listing) {
            $listing['match_score'] = 50; // Neutral score for cold start
            $listing['match_reasons'] = ['Nearby listing that might interest you'];
            $listing['match_type'] = 'cold_start';
            $listing['distance_km'] = $listing['distance_km'] ?? null;
        }

        return $results;
    }

    // =========================================================================
    // NOTIFICATION METHODS
    // =========================================================================

    /**
     * Check for new matches and send notifications
     * Uses the specialized dispatch methods for proper email templates
     */
    public static function notifyNewMatches(int $userId): int
    {
        $notified = 0;

        // Get hot matches (85%+)
        $hotMatches = self::getHotMatches($userId, 10);
        foreach ($hotMatches as $match) {
            if (self::wasMatchNotified($userId, $match['id'])) {
                continue;
            }

            // Add recipient user ID for email template
            $match['recipient_user_id'] = $userId;

            // Use the specialized hot match dispatcher
            NotificationDispatcher::dispatchHotMatch($userId, $match);
            self::markMatchNotified($userId, $match['id']);
            $notified++;
        }

        // Get mutual matches
        $mutualMatches = self::getMutualMatches($userId, 10);
        foreach ($mutualMatches as $match) {
            if (self::wasMatchNotified($userId, $match['id'])) {
                continue;
            }

            // Add recipient user ID for email template
            $match['recipient_user_id'] = $userId;

            // Build reciprocal info from match data
            $reciprocalInfo = [
                'they_offer' => $match['title'] ?? 'a skill you need',
                'you_offer' => $match['reciprocal_title'] ?? 'something they need'
            ];

            // Use the specialized mutual match dispatcher
            NotificationDispatcher::dispatchMutualMatch($userId, $match, $reciprocalInfo);
            self::markMatchNotified($userId, $match['id']);
            $notified++;
        }

        return $notified;
    }

    /**
     * Check if match was already notified
     */
    private static function wasMatchNotified(int $userId, int $listingId): bool
    {
        $tenantId = TenantContext::getId();

        try {
            $result = Database::query(
                "SELECT id FROM match_cache
                 WHERE user_id = ? AND listing_id = ? AND tenant_id = ?
                 AND status != 'dismissed'
                 AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)",
                [$userId, $listingId, $tenantId]
            )->fetch();

            return !empty($result);
        } catch (\Exception $e) {
            return false; // Table might not exist
        }
    }

    /**
     * Mark match as notified
     */
    private static function markMatchNotified(int $userId, int $listingId): void
    {
        $tenantId = TenantContext::getId();

        try {
            Database::query(
                "INSERT INTO match_cache (user_id, listing_id, tenant_id, status, created_at)
                 VALUES (?, ?, ?, 'new', NOW())
                 ON DUPLICATE KEY UPDATE created_at = NOW()",
                [$userId, $listingId, $tenantId]
            );
        } catch (\Exception $e) {
            // Table might not exist yet
        }
    }

    /**
     * Build HTML for match notification email
     */
    private static function buildMatchNotificationHtml(array $match): string
    {
        $score = $match['match_score'];
        $distance = $match['distance_km'] ?? '?';
        $title = htmlspecialchars($match['title']);
        $author = htmlspecialchars(($match['first_name'] ?? '') . ' ' . ($match['last_name'] ?? ''));
        $reasons = implode(' • ', $match['match_reasons'] ?? []);

        return <<<HTML
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 12px; color: white;">
            <div style="font-size: 24px; font-weight: bold; margin-bottom: 10px;">
                🎯 {$score}% Match Found!
            </div>
            <div style="font-size: 18px; margin-bottom: 15px;">
                "{$title}"
            </div>
            <div style="font-size: 14px; opacity: 0.9; margin-bottom: 10px;">
                📍 {$distance} km away • Posted by {$author}
            </div>
            <div style="font-size: 13px; opacity: 0.8;">
                {$reasons}
            </div>
        </div>
        HTML;
    }

    // =========================================================================
    // CACHE INVALIDATION
    // =========================================================================

    /**
     * Invalidate match cache for a specific category
     * Called when a new listing is created in that category
     */
    public static function invalidateCacheForCategory(?int $categoryId): void
    {
        if (!$categoryId) return;

        $db = Database::getInstance();
        $tenantId = TenantContext::getId();

        try {
            // Find all users who have listings in this category
            // Their cached matches might be affected by the new listing
            $sql = "DELETE mc FROM match_cache mc
                    INNER JOIN listings l ON mc.user_id = l.user_id
                    WHERE l.category_id = ?
                    AND mc.tenant_id = ?";

            Database::query($sql, [$categoryId, $tenantId]);
        } catch (\Exception $e) {
            // Cache table might not exist
            error_log("Match cache invalidation error: " . $e->getMessage());
        }
    }

    /**
     * Invalidate all cache for a specific user
     * Called when user updates their listings or preferences
     */
    public static function invalidateCacheForUser(int $userId): void
    {
        $db = Database::getInstance();
        $tenantId = TenantContext::getId();

        try {
            Database::query(
                "DELETE FROM match_cache WHERE user_id = ? AND tenant_id = ?",
                [$userId, $tenantId]
            );
        } catch (\Exception $e) {
            // Cache table might not exist
        }
    }

    /**
     * Clear all expired cache entries
     * Should be run periodically via cron
     */
    public static function clearExpiredCache(): int
    {
        $db = Database::getInstance();

        try {
            $result = Database::query("DELETE FROM match_cache WHERE expires_at < NOW()");
            return $result->rowCount();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Warm up cache for active users
     * Pre-compute matches for users who frequently use the platform
     */
    public static function warmUpCache(int $limit = 50): array
    {
        $tenantId = TenantContext::getId();
        $results = ['processed' => 0, 'cached' => 0];

        try {
            // Find active users without recent cache entries
            $sql = "SELECT DISTINCT u.id
                    FROM users u
                    INNER JOIN listings l ON u.id = l.user_id AND l.status = 'active'
                    LEFT JOIN match_cache mc ON u.id = mc.user_id AND mc.tenant_id = ?
                    WHERE u.tenant_id = ?
                    AND u.status = 'active'
                    AND (mc.id IS NULL OR mc.expires_at < NOW())
                    ORDER BY u.last_login_at DESC
                    LIMIT ?";

            $users = Database::query($sql, [$tenantId, $tenantId, $limit])->fetchAll();

            foreach ($users as $user) {
                // Generate matches for this user
                $matches = self::findMatchesForUser($user['id'], ['limit' => 20]);
                $results['processed']++;

                // Actually cache the matches in the database
                foreach ($matches as $match) {
                    try {
                        Database::query(
                            "INSERT INTO match_cache
                             (user_id, listing_id, tenant_id, match_score, distance_km, match_type, match_reasons, status, created_at, expires_at)
                             VALUES (?, ?, ?, ?, ?, ?, ?, 'new', NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY))
                             ON DUPLICATE KEY UPDATE
                             match_score = VALUES(match_score),
                             distance_km = VALUES(distance_km),
                             match_type = VALUES(match_type),
                             match_reasons = VALUES(match_reasons),
                             expires_at = DATE_ADD(NOW(), INTERVAL 7 DAY)",
                            [
                                $user['id'],
                                $match['id'],
                                $tenantId,
                                $match['match_score'] ?? 0,
                                $match['distance_km'] ?? null,
                                $match['match_type'] ?? 'one_way',
                                json_encode($match['match_reasons'] ?? [])
                            ]
                        );
                        $results['cached']++;
                    } catch (\Exception $e) {
                        error_log("Failed to cache match for user {$user['id']}: " . $e->getMessage());
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Cache warmup error: " . $e->getMessage());
        }

        return $results;
    }
}
