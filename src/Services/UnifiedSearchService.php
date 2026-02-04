<?php

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
     * @param string $query Search query
     * @param int|null $userId User ID for personalization
     * @param array $filters [
     *   'type' => 'all' (default), 'listings', 'users', 'events', 'groups',
     *   'cursor' => string,
     *   'limit' => int (default: 20, max: 50)
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
        $searchTerm = '%' . $query . '%';

        $results = [];
        $totalCount = 0;

        // Search based on type filter
        if ($type === 'all' || $type === 'listings') {
            $listingResults = self::searchListings($searchTerm, $tenantId, $type === 'all' ? 10 : $limit, $offset);
            $results = array_merge($results, $listingResults['items']);
            $totalCount += $listingResults['total'];
        }

        if ($type === 'all' || $type === 'users') {
            $userResults = self::searchUsers($searchTerm, $tenantId, $type === 'all' ? 10 : $limit, $offset);
            $results = array_merge($results, $userResults['items']);
            $totalCount += $userResults['total'];
        }

        if ($type === 'all' || $type === 'events') {
            $eventResults = self::searchEvents($searchTerm, $tenantId, $type === 'all' ? 10 : $limit, $offset);
            $results = array_merge($results, $eventResults['items']);
            $totalCount += $eventResults['total'];
        }

        if ($type === 'all' || $type === 'groups') {
            $groupResults = self::searchGroups($searchTerm, $tenantId, $type === 'all' ? 10 : $limit, $offset);
            $results = array_merge($results, $groupResults['items']);
            $totalCount += $groupResults['total'];
        }

        // If searching all types, sort by relevance (created_at for now)
        if ($type === 'all') {
            usort($results, function ($a, $b) {
                return strtotime($b['created_at'] ?? '1970-01-01') - strtotime($a['created_at'] ?? '1970-01-01');
            });
            $results = array_slice($results, 0, $limit);
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
     * Search listings
     */
    private static function searchListings(string $searchTerm, int $tenantId, int $limit, int $offset): array
    {
        $db = Database::getConnection();

        // Get total count
        $countStmt = $db->prepare("
            SELECT COUNT(*) as total FROM listings
            WHERE tenant_id = ?
            AND status = 'active'
            AND (title LIKE ? OR description LIKE ? OR location LIKE ?)
        ");
        $countStmt->execute([$tenantId, $searchTerm, $searchTerm, $searchTerm]);
        $total = (int)$countStmt->fetch(\PDO::FETCH_ASSOC)['total'];

        // Get results
        $stmt = $db->prepare("
            SELECT
                l.id,
                l.title,
                l.description,
                l.type as listing_type,
                l.image_url,
                l.location,
                l.created_at,
                u.name as user_name,
                u.avatar_url as user_avatar
            FROM listings l
            JOIN users u ON l.user_id = u.id
            WHERE l.tenant_id = ?
            AND l.status = 'active'
            AND (l.title LIKE ? OR l.description LIKE ? OR l.location LIKE ?)
            ORDER BY l.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$tenantId, $searchTerm, $searchTerm, $searchTerm, $limit, $offset]);
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
                'user' => [
                    'name' => $row['user_name'],
                    'avatar_url' => $row['user_avatar'],
                ],
                'created_at' => $row['created_at'],
            ];
        }, $rows);

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Search users
     */
    private static function searchUsers(string $searchTerm, int $tenantId, int $limit, int $offset): array
    {
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
     * Search events
     */
    private static function searchEvents(string $searchTerm, int $tenantId, int $limit, int $offset): array
    {
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
                e.image_url,
                e.location,
                e.start_date,
                e.end_date,
                e.created_at,
                u.name as organizer_name,
                u.avatar_url as organizer_avatar
            FROM events e
            JOIN users u ON e.created_by = u.id
            WHERE e.tenant_id = ?
            AND (e.title LIKE ? OR e.description LIKE ? OR e.location LIKE ?)
            ORDER BY e.start_date DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$tenantId, $searchTerm, $searchTerm, $searchTerm, $limit, $offset]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $items = array_map(function ($row) {
            $now = new \DateTime();
            $startDate = new \DateTime($row['start_date']);

            return [
                'type' => 'event',
                'id' => (int)$row['id'],
                'title' => $row['title'],
                'description' => self::truncate($row['description'], 150),
                'image_url' => $row['image_url'],
                'location' => $row['location'],
                'start_date' => $row['start_date'],
                'end_date' => $row['end_date'],
                'is_upcoming' => $startDate > $now,
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
     * Search groups
     */
    private static function searchGroups(string $searchTerm, int $tenantId, int $limit, int $offset): array
    {
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
                g.cover_image,
                g.privacy,
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
                'cover_image' => $row['cover_image'],
                'privacy' => $row['privacy'] ?? 'public',
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
        $searchTerm = $query . '%'; // Prefix search for autocomplete

        $suggestions = [];

        // Listing titles
        $stmt = $db->prepare("
            SELECT DISTINCT title as suggestion, 'listing' as type
            FROM listings
            WHERE tenant_id = ? AND status = 'active' AND title LIKE ?
            LIMIT ?
        ");
        $stmt->execute([$tenantId, $searchTerm, $limit]);
        $suggestions['listings'] = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'suggestion');

        // User names
        $stmt = $db->prepare("
            SELECT DISTINCT
                CASE
                    WHEN profile_type = 'organisation' AND organization_name IS NOT NULL THEN organization_name
                    ELSE CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))
                END as suggestion,
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
        $suggestions['users'] = array_filter(array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'suggestion'), function ($s) {
            return trim($s) !== '';
        });

        // Event titles
        $stmt = $db->prepare("
            SELECT DISTINCT title as suggestion, 'event' as type
            FROM events
            WHERE tenant_id = ? AND title LIKE ?
            LIMIT ?
        ");
        $stmt->execute([$tenantId, $searchTerm, $limit]);
        $suggestions['events'] = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'suggestion');

        // Group names
        $stmt = $db->prepare("
            SELECT DISTINCT name as suggestion, 'group' as type
            FROM `groups`
            WHERE tenant_id = ? AND name LIKE ?
            LIMIT ?
        ");
        $stmt->execute([$tenantId, $searchTerm, $limit]);
        $suggestions['groups'] = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'suggestion');

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
