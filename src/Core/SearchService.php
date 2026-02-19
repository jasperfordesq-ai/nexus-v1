<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Core;

use Nexus\Core\Database;
use Meilisearch\Client;
use Nexus\Services\SearchAnalyzerService;
use Nexus\Services\PersonalizedSearchService;

class SearchService
{
    private $client;
    private $enabled = false;
    private $analyzerService;
    private $personalizationService;

    public function __construct()
    {
        // 1. Check if Meilisearch Library exists (Composer)
        if (class_exists('Meilisearch\Client') && isset($_ENV['MEILISEARCH_HOST'])) {
            try {
                $this->client = new Client($_ENV['MEILISEARCH_HOST'], $_ENV['MEILISEARCH_KEY'] ?? null);
                $this->enabled = true;
            } catch (\Exception $e) {
                error_log("Meilisearch connection failed: " . $e->getMessage());
                $this->enabled = false;
            }
        }

        // Initialize AI-powered services
        $this->analyzerService = new SearchAnalyzerService();
        $this->personalizationService = new PersonalizedSearchService();
    }

    /**
     * Enhanced search with AI-powered analysis and personalization
     *
     * @param string $query Search query
     * @param int $limit Maximum results
     * @param int|null $userId Optional user ID for personalization
     * @return array Search results with relevance scores
     */
    public function search($query, $limit = 20, $userId = null)
    {
        // Step 1: Analyze the query with AI
        $userContext = [];
        if ($userId) {
            $userContext = $this->personalizationService->getUserContext($userId);
        }

        $intent = $this->analyzerService->analyzeIntent($query, $userContext);

        // Step 2: Check for spelling corrections
        $spelling = $this->analyzerService->checkSpelling($query);
        $searchQuery = $spelling['corrected'] ?? $query;

        // Step 3: Expand query with synonyms
        $expandedQueries = $this->analyzerService->expandQuery($searchQuery, $intent);

        // Step 4: Execute search (Meilisearch or SQL)
        if ($this->enabled) {
            $results = $this->searchMeili($searchQuery, $expandedQueries, $limit);
        } else {
            $results = $this->searchSQL($searchQuery, $expandedQueries, $limit);
        }

        // Step 5: Filter by intent
        $results = $this->personalizationService->filterByIntent($results, $intent);

        // Step 6: Score and rank results
        $results = $this->personalizationService->rankResults($results, $query, $intent, $userContext);

        // Step 7: Add metadata
        return [
            'results' => array_slice($results, 0, $limit),
            'intent' => $intent,
            'corrected_query' => $spelling['corrected'] ?? null,
            'suggestions' => $spelling['suggestions'] ?? [],
            'total' => count($results)
        ];
    }

    /**
     * Enhanced Meilisearch with expanded query support
     */
    private function searchMeili($query, $expandedQueries, $limit)
    {
        try {
            // Search with expanded queries for better recall
            $allResults = [];

            foreach ($expandedQueries as $expandedQuery) {
                $results = [
                    'users' => $this->client->index('users')->search($expandedQuery, ['limit' => $limit])->getHits(),
                    'listings' => $this->client->index('listings')->search($expandedQuery, ['limit' => $limit])->getHits(),
                    'groups' => $this->client->index('groups')->search($expandedQuery, ['limit' => $limit])->getHits(),
                ];

                $formatted = $this->formatResults($results);
                $allResults = array_merge($allResults, $formatted);

                // Break if we have enough results from primary query
                if (!empty($formatted) && $expandedQuery === $query) {
                    break;
                }
            }

            // Remove duplicates (by type+id)
            return $this->deduplicateResults($allResults);

        } catch (\Exception $e) {
            // Fallback if index missing
            return $this->searchSQL($query, $expandedQueries, $limit);
        }
    }

