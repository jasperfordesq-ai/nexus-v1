<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\FeedPost;
use Nexus\Models\User;
use Nexus\Services\RealtimeService;
use Nexus\Services\HashtagService;

/**
 * FeedService - Business logic for social feed operations
 *
 * This service provides cursor-paginated feed operations for the v2 API.
 *
 * Key operations:
 * - Get aggregated feed (posts, listings, events, etc.)
 * - Get user profile feed
 * - Get group feed
 * - Create post
 * - Like/unlike content
 */
class FeedService
{
    /**
     * Validation error messages
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
     * Get main feed with cursor-based pagination
     *
     * @param int|null $userId Current user (for like status)
     * @param array $filters [
     *   'type' => 'all' (default), 'posts', 'listings', 'events', 'polls', 'goals',
     *            'jobs', 'challenges', 'volunteering',
     *   'user_id' => int (for user profile feed),
     *   'group_id' => int (for group feed),
     *   'cursor' => string,
     *   'limit' => int (default: 20, max: 100)
     * ]
     * @return array ['items' => [], 'cursor' => string|null, 'has_more' => bool]
     */
    public static function getFeed(?int $userId, array $filters = []): array
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        $limit = min($filters['limit'] ?? 20, 100);
        $type = $filters['type'] ?? 'all';
        $profileUserId = $filters['user_id'] ?? null;
        $groupId = $filters['group_id'] ?? null;
        $cursor = $filters['cursor'] ?? null;

        // Decode cursor — supports both new (activity_id) and legacy (type_timestamp_id) formats
        $cursorActivityId = null;
        $cursorData = null;
        if ($cursor) {
            $decoded = base64_decode($cursor, true);
            if ($decoded !== false) {
                if (ctype_digit($decoded)) {
                    // New format: just the activity_id
                    $cursorActivityId = (int)$decoded;
                } else {
                    // Legacy format: "type_timestamp_id"
                    $parts = explode('_', $decoded, 3);
                    if (count($parts) === 3) {
                        $cursorData = [
                            'type' => $parts[0],
                            'created_at' => $parts[1],
                            'id' => (int)$parts[2],
                        ];
                    }
                }
            }
        }

        $items = [];

        // Try feed_activity table first (unified single-query approach)
        $useFeedActivity = false;
        try {
            $db->query("SELECT 1 FROM feed_activity LIMIT 1");
            $useFeedActivity = true;
        } catch (\Exception $e) {
            // Table doesn't exist yet — fall back to legacy N-query approach
        }

        if ($useFeedActivity) {
            // Map plural filter names to source_type values
            $sourceType = null;
            if ($type !== 'all') {
                $typeMap = [
                    'posts' => 'post', 'listings' => 'listing', 'events' => 'event',
                    'polls' => 'poll', 'goals' => 'goal', 'jobs' => 'job',
                    'challenges' => 'challenge', 'volunteering' => 'volunteer',
                ];
                $sourceType = $typeMap[$type] ?? $type;
            }

            $items = self::loadFromFeedActivity(
                $userId, $tenantId, $limit + 1,
                $cursorActivityId, $cursorData,
                $sourceType, $profileUserId, $groupId
            );
        } else {
            // Legacy fallback: N-query aggregation (removed once migration is confirmed)
            if ($groupId) {
                $items = self::loadGroupFeed($groupId, $userId, $tenantId, $limit + 1, $cursorData);
            } elseif ($profileUserId) {
                $items = self::loadUserFeed($profileUserId, $userId, $tenantId, $limit + 1, $cursorData);
            } elseif ($type !== 'all') {
                $items = self::loadTypedFeed($type, $userId, $tenantId, $limit + 1, $cursorData);
            } else {
                $items = self::loadAggregatedFeed($userId, $tenantId, $limit + 1, $cursorData);
            }
        }

        // Check if there are more items
        $hasMore = count($items) > $limit;
        if ($hasMore) {
            array_pop($items);
        }

        // Generate cursor from last item
        $nextCursor = null;
        if ($hasMore && !empty($items)) {
            $lastItem = end($items);
            if ($useFeedActivity && isset($lastItem['_activity_id'])) {
                // New cursor format: just the activity_id
                $nextCursor = base64_encode((string)$lastItem['_activity_id']);
            } else {
                // Legacy cursor format
                $nextCursor = base64_encode("{$lastItem['type']}_{$lastItem['created_at']}_{$lastItem['id']}");
            }
        }

        // Strip internal _activity_id from output
        if ($useFeedActivity) {
            foreach ($items as &$item) {
                unset($item['_activity_id']);
            }
            unset($item);
        }

