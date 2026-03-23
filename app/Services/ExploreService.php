<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ExploreService — Aggregates discovery data for the Explore/Discover page.
 *
 * Returns multiple content sections in a single call to avoid waterfall requests.
 * Results are cached in Redis with a 5-minute TTL.
 *
 * All queries are tenant-scoped via TenantContext::getId().
 */
class ExploreService
{
    private const CACHE_TTL_SECONDS = 300; // 5 minutes

    /**
     * Get all explore page data in one call.
     *
     * Global (non-personalized) sections are cached per-tenant.
     * Personalized sections (recommended_listings) are cached per-user.
     */
    public function getExploreData(int $userId): array
    {
        $tenantId = TenantContext::getId();

        // Global sections — shared across all users for this tenant
        $globalKey = "nexus:explore:{$tenantId}:global";
        $globalData = Cache::get($globalKey);

        if ($globalData === null) {
            $globalData = [
                'trending_posts' => $this->getTrendingPosts($tenantId),
                'popular_listings' => $this->getPopularListings($tenantId),
                'active_groups' => $this->getActiveGroups($tenantId),
                'upcoming_events' => $this->getUpcomingEvents($tenantId),
                'top_contributors' => $this->getTopContributors($tenantId),
                'trending_hashtags' => $this->getTrendingHashtags($tenantId),
                'new_members' => $this->getNewMembers($tenantId),
                'featured_challenges' => $this->getFeaturedChallenges($tenantId),
                'community_stats' => $this->getCommunityStats($tenantId),
            ];
            Cache::put($globalKey, $globalData, self::CACHE_TTL_SECONDS);
        }

        // Personalized sections — cached per user
        $userKey = "nexus:explore:{$tenantId}:{$userId}";
        $userData = Cache::get($userKey);

        if ($userData === null) {
            $userData = [
                'recommended_listings' => $this->getRecommendedListings($tenantId, $userId),
            ];
            Cache::put($userKey, $userData, self::CACHE_TTL_SECONDS);
        }

        return array_merge($globalData, $userData);
    }

