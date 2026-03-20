<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * HashtagService - Hashtag extraction, linking, and trending
 *
 * Provides:
 * - Auto-detect #hashtags in post content
 * - Link/unlink hashtags to posts
 * - Trending hashtags
 * - Hashtag search/discovery
 * - Posts by hashtag
 */
class HashtagService
{
    /**
     * Extract hashtags from content text
     *
     * @param string $content Post content
     * @return array Array of hashtag strings (without #)
     */
    public static function extractHashtags(string $content): array
    {
        $matches = [];
        // Match #hashtag (letters, numbers, underscores, hyphens; 2-50 chars)
        // Negative lookahead ensures tags >50 chars are excluded entirely
        preg_match_all('/#([a-zA-Z][a-zA-Z0-9_-]{1,49})(?![a-zA-Z0-9_-])/', $content, $matches);

        if (empty($matches[1])) {
            return [];
        }

        // Normalize: lowercase, unique
        $tags = array_unique(array_map('strtolower', $matches[1]));

        return array_values($tags);
    }

    /**
     * Process hashtags for a post (extract, create, and link)
     *
     * Called after post creation.
     *
     * @param int $postId
     * @param string $content Post content
     */
    public static function processPostHashtags(int $postId, string $content): void
    {
        $tenantId = TenantContext::getId();
        $tags = self::extractHashtags($content);

        if (empty($tags)) {
            return;
        }

        foreach ($tags as $tag) {
            try {
                // Upsert hashtag
                $hashtagId = self::getOrCreateHashtag($tag, $tenantId);

                // Link to post (ignore duplicates)
                Database::query(
                    "INSERT IGNORE INTO post_hashtags (post_id, hashtag_id, tenant_id) VALUES (?, ?, ?)",
                    [$postId, $hashtagId, $tenantId]
                );

                // Update post count and last used
                Database::query(
                    "UPDATE hashtags SET post_count = (SELECT COUNT(*) FROM post_hashtags WHERE hashtag_id = ?), last_used_at = NOW()
                     WHERE id = ? AND tenant_id = ?",
                    [$hashtagId, $hashtagId, $tenantId]
                );
            } catch (\Exception $e) {
                error_log("HashtagService::processPostHashtags error for tag '{$tag}': " . $e->getMessage());
            }
        }
    }

    /**
     * Remove hashtag links when a post is deleted
     */
    public static function removePostHashtags(int $postId): void
    {
        $tenantId = TenantContext::getId();

        try {
            // Get linked hashtags
            $hashtagIds = Database::query(
                "SELECT hashtag_id FROM post_hashtags WHERE post_id = ? AND tenant_id = ?",
                [$postId, $tenantId]
            )->fetchAll(\PDO::FETCH_COLUMN);

            // Delete links
            Database::query(
                "DELETE FROM post_hashtags WHERE post_id = ? AND tenant_id = ?",
                [$postId, $tenantId]
            );

            // Update counts
            if (!empty($hashtagIds)) {
                foreach ($hashtagIds as $hid) {
                    Database::query(
                        "UPDATE hashtags SET post_count = GREATEST(0, (SELECT COUNT(*) FROM post_hashtags WHERE hashtag_id = ?))
                         WHERE id = ? AND tenant_id = ?",
                        [$hid, $hid, $tenantId]
                    );
                }
            }
        } catch (\Exception $e) {
            error_log("HashtagService::removePostHashtags error: " . $e->getMessage());
        }
    }

    /**
     * Update hashtags for a post (when content is edited)
     */
    public static function updatePostHashtags(int $postId, string $newContent): void
    {
        self::removePostHashtags($postId);
        self::processPostHashtags($postId, $newContent);
    }