        return [
            'items' => $items,
            'cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Load feed from the unified feed_activity table (single query).
     *
     * Replaces the N-query aggregation with a single indexed query.
     * Supports all feed modes: main, user profile, group, type-filtered.
     */
    private static function loadFromFeedActivity(
        ?int $userId,
        int $tenantId,
        int $limit,
        ?int $cursorActivityId,
        ?array $legacyCursorData,
        ?string $sourceType = null,
        ?int $profileUserId = null,
        ?int $groupId = null
    ): array {
        $db = Database::getConnection();

        $sql = "
            SELECT fa.id as activity_id, fa.source_type, fa.source_id, fa.user_id,
                   fa.title, fa.content, fa.image_url, fa.metadata, fa.group_id, fa.created_at,
                   COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name,
                   u.avatar_url as author_avatar,
                   (SELECT COUNT(*) FROM likes WHERE target_type = fa.source_type AND target_id = fa.source_id) as likes_count,
                   (SELECT COUNT(*) FROM comments WHERE target_type = fa.source_type AND target_id = fa.source_id) as comments_count
            FROM feed_activity fa
            JOIN users u ON fa.user_id = u.id
            WHERE fa.tenant_id = ? AND fa.is_visible = 1
        ";
        $params = [$tenantId];

        // Apply filters
        if ($sourceType !== null) {
            $sql .= " AND fa.source_type = ?";
            $params[] = $sourceType;
        }

        if ($profileUserId !== null) {
            $sql .= " AND fa.user_id = ?";
            $params[] = $profileUserId;
        }

        if ($groupId !== null) {
            $sql .= " AND fa.group_id = ?";
            $params[] = $groupId;
        }

        // Cursor: new format (activity_id) takes priority
        if ($cursorActivityId !== null) {
            $sql .= " AND fa.id < ?";
            $params[] = $cursorActivityId;
        } elseif ($legacyCursorData !== null) {
            // Legacy cursor: convert timestamp+id to activity_id boundary
            $sql .= " AND (fa.created_at < ? OR (fa.created_at = ? AND fa.id < ?))";
            $params[] = $legacyCursorData['created_at'];
            $params[] = $legacyCursorData['created_at'];
            // Use a high fallback since legacy id may not match activity_id
            $params[] = PHP_INT_MAX;
        }

        $sql .= " ORDER BY fa.id DESC LIMIT {$limit}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Transform rows into the format expected by formatItems()
        $items = [];
        foreach ($rows as $row) {
            $meta = $row['metadata'] ? json_decode($row['metadata'], true) : [];

            $item = [
                'id' => (int)$row['source_id'],
                'type' => $row['source_type'],
                'title' => $row['title'],
                'content' => $row['content'],
                'image_url' => $row['image_url'],
                'user_id' => (int)$row['user_id'],
                'author_name' => $row['author_name'],
                'author_avatar' => $row['author_avatar'],
                'likes_count' => (int)$row['likes_count'],
                'comments_count' => (int)$row['comments_count'],
                'created_at' => $row['created_at'],
                // Event metadata
                'start_date' => $meta['start_date'] ?? null,
                'location' => $meta['location'] ?? null,
                // Review metadata
                'rating' => isset($meta['rating']) ? (int)$meta['rating'] : null,
                'receiver' => isset($meta['receiver_id']) ? ['id' => (int)$meta['receiver_id'], 'name' => ''] : null,
                // Job metadata
                'job_type' => $meta['job_type'] ?? null,
                'commitment' => $meta['commitment'] ?? null,
                // Challenge metadata
                'submission_deadline' => $meta['submission_deadline'] ?? null,
                'ideas_count' => isset($meta['ideas_count']) ? (int)$meta['ideas_count'] : null,
                // Volunteer metadata
                'credits_offered' => isset($meta['credits_offered']) ? (int)$meta['credits_offered'] : null,
                'organization' => $meta['organization'] ?? null,
            ];

            $items[] = $item;
        }

        // Enrich with like status and poll data via existing batch methods
        $formatted = self::formatItems($items, $userId);

        // Attach activity_id for cursor generation (stripped before output)
        foreach ($formatted as $i => &$fItem) {
            $fItem['_activity_id'] = (int)$rows[$i]['activity_id'];
        }
        unset($fItem);

        // For reviews, enrich receiver names
        self::enrichReviewReceivers($formatted);

        return $formatted;
    }

    /**
     * Batch-load receiver names for review-type items.
     * The feed_activity metadata only stores receiver_id, not the name.
     */
    private static function enrichReviewReceivers(array &$items): void
    {
        $receiverIds = [];
        foreach ($items as $item) {
            if ($item['type'] === 'review' && isset($item['receiver']['id']) && $item['receiver']['id'] > 0) {
                $receiverIds[] = $item['receiver']['id'];
            }
        }

        if (empty($receiverIds)) {
            return;
        }

        $db = Database::getConnection();
        $placeholders = implode(',', array_fill(0, count($receiverIds), '?'));
        $stmt = $db->prepare(
            "SELECT id, COALESCE(name, CONCAT(first_name, ' ', last_name)) as name FROM users WHERE id IN ($placeholders)"
        );
        $stmt->execute($receiverIds);
        $nameMap = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $nameMap[(int)$row['id']] = $row['name'];
        }

        foreach ($items as &$item) {
            if ($item['type'] === 'review' && isset($item['receiver']['id'])) {
                $item['receiver']['name'] = $nameMap[$item['receiver']['id']] ?? 'Unknown';
            }
        }
        unset($item);
    }

    /**
     * Load group-specific feed
     */
    private static function loadGroupFeed(int $groupId, ?int $userId, int $tenantId, int $limit, ?array $cursorData): array
    {
        $db = Database::getConnection();

        // Check if group_id column exists
        try {
            $col = $db->query("SHOW COLUMNS FROM feed_posts LIKE 'group_id'")->fetch();
            if (!$col) {
                return [];
            }
        } catch (\Exception $e) {
            return [];
        }

        $sql = "
            SELECT p.id, p.content, p.image_url, p.created_at, p.likes_count, p.user_id,
                   'post' as type,
                   COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name,
                   u.avatar_url as author_avatar,
                   (SELECT COUNT(*) FROM comments WHERE target_type = 'post' AND target_id = p.id) as comments_count
            FROM feed_posts p
            JOIN users u ON p.user_id = u.id
            WHERE p.group_id = ? AND p.tenant_id = ?
        ";
        $params = [$groupId, $tenantId];

        if ($cursorData) {
            $sql .= " AND (p.created_at < ? OR (p.created_at = ? AND p.id < ?))";
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['id'];
        }

        $sql .= " ORDER BY p.created_at DESC, p.id DESC LIMIT {$limit}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $posts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return self::formatItems($posts, $userId);
    }

    /**
     * Load user profile feed — aggregates all content types for a single user.
     * Mirrors loadAggregatedFeed() but scoped to profileUserId.
     */
    private static function loadUserFeed(int $profileUserId, ?int $userId, int $tenantId, int $limit, ?array $cursorData): array
    {
        $perTypeLimit = (int)ceil($limit * 1.5);

        $items = [];
        $items = array_merge($items, self::loadUserPosts($profileUserId, $userId, $tenantId, $perTypeLimit, $cursorData));
        $items = array_merge($items, self::loadUserListings($profileUserId, $userId, $tenantId, (int)ceil($perTypeLimit / 2), $cursorData));
        $items = array_merge($items, self::loadUserEvents($profileUserId, $userId, $tenantId, (int)ceil($perTypeLimit / 3), $cursorData));
        $items = array_merge($items, self::loadUserPolls($profileUserId, $userId, $tenantId, (int)ceil($perTypeLimit / 3), $cursorData));
        $items = array_merge($items, self::loadUserGoals($profileUserId, $userId, $tenantId, (int)ceil($perTypeLimit / 3), $cursorData));
        $items = array_merge($items, self::loadUserReviews($profileUserId, $userId, $tenantId, (int)ceil($perTypeLimit / 3), $cursorData));
        $items = array_merge($items, self::loadUserJobs($profileUserId, $userId, $tenantId, (int)ceil($perTypeLimit / 3), $cursorData));
        $items = array_merge($items, self::loadUserChallenges($profileUserId, $userId, $tenantId, (int)ceil($perTypeLimit / 3), $cursorData));
        $items = array_merge($items, self::loadUserVolunteerOpportunities($profileUserId, $userId, $tenantId, (int)ceil($perTypeLimit / 3), $cursorData));

        // Sort by created_at descending, then id descending
        usort($items, function ($a, $b) {
            $cmp = strcmp($b['created_at'], $a['created_at']);
            if ($cmp === 0) {
                return $b['id'] - $a['id'];
            }
            return $cmp;
        });

        // Apply cursor filtering AFTER merge+sort (same logic as loadAggregatedFeed)
        if ($cursorData) {
            $cursorTs = $cursorData['created_at'];
            $cursorId = (int)$cursorData['id'];
            $items = array_values(array_filter($items, function ($item) use ($cursorTs, $cursorId) {
                $cmp = strcmp($item['created_at'], $cursorTs);
                if ($cmp !== 0) return $cmp < 0;
                return $item['id'] < $cursorId;
            }));
        }

        return array_slice($items, 0, $limit);
    }

