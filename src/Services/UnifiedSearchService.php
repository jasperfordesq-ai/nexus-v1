<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\SearchService;

/**
 * UnifiedSearchService - API-optimized search across all content types
 *
 * This service provides a unified search interface for mobile/API clients
 * with cursor-based pagination and typed result formatting.
 *
 * Search Types:
 * - listings: Service offers and requests
 * - users: Community members
 * - events: Upcoming and past events
 * - groups: Community groups
 *
 * Features:
 * - Cursor-based pagination
 * - Type filtering
 * - AI-powered intent detection (when available)
 * - Spelling suggestions
 */
class UnifiedSearchService
{
    /**
     * Validation errors
     */
    private static array $errors = [];

    /**
     * Get validation errors
     */
    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * Unified search across all content types
     *
     * @param string $query Search query (supports "exact phrase", AND, OR, NOT/-prefix)
     * @param int|null $userId User ID for personalization
     * @param array $filters [
     *   'type' => 'all' (default), 'listings', 'users', 'events', 'groups',
     *   'cursor' => string,
     *   'limit' => int (default: 20, max: 50),
     *   'category_id' => int (listings only),
     *   'date_from' => string ISO date,
     *   'date_to' => string ISO date,
     *   'sort' => 'relevance'|'newest'|'oldest',
     *   'skills' => string (comma-separated, listings only),
     *   'location' => string (location filter),
     * ]
     * @return array Search results with pagination
     */
    public static function search(string $query, ?int $userId, array $filters = []): array
    {
        self::$errors = [];

        $query = trim($query);
        if (strlen($query) < 2) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Search query must be at least 2 characters', 'field' => 'q'];
            return ['items' => [], 'cursor' => null, 'has_more' => false, 'total' => 0];
        }

        $limit = min($filters['limit'] ?? 20, 50);
        $type = $filters['type'] ?? 'all';
        $cursor = $filters['cursor'] ?? null;

        // Decode cursor (offset-based for unified search)
        $offset = 0;
        if ($cursor) {
            $decoded = base64_decode($cursor, true);
            if ($decoded && is_numeric($decoded)) {
                $offset = (int)$decoded;
            }
        }

        $tenantId = TenantContext::getId();

        // Parse the query for boolean operators and exact phrases
        $searchTerms = self::parseSearchQuery($query);
        $searchTerm = '%' . $query . '%';

        $results = [];
        $totalCount = 0;

        // Search based on type filter
        if ($type === 'all' || $type === 'listings') {
            $listingResults = self::searchListings(
                $searchTerm, $tenantId, $type === 'all' ? 10 : $limit, $offset,
                $searchTerms, $filters
            );
            $results = array_merge($results, $listingResults['items']);
            $totalCount += $listingResults['total'];
        }

        if ($type === 'all' || $type === 'users') {
            $userResults = self::searchUsers(
                $searchTerm, $tenantId, $type === 'all' ? 10 : $limit, $offset,
                $searchTerms, $filters
            );
            $results = array_merge($results, $userResults['items']);
            $totalCount += $userResults['total'];
        }

        if ($type === 'all' || $type === 'events') {
            $eventResults = self::searchEvents(
                $searchTerm, $tenantId, $type === 'all' ? 10 : $limit, $offset,
                $searchTerms, $filters
            );
            $results = array_merge($results, $eventResults['items']);
            $totalCount += $eventResults['total'];
        }

        if ($type === 'all' || $type === 'groups') {
            $groupResults = self::searchGroups(
                $searchTerm, $tenantId, $type === 'all' ? 10 : $limit, $offset,
                $searchTerms, $filters
            );
            $results = array_merge($results, $groupResults['items']);
            $totalCount += $groupResults['total'];
        }