    /**
     * Get trending hashtags
     *
     * @param int $limit Number of trending tags to return
     * @param int $days Look back period in days
     * @return array
     */
    public static function getTrending(int $limit = 10, int $days = 7): array
    {
        $tenantId = TenantContext::getId();
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        return Database::query(
            "SELECT h.id, h.tag, h.post_count,
                    COUNT(ph.id) as recent_count,
                    h.last_used_at
             FROM hashtags h
             LEFT JOIN post_hashtags ph ON h.id = ph.hashtag_id AND ph.created_at >= ?
             WHERE h.tenant_id = ? AND h.post_count > 0
             GROUP BY h.id
             ORDER BY recent_count DESC, h.post_count DESC
             LIMIT ?",
            [$since, $tenantId, $limit]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get all-time popular hashtags
     */
    public static function getPopular(int $limit = 20): array
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT id, tag, post_count, last_used_at
             FROM hashtags
             WHERE tenant_id = ? AND post_count > 0
             ORDER BY post_count DESC
             LIMIT ?",
            [$tenantId, $limit]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Search hashtags (autocomplete)
     */
    public static function search(string $query, int $limit = 10): array
    {
        $tenantId = TenantContext::getId();
        $searchTerm = strtolower(trim($query, '#'));

        return Database::query(
            "SELECT id, tag, post_count
             FROM hashtags
             WHERE tenant_id = ? AND tag LIKE ?
             ORDER BY post_count DESC, tag ASC
             LIMIT ?",
            [$tenantId, $searchTerm . '%', $limit]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get posts for a hashtag
     *
     * @param string $tag Hashtag (without #)
     * @param int|null $userId Current user (for like status)
     * @param int $limit
     * @param string|null $cursor
     * @return array ['items' => [], 'cursor' => string|null, 'has_more' => bool]
     */
    public static function getPostsByHashtag(string $tag, ?int $userId = null, int $limit = 20, ?string $cursor = null): array
    {
        $tenantId = TenantContext::getId();
        $tag = strtolower(trim($tag, '#'));

        $sql = "SELECT p.id, p.content, p.image_url, p.created_at, p.likes_count, p.share_count,
                       p.user_id, 'post' as type,
                       CONCAT(u.first_name, ' ', u.last_name) as author_name,
                       u.avatar_url as author_avatar,
                       (SELECT COUNT(*) FROM comments WHERE target_type = 'post' AND target_id = p.id) as comments_count
                FROM feed_posts p
                JOIN post_hashtags ph ON p.id = ph.post_id
                JOIN hashtags h ON ph.hashtag_id = h.id
                JOIN users u ON p.user_id = u.id
                WHERE h.tag = ? AND h.tenant_id = ? AND p.tenant_id = ?";
        $params = [$tag, $tenantId, $tenantId];

        // Cursor pagination
        if ($cursor) {
            $decoded = base64_decode($cursor, true);
            if ($decoded) {
                $parts = explode('_', $decoded, 2);
                if (count($parts) === 2) {
                    $sql .= " AND (p.created_at < ? OR (p.created_at = ? AND p.id < ?))";
                    $params[] = $parts[0];
                    $params[] = $parts[0];
                    $params[] = (int)$parts[1];
                }
            }
        }

        $sql .= " ORDER BY p.created_at DESC, p.id DESC LIMIT " . ($limit + 1);

        $items = Database::query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);

        // Add like status for current user
        if ($userId && !empty($items)) {
            $postIds = array_column($items, 'id');
            $placeholders = implode(',', array_fill(0, count($postIds), '?'));
            $likedRows = Database::query(
                "SELECT target_id FROM likes WHERE user_id = ? AND target_type = 'post' AND tenant_id = ? AND target_id IN ({$placeholders})",
                array_merge([$userId, $tenantId], $postIds)
            )->fetchAll(\PDO::FETCH_COLUMN);

            $likedMap = array_flip($likedRows);
            foreach ($items as &$item) {
                $item['is_liked'] = isset($likedMap[$item['id']]);
            }
            unset($item);
        }

        $hasMore = count($items) > $limit;
        if ($hasMore) {
            array_pop($items);
        }

        $nextCursor = null;
        if ($hasMore && !empty($items)) {
            $last = end($items);
            $nextCursor = base64_encode("{$last['created_at']}_{$last['id']}");
        }

        return [
            'items' => $items,
            'cursor' => $nextCursor,
            'has_more' => $hasMore,
            'tag' => $tag,
        ];
    }

    /**
     * Get hashtags for a specific post
     */
    public static function getPostHashtags(int $postId): array
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT h.id, h.tag
             FROM hashtags h
             JOIN post_hashtags ph ON h.id = ph.hashtag_id
             WHERE ph.post_id = ? AND ph.tenant_id = ?
             ORDER BY h.tag ASC",
            [$postId, $tenantId]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Batch get hashtags for multiple posts
     *
     * @param array $postIds
     * @return array Keyed by post_id => [hashtags]
     */
    public static function getBatchPostHashtags(array $postIds): array
    {
        if (empty($postIds)) {
            return [];
        }

        $tenantId = TenantContext::getId();
        $placeholders = implode(',', array_fill(0, count($postIds), '?'));

        $rows = Database::query(
            "SELECT ph.post_id, h.id, h.tag
             FROM post_hashtags ph
             JOIN hashtags h ON ph.hashtag_id = h.id
             WHERE ph.post_id IN ({$placeholders}) AND ph.tenant_id = ?
             ORDER BY h.tag ASC",
            array_merge($postIds, [$tenantId])
        )->fetchAll(\PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $pid = (int)$row['post_id'];
            if (!isset($result[$pid])) {
                $result[$pid] = [];
            }
            $result[$pid][] = ['id' => $row['id'], 'tag' => $row['tag']];
        }

        return $result;
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Get or create a hashtag record
     */
    private static function getOrCreateHashtag(string $tag, int $tenantId): int
    {
        $tag = strtolower($tag);

        $existing = Database::query(
            "SELECT id FROM hashtags WHERE tenant_id = ? AND tag = ?",
            [$tenantId, $tag]
        )->fetch(\PDO::FETCH_ASSOC);

        if ($existing) {
            return (int)$existing['id'];
        }

        Database::query(
            "INSERT INTO hashtags (tenant_id, tag, post_count, last_used_at) VALUES (?, ?, 0, NOW())",
            [$tenantId, $tag]
        );

        return (int)Database::lastInsertId();
    }
}
