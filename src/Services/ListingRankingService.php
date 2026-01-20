<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * ListingRankingService - MatchRank Algorithm
 *
 * Intelligent ranking for listings (offers & requests) based on:
 *
 * 1. RELEVANCE SCORE
 *    - Category match with user interests
 *    - Search term matching
 *    - Skill/need complementarity
 *
 * 2. FRESHNESS SCORE
 *    - Newer listings get boost (gentler decay than feed)
 *    - Reactivation boost for recently edited listings
 *
 * 3. ENGAGEMENT SCORE
 *    - View count
 *    - Inquiry/contact count
 *    - Save/favorite count
 *
 * 4. PROXIMITY SCORE
 *    - Distance from viewer (if location enabled)
 *
 * 5. QUALITY SCORE
 *    - Listing completeness (description, images)
 *    - Owner profile quality
 *    - Owner response rate
 *    - Owner verified status
 *
 * 6. RECIPROCITY SCORE (Unique to MatchRank)
 *    - Does the listing owner have complementary needs/offers?
 *    - Potential for mutual exchange
 *
 * Final Score = Relevance × Freshness × Engagement × Proximity × Quality × Reciprocity
 */
class ListingRankingService
{
    // =========================================================================
    // DEFAULT CONFIGURATION
    // =========================================================================

    // Relevance weights
    const RELEVANCE_CATEGORY_MATCH = 1.5;     // User interested in this category
    const RELEVANCE_SEARCH_BOOST = 2.0;       // Search term match

    // Freshness parameters (gentler than feed - listings stay relevant longer)
    const FRESHNESS_FULL_DAYS = 7;            // Full freshness for 7 days
    const FRESHNESS_HALF_LIFE_DAYS = 30;      // Half-life of 30 days
    const FRESHNESS_MINIMUM = 0.3;            // Minimum 30%
    const FRESHNESS_EDIT_BOOST_DAYS = 3;      // Recent edit gives boost for 3 days

    // Engagement weights
    const ENGAGEMENT_VIEW_WEIGHT = 0.1;       // Each view adds 0.1
    const ENGAGEMENT_INQUIRY_WEIGHT = 1.0;    // Each inquiry adds 1.0
    const ENGAGEMENT_SAVE_WEIGHT = 0.5;       // Each save/favorite adds 0.5
    const ENGAGEMENT_MINIMUM = 1.0;           // Base engagement score

    // Quality thresholds
    const QUALITY_MIN_DESCRIPTION_LENGTH = 50;
    const QUALITY_HAS_IMAGE_BOOST = 1.3;
    const QUALITY_HAS_LOCATION_BOOST = 1.2;
    const QUALITY_VERIFIED_OWNER_BOOST = 1.4;
    const QUALITY_HIGH_RESPONSE_RATE_BOOST = 1.2; // >80% response rate