    /**
     * Enhanced SQL search with expanded query support and better ranking
     */
    private function searchSQL($query, $expandedQueries, $limit)
    {
        // SECURITY: Cast to int to prevent SQL injection
        $limit = (int)$limit;

        $tenantId = \Nexus\Core\TenantContext::getId();
        $allResults = [];

        // Build OR conditions for expanded queries
        $searchTerms = array_unique($expandedQueries);
        $primaryTerm = "%$query%";

        // 1. Users - Search by name, username, email (if available)
        $userConditions = [];
        $userParams = [];

        foreach ($searchTerms as $term) {
            $likeTerm = "%$term%";
            $userConditions[] = "(name LIKE ? OR CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ?)";
            $userParams[] = $likeTerm;
            $userParams[] = $likeTerm;
        }

        $userParams[] = $tenantId;
        $userWhere = implode(' OR ', $userConditions);

        try {
            $users = Database::query(
                "SELECT id,
                    CASE
                        WHEN profile_type = 'organisation' AND organization_name IS NOT NULL THEN organization_name
                        ELSE CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))
                    END as title,
                    'user' as type,
                    avatar_url as image,
                    bio as description,
                    location,
                    created_at
                FROM users
                WHERE ($userWhere) AND tenant_id = ?
                LIMIT $limit",
                $userParams
            )->fetchAll();

            $allResults = array_merge($allResults, $users);
        } catch (\PDOException $e) {
            error_log("User search error: " . $e->getMessage());
        }

        // 2. Listings - Search by title, description, location
        $listingConditions = [];
        $listingParams = [];

        foreach ($searchTerms as $term) {
            $likeTerm = "%$term%";
            $listingConditions[] = "(title LIKE ? OR description LIKE ? OR location LIKE ?)";
            $listingParams[] = $likeTerm;
            $listingParams[] = $likeTerm;
            $listingParams[] = $likeTerm;
        }

        $listingParams[] = $tenantId;
        $listingWhere = implode(' OR ', $listingConditions);

        try {
            $listings = Database::query(
                "SELECT id, title, description, 'listing' as type, image_url as image,
                    type as listing_type, location, status, created_at
                FROM listings
                WHERE ($listingWhere) AND tenant_id = ?
                LIMIT $limit",
                $listingParams
            )->fetchAll();

            $allResults = array_merge($allResults, $listings);
        } catch (\PDOException $e) {
            error_log("Listing search error: " . $e->getMessage());
        }

        // 3. Groups - Search by name, description
        $groupConditions = [];
        $groupParams = [];

        foreach ($searchTerms as $term) {
            $likeTerm = "%$term%";
            $groupConditions[] = "(name LIKE ? OR description LIKE ?)";
            $groupParams[] = $likeTerm;
            $groupParams[] = $likeTerm;
        }

        $groupParams[] = $tenantId;
        $groupWhere = implode(' OR ', $groupConditions);

        try {
            $groups = Database::query(
                "SELECT g.id, g.name as title, g.description, 'group' as type,
                    NULL as image, g.created_at,
                    COUNT(DISTINCT gm.user_id) as member_count
                FROM groups g
                LEFT JOIN group_members gm ON g.id = gm.group_id
                WHERE ($groupWhere) AND g.tenant_id = ?
                GROUP BY g.id
                LIMIT $limit",
                $groupParams
            )->fetchAll();

            $allResults = array_merge($allResults, $groups);
        } catch (\PDOException $e) {
            error_log("Group search error: " . $e->getMessage());
        }

        // Remove duplicates
        return $this->deduplicateResults($allResults);
    }

    /**
     * Remove duplicate results based on type and ID
     */
    private function deduplicateResults(array $results): array
    {
        $seen = [];
        $unique = [];

        foreach ($results as $result) {
            $key = $result['type'] . '_' . $result['id'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $result;
            }
        }

        return $unique;
    }

    private function formatResults($meiliResults)
    {
        $final = [];
        foreach ($meiliResults as $type => $hits) {
            foreach ($hits as $hit) {
                $final[] = [
                    'id' => $hit['id'],
                    'title' => $hit['name'] ?? $hit['title'],
                    'type' => rtrim($type, 's'), // users -> user
                    'image' => $hit['avatar_url'] ?? $hit['image_url'] ?? null
                ];
            }
        }
        return $final;
    }

    public function indexAll()
    {
        if (!$this->enabled) {
            echo "Meilisearch not compiled/configured.\n";
            return;
        }

        // Logic to clear and re-add all documents would go here
        // Called by scripts/reindex_search.php
    }
}