        // Sort results
        $sort = $filters['sort'] ?? 'relevance';
        if ($type === 'all' || $sort !== 'relevance') {
            usort($results, function ($a, $b) use ($sort) {
                if ($sort === 'oldest') {
                    return strtotime($a['created_at'] ?? '1970-01-01') - strtotime($b['created_at'] ?? '1970-01-01');
                }
                // Default: newest first (relevance fallback)
                return strtotime($b['created_at'] ?? '1970-01-01') - strtotime($a['created_at'] ?? '1970-01-01');
            });
            if ($type === 'all') {
                $results = array_slice($results, 0, $limit);
            }
        }

        $hasMore = count($results) >= $limit;
        $nextCursor = $hasMore ? base64_encode((string)($offset + $limit)) : null;

        return [
            'items' => $results,
            'cursor' => $nextCursor,
            'has_more' => $hasMore,
            'total' => $totalCount,
            'query' => $query,
        ];
    }

    /**
     * Parse a search query supporting:
     * - Exact phrases: "time banking"
     * - NOT / exclusion: -spam, NOT spam
     * - AND: cooking AND baking
     * - OR: cooking OR baking
     *
     * Returns structured parsed terms for SQL WHERE clause building.
     *
     * @param string $query Raw user query
     * @return array ['must' => string[], 'must_not' => string[], 'exact' => string[]]
     */
    private static function parseSearchQuery(string $query): array
    {
        $must = [];
        $mustNot = [];
        $exact = [];

        // Extract quoted exact phrases first
        if (preg_match_all('/"([^"]+)"/', $query, $matches)) {
            foreach ($matches[1] as $phrase) {
                $exact[] = trim($phrase);
            }
            // Remove quoted phrases from query for further parsing
            $query = preg_replace('/"[^"]*"/', '', $query);
        }

        // Split remaining query by spaces
        $tokens = preg_split('/\s+/', trim($query));
        $tokens = array_filter($tokens, fn($t) => $t !== '');

        $i = 0;
        while ($i < count($tokens)) {
            $token = $tokens[$i];

            // Skip boolean operators (they modify the next token)
            if (strtoupper($token) === 'AND') {
                $i++;
                continue;
            }
            if (strtoupper($token) === 'OR') {
                $i++;
                continue;
            }

            // NOT prefix: -term or NOT term
            if (str_starts_with($token, '-') && strlen($token) > 1) {
                $mustNot[] = substr($token, 1);
                $i++;
                continue;
            }
            if (strtoupper($token) === 'NOT' && isset($tokens[$i + 1])) {
                $mustNot[] = $tokens[$i + 1];
                $i += 2;
                continue;
            }

            // Regular term
            if (strlen($token) >= 2) {
                $must[] = $token;
            }
            $i++;
        }

        return [
            'must' => $must,
            'must_not' => $mustNot,
            'exact' => $exact,
        ];
    }

    /**
     * Build SQL WHERE conditions for boolean search terms.
     *
     * @param array $searchTerms Parsed search terms
     * @param array $columns Column names to search in
     * @param array &$params Reference to params array to append to
     * @return string SQL WHERE fragment (empty string if no conditions)
     */
    private static function buildBooleanSearchSql(array $searchTerms, array $columns, array &$params): string
    {
        $conditions = [];

        // Exact phrases: all columns must match
        foreach ($searchTerms['exact'] as $phrase) {
            $phraseConditions = [];
            foreach ($columns as $col) {
                $phraseConditions[] = "{$col} LIKE ?";
                $params[] = '%' . $phrase . '%';
            }
            $conditions[] = '(' . implode(' OR ', $phraseConditions) . ')';
        }

        // Must NOT contain
        foreach ($searchTerms['must_not'] as $term) {
            foreach ($columns as $col) {
                $conditions[] = "({$col} NOT LIKE ? OR {$col} IS NULL)";
                $params[] = '%' . $term . '%';
            }
        }

        return !empty($conditions) ? implode(' AND ', $conditions) : '';
    }

    /**
     * Search listings with advanced filters and boolean search
     */
    private static function searchListings(
        string $searchTerm, int $tenantId, int $limit, int $offset,
        array $searchTerms = [], array $filters = []
    ): array {
        $db = Database::getConnection();

        // Build WHERE clause
        $where = "l.tenant_id = ? AND l.status = 'active'
                  AND (l.title LIKE ? OR l.description LIKE ? OR l.location LIKE ?)";
        $countParams = [$tenantId, $searchTerm, $searchTerm, $searchTerm];
        $queryParams = [$tenantId, $searchTerm, $searchTerm, $searchTerm];

        // Boolean search conditions
        if (!empty($searchTerms)) {
            $boolParams = [];
            $boolSql = self::buildBooleanSearchSql(
                $searchTerms,
                ['l.title', 'l.description'],
                $boolParams
            );
            if ($boolSql) {
                $where .= " AND {$boolSql}";
                $countParams = array_merge($countParams, $boolParams);
                $queryParams = array_merge($queryParams, $boolParams);
            }
        }

        // Category filter
        if (!empty($filters['category_id'])) {
            $where .= " AND l.category_id = ?";
            $countParams[] = (int)$filters['category_id'];
            $queryParams[] = (int)$filters['category_id'];
        }

        // Date range filter
        if (!empty($filters['date_from'])) {
            $where .= " AND l.created_at >= ?";
            $countParams[] = $filters['date_from'];
            $queryParams[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where .= " AND l.created_at <= ?";
            $countParams[] = $filters['date_to'] . ' 23:59:59';
            $queryParams[] = $filters['date_to'] . ' 23:59:59';
        }

        // Skills filter
        if (!empty($filters['skills'])) {
            $skills = is_array($filters['skills']) ? $filters['skills'] : explode(',', $filters['skills']);
            $skills = array_map('trim', array_filter($skills));
            if (!empty($skills)) {
                $skillPlaceholders = implode(',', array_fill(0, count($skills), '?'));
                $where .= " AND l.id IN (
                    SELECT lst.listing_id FROM listing_skill_tags lst
                    WHERE lst.tenant_id = ? AND lst.tag IN ({$skillPlaceholders})
                )";
                $countParams[] = $tenantId;
                $queryParams[] = $tenantId;
                foreach ($skills as $skill) {
                    $countParams[] = strtolower($skill);
                    $queryParams[] = strtolower($skill);
                }
            }
        }

        // Location filter
        if (!empty($filters['location'])) {
            $where .= " AND l.location LIKE ?";
            $locTerm = '%' . $filters['location'] . '%';
            $countParams[] = $locTerm;
            $queryParams[] = $locTerm;
        }

        // Sort
        $sort = $filters['sort'] ?? 'relevance';
        $orderBy = match ($sort) {
            'oldest' => 'l.created_at ASC',
            'newest' => 'l.created_at DESC',
            default => 'l.is_featured DESC, l.created_at DESC',
        };

        // Get total count
        $countStmt = $db->prepare("SELECT COUNT(*) as total FROM listings l WHERE {$where}");
        $countStmt->execute($countParams);
        $total = (int)$countStmt->fetch(\PDO::FETCH_ASSOC)['total'];

        // Get results
        $queryParams[] = $limit;
        $queryParams[] = $offset;

        $stmt = $db->prepare("
            SELECT
                l.id,
                l.title,
                l.description,
                l.type as listing_type,
                l.image_url,
                l.location,
                l.is_featured,
                l.hours_estimate,
                l.created_at,
                CASE
                    WHEN u.profile_type = 'organisation' AND u.organization_name IS NOT NULL AND u.organization_name != ''
                    THEN u.organization_name
                    ELSE CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))
                END as user_name,
                u.avatar_url as user_avatar
            FROM listings l
            JOIN users u ON l.user_id = u.id
            WHERE {$where}
            ORDER BY {$orderBy}
            LIMIT ? OFFSET ?
        ");
        $stmt->execute($queryParams);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $items = array_map(function ($row) {
            return [
                'type' => 'listing',
                'id' => (int)$row['id'],
                'title' => $row['title'],
                'description' => self::truncate($row['description'], 150),
                'listing_type' => $row['listing_type'],
                'image_url' => $row['image_url'],
                'location' => $row['location'],
                'is_featured' => (bool)($row['is_featured'] ?? false),
                'hours_estimate' => isset($row['hours_estimate']) ? (float)$row['hours_estimate'] : null,
                'user' => [
                    'name' => trim($row['user_name'] ?? ''),
                    'avatar_url' => $row['user_avatar'],
                ],
                'created_at' => $row['created_at'],
            ];
        }, $rows);

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Search users with advanced filters
     */
    private static function searchUsers(
        string $searchTerm, int $tenantId, int $limit, int $offset,
        array $searchTerms = [], array $filters = []
    ): array {
        $db = Database::getConnection();

        // Get total count
        $countStmt = $db->prepare("
            SELECT COUNT(*) as total FROM users
            WHERE tenant_id = ?
            AND status = 'active'
            AND (name LIKE ? OR CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ? OR bio LIKE ?)
        ");
        $countStmt->execute([$tenantId, $searchTerm, $searchTerm, $searchTerm]);
        $total = (int)$countStmt->fetch(\PDO::FETCH_ASSOC)['total'];

        // Get results
        $stmt = $db->prepare("
            SELECT
                id,
                CASE
                    WHEN profile_type = 'organisation' AND organization_name IS NOT NULL THEN organization_name
                    ELSE CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))
                END as name,
                avatar_url,
                bio,
                location,
                profile_type,
                created_at
            FROM users
            WHERE tenant_id = ?
            AND status = 'active'
            AND (name LIKE ? OR CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ? OR bio LIKE ?)
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$tenantId, $searchTerm, $searchTerm, $searchTerm, $limit, $offset]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $items = array_map(function ($row) {
            return [
                'type' => 'user',
                'id' => (int)$row['id'],
                'name' => trim($row['name']),
                'avatar_url' => $row['avatar_url'],
                'bio' => self::truncate($row['bio'], 150),
                'location' => $row['location'],
                'profile_type' => $row['profile_type'] ?? 'individual',
                'created_at' => $row['created_at'],
            ];
        }, $rows);

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Search events with advanced filters
     */
    private static function searchEvents(
        string $searchTerm, int $tenantId, int $limit, int $offset,
        array $searchTerms = [], array $filters = []
    ): array {
        $db = Database::getConnection();

        // Get total count
        $countStmt = $db->prepare("
            SELECT COUNT(*) as total FROM events
            WHERE tenant_id = ?
            AND (title LIKE ? OR description LIKE ? OR location LIKE ?)
        ");
        $countStmt->execute([$tenantId, $searchTerm, $searchTerm, $searchTerm]);
        $total = (int)$countStmt->fetch(\PDO::FETCH_ASSOC)['total'];

        // Get results
        $stmt = $db->prepare("
            SELECT
                e.id,
                e.title,
                e.description,
                e.cover_image,
                e.location,
                e.start_time,
                e.end_time,
                e.created_at,
                u.name as organizer_name,
                u.avatar_url as organizer_avatar
            FROM events e
            JOIN users u ON e.user_id = u.id
            WHERE e.tenant_id = ?
            AND (e.title LIKE ? OR e.description LIKE ? OR e.location LIKE ?)
            ORDER BY e.start_time DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$tenantId, $searchTerm, $searchTerm, $searchTerm, $limit, $offset]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $items = array_map(function ($row) {
            $now = new \DateTime();
            $startTime = new \DateTime($row['start_time']);

            return [
                'type' => 'event',
                'id' => (int)$row['id'],
                'title' => $row['title'],
                'description' => self::truncate($row['description'], 150),
                'image_url' => $row['cover_image'],
                'location' => $row['location'],
                'start_date' => $row['start_time'],
                'end_date' => $row['end_time'],
                'is_upcoming' => $startTime > $now,
                'organizer' => [
                    'name' => $row['organizer_name'],
                    'avatar_url' => $row['organizer_avatar'],
                ],
                'created_at' => $row['created_at'],
            ];
        }, $rows);

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Search groups with advanced filters
     */
    private static function searchGroups(
        string $searchTerm, int $tenantId, int $limit, int $offset,
        array $searchTerms = [], array $filters = []
    ): array {
        $db = Database::getConnection();

        // Get total count
        $countStmt = $db->prepare("
            SELECT COUNT(*) as total FROM `groups`
            WHERE tenant_id = ?
            AND (name LIKE ? OR description LIKE ?)
        ");
        $countStmt->execute([$tenantId, $searchTerm, $searchTerm]);
        $total = (int)$countStmt->fetch(\PDO::FETCH_ASSOC)['total'];

        // Get results with member count
        $stmt = $db->prepare("
            SELECT
                g.id,
                g.name,
                g.description,
                g.image_url,
                g.visibility,
                g.created_at,
                COUNT(DISTINCT gm.user_id) as member_count
            FROM `groups` g
            LEFT JOIN group_members gm ON g.id = gm.group_id
            WHERE g.tenant_id = ?
            AND (g.name LIKE ? OR g.description LIKE ?)
            GROUP BY g.id
            ORDER BY g.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$tenantId, $searchTerm, $searchTerm, $limit, $offset]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $items = array_map(function ($row) {
            return [
                'type' => 'group',
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'description' => self::truncate($row['description'], 150),
                'cover_image' => $row['image_url'],
                'privacy' => $row['visibility'] ?? 'public',
                'member_count' => (int)$row['member_count'],
                'created_at' => $row['created_at'],
            ];
        }, $rows);

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Get search suggestions (autocomplete)
     *
     * @param string $query Partial query
     * @param int $tenantId Tenant ID
     * @param int $limit Max suggestions
     * @return array Suggestions by type
     */
    public static function getSuggestions(string $query, int $tenantId, int $limit = 5): array
    {
        if (strlen($query) < 2) {
            return ['listings' => [], 'users' => [], 'events' => [], 'groups' => []];
        }

        $db = Database::getConnection();
        $searchTerm = '%' . $query . '%'; // Contains search for autocomplete

        $suggestions = [];

        // Listing titles
        $stmt = $db->prepare("
            SELECT id, title, 'listing' as type
            FROM listings
            WHERE tenant_id = ? AND status = 'active' AND title LIKE ?
            ORDER BY title
            LIMIT ?
        ");
        $stmt->execute([$tenantId, $searchTerm, $limit]);
        $suggestions['listings'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // User names
        $stmt = $db->prepare("
            SELECT id,
                CASE
                    WHEN profile_type = 'organisation' AND organization_name IS NOT NULL THEN organization_name
                    ELSE CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))
                END as name,
                'user' as type
            FROM users
            WHERE tenant_id = ? AND status = 'active'
            AND (
                CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ?
                OR organization_name LIKE ?
            )
            LIMIT ?
        ");
        $stmt->execute([$tenantId, $searchTerm, $searchTerm, $limit]);
        $suggestions['users'] = array_values(array_filter($stmt->fetchAll(\PDO::FETCH_ASSOC), function ($s) {
            return trim($s['name'] ?? '') !== '';
        }));

        // Event titles
        $stmt = $db->prepare("
            SELECT id, title, 'event' as type
            FROM events
            WHERE tenant_id = ? AND title LIKE ?
            ORDER BY title
            LIMIT ?
        ");
        $stmt->execute([$tenantId, $searchTerm, $limit]);
        $suggestions['events'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Group names
        $stmt = $db->prepare("
            SELECT id, name, 'group' as type
            FROM `groups`
            WHERE tenant_id = ? AND name LIKE ?
            ORDER BY name
            LIMIT ?
        ");
        $stmt->execute([$tenantId, $searchTerm, $limit]);
        $suggestions['groups'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $suggestions;
    }

    /**
     * Truncate text to a maximum length
     */
    private static function truncate(?string $text, int $length): ?string
    {
        if ($text === null) {
            return null;
        }

        $text = strip_tags($text);

        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length - 3) . '...';
    }
}