    /**
     * Top 10 posts by engagement (likes + comments) in last 7 days.
     */
    private function getTrendingPosts(int $tenantId): array
    {
        try {
            $rows = DB::select("
                SELECT
                    fp.id,
                    fp.user_id,
                    LEFT(fp.content, 200) AS excerpt,
                    fp.image_url,
                    fp.created_at,
                    COALESCE(u.first_name, '') AS author_first_name,
                    COALESCE(u.last_name, '') AS author_last_name,
                    u.avatar AS author_avatar,
                    (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = fp.id) AS likes_count,
                    (SELECT COUNT(*) FROM comments c WHERE c.commentable_type = 'post' AND c.commentable_id = fp.id AND c.tenant_id = ?) AS comments_count,
                    (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = fp.id)
                        + (SELECT COUNT(*) FROM comments c WHERE c.commentable_type = 'post' AND c.commentable_id = fp.id AND c.tenant_id = ?) AS engagement
                FROM feed_posts fp
                JOIN users u ON u.id = fp.user_id AND u.tenant_id = ?
                WHERE fp.tenant_id = ?
                    AND fp.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    AND fp.is_deleted = 0
                ORDER BY engagement DESC, fp.created_at DESC
                LIMIT 10
            ", [$tenantId, $tenantId, $tenantId, $tenantId]);

            return array_map(fn($row) => [
                'id' => $row->id,
                'user_id' => $row->user_id,
                'excerpt' => $row->excerpt,
                'image_url' => $row->image_url,
                'created_at' => $row->created_at,
                'author_name' => trim($row->author_first_name . ' ' . $row->author_last_name),
                'author_avatar' => $row->author_avatar,
                'likes_count' => (int) $row->likes_count,
                'comments_count' => (int) $row->comments_count,
                'engagement' => (int) $row->engagement,
            ], $rows);
        } catch (\Throwable $e) {
            Log::warning('ExploreService::getTrendingPosts failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Top 8 active listings by view_count in last 30 days.
     */
    private function getPopularListings(int $tenantId): array
    {
        try {
            $rows = DB::select("
                SELECT
                    l.id,
                    l.title,
                    l.type,
                    l.image_url,
                    l.location,
                    l.estimated_hours,
                    l.created_at,
                    COALESCE(l.view_count, 0) AS view_count,
                    COALESCE(l.save_count, 0) AS save_count,
                    l.category_id,
                    COALESCE(cat.name, '') AS category_name,
                    COALESCE(cat.slug, '') AS category_slug,
                    cat.color AS category_color,
                    COALESCE(u.first_name, '') AS author_first_name,
                    COALESCE(u.last_name, '') AS author_last_name,
                    u.avatar AS author_avatar
                FROM listings l
                LEFT JOIN categories cat ON cat.id = l.category_id
                JOIN users u ON u.id = l.user_id AND u.tenant_id = ?
                WHERE l.tenant_id = ?
                    AND l.status = 'active'
                    AND l.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ORDER BY (COALESCE(l.view_count, 0) + COALESCE(l.save_count, 0)) DESC
                LIMIT 8
            ", [$tenantId, $tenantId]);

            return array_map(fn($row) => [
                'id' => $row->id,
                'title' => $row->title,
                'type' => $row->type,
                'image_url' => $row->image_url,
                'location' => $row->location,
                'estimated_hours' => $row->estimated_hours,
                'created_at' => $row->created_at,
                'view_count' => (int) $row->view_count,
                'save_count' => (int) $row->save_count,
                'category_name' => $row->category_name,
                'category_slug' => $row->category_slug,
                'category_color' => $row->category_color,
                'author_name' => trim($row->author_first_name . ' ' . $row->author_last_name),
                'author_avatar' => $row->author_avatar,
            ], $rows);
        } catch (\Throwable $e) {
            Log::warning('ExploreService::getPopularListings failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Top 6 groups by member count (active).
     */
    private function getActiveGroups(int $tenantId): array
    {
        try {
            $rows = DB::select("
                SELECT
                    g.id,
                    g.name,
                    g.description,
                    g.image_url,
                    g.privacy,
                    g.created_at,
                    (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id AND gm.status = 'approved') AS member_count
                FROM `groups` g
                WHERE g.tenant_id = ?
                    AND g.status = 'active'
                ORDER BY member_count DESC
                LIMIT 6
            ", [$tenantId]);

            return array_map(fn($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'description' => $row->description ? mb_substr($row->description, 0, 120) : null,
                'image_url' => $row->image_url,
                'privacy' => $row->privacy,
                'member_count' => (int) $row->member_count,
                'created_at' => $row->created_at,
            ], $rows);
        } catch (\Throwable $e) {
            Log::warning('ExploreService::getActiveGroups failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Next 8 upcoming events.
     */
    private function getUpcomingEvents(int $tenantId): array
    {
        try {
            $rows = DB::select("
                SELECT
                    e.id,
                    e.title,
                    e.description,
                    e.image_url,
                    e.start_at,
                    e.end_at,
                    e.location,
                    e.is_online,
                    e.max_attendees,
                    (SELECT COUNT(*) FROM event_rsvps er WHERE er.event_id = e.id AND er.status = 'going') AS rsvp_count
                FROM events e
                WHERE e.tenant_id = ?
                    AND e.start_at > NOW()
                    AND e.status = 'published'
                ORDER BY e.start_at ASC
                LIMIT 8
            ", [$tenantId]);

            return array_map(fn($row) => [
                'id' => $row->id,
                'title' => $row->title,
                'description' => $row->description ? mb_substr($row->description, 0, 120) : null,
                'image_url' => $row->image_url,
                'start_at' => $row->start_at,
                'end_at' => $row->end_at,
                'location' => $row->location,
                'is_online' => (bool) $row->is_online,
                'max_attendees' => $row->max_attendees,
                'rsvp_count' => (int) $row->rsvp_count,
            ], $rows);
        } catch (\Throwable $e) {
            Log::warning('ExploreService::getUpcomingEvents failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Top 6 users by XP earned in last 30 days.
     */
    private function getTopContributors(int $tenantId): array
    {
        try {
            $rows = DB::select("
                SELECT
                    u.id,
                    COALESCE(u.first_name, '') AS first_name,
                    COALESCE(u.last_name, '') AS last_name,
                    u.avatar,
                    COALESCE(u.xp, 0) AS xp,
                    COALESCE(u.level, 1) AS level,
                    u.tagline
                FROM users u
                WHERE u.tenant_id = ?
                    AND u.status = 'active'
                    AND u.xp > 0
                ORDER BY u.xp DESC
                LIMIT 6
            ", [$tenantId]);

            return array_map(fn($row) => [
                'id' => $row->id,
                'name' => trim($row->first_name . ' ' . $row->last_name),
                'avatar' => $row->avatar,
                'xp' => (int) $row->xp,
                'level' => (int) $row->level,
                'tagline' => $row->tagline,
            ], $rows);
        } catch (\Throwable $e) {
            Log::warning('ExploreService::getTopContributors failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Top 10 hashtags by usage in last 7 days.
     */
    private function getTrendingHashtags(int $tenantId): array
    {
        try {
            $rows = DB::select("
                SELECT
                    h.id,
                    h.tag,
                    h.post_count,
                    h.last_used_at
                FROM hashtags h
                WHERE h.tenant_id = ?
                    AND h.post_count > 0
                ORDER BY h.post_count DESC, h.last_used_at DESC
                LIMIT 10
            ", [$tenantId]);

            return array_map(fn($row) => [
                'id' => $row->id,
                'tag' => $row->tag,
                'post_count' => (int) $row->post_count,
                'last_used_at' => $row->last_used_at,
            ], $rows);
        } catch (\Throwable $e) {
            Log::warning('ExploreService::getTrendingHashtags failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * 8 newest members joined in last 14 days.
     */
    private function getNewMembers(int $tenantId): array
    {
        try {
            $rows = DB::select("
                SELECT
                    u.id,
                    COALESCE(u.first_name, '') AS first_name,
                    COALESCE(u.last_name, '') AS last_name,
                    u.avatar,
                    u.tagline,
                    u.created_at
                FROM users u
                WHERE u.tenant_id = ?
                    AND u.status = 'active'
                    AND u.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                ORDER BY u.created_at DESC
                LIMIT 8
            ", [$tenantId]);

            return array_map(fn($row) => [
                'id' => $row->id,
                'name' => trim($row->first_name . ' ' . $row->last_name),
                'avatar' => $row->avatar,
                'tagline' => $row->tagline,
                'created_at' => $row->created_at,
            ], $rows);
        } catch (\Throwable $e) {
            Log::warning('ExploreService::getNewMembers failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Active ideation challenges (if feature is enabled).
     */
    private function getFeaturedChallenges(int $tenantId): array
    {
        try {
            // Check if the challenges table exists before querying
            $tableExists = DB::select("SHOW TABLES LIKE 'challenges'");
            if (empty($tableExists)) {
                return [];
            }

            $rows = DB::select("
                SELECT
                    ch.id,
                    ch.title,
                    ch.description,
                    ch.status,
                    ch.start_date,
                    ch.end_date,
                    (SELECT COUNT(*) FROM ideas i WHERE i.challenge_id = ch.id AND i.tenant_id = ?) AS idea_count
                FROM challenges ch
                WHERE ch.tenant_id = ?
                    AND ch.status = 'active'
                ORDER BY ch.end_date ASC
                LIMIT 4
            ", [$tenantId, $tenantId]);

            return array_map(fn($row) => [
                'id' => $row->id,
                'title' => $row->title,
                'description' => $row->description ? mb_substr($row->description, 0, 150) : null,
                'status' => $row->status,
                'start_date' => $row->start_date,
                'end_date' => $row->end_date,
                'idea_count' => (int) $row->idea_count,
            ], $rows);
        } catch (\Throwable $e) {
            Log::warning('ExploreService::getFeaturedChallenges failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Community stats: total members, exchanges this month, hours exchanged, active listings.
     */
    private function getCommunityStats(int $tenantId): array
    {
        try {
            $memberCount = DB::selectOne(
                "SELECT COUNT(*) AS cnt FROM users WHERE tenant_id = ? AND status = 'active'",
                [$tenantId]
            );

            $exchangesThisMonth = DB::selectOne(
                "SELECT COUNT(*) AS cnt FROM transactions WHERE tenant_id = ? AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')",
                [$tenantId]
            );

            $hoursExchanged = DB::selectOne(
                "SELECT COALESCE(SUM(amount), 0) AS total FROM transactions WHERE tenant_id = ? AND type = 'debit' AND status = 'completed'",
                [$tenantId]
            );

            $activeListings = DB::selectOne(
                "SELECT COUNT(*) AS cnt FROM listings WHERE tenant_id = ? AND status = 'active'",
                [$tenantId]
            );

            return [
                'total_members' => (int) ($memberCount->cnt ?? 0),
                'exchanges_this_month' => (int) ($exchangesThisMonth->cnt ?? 0),
                'hours_exchanged' => round((float) ($hoursExchanged->total ?? 0), 1),
                'active_listings' => (int) ($activeListings->cnt ?? 0),
            ];
        } catch (\Throwable $e) {
            Log::warning('ExploreService::getCommunityStats failed', ['error' => $e->getMessage()]);
            return [
                'total_members' => 0,
                'exchanges_this_month' => 0,
                'hours_exchanged' => 0,
                'active_listings' => 0,
            ];
        }
    }

    /**
     * Recommended listings based on user's category affinity scores.
     */
    private function getRecommendedListings(int $tenantId, int $userId): array
    {
        try {
            // Get user's top category affinities
            $affinities = DB::select("
                SELECT uca.category_id, uca.affinity_score, COALESCE(cat.name, '') AS category_name
                FROM user_category_affinity uca
                LEFT JOIN categories cat ON cat.id = uca.category_id
                WHERE uca.tenant_id = ? AND uca.user_id = ?
                ORDER BY uca.affinity_score DESC
                LIMIT 5
            ", [$tenantId, $userId]);

            if (empty($affinities)) {
                // No affinity data — return recent popular listings instead
                return DB::select("
                    SELECT
                        l.id,
                        l.title,
                        l.type,
                        l.image_url,
                        l.location,
                        COALESCE(cat.name, '') AS category_name,
                        cat.slug AS category_slug,
                        COALESCE(u.first_name, '') AS author_first_name,
                        COALESCE(u.last_name, '') AS author_last_name,
                        u.avatar AS author_avatar,
                        NULL AS match_reason
                    FROM listings l
                    LEFT JOIN categories cat ON cat.id = l.category_id
                    JOIN users u ON u.id = l.user_id AND u.tenant_id = ?
                    WHERE l.tenant_id = ?
                        AND l.status = 'active'
                        AND l.user_id != ?
                    ORDER BY l.created_at DESC
                    LIMIT 6
                ", [$tenantId, $tenantId, $userId]);
            }

            // Get category IDs for the IN clause
            $categoryIds = array_map(fn($a) => $a->category_id, $affinities);
            $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
            $categoryNames = [];
            foreach ($affinities as $a) {
                $categoryNames[$a->category_id] = $a->category_name;
            }

            $params = array_merge([$tenantId, $tenantId, $userId], $categoryIds);

            $rows = DB::select("
                SELECT
                    l.id,
                    l.title,
                    l.type,
                    l.image_url,
                    l.location,
                    l.category_id,
                    COALESCE(cat.name, '') AS category_name,
                    cat.slug AS category_slug,
                    COALESCE(u.first_name, '') AS author_first_name,
                    COALESCE(u.last_name, '') AS author_last_name,
                    u.avatar AS author_avatar
                FROM listings l
                LEFT JOIN categories cat ON cat.id = l.category_id
                JOIN users u ON u.id = l.user_id AND u.tenant_id = ?
                WHERE l.tenant_id = ?
                    AND l.status = 'active'
                    AND l.user_id != ?
                    AND l.category_id IN ({$placeholders})
                ORDER BY COALESCE(l.view_count, 0) DESC, l.created_at DESC
                LIMIT 6
            ", $params);

            return array_map(fn($row) => [
                'id' => $row->id,
                'title' => $row->title,
                'type' => $row->type,
                'image_url' => $row->image_url,
                'location' => $row->location,
                'category_name' => $row->category_name,
                'category_slug' => $row->category_slug,
                'author_name' => trim($row->author_first_name . ' ' . $row->author_last_name),
                'author_avatar' => $row->author_avatar,
                'match_reason' => isset($categoryNames[$row->category_id])
                    ? "Matches your interest in {$categoryNames[$row->category_id]}"
                    : null,
            ], $rows);
        } catch (\Throwable $e) {
            Log::warning('ExploreService::getRecommendedListings failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get trending posts with pagination (for "see more" view).
     */
    public function getTrendingPostsPaginated(int $tenantId, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;

        try {
            $total = DB::selectOne("
                SELECT COUNT(*) AS cnt
                FROM feed_posts fp
                WHERE fp.tenant_id = ?
                    AND fp.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    AND fp.is_deleted = 0
            ", [$tenantId]);

            $rows = DB::select("
                SELECT
                    fp.id,
                    fp.user_id,
                    LEFT(fp.content, 300) AS excerpt,
                    fp.image_url,
                    fp.created_at,
                    COALESCE(u.first_name, '') AS author_first_name,
                    COALESCE(u.last_name, '') AS author_last_name,
                    u.avatar AS author_avatar,
                    (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = fp.id) AS likes_count,
                    (SELECT COUNT(*) FROM comments c WHERE c.commentable_type = 'post' AND c.commentable_id = fp.id AND c.tenant_id = ?) AS comments_count
                FROM feed_posts fp
                JOIN users u ON u.id = fp.user_id AND u.tenant_id = ?
                WHERE fp.tenant_id = ?
                    AND fp.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    AND fp.is_deleted = 0
                ORDER BY (
                    (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = fp.id)
                    + (SELECT COUNT(*) FROM comments c WHERE c.commentable_type = 'post' AND c.commentable_id = fp.id AND c.tenant_id = ?)
                ) DESC, fp.created_at DESC
                LIMIT ? OFFSET ?
            ", [$tenantId, $tenantId, $tenantId, $tenantId, $perPage, $offset]);

            return [
                'items' => array_map(fn($row) => [
                    'id' => $row->id,
                    'user_id' => $row->user_id,
                    'excerpt' => $row->excerpt,
                    'image_url' => $row->image_url,
                    'created_at' => $row->created_at,
                    'author_name' => trim($row->author_first_name . ' ' . $row->author_last_name),
                    'author_avatar' => $row->author_avatar,
                    'likes_count' => (int) $row->likes_count,
                    'comments_count' => (int) $row->comments_count,
                ], $rows),
                'total' => (int) ($total->cnt ?? 0),
                'page' => $page,
                'per_page' => $perPage,
            ];
        } catch (\Throwable $e) {
            Log::warning('ExploreService::getTrendingPostsPaginated failed', ['error' => $e->getMessage()]);
            return ['items' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
        }
    }

    /**
     * Get popular listings with pagination.
     */
    public function getPopularListingsPaginated(int $tenantId, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;

        try {
            $total = DB::selectOne(
                "SELECT COUNT(*) AS cnt FROM listings WHERE tenant_id = ? AND status = 'active'",
                [$tenantId]
            );

            $rows = DB::select("
                SELECT
                    l.id,
                    l.title,
                    l.type,
                    l.description,
                    l.image_url,
                    l.location,
                    l.estimated_hours,
                    l.created_at,
                    COALESCE(l.view_count, 0) AS view_count,
                    COALESCE(l.save_count, 0) AS save_count,
                    COALESCE(cat.name, '') AS category_name,
                    COALESCE(cat.slug, '') AS category_slug,
                    cat.color AS category_color,
                    COALESCE(u.first_name, '') AS author_first_name,
                    COALESCE(u.last_name, '') AS author_last_name,
                    u.avatar AS author_avatar
                FROM listings l
                LEFT JOIN categories cat ON cat.id = l.category_id
                JOIN users u ON u.id = l.user_id AND u.tenant_id = ?
                WHERE l.tenant_id = ?
                    AND l.status = 'active'
                ORDER BY (COALESCE(l.view_count, 0) + COALESCE(l.save_count, 0)) DESC
                LIMIT ? OFFSET ?
            ", [$tenantId, $tenantId, $perPage, $offset]);

            return [
                'items' => array_map(fn($row) => [
                    'id' => $row->id,
                    'title' => $row->title,
                    'type' => $row->type,
                    'description' => $row->description ? mb_substr($row->description, 0, 200) : null,
                    'image_url' => $row->image_url,
                    'location' => $row->location,
                    'estimated_hours' => $row->estimated_hours,
                    'created_at' => $row->created_at,
                    'view_count' => (int) $row->view_count,
                    'save_count' => (int) $row->save_count,
                    'category_name' => $row->category_name,
                    'category_slug' => $row->category_slug,
                    'category_color' => $row->category_color,
                    'author_name' => trim($row->author_first_name . ' ' . $row->author_last_name),
                    'author_avatar' => $row->author_avatar,
                ], $rows),
                'total' => (int) ($total->cnt ?? 0),
                'page' => $page,
                'per_page' => $perPage,
            ];
        } catch (\Throwable $e) {
            Log::warning('ExploreService::getPopularListingsPaginated failed', ['error' => $e->getMessage()]);
            return ['items' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
        }
    }

    /**
     * Browse listings by category slug.
     */
    public function getListingsByCategory(int $tenantId, string $slug, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;

        try {
            $category = DB::selectOne(
                "SELECT id, name, slug, color FROM categories WHERE tenant_id = ? AND slug = ?",
                [$tenantId, $slug]
            );

            if (!$category) {
                return ['items' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'category' => null];
            }

            $total = DB::selectOne(
                "SELECT COUNT(*) AS cnt FROM listings WHERE tenant_id = ? AND category_id = ? AND status = 'active'",
                [$tenantId, $category->id]
            );

            $rows = DB::select("
                SELECT
                    l.id,
                    l.title,
                    l.type,
                    l.description,
                    l.image_url,
                    l.location,
                    l.estimated_hours,
                    l.created_at,
                    COALESCE(l.view_count, 0) AS view_count,
                    COALESCE(u.first_name, '') AS author_first_name,
                    COALESCE(u.last_name, '') AS author_last_name,
                    u.avatar AS author_avatar
                FROM listings l
                JOIN users u ON u.id = l.user_id AND u.tenant_id = ?
                WHERE l.tenant_id = ?
                    AND l.category_id = ?
                    AND l.status = 'active'
                ORDER BY l.created_at DESC
                LIMIT ? OFFSET ?
            ", [$tenantId, $tenantId, $category->id, $perPage, $offset]);

            return [
                'items' => array_map(fn($row) => [
                    'id' => $row->id,
                    'title' => $row->title,
                    'type' => $row->type,
                    'description' => $row->description ? mb_substr($row->description, 0, 200) : null,
                    'image_url' => $row->image_url,
                    'location' => $row->location,
                    'estimated_hours' => $row->estimated_hours,
                    'created_at' => $row->created_at,
                    'view_count' => (int) $row->view_count,
                    'author_name' => trim($row->author_first_name . ' ' . $row->author_last_name),
                    'author_avatar' => $row->author_avatar,
                ], $rows),
                'total' => (int) ($total->cnt ?? 0),
                'page' => $page,
                'per_page' => $perPage,
                'category' => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'color' => $category->color,
                ],
            ];
        } catch (\Throwable $e) {
            Log::warning('ExploreService::getListingsByCategory failed', ['error' => $e->getMessage()]);
            return ['items' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'category' => null];
        }
    }
}
