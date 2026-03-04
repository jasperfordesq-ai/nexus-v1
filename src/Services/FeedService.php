<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\RealtimeService;
use Nexus\Services\HashtagService;

/**
 * FeedService - Business logic for social feed operations
 *
 * This service provides cursor-paginated feed operations for the v2 API,
 * powered by the unified feed_activity table (single-query approach).
 *
 * Key operations:
 * - Get feed (main, user profile, group, type-filtered) via feed_activity
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
        $tenantId = TenantContext::getId();

        $limit = min($filters['limit'] ?? 20, 100);
        $type = $filters['type'] ?? 'all';
        $profileUserId = $filters['user_id'] ?? null;
        $groupId = $filters['group_id'] ?? null;
        $cursor = $filters['cursor'] ?? null;

        // Decode cursor (activity_id encoded as base64)
        $cursorActivityId = null;
        if ($cursor) {
            $decoded = base64_decode($cursor, true);
            if ($decoded !== false && ctype_digit($decoded)) {
                $cursorActivityId = (int)$decoded;
            }
        }

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
            $cursorActivityId,
            $sourceType, $profileUserId, $groupId
        );

        // Check if there are more items
        $hasMore = count($items) > $limit;
        if ($hasMore) {
            array_pop($items);
        }

        // Generate cursor from last item (activity_id based)
        $nextCursor = null;
        if ($hasMore && !empty($items)) {
            $lastItem = end($items);
            if (isset($lastItem['_activity_id'])) {
                $nextCursor = base64_encode((string)$lastItem['_activity_id']);
            }
        }

        // Strip internal _activity_id from output
        foreach ($items as &$item) {
            unset($item['_activity_id']);
        }
        unset($item);

        return [
            'items' => $items,
            'cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Load feed from the unified feed_activity table (single query).
     *
     * Supports all feed modes: main, user profile, group, type-filtered.
     */
    private static function loadFromFeedActivity(
        ?int $userId,
        int $tenantId,
        int $limit,
        ?int $cursorActivityId,
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

        // Cursor: paginate by activity_id
        if ($cursorActivityId !== null) {
            $sql .= " AND fa.id < ?";
            $params[] = $cursorActivityId;
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
        $db = Database::getConnection();
        $items = [];

        switch ($type) {
            case 'post':
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