    /**
     * Load posts for a specific user's profile feed
     */
    private static function loadUserPosts(int $profileUserId, ?int $userId, int $tenantId, int $limit, ?array $cursorData): array
    {
        $db = Database::getConnection();

        $sql = "
            SELECT p.id, p.content, p.image_url, p.created_at, p.likes_count, p.user_id,
                   'post' as type,
                   COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name,
                   u.avatar_url as author_avatar,
                   (SELECT COUNT(*) FROM comments WHERE target_type = 'post' AND target_id = p.id) as comments_count
            FROM feed_posts p
            JOIN users u ON p.user_id = u.id
            WHERE p.user_id = ? AND p.tenant_id = ? AND p.visibility = 'public'
        ";
        $params = [$profileUserId, $tenantId];

        if ($cursorData && $cursorData['type'] === 'post') {
            $sql .= " AND (p.created_at < ? OR (p.created_at = ? AND p.id < ?))";
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['id'];
        }

        $sql .= " ORDER BY p.created_at DESC, p.id DESC LIMIT {$limit}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return self::formatItems($stmt->fetchAll(\PDO::FETCH_ASSOC), $userId);
    }

    /**
     * Load listings for a specific user's profile feed
     */
    private static function loadUserListings(int $profileUserId, ?int $userId, int $tenantId, int $limit, ?array $cursorData): array
    {
        $db = Database::getConnection();

        $sql = "
            SELECT l.id, l.title, l.description as content, l.image_url, l.created_at, l.user_id,
                   'listing' as type,
                   COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name,
                   u.avatar_url as author_avatar,
                   (SELECT COUNT(*) FROM likes WHERE target_type = 'listing' AND target_id = l.id) as likes_count,
                   (SELECT COUNT(*) FROM comments WHERE target_type = 'listing' AND target_id = l.id) as comments_count
            FROM listings l
            JOIN users u ON l.user_id = u.id
            WHERE l.user_id = ? AND l.tenant_id = ? AND l.status = 'active'
        ";
        $params = [$profileUserId, $tenantId];

        if ($cursorData && $cursorData['type'] === 'listing') {
            $sql .= " AND (l.created_at < ? OR (l.created_at = ? AND l.id < ?))";
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['id'];
        }

        $sql .= " ORDER BY l.created_at DESC, l.id DESC LIMIT {$limit}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return self::formatItems($stmt->fetchAll(\PDO::FETCH_ASSOC), $userId);
    }

    /**
     * Load events for a specific user's profile feed
     */
    private static function loadUserEvents(int $profileUserId, ?int $userId, int $tenantId, int $limit, ?array $cursorData): array
    {
        $db = Database::getConnection();

        $sql = "
            SELECT e.id, e.title, e.description as content, e.cover_image as image_url, e.created_at, e.user_id,
                   e.start_time as start_date, e.location,
                   'event' as type,
                   COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name,
                   u.avatar_url as author_avatar,
                   (SELECT COUNT(*) FROM likes WHERE target_type = 'event' AND target_id = e.id) as likes_count,
                   (SELECT COUNT(*) FROM comments WHERE target_type = 'event' AND target_id = e.id) as comments_count
            FROM events e
            JOIN users u ON e.user_id = u.id
            WHERE e.user_id = ? AND e.tenant_id = ?
        ";
        $params = [$profileUserId, $tenantId];

        if ($cursorData && $cursorData['type'] === 'event') {
            $sql .= " AND (e.created_at < ? OR (e.created_at = ? AND e.id < ?))";
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['id'];
        }

        $sql .= " ORDER BY e.created_at DESC, e.id DESC LIMIT {$limit}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return self::formatItems($stmt->fetchAll(\PDO::FETCH_ASSOC), $userId);
    }

    /**
     * Load polls for a specific user's profile feed
     */
    private static function loadUserPolls(int $profileUserId, ?int $userId, int $tenantId, int $limit, ?array $cursorData): array
    {
        $db = Database::getConnection();

        $sql = "
            SELECT po.id, po.question as title, po.question as content, po.created_at, po.user_id,
                   'poll' as type,
                   COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name,
                   u.avatar_url as author_avatar,
                   (SELECT COUNT(*) FROM likes WHERE target_type = 'poll' AND target_id = po.id) as likes_count,
                   (SELECT COUNT(*) FROM comments WHERE target_type = 'poll' AND target_id = po.id) as comments_count
            FROM polls po
            JOIN users u ON po.user_id = u.id
            WHERE po.user_id = ? AND po.tenant_id = ? AND po.is_active = 1
        ";
        $params = [$profileUserId, $tenantId];

        if ($cursorData && $cursorData['type'] === 'poll') {
            $sql .= " AND (po.created_at < ? OR (po.created_at = ? AND po.id < ?))";
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['id'];
        }

        $sql .= " ORDER BY po.created_at DESC, po.id DESC LIMIT {$limit}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return self::formatItems($stmt->fetchAll(\PDO::FETCH_ASSOC), $userId);
    }

    /**
     * Load goals for a specific user's profile feed
     */
    private static function loadUserGoals(int $profileUserId, ?int $userId, int $tenantId, int $limit, ?array $cursorData): array
    {
        $db = Database::getConnection();

        $sql = "
            SELECT g.id, g.title, g.description as content, g.created_at, g.user_id,
                   'goal' as type,
                   COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name,
                   u.avatar_url as author_avatar,
                   (SELECT COUNT(*) FROM likes WHERE target_type = 'goal' AND target_id = g.id) as likes_count,
                   (SELECT COUNT(*) FROM comments WHERE target_type = 'goal' AND target_id = g.id) as comments_count
            FROM goals g
            JOIN users u ON g.user_id = u.id
            WHERE g.user_id = ? AND g.tenant_id = ?
        ";
        $params = [$profileUserId, $tenantId];

        if ($cursorData && $cursorData['type'] === 'goal') {
            $sql .= " AND (g.created_at < ? OR (g.created_at = ? AND g.id < ?))";
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['id'];
        }

        $sql .= " ORDER BY g.created_at DESC, g.id DESC LIMIT {$limit}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return self::formatItems($stmt->fetchAll(\PDO::FETCH_ASSOC), $userId);
    }

    /**
     * Load reviews written BY a specific user for their profile feed
     */
    private static function loadUserReviews(int $profileUserId, ?int $userId, int $tenantId, int $limit, ?array $cursorData): array
    {
        $db = Database::getConnection();

        $sql = "
            SELECT r.id, r.rating, r.comment as content, r.created_at, r.reviewer_id as user_id,
                   'review' as type,
                   COALESCE(reviewer.name, CONCAT(reviewer.first_name, ' ', reviewer.last_name)) as author_name,
                   reviewer.avatar_url as author_avatar,
                   COALESCE(receiver.name, CONCAT(receiver.first_name, ' ', receiver.last_name)) as receiver_name,
                   receiver.id as receiver_id,
                   (SELECT COUNT(*) FROM likes WHERE target_type = 'review' AND target_id = r.id) as likes_count,
                   (SELECT COUNT(*) FROM comments WHERE target_type = 'review' AND target_id = r.id) as comments_count
            FROM reviews r
            JOIN users reviewer ON r.reviewer_id = reviewer.id
            JOIN users receiver ON r.receiver_id = receiver.id
            WHERE r.reviewer_id = ? AND r.reviewer_tenant_id = ? AND r.status = 'approved'
        ";
        $params = [$profileUserId, $tenantId];

        if ($cursorData && $cursorData['type'] === 'review') {
            $sql .= " AND (r.created_at < ? OR (r.created_at = ? AND r.id < ?))";
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['id'];
        }

        $sql .= " ORDER BY r.created_at DESC, r.id DESC LIMIT {$limit}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $reviews = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($reviews as &$review) {
            $review['rating'] = (int)$review['rating'];
            $review['receiver'] = [
                'id' => (int)$review['receiver_id'],
                'name' => $review['receiver_name'],
            ];
        }

