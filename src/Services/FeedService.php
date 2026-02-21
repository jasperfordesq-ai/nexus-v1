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

        // Decode cursor (format: "type_timestamp_id" e.g., "post_2026-01-30 12:00:00_123")
        $cursorData = null;
        if ($cursor) {
            $decoded = base64_decode($cursor, true);
            if ($decoded) {
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

        $items = [];

        // For aggregated feed, we need to merge different content types
        // For single type or specific views (user/group), we can query directly
        if ($groupId) {
            $items = self::loadGroupFeed($groupId, $userId, $tenantId, $limit + 1, $cursorData);
        } elseif ($profileUserId) {
            $items = self::loadUserFeed($profileUserId, $userId, $tenantId, $limit + 1, $cursorData);
        } elseif ($type !== 'all') {
            $items = self::loadTypedFeed($type, $userId, $tenantId, $limit + 1, $cursorData);
        } else {
            $items = self::loadAggregatedFeed($userId, $tenantId, $limit + 1, $cursorData);
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
            $nextCursor = base64_encode("{$lastItem['type']}_{$lastItem['created_at']}_{$lastItem['id']}");
        }

        return [
            'items' => $items,
            'cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
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
     * Load user profile feed
     */
    private static function loadUserFeed(int $profileUserId, ?int $userId, int $tenantId, int $limit, ?array $cursorData): array
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

        // Sort by created_at descending
        usort($items, function ($a, $b) {
            $cmp = strcmp($b['created_at'], $a['created_at']);
            if ($cmp === 0) {
                return $b['id'] - $a['id'];
            }
            return $cmp;
        });

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

            $entry = [
                'id' => (int)$item['id'],
                'type' => $item['type'],
                'title' => $item['title'] ?? null,
                'content' => self::truncate($item['content'] ?? '', 500),
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
            "SELECT po.id as option_id, po.poll_id, po.option_text,
                    (SELECT COUNT(*) FROM poll_votes pv WHERE pv.option_id = po.id) as vote_count
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
                    'text' => $opt['option_text'],
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

        $content = trim($data['content'] ?? '');
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

            return (int)$db->lastInsertId();
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
     * Truncate text
     */
    private static function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        return mb_substr($text, 0, $length - 3) . '...';
    }
}
