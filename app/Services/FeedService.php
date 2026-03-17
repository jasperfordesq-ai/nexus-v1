<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\FeedActivity;
use App\Models\FeedPost;
use App\Models\Like;
use Illuminate\Support\Facades\DB;
use Nexus\Core\TenantContext;

/**
 * FeedService — Laravel DI-based service for social feed operations.
 *
 * Queries the feed_activity table (unified feed) to match the legacy response
 * shape expected by the React frontend. All queries are tenant-scoped
 * automatically via the HasTenantScope trait on the FeedActivity model.
 */
class FeedService
{
    /**
     * Map plural filter names to source_type values (matches legacy).
     */
    private const TYPE_MAP = [
        'posts' => 'post', 'listings' => 'listing', 'events' => 'event',
        'polls' => 'poll', 'goals' => 'goal', 'jobs' => 'job',
        'challenges' => 'challenge', 'volunteering' => 'volunteer',
        'blogs' => 'blog', 'discussions' => 'discussion',
    ];

    public function __construct(
        private readonly FeedActivity $feedActivity,
        private readonly FeedPost $feedPost,
    ) {}

    /**
     * Get feed items from feed_activity with cursor-based pagination.
     *
     * Returns the same response shape as the legacy FeedService::getFeed().
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public function getFeed(?int $currentUserId, array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $type = $filters['type'] ?? 'all';
        $profileUserId = $filters['user_id'] ?? null;
        $groupId = $filters['group_id'] ?? null;
        $subtype = $filters['subtype'] ?? null;
        $cursor = $filters['cursor'] ?? null;

        // Decode cursor: base64("created_at|activity_id") or legacy base64("activity_id")
        $cursorCreatedAt = null;
        $cursorActivityId = null;
        if ($cursor) {
            $decoded = base64_decode($cursor, true);
            if ($decoded !== false) {
                if (str_contains($decoded, '|')) {
                    [$cursorCreatedAt, $cursorActivityIdStr] = explode('|', $decoded, 2);
                    $cursorActivityId = ctype_digit($cursorActivityIdStr) ? (int) $cursorActivityIdStr : null;
                } elseif (ctype_digit($decoded)) {
                    $cursorActivityId = (int) $decoded;
                }
            }
        }

        // Map type filter to source_type
        $sourceType = null;
        if ($type !== 'all') {
            $sourceType = self::TYPE_MAP[$type] ?? $type;
        }

        // Build query
        $query = $this->feedActivity->newQuery()
            ->join('users as u', 'feed_activity.user_id', '=', 'u.id')
            ->where('feed_activity.is_visible', true)
            ->select([
                'feed_activity.id as activity_id',
                'feed_activity.source_type',
                'feed_activity.source_id',
                'feed_activity.user_id',
                'feed_activity.title',
                'feed_activity.content',
                'feed_activity.image_url',
                'feed_activity.metadata',
                'feed_activity.group_id',
                'feed_activity.created_at',
                DB::raw("COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name"),
                'u.avatar_url as author_avatar',
                'u.location as user_location',
            ]);

        if ($sourceType !== null) {
            $query->where('feed_activity.source_type', $sourceType);
        }

        if ($profileUserId !== null) {
            $query->where('feed_activity.user_id', (int) $profileUserId);
        }

        if ($groupId !== null) {
            $query->where('feed_activity.group_id', (int) $groupId);
        }

        if ($subtype !== null) {
            $query->whereRaw(
                "JSON_UNQUOTE(JSON_EXTRACT(feed_activity.metadata, '$.listing_type')) = ?",
                [$subtype]
            );
        }

        // Cursor pagination by (created_at, id) tuple
        if ($cursorCreatedAt !== null && $cursorActivityId !== null) {
            $query->where(function ($q) use ($cursorCreatedAt, $cursorActivityId) {
                $q->where('feed_activity.created_at', '<', $cursorCreatedAt)
                  ->orWhere(function ($q2) use ($cursorCreatedAt, $cursorActivityId) {
                      $q2->where('feed_activity.created_at', '=', $cursorCreatedAt)
                         ->where('feed_activity.id', '<', $cursorActivityId);
                  });
            });
        } elseif ($cursorActivityId !== null) {
            $query->where('feed_activity.id', '<', $cursorActivityId);
        }

        $query->orderByDesc('feed_activity.created_at')
              ->orderByDesc('feed_activity.id');

        $rows = $query->limit($limit + 1)->get();

        $hasMore = $rows->count() > $limit;
        if ($hasMore) {
            $rows->pop();
        }

        if ($rows->isEmpty()) {
            return ['items' => [], 'cursor' => null, 'has_more' => false];
        }

        // Collect source IDs grouped by type for batch loading likes/comments
        $sourcesByType = [];
        foreach ($rows as $row) {
            $sourcesByType[$row->source_type][] = (int) $row->source_id;
        }

        // Batch load like counts
        $likeCounts = [];
        foreach ($sourcesByType as $sType => $sIds) {
            $counts = DB::table('likes')
                ->selectRaw('target_id, COUNT(*) as cnt')
                ->where('target_type', $sType)
                ->whereIn('target_id', $sIds)
                ->groupBy('target_id')
                ->pluck('cnt', 'target_id');
            foreach ($counts as $targetId => $cnt) {
                $likeCounts[$sType . ':' . $targetId] = (int) $cnt;
            }
        }

        // Batch load comment counts
        $commentCounts = [];
        foreach ($sourcesByType as $sType => $sIds) {
            $counts = DB::table('comments')
                ->selectRaw('target_id, COUNT(*) as cnt')
                ->where('target_type', $sType)
                ->whereIn('target_id', $sIds)
                ->groupBy('target_id')
                ->pluck('cnt', 'target_id');
            foreach ($counts as $targetId => $cnt) {
                $commentCounts[$sType . ':' . $targetId] = (int) $cnt;
            }
        }

        // Batch load liked status for current user
        $likedSet = [];
        if ($currentUserId) {
            $tenantId = TenantContext::getId();
            foreach ($sourcesByType as $sType => $sIds) {
                $liked = DB::table('likes')
                    ->where('user_id', $currentUserId)
                    ->where('target_type', $sType)
                    ->where('tenant_id', $tenantId)
                    ->whereIn('target_id', $sIds)
                    ->pluck('target_id');
                foreach ($liked as $targetId) {
                    $likedSet[$sType . ':' . $targetId] = true;
                }
            }
        }

        // Batch load poll data for poll items
        $pollDataMap = $this->batchLoadPollData($rows, $currentUserId);

        // Collect receiver IDs for review enrichment
        $receiverIds = [];

        // Transform rows into the format expected by the React frontend
        $items = [];
        foreach ($rows as $row) {
            $meta = $row->metadata ? json_decode($row->metadata, true) : [];
            $likeKey = $row->source_type . ':' . $row->source_id;

            $contentResult = $this->truncateWithFlag($row->content ?? '', 500);

            $entry = [
                'id' => (int) $row->source_id,
                'type' => $row->source_type,
                'title' => $row->title,
                'content' => $contentResult['text'],
                'content_truncated' => $contentResult['truncated'],
                'image_url' => $row->image_url,
                'author' => [
                    'id' => (int) $row->user_id,
                    'name' => $row->author_name,
                    'avatar_url' => $row->author_avatar ?? '/assets/img/defaults/default_avatar.png',
                ],
                'likes_count' => $likeCounts[$likeKey] ?? 0,
                'comments_count' => $commentCounts[$likeKey] ?? 0,
                'is_liked' => isset($likedSet[$likeKey]),
                'created_at' => $row->created_at,
                // Event metadata
                'start_date' => $meta['start_date'] ?? null,
                'location' => $meta['location'] ?? ($row->source_type === 'listing' ? $row->user_location : null),
                // Review metadata
                'rating' => isset($meta['rating']) ? (int) $meta['rating'] : null,
                'receiver' => isset($meta['receiver_id']) ? ['id' => (int) $meta['receiver_id'], 'name' => ''] : null,
                // Job metadata
                'job_type' => $meta['job_type'] ?? null,
                'commitment' => $meta['commitment'] ?? null,
                // Challenge metadata
                'submission_deadline' => $meta['submission_deadline'] ?? null,
                'ideas_count' => isset($meta['ideas_count']) ? (int) $meta['ideas_count'] : null,
                // Listing metadata
                'listing_type' => $meta['listing_type'] ?? null,
                // Volunteer metadata
                'credits_offered' => isset($meta['credits_offered']) ? (int) $meta['credits_offered'] : null,
                'organization' => $meta['organization'] ?? null,
                // Internal cursor fields
                '_activity_id' => (int) $row->activity_id,
                '_activity_created_at' => (string) $row->created_at,
            ];

            // Include poll_data for poll-type items
            if ($row->source_type === 'poll' && isset($pollDataMap[(int) $row->source_id])) {
                $entry['poll_data'] = $pollDataMap[(int) $row->source_id];
            }

            // Collect receiver IDs for review enrichment
            if ($row->source_type === 'review' && isset($meta['receiver_id']) && (int) $meta['receiver_id'] > 0) {
                $receiverIds[] = (int) $meta['receiver_id'];
            }

            $items[] = $entry;
        }

        // Enrich review receivers with names
        if (!empty($receiverIds)) {
            $nameMap = DB::table('users')
                ->whereIn('id', array_unique($receiverIds))
                ->pluck(DB::raw("COALESCE(name, CONCAT(first_name, ' ', last_name))"), 'id');
            foreach ($items as &$item) {
                if ($item['type'] === 'review' && isset($item['receiver']['id'])) {
                    $item['receiver']['name'] = $nameMap[$item['receiver']['id']] ?? 'Unknown';
                }
            }
            unset($item);
        }

        // Generate cursor
        $nextCursor = null;
        if ($hasMore && !empty($items)) {
            $lastItem = end($items);
            if (isset($lastItem['_activity_id'], $lastItem['_activity_created_at'])) {
                $nextCursor = base64_encode($lastItem['_activity_created_at'] . '|' . $lastItem['_activity_id']);
            }
        }

        return [
            'items'    => $items,
            'cursor'   => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Truncate text content and return a truncated flag.
     */
    private function truncateWithFlag(string $text, int $maxLength): array
    {
        if (mb_strlen($text) <= $maxLength) {
            return ['text' => $text, 'truncated' => false];
        }

        return [
            'text' => mb_substr($text, 0, $maxLength) . '...',
            'truncated' => true,
        ];
    }

