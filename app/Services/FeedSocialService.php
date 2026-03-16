<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

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
        $shareId = DB::table('feed_shares')->insertGetId([
            'post_id'    => $postId,
            'user_id'    => $userId,
            'comment'    => $comment,
            'created_at' => now(),
        ]);

        DB::table('feed_posts')->where('id', $postId)->increment('share_count');

        return $shareId;
    }

    /**
     * Get trending hashtags over the last N days.
     */
    public function getTrendingHashtags(int $days = 7, int $limit = 10): array
    {
        $since = now()->subDays($days);

        return DB::table('feed_hashtags as fh')
            ->join('feed_posts as fp', 'fh.post_id', '=', 'fp.id')
            ->where('fp.created_at', '>=', $since)
            ->select('fh.hashtag', DB::raw('COUNT(*) as usage_count'))
            ->groupBy('fh.hashtag')
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

        $query = DB::table('feed_posts as fp')
            ->join('feed_hashtags as fh', 'fp.id', '=', 'fh.post_id')
            ->leftJoin('users as u', 'fp.user_id', '=', 'u.id')
            ->where('fh.hashtag', strtolower(ltrim($hashtag, '#')))
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
