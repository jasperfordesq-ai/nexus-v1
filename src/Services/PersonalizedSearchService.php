<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * PersonalizedSearchService
 *
 * Provides relevance scoring and personalized ranking for search results.
 * Model after GroupRecommendationEngine but for search results.
 */
class PersonalizedSearchService
{
    /**
     * Scoring weights (sum = 1.0)
     */
    const WEIGHT_POPULARITY = 0.40;    // 40% - Most important for general users
    const WEIGHT_RELEVANCE = 0.30;     // 30% - How well the query matches
    const WEIGHT_LOCATION = 0.20;      // 20% - Geographic relevance
    const WEIGHT_RECENCY = 0.10;       // 10% - Newer content slightly preferred

    /**
     * Score and rank search results with personalization
     *
     * @param array $results Raw search results
     * @param string $query Original search query
     * @param array $intent Search intent from SearchAnalyzerService
     * @param array $userContext User context (location, interests, etc.)
     * @return array Scored and ranked results
     */
    public function rankResults(array $results, string $query, array $intent, array $userContext = []): array
    {
        if (empty($results)) {
            return [];
        }

        // Score each result
        $scoredResults = [];
        foreach ($results as $result) {
            $score = $this->calculateScore($result, $query, $intent, $userContext);
            $result['relevance_score'] = $score;
            $result['score_breakdown'] = $this->getScoreBreakdown($result, $query, $intent, $userContext);
            $scoredResults[] = $result;
        }

        // Sort by score (highest first)
        usort($scoredResults, function($a, $b) {
            return $b['relevance_score'] <=> $a['relevance_score'];
        });

        return $scoredResults;
    }

    /**
     * Calculate total relevance score for a result
     */
    private function calculateScore(array $result, string $query, array $intent, array $userContext): float
    {
        $score = 0;

        // 1. Popularity Score (40%)
        $score += $this->calculatePopularityScore($result) * self::WEIGHT_POPULARITY;

        // 2. Relevance Score (30%)
        $score += $this->calculateRelevanceScore($result, $query, $intent) * self::WEIGHT_RELEVANCE;

        // 3. Location Score (20%)
        $score += $this->calculateLocationScore($result, $userContext) * self::WEIGHT_LOCATION;

        // 4. Recency Score (10%)
        $score += $this->calculateRecencyScore($result) * self::WEIGHT_RECENCY;

        return $score;
    }

    /**
     * Get detailed score breakdown for debugging/display
     */
    private function getScoreBreakdown(array $result, string $query, array $intent, array $userContext): array
    {
        return [
            'popularity' => $this->calculatePopularityScore($result),
            'relevance' => $this->calculateRelevanceScore($result, $query, $intent),
            'location' => $this->calculateLocationScore($result, $userContext),
            'recency' => $this->calculateRecencyScore($result),
        ];
    }

    /**
     * Popularity Score: Based on views, likes, members, activity
     */
    private function calculatePopularityScore(array $result): float
    {
        $score = 0.5; // Base score

        switch ($result['type']) {
            case 'user':
                // User popularity based on profile completeness and activity
                if (!empty($result['avatar_url'])) {
                    $score += 0.1;
                }
                if (!empty($result['bio'])) {
                    $score += 0.1;
                }
                // Could add: follower count, post count, etc.
                break;

            case 'listing':
                // Listing popularity based on status and recency
                if (isset($result['status']) && $result['status'] === 'active') {
                    $score += 0.3;
                }
                if (!empty($result['image'])) {
                    $score += 0.1; // Listings with images are more complete
                }
                if (!empty($result['description'])) {
                    $score += 0.1;
                }
                break;

            case 'group':
                // Group popularity based on member count
                if (isset($result['member_count'])) {
                    $score += min($result['member_count'] / 50, 0.4); // Max 0.4 bonus for members
                }
                if (!empty($result['description'])) {
                    $score += 0.1;
                }
                break;
        }

        return min($score, 1.0);
    }

    /**
     * Relevance Score: How well the result matches the query
     */
    private function calculateRelevanceScore(array $result, string $query, array $intent): float
    {
        $score = 0.3; // Base score
        $queryLower = strtolower($query);
        $keywords = $intent['keywords'] ?? [$query];
        $expandedKeywords = $intent['expanded_keywords'] ?? [];

        $title = strtolower($result['title'] ?? '');
        $description = strtolower($result['description'] ?? '');

        // Exact title match (highest relevance)
        if ($title === $queryLower) {
            return 1.0;
        }

        // Title starts with query
        if (strpos($title, $queryLower) === 0) {
            $score += 0.5;
        }
        // Title contains query
        elseif (strpos($title, $queryLower) !== false) {
            $score += 0.3;
        }

        // Check each keyword in title and description
        foreach ($keywords as $keyword) {
            $keyword = strtolower(trim($keyword));
            if (empty($keyword)) continue;

            if (strpos($title, $keyword) !== false) {
                $score += 0.15;
            }
            if (strpos($description, $keyword) !== false) {
                $score += 0.05;
            }
        }

        // Check expanded keywords (synonyms) - lower weight
        foreach ($expandedKeywords as $keyword) {
            $keyword = strtolower(trim($keyword));
            if (empty($keyword)) continue;

            if (strpos($title, $keyword) !== false) {
                $score += 0.08;
            }
            if (strpos($description, $keyword) !== false) {
                $score += 0.03;
            }
        }

        // Type match bonus (if intent suggests a specific type)
        if (!empty($intent['filters']['type']) && $intent['filters']['type'] === $result['type']) {
            $score += 0.2;
        }

        // Category match bonus
        if (!empty($intent['category']) && !empty($result['category'])) {
            if (stripos($result['category'], $intent['category']) !== false) {
                $score += 0.2;
            }
        }

        return min($score, 1.0);
    }

