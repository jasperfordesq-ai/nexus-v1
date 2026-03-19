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
use App\Core\TenantContext;

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
        $tenantId = TenantContext::getId();
        foreach ($sourcesByType as $sType => $sIds) {
            $counts = DB::table('likes')
                ->selectRaw('target_id, COUNT(*) as cnt')
                ->where('target_type', $sType)
                ->whereIn('target_id', $sIds)
                ->where('tenant_id', $tenantId)
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
                ->where('tenant_id', $tenantId)
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
            $meta = is_array($row->metadata) ? $row->metadata : ($row->metadata ? json_decode($row->metadata, true) : []);
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

    /** @var array Validation error messages */
    private array $errors = [];

    /**
     * Create a feed post via direct DB insert.
     *
     * @return int|null Post ID or null on failure
     */
    public function createPostLegacy(int $userId, array $data): ?int
    {
        $this->errors = [];

        $rawContent = trim($data['content'] ?? '');
        $content = $rawContent;
        $imageUrl = $data['image_url'] ?? null;
        $visibility = $data['visibility'] ?? 'public';
        $groupId = (int) ($data['group_id'] ?? 0);

        $validVisibility = ['public', 'private', 'friends'];
        if (!in_array($visibility, $validVisibility, true)) {
            $visibility = 'public';
        }

        if (empty($content) && empty($imageUrl)) {
            $this->errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Content or image is required', 'field' => 'content'];
            return null;
        }

        // Validate group membership if posting to group
        if ($groupId > 0) {
            $isMember = DB::selectOne(
                "SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ? AND status = 'active'",
                [$groupId, $userId]
            );
            if (!$isMember) {
                $this->errors[] = ['code' => 'FORBIDDEN', 'message' => 'You must be a group member to post'];
                return null;
            }
        }

        try {
            $tenantId = TenantContext::getId();

            if ($groupId > 0) {
                DB::insert(
                    "INSERT INTO feed_posts (user_id, tenant_id, content, image_url, likes_count, visibility, group_id, created_at) VALUES (?, ?, ?, ?, 0, ?, ?, NOW())",
                    [$userId, $tenantId, $content, $imageUrl, $visibility, $groupId]
                );
            } else {
                DB::insert(
                    "INSERT INTO feed_posts (user_id, tenant_id, content, image_url, likes_count, visibility, created_at) VALUES (?, ?, ?, ?, 0, ?, NOW())",
                    [$userId, $tenantId, $content, $imageUrl, $visibility]
                );
            }

            $postId = (int) DB::getPdo()->lastInsertId();

            // Record in feed_activity table
            try {
                DB::statement(
                    "INSERT INTO feed_activity
                        (tenant_id, user_id, source_type, source_id, group_id, title, content, image_url, metadata, is_visible, created_at)
                    VALUES (?, ?, 'post', ?, ?, NULL, ?, ?, NULL, 1, NOW())
                    ON DUPLICATE KEY UPDATE
                        content = VALUES(content), image_url = VALUES(image_url), is_visible = 1, created_at = VALUES(created_at)",
                    [$tenantId, $userId, $postId, $groupId ?: null, $content, $imageUrl]
                );
            } catch (\Exception $faEx) {
                \Illuminate\Support\Facades\Log::warning("FeedService::createPostLegacy feed_activity record failed: " . $faEx->getMessage());
            }

            return $postId;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("FeedService::createPostLegacy error: " . $e->getMessage());
            $this->errors[] = ['code' => 'SERVER_ERROR', 'message' => 'Failed to create post'];
            return null;
        }
    }

    /**
     * Get a single feed item by type and ID.
     */
    public function getItem(string $type, int $id, ?int $userId): ?array
    {
        $tenantId = TenantContext::getId();
        $items = [];

        switch ($type) {
            case 'post':
                $rows = DB::select(
                    "SELECT p.id, p.content, p.image_url, p.created_at, p.likes_count, p.user_id,
                           'post' as type,
                           COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name,
                           u.avatar_url as author_avatar,
                           (SELECT COUNT(*) FROM comments WHERE target_type = 'post' AND target_id = p.id) as comments_count
                    FROM feed_posts p
                    JOIN users u ON p.user_id = u.id
                    WHERE p.id = ? AND p.tenant_id = ?",
                    [$id, $tenantId]
                );
                $items = array_map(fn($r) => (array) $r, $rows);
                break;

            case 'blog':
                $rows = DB::select(
                    "SELECT p.id, p.title, p.content, p.featured_image as image_url, p.created_at,
                           0 as likes_count, p.author_id as user_id, 'blog' as type,
                           COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name,
                           u.avatar_url as author_avatar,
                           (SELECT COUNT(*) FROM comments WHERE target_type = 'blog' AND target_id = p.id) as comments_count
                    FROM posts p
                    JOIN users u ON p.author_id = u.id
                    WHERE p.id = ? AND p.tenant_id = ? AND p.status = 'published'",
                    [$id, $tenantId]
                );
                $items = array_map(fn($r) => (array) $r, $rows);
                break;

            case 'discussion':
                $rows = DB::select(
                    "SELECT gd.id, gd.title, gp_first.content, NULL as image_url, gd.created_at,
                           0 as likes_count, gd.user_id, 'discussion' as type,
                           COALESCE(u.name, CONCAT(u.first_name, ' ', u.last_name)) as author_name,
                           u.avatar_url as author_avatar,
                           (SELECT COUNT(*) FROM group_posts gpc WHERE gpc.discussion_id = gd.id AND gpc.tenant_id = gd.tenant_id) as comments_count
                    FROM group_discussions gd
                    JOIN users u ON gd.user_id = u.id
                    LEFT JOIN group_posts gp_first ON gp_first.discussion_id = gd.id AND gp_first.tenant_id = gd.tenant_id
                        AND gp_first.id = (SELECT MIN(gpm.id) FROM group_posts gpm WHERE gpm.discussion_id = gd.id AND gpm.tenant_id = gd.tenant_id)
                    WHERE gd.id = ? AND gd.tenant_id = ?",
                    [$id, $tenantId]
                );
                $items = array_map(fn($r) => (array) $r, $rows);
                break;
        }

        if (empty($items)) {
            return null;
        }

        // Format like the feed does — enrich with like status
        $item = $items[0];
        $contentResult = $this->truncateWithFlag($item['content'] ?? '', 500);

        $isLiked = false;
        if ($userId) {
            $liked = DB::selectOne(
                "SELECT 1 FROM likes WHERE user_id = ? AND target_type = ? AND target_id = ? AND tenant_id = ?",
                [$userId, $type, $id, $tenantId]
            );
            $isLiked = (bool) $liked;
        }

        return [
            'id' => (int) $item['id'],
            'type' => $item['type'],
            'title' => $item['title'] ?? null,
            'content' => $contentResult['text'],
            'content_truncated' => $contentResult['truncated'],
            'image_url' => $item['image_url'] ?? null,
            'author' => [
                'id' => (int) $item['user_id'],
                'name' => $item['author_name'],
                'avatar_url' => $item['author_avatar'] ?? '/assets/img/defaults/default_avatar.png',
            ],
            'likes_count' => (int) ($item['likes_count'] ?? 0),
            'comments_count' => (int) ($item['comments_count'] ?? 0),
            'is_liked' => $isLiked,
            'created_at' => $item['created_at'],
        ];
    }

    /**
     * Get validation errors from the last operation.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Toggle like on a feed post.
     *
     * @return array{liked: bool, likes_count: int}
     */
    public function like(int $postId, int $userId): array
    {
        $tenantId = TenantContext::getId();

        $existing = DB::table('likes')
            ->where('target_type', 'feed_post')
            ->where('target_id', $postId)
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->first();

        if ($existing) {
            DB::table('likes')
                ->where('target_type', 'feed_post')
                ->where('target_id', $postId)
                ->where('user_id', $userId)
                ->where('tenant_id', $tenantId)
                ->delete();
            $liked = false;
        } else {
            DB::table('likes')->insert([
                'target_type' => 'feed_post',
                'target_id'   => $postId,
                'user_id'     => $userId,
                'tenant_id'   => $tenantId,
                'created_at'  => now(),
            ]);
            $liked = true;
        }

        $count = (int) DB::table('likes')
            ->where('target_type', 'feed_post')
            ->where('target_id', $postId)
            ->where('tenant_id', $tenantId)
            ->count();

        return ['liked' => $liked, 'likes_count' => $count];
    }
}