    // Reciprocity parameters
    const RECIPROCITY_ENABLED = true;
    const RECIPROCITY_MATCH_BOOST = 1.5;      // Boost when owner has what viewer needs
    const RECIPROCITY_MUTUAL_BOOST = 2.0;     // Boost for potential mutual exchange

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
            // Relevance
            'relevance_category_match' => self::RELEVANCE_CATEGORY_MATCH,
            'relevance_search_boost' => self::RELEVANCE_SEARCH_BOOST,
            // Freshness
            'freshness_full_days' => self::FRESHNESS_FULL_DAYS,
            'freshness_half_life_days' => self::FRESHNESS_HALF_LIFE_DAYS,
            'freshness_minimum' => self::FRESHNESS_MINIMUM,
            'freshness_edit_boost_days' => self::FRESHNESS_EDIT_BOOST_DAYS,
            // Engagement
            'engagement_view_weight' => self::ENGAGEMENT_VIEW_WEIGHT,
            'engagement_inquiry_weight' => self::ENGAGEMENT_INQUIRY_WEIGHT,
            'engagement_save_weight' => self::ENGAGEMENT_SAVE_WEIGHT,
            'engagement_minimum' => self::ENGAGEMENT_MINIMUM,
            // Quality
            'quality_min_description' => self::QUALITY_MIN_DESCRIPTION_LENGTH,
            'quality_image_boost' => self::QUALITY_HAS_IMAGE_BOOST,
            'quality_location_boost' => self::QUALITY_HAS_LOCATION_BOOST,
            'quality_verified_boost' => self::QUALITY_VERIFIED_OWNER_BOOST,
            'quality_response_boost' => self::QUALITY_HIGH_RESPONSE_RATE_BOOST,
            // Reciprocity
            'reciprocity_enabled' => self::RECIPROCITY_ENABLED,
            'reciprocity_match_boost' => self::RECIPROCITY_MATCH_BOOST,
            'reciprocity_mutual_boost' => self::RECIPROCITY_MUTUAL_BOOST,
            // Geo (inherited from shared, but can be overridden)
            'geo_enabled' => true,
            'geo_full_radius_km' => 50,       // Listings: wider radius than feed
            'geo_decay_per_km' => 0.003,      // Gentler decay
        ];

        try {
            $tenantId = TenantContext::getId();
            $configJson = Database::query(
                "SELECT configuration FROM tenants WHERE id = ?",
                [$tenantId]
            )->fetchColumn();

            if ($configJson) {
                $configArr = json_decode($configJson, true);
                if (is_array($configArr) && isset($configArr['algorithms']['listings'])) {
                    self::$config = array_merge($defaults, $configArr['algorithms']['listings']);
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
     * Check if MatchRank is enabled
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
     * Rank a list of listings for a specific viewer
     *
     * @param array $listings Array of listing data
     * @param int|null $viewerId Viewing user ID (null for anonymous)
     * @param array $options Additional options (search term, filters, etc.)
     * @return array Ranked listings with scores
     */
    public static function rankListings(
        array $listings,
        ?int $viewerId = null,
        array $options = []
    ): array {
        if (!self::isEnabled() || empty($listings)) {
            return $listings;
        }

        $config = self::getConfig();

        // Get viewer data if logged in
        $viewerData = null;
        $viewerCoords = ['lat' => null, 'lon' => null];
        $viewerInterests = [];
        $viewerListings = [];

        if ($viewerId) {
            $viewerData = self::getViewerData($viewerId);
            $viewerCoords = RankingService::getViewerCoordinates($viewerId);
            $viewerInterests = self::getUserInterests($viewerId);
            $viewerListings = self::getUserListings($viewerId);
        }

        $searchTerm = $options['search'] ?? null;
        $rankedListings = [];

        foreach ($listings as $listing) {
            $scores = self::calculateListingScores(
                $listing,
                $viewerId,
                $viewerCoords,
                $viewerInterests,
                $viewerListings,
                $searchTerm
            );

            // Calculate final score
            $finalScore =
                $scores['relevance'] *
                $scores['freshness'] *
                $scores['engagement'] *
                $scores['proximity'] *
                $scores['quality'] *
                $scores['reciprocity'];

            $listing['_match_rank'] = $finalScore;
            $listing['_score_breakdown'] = $scores;
            $rankedListings[] = $listing;
        }

        // Sort by score descending
        usort($rankedListings, function($a, $b) {
            return $b['_match_rank'] <=> $a['_match_rank'];
        });

        return $rankedListings;
    }

    /**
     * Build a ranked listings SQL query
     *
     * @param int|null $viewerId Viewing user ID
     * @param array $filters Filter options (type, category, etc.)
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
        $freshnessSql = self::getFreshnessScoreSql();
        $engagementSql = self::getEngagementScoreSql();
        $qualitySql = self::getQualityScoreSql();
        $geoSql = self::getGeoScoreSql($viewerCoords['lat'], $viewerCoords['lon']);

        // Total score calculation
        $totalScoreSql = "({$freshnessSql}) * ({$engagementSql}) * ({$qualitySql}) * ({$geoSql})";

        $sql = "
            SELECT
                l.*,
                CASE
                    WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL AND u.organization_name != ''
                    THEN u.organization_name
                    ELSE CONCAT(u.first_name, ' ', u.last_name)
                END as author_name,
                u.avatar_url,
                u.location as user_location,
                u.latitude as owner_lat,
                u.longitude as owner_lon,
                c.name as category_name,
                c.color as category_color,

                -- Score components
                ({$freshnessSql}) as freshness_score,
                ({$engagementSql}) as engagement_score,
                ({$qualitySql}) as quality_score,
                ({$geoSql}) as geo_score,

                -- Final rank score
                ({$totalScoreSql}) as match_rank

            FROM listings l
            JOIN users u ON l.user_id = u.id
            LEFT JOIN categories c ON l.category_id = c.id
            WHERE l.tenant_id = ?
            AND l.status = 'active'
        ";

        $params = [$tenantId];

        // Apply filters
        if (!empty($filters['type'])) {
            if (is_array($filters['type'])) {
                // Handle multiple types with IN clause
                $placeholders = str_repeat('?,', count($filters['type']) - 1) . '?';
                $sql .= " AND l.type IN ($placeholders)";
                $params = array_merge($params, $filters['type']);
            } else {
                $sql .= " AND l.type = ?";
                $params[] = $filters['type'];
            }
        }

        if (!empty($filters['category_id'])) {
            $sql .= " AND l.category_id = ?";
            $params[] = $filters['category_id'];
        }

        if (!empty($filters['user_id'])) {
            $sql .= " AND l.user_id = ?";
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (l.title LIKE ? OR l.description LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Exclude viewer's own listings
        if ($viewerId && empty($filters['include_own'])) {
            $sql .= " AND l.user_id != ?";
            $params[] = $viewerId;
        }

        // Order by match_rank
        $sql .= " ORDER BY match_rank DESC, l.created_at DESC";

        // Limit
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
        }

        return [
            'sql' => $sql,
            'params' => $params
        ];
    }

    /**
     * Get recommended listings for a user (personalized)
     *
     * @param int $userId User to get recommendations for
     * @param int $limit Maximum results
     * @return array Recommended listings
     */
    public static function getRecommendedListings(int $userId, int $limit = 10): array
    {
        $tenantId = TenantContext::getId();
        $userInterests = self::getUserInterests($userId);
        $userListings = self::getUserListings($userId);
        $viewerCoords = RankingService::getViewerCoordinates($userId);

        // Get the user's listing types to find complementary listings
        $userOfferCategories = [];
        $userRequestCategories = [];

        foreach ($userListings as $listing) {
            if ($listing['type'] === 'offer') {
                $userOfferCategories[] = $listing['category_id'];
            } else {
                $userRequestCategories[] = $listing['category_id'];
            }
        }

        // Build query for recommendations
        // Prioritize: 1) Requests in categories user offers 2) Offers in categories user requests
        $query = self::buildRankedQuery($userId, ['limit' => $limit * 2]);
        $listings = Database::query($query['sql'], $query['params'])->fetchAll(\PDO::FETCH_ASSOC);

        // Apply reciprocity scoring
        $recommendations = [];
        foreach ($listings as $listing) {
            $reciprocityBoost = 1.0;

            // Boost requests in categories the user offers
            if ($listing['type'] === 'request' && in_array($listing['category_id'], $userOfferCategories)) {
                $reciprocityBoost = self::getConfig()['reciprocity_match_boost'];
            }

            // Boost offers in categories the user requests
            if ($listing['type'] === 'offer' && in_array($listing['category_id'], $userRequestCategories)) {
                $reciprocityBoost = self::getConfig()['reciprocity_match_boost'];
            }

            $listing['match_rank'] = ($listing['match_rank'] ?? 1) * $reciprocityBoost;
            $listing['_reciprocity_boost'] = $reciprocityBoost;
            $recommendations[] = $listing;
        }

        // Re-sort and limit
        usort($recommendations, function($a, $b) {
            return $b['match_rank'] <=> $a['match_rank'];
        });

        return array_slice($recommendations, 0, $limit);
    }

    // =========================================================================
    // SCORE CALCULATION METHODS
    // =========================================================================

    /**
     * Calculate all score components for a listing
     */
    private static function calculateListingScores(
        array $listing,
        ?int $viewerId,
        array $viewerCoords,
        array $viewerInterests,
        array $viewerListings,
        ?string $searchTerm
    ): array {
        $config = self::getConfig();

        return [
            'relevance' => self::calculateRelevanceScore($listing, $viewerInterests, $searchTerm),
            'freshness' => self::calculateFreshnessScore($listing),
            'engagement' => self::calculateEngagementScore($listing),
            'proximity' => self::calculateProximityScore($listing, $viewerCoords),
            'quality' => self::calculateQualityScore($listing),
            'reciprocity' => self::calculateReciprocityScore($listing, $viewerListings),
        ];
    }

    /**
     * Calculate relevance score
     */
    private static function calculateRelevanceScore(
        array $listing,
        array $viewerInterests,
        ?string $searchTerm
    ): float {
        $config = self::getConfig();
        $score = 1.0;

        // Category match with user interests
        if (!empty($listing['category_id']) && in_array($listing['category_id'], $viewerInterests)) {
            $score *= $config['relevance_category_match'];
        }

        // Search term match
        if ($searchTerm) {
            $title = strtolower($listing['title'] ?? '');
            $description = strtolower($listing['description'] ?? '');
            $search = strtolower($searchTerm);

            if (strpos($title, $search) !== false) {
                $score *= $config['relevance_search_boost'];
            } elseif (strpos($description, $search) !== false) {
                $score *= ($config['relevance_search_boost'] * 0.7);
            }
        }

        return $score;
    }

    /**
     * Calculate freshness score
     */
    private static function calculateFreshnessScore(array $listing): float
    {
        $config = self::getConfig();

        $createdAt = $listing['created_at'] ?? null;
        $updatedAt = $listing['updated_at'] ?? null;

        // Use the more recent date (creation or last edit)
        $relevantDate = $updatedAt && $updatedAt > $createdAt ? $updatedAt : $createdAt;

        if (!$relevantDate) {
            return $config['freshness_minimum'];
        }

        $daysSince = RankingService::getDaysSince($relevantDate);

        // Within full freshness period = 100%
        if ($daysSince <= $config['freshness_full_days']) {
            return 1.0;
        }

        // Exponential decay
        $decayDays = $daysSince - $config['freshness_full_days'];
        $halfLife = $config['freshness_half_life_days'];
        $decayFactor = exp(-0.693 * $decayDays / $halfLife);

        return max($config['freshness_minimum'], $decayFactor);
    }

    /**
     * Calculate engagement score
     */
    private static function calculateEngagementScore(array $listing): float
    {
        $config = self::getConfig();

        $views = (int)($listing['view_count'] ?? 0);
        $inquiries = (int)($listing['inquiry_count'] ?? 0);
        $saves = (int)($listing['save_count'] ?? 0);

        $score =
            ($views * $config['engagement_view_weight']) +
            ($inquiries * $config['engagement_inquiry_weight']) +
            ($saves * $config['engagement_save_weight']);

        return max($config['engagement_minimum'], 1.0 + ($score / 10));
    }

    /**
     * Calculate proximity score
     */
    private static function calculateProximityScore(array $listing, array $viewerCoords): float
    {
        $config = self::getConfig();

        if (!$config['geo_enabled']) {
            return 1.0;
        }

        $listingLat = $listing['latitude'] ?? $listing['owner_lat'] ?? null;
        $listingLon = $listing['longitude'] ?? $listing['owner_lon'] ?? null;

        return RankingService::calculateGeoScore(
            $viewerCoords['lat'],
            $viewerCoords['lon'],
            $listingLat ? (float)$listingLat : null,
            $listingLon ? (float)$listingLon : null
        );
    }

    /**
     * Calculate quality score
     */
    private static function calculateQualityScore(array $listing): float
    {
        $config = self::getConfig();
        $score = 1.0;

        // Description length
        $descLength = strlen($listing['description'] ?? '');
        if ($descLength >= $config['quality_min_description']) {
            $score *= 1.1;
        }

        // Has image
        if (!empty($listing['image_url'])) {
            $score *= $config['quality_image_boost'];
        }

        // Has location
        if (!empty($listing['location']) || !empty($listing['latitude'])) {
            $score *= $config['quality_location_boost'];
        }

        // Owner verified (if available)
        if (!empty($listing['owner_verified'])) {
            $score *= $config['quality_verified_boost'];
        }

        return $score;
    }

    /**
     * Calculate reciprocity score
     */
    private static function calculateReciprocityScore(array $listing, array $viewerListings): float
    {
        $config = self::getConfig();

        if (!$config['reciprocity_enabled'] || empty($viewerListings)) {
            return 1.0;
        }

        $listingType = $listing['type'] ?? '';
        $listingCategory = $listing['category_id'] ?? null;
        $listingOwnerId = $listing['user_id'] ?? null;

        // Check if viewer has complementary listings
        foreach ($viewerListings as $viewerListing) {
            // If listing is a request and viewer has an offer in same category
            if ($listingType === 'request' &&
                $viewerListing['type'] === 'offer' &&
                $viewerListing['category_id'] == $listingCategory) {
                return $config['reciprocity_match_boost'];
            }

            // If listing is an offer and viewer has a request in same category
            if ($listingType === 'offer' &&
                $viewerListing['type'] === 'request' &&
                $viewerListing['category_id'] == $listingCategory) {
                return $config['reciprocity_match_boost'];
            }
        }

        return 1.0;
    }

    // =========================================================================
    // SQL SCORE METHODS
    // =========================================================================

    /**
     * SQL snippet for freshness score
     */
    private static function getFreshnessScoreSql(): string
    {
        $config = self::getConfig();
        $fullDays = (float)$config['freshness_full_days'];
        $halfLife = (float)$config['freshness_half_life_days'];
        $minimum = (float)$config['freshness_minimum'];

        // Use created_at only (safer, always exists)
        return "
            CASE
                WHEN DATEDIFF(NOW(), l.created_at) <= {$fullDays} THEN 1.0
                ELSE GREATEST(
                    {$minimum},
                    EXP(-0.693 * (DATEDIFF(NOW(), l.created_at) - {$fullDays}) / {$halfLife})
                )
            END
        ";
    }

    /**
     * SQL snippet for engagement score
     */
    private static function getEngagementScoreSql(): string
    {
        // Return neutral score - engagement metrics not yet implemented
        // Future: add view_count, inquiry_count, save_count columns to listings table
        return "1.0";
    }

    /**
     * SQL snippet for quality score
     */
    private static function getQualityScoreSql(): string
    {
        $config = self::getConfig();
        $minDesc = (int)$config['quality_min_description'];
        $imageBoost = (float)$config['quality_image_boost'];
        $locationBoost = (float)$config['quality_location_boost'];

        return "
            (
                1.0
                * CASE WHEN CHAR_LENGTH(COALESCE(l.description, '')) >= {$minDesc} THEN 1.1 ELSE 1.0 END
                * CASE WHEN COALESCE(l.image_url, '') != '' THEN {$imageBoost} ELSE 1.0 END
                * CASE WHEN COALESCE(l.location, '') != '' OR l.latitude IS NOT NULL THEN {$locationBoost} ELSE 1.0 END
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

        // Use listing coordinates first, fall back to owner coordinates
        return "
            CASE
                WHEN l.latitude IS NOT NULL AND l.longitude IS NOT NULL THEN
                    " . RankingService::getGeoScoreSql($viewerLat, $viewerLon, 'latitude', 'longitude', 'l') . "
                WHEN u.latitude IS NOT NULL AND u.longitude IS NOT NULL THEN
                    " . RankingService::getGeoScoreSql($viewerLat, $viewerLon, 'latitude', 'longitude', 'u') . "
                ELSE 1.0
            END
        ";
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Get viewer's profile data
     */
    private static function getViewerData(int $userId): ?array
    {
        try {
            return Database::query(
                "SELECT * FROM users WHERE id = ?",
                [$userId]
            )->fetch(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get user's interest categories (from their activity and preferences)
     */
    private static function getUserInterests(int $userId): array
    {
        try {
            // Get categories from user's own listings
            $ownCategories = Database::query(
                "SELECT DISTINCT category_id FROM listings WHERE user_id = ? AND category_id IS NOT NULL",
                [$userId]
            )->fetchAll(\PDO::FETCH_COLUMN);

            // Get categories from listings they've viewed/contacted (if tracking exists)
            // For now, just return their own categories
            return $ownCategories ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get user's active listings
     */
    private static function getUserListings(int $userId): array
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

    // =========================================================================
    // DEBUG & ANALYTICS
    // =========================================================================

    /**
     * Get score breakdown for a listing (for debugging/analytics)
     */
    public static function debugListingScore(int $listingId, ?int $viewerId = null): array
    {
        try {
            $listing = Database::query(
                "SELECT l.*, u.latitude as owner_lat, u.longitude as owner_lon
                 FROM listings l
                 JOIN users u ON l.user_id = u.id
                 WHERE l.id = ?",
                [$listingId]
            )->fetch(\PDO::FETCH_ASSOC);

            if (!$listing) {
                return ['error' => 'Listing not found'];
            }

            $viewerCoords = ['lat' => null, 'lon' => null];
            $viewerInterests = [];
            $viewerListings = [];

            if ($viewerId) {
                $viewerCoords = RankingService::getViewerCoordinates($viewerId);
                $viewerInterests = self::getUserInterests($viewerId);
                $viewerListings = self::getUserListings($viewerId);
            }

            $scores = self::calculateListingScores(
                $listing,
                $viewerId,
                $viewerCoords,
                $viewerInterests,
                $viewerListings,
                null
            );

            $finalScore = array_product($scores);

            return [
                'listing_id' => $listingId,
                'title' => $listing['title'],
                'scores' => $scores,
                'final_score' => $finalScore,
                'config' => self::getConfig()
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