    /**
     * Location Score: Geographic relevance
     */
    private function calculateLocationScore(array $result, array $userContext): float
    {
        $score = 0.5; // Neutral base (no location penalty)

        // If no location data available, return neutral score
        if (empty($result['location']) && empty($userContext['location'])) {
            return $score;
        }

        $userLocation = strtolower($userContext['location'] ?? '');
        $resultLocation = strtolower($result['location'] ?? '');

        // Exact location match
        if (!empty($userLocation) && !empty($resultLocation) && $userLocation === $resultLocation) {
            return 1.0;
        }

        // Partial location match (e.g., "Dublin" in "Dublin City Centre")
        if (!empty($userLocation) && !empty($resultLocation)) {
            if (strpos($resultLocation, $userLocation) !== false || strpos($userLocation, $resultLocation) !== false) {
                $score = 0.8;
            }
        }

        // TODO: Could add coordinate-based distance calculation if lat/lng available
        // For now, simple text matching

        return $score;
    }

    /**
     * Recency Score: Newer content is slightly preferred
     */
    private function calculateRecencyScore(array $result): float
    {
        $score = 0.5; // Neutral base

        // Check for created_at or updated_at timestamp
        $timestamp = null;
        if (isset($result['created_at'])) {
            $timestamp = strtotime($result['created_at']);
        } elseif (isset($result['updated_at'])) {
            $timestamp = strtotime($result['updated_at']);
        }

        if (!$timestamp) {
            return $score; // No timestamp data
        }

        $now = time();
        $ageInDays = ($now - $timestamp) / 86400;

        // Scoring based on age
        if ($ageInDays < 7) {
            $score = 1.0; // Within 1 week - maximum recency
        } elseif ($ageInDays < 30) {
            $score = 0.9; // Within 1 month
        } elseif ($ageInDays < 90) {
            $score = 0.7; // Within 3 months
        } elseif ($ageInDays < 180) {
            $score = 0.6; // Within 6 months
        } else {
            $score = 0.4; // Older than 6 months
        }

        return $score;
    }

    /**
     * Get user context for personalization
     */
    public function getUserContext(int $userId): array
    {
        $tenantId = TenantContext::getId();

        // Fetch user data
        $user = Database::query(
            "SELECT location, latitude, longitude FROM users WHERE id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        )->fetch();

        if (!$user) {
            return [];
        }

        $context = [
            'user_id' => $userId,
            'location' => $user['location'],
        ];

        if ($user['latitude'] && $user['longitude']) {
            $context['coordinates'] = [
                'lat' => $user['latitude'],
                'lng' => $user['longitude']
            ];
        }

        // TODO: Could add more context:
        // - User interests/tags
        // - Groups joined
        // - Recent activity
        // - Search history

        return $context;
    }

    /**
     * Filter results based on intent
     *
     * @param array $results
     * @param array $intent
     * @return array Filtered results
     */
    public function filterByIntent(array $results, array $intent): array
    {
        // Apply type filter if specified
        if (!empty($intent['filters']['type']) && $intent['filters']['type'] !== 'all') {
            $targetType = $intent['filters']['type'];
            $results = array_filter($results, function($result) use ($targetType) {
                return $result['type'] === $targetType;
            });
        }

        // Apply active_only filter for listings
        if (!empty($intent['filters']['active_only'])) {
            $results = array_filter($results, function($result) {
                if ($result['type'] !== 'listing') {
                    return true; // Keep non-listings
                }
                return !isset($result['status']) || $result['status'] === 'active';
            });
        }

        // Apply location filter if specified
        if (!empty($intent['location'])) {
            $targetLocation = strtolower($intent['location']);
            $results = array_filter($results, function($result) use ($targetLocation) {
                if (empty($result['location'])) {
                    return true; // Keep results without location
                }
                $resultLocation = strtolower($result['location']);
                return strpos($resultLocation, $targetLocation) !== false;
            });
        }

        return array_values($results); // Re-index array
    }
}
