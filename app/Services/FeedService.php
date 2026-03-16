<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\FeedPost;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * FeedService — Laravel DI-based service for social feed operations.
 *
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\FeedService.
 * All queries are tenant-scoped automatically via the HasTenantScope trait.
 */
class FeedService
{
    public function __construct(
        private readonly FeedPost $feedPost,
    ) {}

    /**
     * Get feed posts with cursor-based pagination.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public function getFeed(?int $currentUserId, array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $cursor = $filters['cursor'] ?? null;

        $query = $this->feedPost->newQuery()
            ->with(['user:id,first_name,last_name,avatar_url,organization_name,profile_type'])
            ->whereNull('parent_id'); // Top-level posts only

        // Profile feed
        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        // Group feed
        if (! empty($filters['group_id'])) {
            $query->where('parent_type', 'group')
                  ->where('parent_id', (int) $filters['group_id']);
        }

        // Cursor
        if ($cursor !== null) {
            $cursorId = base64_decode($cursor, true);
            if ($cursorId !== false) {
                $query->where('id', '<', (int) $cursorId);
            }
        }

        $query->orderByDesc('id');

        $items = $query->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items->pop();
        }

        // Batch-load engagement counts
        $postIds = $items->pluck('id');

        $likeCounts = DB::table('likes')
            ->selectRaw('target_id, COUNT(*) as count')
            ->where('target_type', 'feed_post')
            ->whereIn('target_id', $postIds)
            ->groupBy('target_id')
            ->pluck('count', 'target_id');

        $commentCounts = DB::table('comments')
            ->selectRaw('target_id, COUNT(*) as count')
            ->where('target_type', 'feed_post')
            ->whereIn('target_id', $postIds)
            ->groupBy('target_id')
            ->pluck('count', 'target_id');

        // Check liked status for current user
        $likedByUser = [];
        if ($currentUserId) {
            $likedByUser = DB::table('likes')
                ->where('target_type', 'feed_post')
                ->where('user_id', $currentUserId)
                ->whereIn('target_id', $postIds)
                ->pluck('target_id')
                ->flip()
                ->all();
        }

        $result = $items->map(function (FeedPost $post) use ($likeCounts, $commentCounts, $likedByUser) {
            $data = $post->toArray();
            $data['likes_count'] = (int) ($likeCounts[$post->id] ?? 0);
            $data['comments_count'] = (int) ($commentCounts[$post->id] ?? 0);
            $data['is_liked'] = isset($likedByUser[$post->id]);
            return $data;
        })->all();

        return [
            'items'    => array_values($result),
            'cursor'   => $hasMore && $items->isNotEmpty() ? base64_encode((string) $items->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Create a feed post.
     */
    public function createPost(int $userId, array $data): FeedPost
    {
        $post = $this->feedPost->newInstance([
            'user_id'     => $userId,
            'content'     => trim($data['content']),
            'emoji'       => $data['emoji'] ?? null,
            'image_url'   => $data['image_url'] ?? null,
            'parent_type' => $data['parent_type'] ?? null,
            'parent_id'   => $data['parent_id'] ?? null,
            'visibility'  => $data['visibility'] ?? 'public',
        ]);

        $post->save();

        return $post->fresh(['user']);
    }

    /**
     * Toggle like on a feed post.
     *
     * @return array{liked: bool, likes_count: int}
     */
    public function like(int $postId, int $userId): array
    {
        $existing = DB::table('likes')
            ->where('target_type', 'feed_post')
            ->where('target_id', $postId)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            DB::table('likes')
                ->where('target_type', 'feed_post')
                ->where('target_id', $postId)
                ->where('user_id', $userId)
                ->delete();
            $liked = false;
        } else {
            DB::table('likes')->insert([
                'target_type' => 'feed_post',
                'target_id'   => $postId,
                'user_id'     => $userId,
                'created_at'  => now(),
            ]);
            $liked = true;
        }

        $count = (int) DB::table('likes')
            ->where('target_type', 'feed_post')
            ->where('target_id', $postId)
            ->count();

        return ['liked' => $liked, 'likes_count' => $count];
    }
}
