<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * FeedSocialService — Laravel DI-based service for social feed features.
 *
 * Manages post sharing, trending hashtags, and hashtag-based feed filtering.
 */
class FeedSocialService
{
    /**
     * Share a feed post (create a share record).
     *
     * @return int Share ID.
     */
    public function sharePost(int $postId, int $userId, ?string $comment = null): int
    {
        $tenantId = TenantContext::getId();

        $shareId = DB::table('post_shares')->insertGetId([
            'original_post_id' => $postId,
            'user_id'          => $userId,
            'tenant_id'        => $tenantId,
            'comment'          => $comment,
            'created_at'       => now(),
        ]);

        DB::table('feed_posts')->where('id', $postId)->where('tenant_id', $tenantId)->increment('share_count');

        return $shareId;
    }

    /**
     * Get trending hashtags over the last N days.
     */
    public function getTrendingHashtags(int $days = 7, int $limit = 10): array
    {
        $since = now()->subDays($days);
        $tenantId = TenantContext::getId();

        return DB::table('post_hashtags as ph')
            ->join('hashtags as h', 'ph.hashtag_id', '=', 'h.id')
            ->join('feed_posts as fp', 'ph.post_id', '=', 'fp.id')
            ->where('ph.tenant_id', $tenantId)
            ->where('fp.tenant_id', $tenantId)
            ->where('fp.created_at', '>=', $since)
            ->select('h.tag as hashtag', DB::raw('COUNT(*) as usage_count'))
            ->groupBy('h.tag')
            ->orderByDesc('usage_count')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * Get posts containing a specific hashtag.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public function getHashtagPosts(string $hashtag, array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $cursor = $filters['cursor'] ?? null;
        $tenantId = TenantContext::getId();

        $query = DB::table('feed_posts as fp')
            ->join('post_hashtags as ph', 'fp.id', '=', 'ph.post_id')
            ->join('hashtags as h', 'ph.hashtag_id', '=', 'h.id')
            ->leftJoin('users as u', 'fp.user_id', '=', 'u.id')
            ->where('fp.tenant_id', $tenantId)
            ->where('ph.tenant_id', $tenantId)
            ->where('h.tag', strtolower(ltrim($hashtag, '#')))
            ->select('fp.*', 'u.first_name', 'u.last_name', 'u.avatar_url');

        if ($cursor !== null) {
            $query->where('fp.id', '<', (int) base64_decode($cursor));
        }

        $query->orderByDesc('fp.id');
        $items = $query->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items->pop();
        }

        return [
            'items'    => $items->map(fn ($i) => (array) $i)->values()->all(),
            'cursor'   => $hasMore && $items->isNotEmpty() ? base64_encode((string) $items->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }
}