        return self::formatItems($reviews, $userId);
    }

    /**
     * Load feed filtered by content type
     */
    private static function loadTypedFeed(string $type, ?int $userId, int $tenantId, int $limit, ?array $cursorData): array
    {
        $db = Database::getConnection();

        switch ($type) {
            case 'posts':
                return self::loadPosts($userId, $tenantId, $limit, $cursorData);
            case 'listings':
                return self::loadListings($userId, $tenantId, $limit, $cursorData);
            case 'events':
                return self::loadEvents($userId, $tenantId, $limit, $cursorData);
            case 'polls':
                return self::loadPolls($userId, $tenantId, $limit, $cursorData);
            case 'goals':
                return self::loadGoals($userId, $tenantId, $limit, $cursorData);
            case 'jobs':
                return self::loadJobs($userId, $tenantId, $limit, $cursorData);
            case 'challenges':
                return self::loadChallenges($userId, $tenantId, $limit, $cursorData);
            case 'volunteering':
                return self::loadVolunteerOpportunities($userId, $tenantId, $limit, $cursorData);
            default:
                return [];
        }
    }

    /**
     * Load aggregated feed from all content types
     */
    private static function loadAggregatedFeed(?int $userId, int $tenantId, int $limit, ?array $cursorData): array
    {
        // For aggregated feed, we query each type with a reasonable limit
        // and merge/sort them. This is less efficient but provides mixed content.
        $perTypeLimit = ceil($limit * 1.5);

        $items = [];
        $items = array_merge($items, self::loadPosts($userId, $tenantId, $perTypeLimit, $cursorData));
        $items = array_merge($items, self::loadListings($userId, $tenantId, ceil($perTypeLimit / 2), $cursorData));
        $items = array_merge($items, self::loadEvents($userId, $tenantId, ceil($perTypeLimit / 3), $cursorData));
        $items = array_merge($items, self::loadPolls($userId, $tenantId, ceil($perTypeLimit / 3), $cursorData));
        $items = array_merge($items, self::loadGoals($userId, $tenantId, ceil($perTypeLimit / 3), $cursorData));
        $items = array_merge($items, self::loadReviews($userId, $tenantId, ceil($perTypeLimit / 3), $cursorData));
        $items = array_merge($items, self::loadJobs($userId, $tenantId, ceil($perTypeLimit / 3), $cursorData));
        $items = array_merge($items, self::loadChallenges($userId, $tenantId, ceil($perTypeLimit / 3), $cursorData));
        $items = array_merge($items, self::loadVolunteerOpportunities($userId, $tenantId, ceil($perTypeLimit / 3), $cursorData));

        // Sort by created_at descending
        usort($items, function ($a, $b) {
            $cmp = strcmp($b['created_at'], $a['created_at']);
            if ($cmp === 0) {
                return $b['id'] - $a['id'];
            }
            return $cmp;
        });

        // Apply cursor filtering AFTER merge+sort to handle cross-type pagination.
        // Individual loaders only filter when cursor type matches their own type,
        // so non-matching types return their first N items unfiltered — causing duplicates.
        // Since the sort order is uniform (created_at DESC, id DESC) across all types,
        // we filter uniformly: keep only items that sort AFTER the cursor position.
        if ($cursorData) {
            $cursorTs = $cursorData['created_at'];
            $cursorId = (int)$cursorData['id'];
            $items = array_values(array_filter($items, function ($item) use ($cursorTs, $cursorId) {
                $cmp = strcmp($item['created_at'], $cursorTs);
                if ($cmp !== 0) return $cmp < 0; // Keep only items older than cursor
                return $item['id'] < $cursorId;   // Same timestamp: keep lower IDs
            }));
        }

        return array_slice($items, 0, $limit);
    }

    /**
     * Load posts
     */
    private static function loadPosts(?int $userId, int $tenantId, int $limit, ?array $cursorData): array
    {
        $db = Database::getConnection();

        $sql = "
            SELECT p.id, p.content, p.image_url, p.created_at, p.likes_count, p.user_id,
                   'post' as type,
                   COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name,
                   u.avatar_url as author_avatar,
                   (SELECT COUNT(*) FROM comments WHERE target_type = 'post' AND target_id = p.id) as comments_count
            FROM feed_posts p
            JOIN users u ON p.user_id = u.id
            WHERE p.tenant_id = ? AND p.visibility = 'public'
        ";
        $params = [$tenantId];

        if ($cursorData && $cursorData['type'] === 'post') {
            $sql .= " AND (p.created_at < ? OR (p.created_at = ? AND p.id < ?))";
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['id'];
        }

        $sql .= " ORDER BY p.created_at DESC, p.id DESC LIMIT {$limit}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return self::formatItems($stmt->fetchAll(\PDO::FETCH_ASSOC), $userId);
    }

    /**
     * Load listings
     */
    private static function loadListings(?int $userId, int $tenantId, int $limit, ?array $cursorData): array
    {
        $db = Database::getConnection();

        $sql = "
            SELECT l.id, l.title, l.description as content, l.image_url, l.created_at, l.user_id,
                   'listing' as type,
                   COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name,
                   u.avatar_url as author_avatar,
                   (SELECT COUNT(*) FROM likes WHERE target_type = 'listing' AND target_id = l.id) as likes_count,
                   (SELECT COUNT(*) FROM comments WHERE target_type = 'listing' AND target_id = l.id) as comments_count
            FROM listings l
            JOIN users u ON l.user_id = u.id
            WHERE l.tenant_id = ? AND l.status = 'active'
        ";
        $params = [$tenantId];

        if ($cursorData && $cursorData['type'] === 'listing') {
            $sql .= " AND (l.created_at < ? OR (l.created_at = ? AND l.id < ?))";
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['id'];
        }

        $sql .= " ORDER BY l.created_at DESC, l.id DESC LIMIT {$limit}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return self::formatItems($stmt->fetchAll(\PDO::FETCH_ASSOC), $userId);
    }

    /**
     * Load events
     */
    private static function loadEvents(?int $userId, int $tenantId, int $limit, ?array $cursorData): array
    {
        $db = Database::getConnection();

        $sql = "
            SELECT e.id, e.title, e.description as content, e.cover_image as image_url, e.created_at, e.user_id,
                   e.start_time as start_date, e.location,
                   'event' as type,
                   COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name,
                   u.avatar_url as author_avatar,
                   (SELECT COUNT(*) FROM likes WHERE target_type = 'event' AND target_id = e.id) as likes_count,
                   (SELECT COUNT(*) FROM comments WHERE target_type = 'event' AND target_id = e.id) as comments_count
            FROM events e
            JOIN users u ON e.user_id = u.id
            WHERE e.tenant_id = ?
        ";
        $params = [$tenantId];

        if ($cursorData && $cursorData['type'] === 'event') {
            $sql .= " AND (e.created_at < ? OR (e.created_at = ? AND e.id < ?))";
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['id'];
        }

        $sql .= " ORDER BY e.created_at DESC, e.id DESC LIMIT {$limit}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return self::formatItems($stmt->fetchAll(\PDO::FETCH_ASSOC), $userId);
    }

    /**
     * Load polls
     */
    private static function loadPolls(?int $userId, int $tenantId, int $limit, ?array $cursorData): array
    {
        $db = Database::getConnection();

        $sql = "
            SELECT po.id, po.question as title, po.question as content, po.created_at, po.user_id,
                   'poll' as type,
                   COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name,
                   u.avatar_url as author_avatar,
                   (SELECT COUNT(*) FROM likes WHERE target_type = 'poll' AND target_id = po.id) as likes_count,
                   (SELECT COUNT(*) FROM comments WHERE target_type = 'poll' AND target_id = po.id) as comments_count
            FROM polls po
            JOIN users u ON po.user_id = u.id
            WHERE po.tenant_id = ? AND po.is_active = 1
        ";
        $params = [$tenantId];

        if ($cursorData && $cursorData['type'] === 'poll') {
            $sql .= " AND (po.created_at < ? OR (po.created_at = ? AND po.id < ?))";
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['id'];
        }

        $sql .= " ORDER BY po.created_at DESC, po.id DESC LIMIT {$limit}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return self::formatItems($stmt->fetchAll(\PDO::FETCH_ASSOC), $userId);
    }

    /**
     * Load goals
     */
    private static function loadGoals(?int $userId, int $tenantId, int $limit, ?array $cursorData): array
    {
        $db = Database::getConnection();

        $sql = "
            SELECT g.id, g.title, g.description as content, g.created_at, g.user_id,
                   'goal' as type,
                   COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name,
                   u.avatar_url as author_avatar,
                   (SELECT COUNT(*) FROM likes WHERE target_type = 'goal' AND target_id = g.id) as likes_count,
                   (SELECT COUNT(*) FROM comments WHERE target_type = 'goal' AND target_id = g.id) as comments_count
            FROM goals g
            JOIN users u ON g.user_id = u.id
            WHERE g.tenant_id = ?
        ";
        $params = [$tenantId];

        if ($cursorData && $cursorData['type'] === 'goal') {
            $sql .= " AND (g.created_at < ? OR (g.created_at = ? AND g.id < ?))";
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['id'];
        }

        $sql .= " ORDER BY g.created_at DESC, g.id DESC LIMIT {$limit}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return self::formatItems($stmt->fetchAll(\PDO::FETCH_ASSOC), $userId);
    }

    /**
     * Load reviews
     */
    private static function loadReviews(?int $userId, int $tenantId, int $limit, ?array $cursorData): array
    {
        $db = Database::getConnection();

        $sql = "
            SELECT r.id, r.rating, r.comment as content, r.created_at, r.reviewer_id as user_id,
                   'review' as type,
                   COALESCE(reviewer.name, CONCAT(reviewer.first_name, ' ', reviewer.last_name)) as author_name,
                   reviewer.avatar_url as author_avatar,
                   COALESCE(receiver.name, CONCAT(receiver.first_name, ' ', receiver.last_name)) as receiver_name,
                   receiver.id as receiver_id,
                   (SELECT COUNT(*) FROM likes WHERE target_type = 'review' AND target_id = r.id) as likes_count,
                   (SELECT COUNT(*) FROM comments WHERE target_type = 'review' AND target_id = r.id) as comments_count
            FROM reviews r
            JOIN users reviewer ON r.reviewer_id = reviewer.id
            JOIN users receiver ON r.receiver_id = receiver.id
            WHERE r.reviewer_tenant_id = ? AND r.status = 'approved'
        ";
        $params = [$tenantId];

        if ($cursorData && $cursorData['type'] === 'review') {
            $sql .= " AND (r.created_at < ? OR (r.created_at = ? AND r.id < ?))";
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['id'];
        }

        $sql .= " ORDER BY r.created_at DESC, r.id DESC LIMIT {$limit}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $reviews = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Add rating and receiver_name to each review before formatting
        foreach ($reviews as &$review) {
            $review['rating'] = (int)$review['rating'];
            $review['receiver'] = [
                'id' => (int)$review['receiver_id'],
                'name' => $review['receiver_name'],
            ];
        }

        return self::formatItems($reviews, $userId);
    }

    /**
     * Load job vacancies
     */
    private static function loadJobs(?int $userId, int $tenantId, int $limit, ?array $cursorData): array
    {
        $db = Database::getConnection();

        // Guard: table may not exist yet
        try {
            $db->query("SELECT 1 FROM job_vacancies LIMIT 1");
        } catch (\Exception $e) {
            return [];
        }

        $sql = "
            SELECT j.id, j.title, j.description as content, j.created_at, j.user_id,
                   'job' as type,
                   j.location, j.type as job_type, j.commitment,
                   COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name,
                   u.avatar_url as author_avatar,
                   (SELECT COUNT(*) FROM likes WHERE target_type = 'job' AND target_id = j.id) as likes_count,
                   (SELECT COUNT(*) FROM comments WHERE target_type = 'job' AND target_id = j.id) as comments_count
            FROM job_vacancies j
            JOIN users u ON j.user_id = u.id
            WHERE j.tenant_id = ? AND j.status = 'open'
        ";
        $params = [$tenantId];

        if ($cursorData && $cursorData['type'] === 'job') {
            $sql .= " AND (j.created_at < ? OR (j.created_at = ? AND j.id < ?))";
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['id'];
        }

        $sql .= " ORDER BY j.created_at DESC, j.id DESC LIMIT {$limit}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return self::formatItems($stmt->fetchAll(\PDO::FETCH_ASSOC), $userId);
    }

    /**
     * Load ideation challenges
     */
    private static function loadChallenges(?int $userId, int $tenantId, int $limit, ?array $cursorData): array
    {
        $db = Database::getConnection();

        // Guard: table may not exist yet
        try {
            $db->query("SELECT 1 FROM ideation_challenges LIMIT 1");
        } catch (\Exception $e) {
            return [];
        }

        $sql = "
            SELECT ic.id, ic.title, ic.description as content, ic.cover_image as image_url,
                   ic.created_at, ic.user_id,
                   'challenge' as type,
                   ic.submission_deadline, ic.ideas_count,
                   COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name,
                   u.avatar_url as author_avatar,
                   (SELECT COUNT(*) FROM likes WHERE target_type = 'challenge' AND target_id = ic.id) as likes_count,
                   (SELECT COUNT(*) FROM comments WHERE target_type = 'challenge' AND target_id = ic.id) as comments_count
            FROM ideation_challenges ic
            JOIN users u ON ic.user_id = u.id
            WHERE ic.tenant_id = ? AND ic.status = 'open'
        ";
        $params = [$tenantId];

        if ($cursorData && $cursorData['type'] === 'challenge') {
            $sql .= " AND (ic.created_at < ? OR (ic.created_at = ? AND ic.id < ?))";
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['id'];
        }

        $sql .= " ORDER BY ic.created_at DESC, ic.id DESC LIMIT {$limit}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return self::formatItems($stmt->fetchAll(\PDO::FETCH_ASSOC), $userId);
    }

    /**
     * Load volunteer opportunities
     */
    private static function loadVolunteerOpportunities(?int $userId, int $tenantId, int $limit, ?array $cursorData): array
    {
        $db = Database::getConnection();

        // Guard: table may not exist yet
        try {
            $db->query("SELECT 1 FROM vol_opportunities LIMIT 1");
        } catch (\Exception $e) {
            return [];
        }

        $sql = "
            SELECT vo.id, vo.title, vo.description as content, vo.created_at,
                   COALESCE(vo.created_by, org.user_id) as user_id,
                   'volunteer' as type,
                   vo.location, vo.credits_offered,
                   COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name,
                   u.avatar_url as author_avatar,
                   org.name as organization_name,
                   (SELECT COUNT(*) FROM likes WHERE target_type = 'volunteer' AND target_id = vo.id) as likes_count,
                   (SELECT COUNT(*) FROM comments WHERE target_type = 'volunteer' AND target_id = vo.id) as comments_count
            FROM vol_opportunities vo
            LEFT JOIN vol_organizations org ON vo.organization_id = org.id
            JOIN users u ON COALESCE(vo.created_by, org.user_id) = u.id
            WHERE vo.tenant_id = ? AND vo.status = 'open' AND vo.is_active = 1
        ";
        $params = [$tenantId];

        if ($cursorData && $cursorData['type'] === 'volunteer') {
            $sql .= " AND (vo.created_at < ? OR (vo.created_at = ? AND vo.id < ?))";
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['id'];
        }

        $sql .= " ORDER BY vo.created_at DESC, vo.id DESC LIMIT {$limit}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Add organization name to each item
        foreach ($items as &$item) {
            $item['organization'] = $item['organization_name'] ?? null;
        }

        return self::formatItems($items, $userId);
    }

    /**
     * Load jobs for a specific user's profile feed
     */
    private static function loadUserJobs(int $profileUserId, ?int $userId, int $tenantId, int $limit, ?array $cursorData): array
    {
        $db = Database::getConnection();

        try {
            $db->query("SELECT 1 FROM job_vacancies LIMIT 1");
        } catch (\Exception $e) {
            return [];
        }

        $sql = "
            SELECT j.id, j.title, j.description as content, j.created_at, j.user_id,
                   'job' as type,
                   j.location, j.type as job_type, j.commitment,
                   COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name,
                   u.avatar_url as author_avatar,
                   (SELECT COUNT(*) FROM likes WHERE target_type = 'job' AND target_id = j.id) as likes_count,
                   (SELECT COUNT(*) FROM comments WHERE target_type = 'job' AND target_id = j.id) as comments_count
            FROM job_vacancies j
            JOIN users u ON j.user_id = u.id
            WHERE j.user_id = ? AND j.tenant_id = ? AND j.status = 'open'
        ";
        $params = [$profileUserId, $tenantId];

        if ($cursorData && $cursorData['type'] === 'job') {
            $sql .= " AND (j.created_at < ? OR (j.created_at = ? AND j.id < ?))";
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['id'];
        }

        $sql .= " ORDER BY j.created_at DESC, j.id DESC LIMIT {$limit}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return self::formatItems($stmt->fetchAll(\PDO::FETCH_ASSOC), $userId);
    }

    /**
     * Load challenges for a specific user's profile feed
     */
    private static function loadUserChallenges(int $profileUserId, ?int $userId, int $tenantId, int $limit, ?array $cursorData): array
    {
        $db = Database::getConnection();

        try {
            $db->query("SELECT 1 FROM ideation_challenges LIMIT 1");
        } catch (\Exception $e) {
            return [];
        }

        $sql = "
            SELECT ic.id, ic.title, ic.description as content, ic.cover_image as image_url,
                   ic.created_at, ic.user_id,
                   'challenge' as type,
                   ic.submission_deadline, ic.ideas_count,
                   COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name,
                   u.avatar_url as author_avatar,
                   (SELECT COUNT(*) FROM likes WHERE target_type = 'challenge' AND target_id = ic.id) as likes_count,
                   (SELECT COUNT(*) FROM comments WHERE target_type = 'challenge' AND target_id = ic.id) as comments_count
            FROM ideation_challenges ic
            JOIN users u ON ic.user_id = u.id
            WHERE ic.user_id = ? AND ic.tenant_id = ? AND ic.status = 'open'
        ";
        $params = [$profileUserId, $tenantId];

        if ($cursorData && $cursorData['type'] === 'challenge') {
            $sql .= " AND (ic.created_at < ? OR (ic.created_at = ? AND ic.id < ?))";
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['id'];
        }

        $sql .= " ORDER BY ic.created_at DESC, ic.id DESC LIMIT {$limit}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return self::formatItems($stmt->fetchAll(\PDO::FETCH_ASSOC), $userId);
    }

    /**
     * Load volunteer opportunities for a specific user's profile feed
     */
    private static function loadUserVolunteerOpportunities(int $profileUserId, ?int $userId, int $tenantId, int $limit, ?array $cursorData): array
    {
        $db = Database::getConnection();

        try {
            $db->query("SELECT 1 FROM vol_opportunities LIMIT 1");
        } catch (\Exception $e) {
            return [];
        }

        $sql = "
            SELECT vo.id, vo.title, vo.description as content, vo.created_at,
                   COALESCE(vo.created_by, org.user_id) as user_id,
                   'volunteer' as type,
                   vo.location, vo.credits_offered,
                   COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name,
                   u.avatar_url as author_avatar,
                   org.name as organization_name,
                   (SELECT COUNT(*) FROM likes WHERE target_type = 'volunteer' AND target_id = vo.id) as likes_count,
                   (SELECT COUNT(*) FROM comments WHERE target_type = 'volunteer' AND target_id = vo.id) as comments_count
            FROM vol_opportunities vo
            LEFT JOIN vol_organizations org ON vo.organization_id = org.id
            JOIN users u ON COALESCE(vo.created_by, org.user_id) = u.id
            WHERE COALESCE(vo.created_by, org.user_id) = ? AND vo.tenant_id = ? AND vo.status = 'open' AND vo.is_active = 1
        ";
        $params = [$profileUserId, $tenantId];

        if ($cursorData && $cursorData['type'] === 'volunteer') {
            $sql .= " AND (vo.created_at < ? OR (vo.created_at = ? AND vo.id < ?))";
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['created_at'];
            $params[] = $cursorData['id'];
        }

        $sql .= " ORDER BY vo.created_at DESC, vo.id DESC LIMIT {$limit}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($items as &$item) {
            $item['organization'] = $item['organization_name'] ?? null;
        }

        return self::formatItems($items, $userId);
    }

    /**
     * Format feed items with like status (batch-loaded to avoid N+1 queries)
     * Also includes poll_data for poll-type items to avoid frontend N+1 requests.
     */
    private static function formatItems(array $items, ?int $userId): array
    {
        if (empty($items)) {
            return [];
        }

        // Batch load like status for all items in a single query
        $likedSet = [];
        if ($userId) {
            $likedSet = self::batchLoadLikeStatus($userId, $items);
        }

        // Batch load poll data for poll-type items
        $pollDataMap = self::batchLoadPollData($items, $userId);

        $formatted = [];
        foreach ($items as $item) {
            $likeKey = $item['type'] . ':' . $item['id'];
            $isLiked = isset($likedSet[$likeKey]);

            $contentResult = self::truncateWithFlag($item['content'] ?? '', 500);

            $entry = [
                'id' => (int)$item['id'],
                'type' => $item['type'],
                'title' => $item['title'] ?? null,
                'content' => $contentResult['text'],
                'content_truncated' => $contentResult['truncated'],
                'image_url' => $item['image_url'] ?? null,
                'author' => [
                    'id' => (int)$item['user_id'],
                    'name' => $item['author_name'],
                    'avatar_url' => $item['author_avatar'] ?? '/assets/img/defaults/default_avatar.png',
                ],
                'likes_count' => (int)($item['likes_count'] ?? 0),
                'comments_count' => (int)($item['comments_count'] ?? 0),
                'is_liked' => $isLiked,
                'created_at' => $item['created_at'],
                // Include extra fields for events
                'start_date' => $item['start_date'] ?? null,
                'location' => $item['location'] ?? null,
                // Include extra fields for reviews
                'rating' => isset($item['rating']) ? (int)$item['rating'] : null,
                'receiver' => $item['receiver'] ?? null,
                // Include extra fields for jobs
                'job_type' => $item['job_type'] ?? null,
                'commitment' => $item['commitment'] ?? null,
                // Include extra fields for challenges
                'submission_deadline' => $item['submission_deadline'] ?? null,
                'ideas_count' => isset($item['ideas_count']) ? (int)$item['ideas_count'] : null,
                // Include extra fields for volunteer opportunities
                'credits_offered' => isset($item['credits_offered']) ? (int)$item['credits_offered'] : null,
                'organization' => $item['organization'] ?? null,
            ];

            // Include poll_data for poll-type items (avoids frontend N+1 requests)
            if ($item['type'] === 'poll' && isset($pollDataMap[(int)$item['id']])) {
                $entry['poll_data'] = $pollDataMap[(int)$item['id']];
            }

            $formatted[] = $entry;
        }

        return $formatted;
    }

    /**
     * Batch load like status for multiple items in a single query
     * Returns a set of "type:id" keys that the user has liked
     */
    private static function batchLoadLikeStatus(int $userId, array $items): array
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        // Group items by type for efficient querying
        $byType = [];
        foreach ($items as $item) {
            $byType[$item['type']][] = (int)$item['id'];
        }

        $likedSet = [];
        foreach ($byType as $type => $ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = array_merge([$userId, $type, $tenantId], $ids);
            $stmt = $db->prepare(
                "SELECT target_id FROM likes WHERE user_id = ? AND target_type = ? AND tenant_id = ? AND target_id IN ($placeholders)"
            );
            $stmt->execute($params);

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $likedSet[$type . ':' . $row['target_id']] = true;
            }
        }

        return $likedSet;
    }

    /**
     * Batch load poll data for poll-type items in the feed.
     * Returns a map of pollId => pollData (with options, votes, user vote).
     */
    private static function batchLoadPollData(array $items, ?int $userId): array
    {
        // Collect poll IDs
        $pollIds = [];
        foreach ($items as $item) {
            if ($item['type'] === 'poll') {
                $pollIds[] = (int)$item['id'];
            }
        }

        if (empty($pollIds)) {
            return [];
        }

        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        // Load all poll options in one query
        $placeholders = implode(',', array_fill(0, count($pollIds), '?'));
        $stmt = $db->prepare(
            "SELECT po.id as option_id, po.poll_id, po.label,
                    po.votes as vote_count
             FROM poll_options po
             WHERE po.poll_id IN ($placeholders)
             ORDER BY po.id ASC"
        );
        $stmt->execute($pollIds);
        $allOptions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Load user's votes if authenticated
        $userVotes = [];
        if ($userId) {
            $stmt = $db->prepare(
                "SELECT pv.poll_id, pv.option_id
                 FROM poll_votes pv
                 WHERE pv.poll_id IN ($placeholders) AND pv.user_id = ?"
            );
            $stmt->execute(array_merge($pollIds, [$userId]));
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $vote) {
                $userVotes[(int)$vote['poll_id']] = (int)$vote['option_id'];
            }
        }

        // Load poll metadata (is_active)
        $stmt = $db->prepare(
            "SELECT id, is_active, question FROM polls WHERE id IN ($placeholders) AND tenant_id = ?"
        );
        $stmt->execute(array_merge($pollIds, [$tenantId]));
        $pollMeta = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $poll) {
            $pollMeta[(int)$poll['id']] = $poll;
        }

        // Build poll data map
        $pollDataMap = [];
        $optionsByPoll = [];
        foreach ($allOptions as $opt) {
            $optionsByPoll[(int)$opt['poll_id']][] = $opt;
        }

        foreach ($pollIds as $pollId) {
            $options = $optionsByPoll[$pollId] ?? [];
            $totalVotes = 0;
            foreach ($options as $opt) {
                $totalVotes += (int)$opt['vote_count'];
            }

            $formattedOptions = [];
            foreach ($options as $opt) {
                $voteCount = (int)$opt['vote_count'];
                $formattedOptions[] = [
                    'id' => (int)$opt['option_id'],
                    'text' => $opt['label'],
                    'vote_count' => $voteCount,
                    'percentage' => $totalVotes > 0 ? round(($voteCount / $totalVotes) * 100, 1) : 0,
                ];
            }

            $meta = $pollMeta[$pollId] ?? null;
            $pollDataMap[$pollId] = [
                'id' => $pollId,
                'question' => $meta['question'] ?? '',
                'options' => $formattedOptions,
                'total_votes' => $totalVotes,
                'user_vote_option_id' => $userVotes[$pollId] ?? null,
                'is_active' => (bool)($meta['is_active'] ?? true),
            ];
        }

        return $pollDataMap;
    }

    /**
     * Get a single feed item by type and ID
     */
    public static function getItem(string $type, int $id, ?int $userId): ?array
    {
        $tenantId = TenantContext::getId();
        $items = [];

        switch ($type) {
            case 'post':
                $items = self::loadPosts($userId, $tenantId, 1, null);
                // Filter to specific post
                $db = Database::getConnection();
                $sql = "
                    SELECT p.id, p.content, p.image_url, p.created_at, p.likes_count, p.user_id,
                           'post' as type,
                           COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name,
                           u.avatar_url as author_avatar,
                           (SELECT COUNT(*) FROM comments WHERE target_type = 'post' AND target_id = p.id) as comments_count
                    FROM feed_posts p
                    JOIN users u ON p.user_id = u.id
                    WHERE p.id = ? AND p.tenant_id = ?
                ";
                $stmt = $db->prepare($sql);
                $stmt->execute([$id, $tenantId]);
                $items = self::formatItems($stmt->fetchAll(\PDO::FETCH_ASSOC), $userId);
                break;
            // Add other types as needed
        }

        return !empty($items) ? $items[0] : null;
    }

    /**
     * Create a new feed post
     *
     * @param int $userId
     * @param array $data ['content' => string, 'image_url' => string, 'visibility' => string, 'group_id' => int]
     * @return int|null Post ID or null on failure
     */
    public static function createPost(int $userId, array $data): ?int
    {
        self::$errors = [];

        $rawContent = trim($data['content'] ?? '');
        // Sanitize HTML if present, otherwise use plain text
        $content = HtmlSanitizer::containsHtml($rawContent)
            ? HtmlSanitizer::sanitize($rawContent)
            : $rawContent;
        $imageUrl = $data['image_url'] ?? null;
        $visibility = $data['visibility'] ?? 'public';
        $groupId = (int)($data['group_id'] ?? 0);

        // Validate visibility
        $validVisibility = ['public', 'private', 'friends'];
        if (!in_array($visibility, $validVisibility, true)) {
            $visibility = 'public';
        }

        if (empty($content) && empty($imageUrl)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Content or image is required', 'field' => 'content'];
            return null;
        }

        // Validate group membership if posting to group
        if ($groupId > 0) {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ? AND status = 'active'");
            $stmt->execute([$groupId, $userId]);
            if (!$stmt->fetchColumn()) {
                self::$errors[] = ['code' => 'FORBIDDEN', 'message' => 'You must be a group member to post'];
                return null;
            }
        }

        try {
            $db = Database::getConnection();
            $tenantId = TenantContext::getId();

            // Check if group_id column exists
            $hasGroupColumn = false;
            try {
                $col = $db->query("SHOW COLUMNS FROM feed_posts LIKE 'group_id'")->fetch();
                $hasGroupColumn = !empty($col);
            } catch (\Exception $e) {
            }

            if ($hasGroupColumn && $groupId > 0) {
                $stmt = $db->prepare("INSERT INTO feed_posts (user_id, tenant_id, content, image_url, likes_count, visibility, group_id, created_at) VALUES (?, ?, ?, ?, 0, ?, ?, NOW())");
                $stmt->execute([$userId, $tenantId, $content, $imageUrl, $visibility, $groupId]);
            } else {
                $stmt = $db->prepare("INSERT INTO feed_posts (user_id, tenant_id, content, image_url, likes_count, visibility, created_at) VALUES (?, ?, ?, ?, 0, ?, NOW())");
                $stmt->execute([$userId, $tenantId, $content, $imageUrl, $visibility]);
            }

            $postId = (int)$db->lastInsertId();

            // Record in feed_activity table
            try {
                FeedActivityService::recordActivity($tenantId, $userId, 'post', $postId, [
                    'content' => $content,
                    'image_url' => $imageUrl,
                    'group_id' => $groupId ?: null,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            } catch (\Exception $faEx) {
                error_log("FeedService::createPost feed_activity record failed: " . $faEx->getMessage());
            }

            // Process hashtags in the content (F4)
            try {
                HashtagService::processPostHashtags($postId, $content);
            } catch (\Exception $hashtagEx) {
                error_log("FeedService::createPost hashtag processing failed: " . $hashtagEx->getMessage());
            }

            // Broadcast the new post to all feed subscribers in real time.
            // Failures are swallowed so a Pusher outage never fails post creation.
            try {
                $formattedPost = self::getItem('post', $postId, $userId);
                if ($formattedPost !== null) {
                    RealtimeService::broadcastFeedPost($formattedPost);
                }
            } catch (\Exception $pusherEx) {
                error_log("FeedService::createPost Pusher broadcast failed: " . $pusherEx->getMessage());
            }

            return $postId;
        } catch (\Exception $e) {
            error_log("FeedService::createPost error: " . $e->getMessage());
            self::$errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to create post'];
            return null;
        }
    }

    /**
     * Toggle like on content
     *
     * @param int $userId
     * @param string $targetType post, listing, event, poll, goal, etc.
     * @param int $targetId
     * @return array ['action' => 'liked'|'unliked', 'likes_count' => int]
     */
    public static function toggleLike(int $userId, string $targetType, int $targetId): array
    {
        $db = Database::getConnection();
        $tenantId = TenantContext::getId();

        // Check existing like — scoped by tenant
        $stmt = $db->prepare("SELECT id FROM likes WHERE user_id = ? AND target_type = ? AND target_id = ? AND tenant_id = ?");
        $stmt->execute([$userId, $targetType, $targetId, $tenantId]);
        $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($existing) {
            // Unlike — scoped by tenant
            $db->prepare("DELETE FROM likes WHERE id = ? AND tenant_id = ?")->execute([$existing['id'], $tenantId]);

            if ($targetType === 'post') {
                $db->prepare("UPDATE feed_posts SET likes_count = GREATEST(likes_count - 1, 0) WHERE id = ?")->execute([$targetId]);
            }

            $action = 'unliked';
        } else {
            // Like
            $db->prepare("INSERT INTO likes (user_id, target_type, target_id, tenant_id, created_at) VALUES (?, ?, ?, ?, NOW())")
                ->execute([$userId, $targetType, $targetId, $tenantId]);

            if ($targetType === 'post') {
                $db->prepare("UPDATE feed_posts SET likes_count = likes_count + 1 WHERE id = ?")->execute([$targetId]);
            }

            $action = 'liked';
        }

        // Get updated count (tenant-scoped)
        $stmt = $db->prepare("SELECT COUNT(*) FROM likes WHERE target_type = ? AND target_id = ? AND tenant_id = ?");
        $stmt->execute([$targetType, $targetId, $tenantId]);
        $count = (int)$stmt->fetchColumn();

        return [
            'action' => $action,
            'likes_count' => $count,
        ];
    }

    /**
     * Truncate text and report whether it was truncated
     *
     * @return array{text: string, truncated: bool}
     */
    private static function truncateWithFlag(string $text, int $length): array
    {
        if (mb_strlen($text) <= $length) {
            return ['text' => $text, 'truncated' => false];
        }
        return ['text' => mb_substr($text, 0, $length - 3) . '...', 'truncated' => true];
    }

    /**
     * Truncate text (legacy helper — delegates to truncateWithFlag)
     */
    private static function truncate(string $text, int $length): string
    {
        return self::truncateWithFlag($text, $length)['text'];
    }
}