    /**
     * Batch load poll data for poll-type items in the feed.
     */
    private function batchLoadPollData($rows, ?int $userId): array
    {
        $pollIds = [];
        foreach ($rows as $row) {
            if ($row->source_type === 'poll') {
                $pollIds[] = (int) $row->source_id;
            }
        }

        if (empty($pollIds)) {
            return [];
        }

        $tenantId = TenantContext::getId();
        $pollDataMap = [];

        // Load poll options
        $options = DB::table('poll_options')
            ->whereIn('poll_id', $pollIds)
            ->get();

        $optionsByPoll = $options->groupBy('poll_id');

        // Load vote counts
        $voteCounts = DB::table('poll_votes')
            ->selectRaw('poll_id, option_id, COUNT(*) as cnt')
            ->whereIn('poll_id', $pollIds)
            ->groupBy('poll_id', 'option_id')
            ->get();

        $voteCountMap = [];
        foreach ($voteCounts as $vc) {
            $voteCountMap[$vc->poll_id][$vc->option_id] = (int) $vc->cnt;
        }

        // Load user's votes
        $userVotes = [];
        if ($userId) {
            $votes = DB::table('poll_votes')
                ->where('user_id', $userId)
                ->whereIn('poll_id', $pollIds)
                ->pluck('option_id', 'poll_id');
            $userVotes = $votes->all();
        }

        foreach ($pollIds as $pollId) {
            $opts = $optionsByPoll[$pollId] ?? collect();
            $totalVotes = 0;
            $formattedOptions = [];
            foreach ($opts as $opt) {
                $count = $voteCountMap[$pollId][$opt->id] ?? 0;
                $totalVotes += $count;
                $formattedOptions[] = [
                    'id' => (int) $opt->id,
                    'text' => $opt->option_text ?? $opt->text ?? '',
                    'votes' => $count,
                ];
            }

            $pollDataMap[$pollId] = [
                'options' => $formattedOptions,
                'total_votes' => $totalVotes,
                'user_vote' => $userVotes[$pollId] ?? null,
            ];
        }

        return $pollDataMap;
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
     * Create a feed post — delegates to legacy FeedService.
     *
     * @return int|null Post ID or null on failure
     */
    public function createPostLegacy(int $userId, array $data): ?int
    {
        return \Nexus\Services\FeedService::createPost($userId, $data);
    }

    /**
     * Get a single feed item by type and ID — delegates to legacy FeedService.
     */
    public function getItem(string $type, int $id, ?int $userId): ?array
    {
        return \Nexus\Services\FeedService::getItem($type, $id, $userId);
    }

    /**
     * Get validation errors — delegates to legacy FeedService.
     */
    public function getErrors(): array
    {
        return \Nexus\Services\FeedService::getErrors();
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
