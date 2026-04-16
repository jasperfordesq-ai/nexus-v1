<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\FeedActivity;
use App\Models\FeedPost;
use App\Models\Like;
use App\Services\LinkPreviewService;
use App\Services\MentionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
        'badge_earned' => 'badge_earned', 'level_up' => 'level_up',
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

        if (isset($filters['post_id'])) {
            $query->where('feed_activity.source_id', (int) $filters['post_id'])
                  ->where('feed_activity.source_type', 'post');
        }

        if ($subtype !== null) {
            $query->whereRaw(
                "JSON_UNQUOTE(JSON_EXTRACT(feed_activity.metadata, '$.listing_type')) = ?",
                [$subtype]
            );
        }

        // Exclude admin-hidden posts
        $query->where('feed_activity.is_hidden', false);

        // Exclude posts hidden by current user (both legacy and V2 tables)
        if ($currentUserId) {
            $query->whereNotExists(function ($sub) use ($currentUserId) {
                $sub->select(DB::raw(1))
                    ->from('feed_hidden')
                    ->whereColumn('feed_hidden.target_id', 'feed_activity.source_id')
                    ->whereColumn('feed_hidden.target_type', 'feed_activity.source_type')
                    ->where('feed_hidden.user_id', $currentUserId);
            });
            $query->whereNotExists(function ($sub) use ($currentUserId) {
                $sub->select(DB::raw(1))
                    ->from('user_hidden_posts')
                    ->whereColumn('user_hidden_posts.post_id', 'feed_activity.source_id')
                    ->where('user_hidden_posts.user_id', $currentUserId);
            });
        }

        // Exclude blocked users (both directions)
        if ($currentUserId) {
            $blockedIds = BlockUserService::getBlockedPairIds($currentUserId);
            if (!empty($blockedIds)) {
                $query->whereNotIn('feed_activity.user_id', $blockedIds);
            }
        }

        // Exclude muted users (both legacy and V2 tables)
        if ($currentUserId) {
            $query->whereNotExists(function ($sub) use ($currentUserId) {
                $sub->select(DB::raw(1))
                    ->from('feed_muted_users')
                    ->whereColumn('feed_muted_users.muted_user_id', 'feed_activity.user_id')
                    ->where('feed_muted_users.user_id', $currentUserId);
            });
            $query->whereNotExists(function ($sub) use ($currentUserId) {
                $sub->select(DB::raw(1))
                    ->from('user_muted_users')
                    ->whereColumn('user_muted_users.muted_user_id', 'feed_activity.user_id')
                    ->where('user_muted_users.user_id', $currentUserId);
            });
        }

        // Hide private group posts from non-members (unless viewing a specific group feed)
        $tenantId = TenantContext::getId();
        if (!isset($filters['group_id'])) {
            if ($currentUserId) {
                // Authenticated: show public group posts + groups user is a member of
                $query->where(function ($q) use ($currentUserId, $tenantId) {
                    $q->whereNull('feed_activity.group_id')
                      ->orWhereExists(function ($sub) use ($currentUserId, $tenantId) {
                          $sub->select(DB::raw(1))
                              ->from('group_members')
                              ->whereColumn('group_members.group_id', 'feed_activity.group_id')
                              ->where('group_members.user_id', $currentUserId)
                              ->where('group_members.tenant_id', $tenantId)
                              ->where('group_members.status', 'active');
                      })
                      ->orWhereExists(function ($sub) use ($tenantId) {
                          $sub->select(DB::raw(1))
                              ->from('groups')
                              ->whereColumn('groups.id', 'feed_activity.group_id')
                              ->where('groups.tenant_id', $tenantId)
                              ->where('groups.visibility', 'public');
                      });
                });
            } else {
                // Unauthenticated: only show non-group posts and public group posts
                $query->where(function ($q) use ($tenantId) {
                    $q->whereNull('feed_activity.group_id')
                      ->orWhereExists(function ($sub) use ($tenantId) {
                          $sub->select(DB::raw(1))
                              ->from('groups')
                              ->whereColumn('groups.id', 'feed_activity.group_id')
                              ->where('groups.tenant_id', $tenantId)
                              ->where('groups.visibility', 'public');
                      });
                });
            }
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
                // Gamification metadata
                'badge_key' => $meta['badge_key'] ?? null,
                'badge_name' => $meta['badge_name'] ?? null,
                'badge_icon' => $meta['badge_icon'] ?? null,
                'new_level' => isset($meta['new_level']) ? (int) $meta['new_level'] : null,
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
                ->where('tenant_id', $tenantId)
                ->pluck(DB::raw("COALESCE(name, CONCAT(first_name, ' ', last_name))"), 'id');
            foreach ($items as &$item) {
                if ($item['type'] === 'review' && isset($item['receiver']['id'])) {
                    $item['receiver']['name'] = $nameMap[$item['receiver']['id']] ?? 'Unknown';
                }
            }
            unset($item);
        }

        // Batch load views_count + share_count for post items from feed_posts table
        $postIds = [];
        foreach ($items as $item) {
            if ($item['type'] === 'post') {
                $postIds[] = (int) $item['id'];
            }
        }
        if (!empty($postIds)) {
            $selectCols = ['id', 'share_count'];
            $hasViewsCount = Schema::hasColumn('feed_posts', 'views_count');
            if ($hasViewsCount) {
                $selectCols[] = 'views_count';
            }
            $postMeta = DB::table('feed_posts')
                ->whereIn('id', $postIds)
                ->where('tenant_id', $tenantId)
                ->select($selectCols)
                ->get()
                ->keyBy('id');
            foreach ($items as &$item) {
                if ($item['type'] === 'post' && isset($postMeta[$item['id']])) {
                    $item['views_count'] = $hasViewsCount ? (int) $postMeta[$item['id']]->views_count : 0;
                    $item['share_count'] = (int) $postMeta[$item['id']]->share_count;
                }
            }
            unset($item);
        }

        // Batch load bookmark status for current user
        if ($currentUserId && !empty($postIds)) {
            $bookmarkedPostIds = DB::table('bookmarks')
                ->where('user_id', $currentUserId)
                ->where('tenant_id', $tenantId)
                ->where('bookmarkable_type', 'post')
                ->whereIn('bookmarkable_id', $postIds)
                ->pluck('bookmarkable_id')
                ->all();
            $bookmarkedSet = array_flip($bookmarkedPostIds);
            foreach ($items as &$item) {
                if ($item['type'] === 'post') {
                    $item['is_bookmarked'] = isset($bookmarkedSet[$item['id']]);
                }
            }
            unset($item);
        }

        // Batch load link previews for post items
        try {
            if (!empty($postIds)) {
                /** @var LinkPreviewService $linkPreviewService */
                $linkPreviewService = app(LinkPreviewService::class);
                $previewMap = $linkPreviewService->batchLoadPostPreviews($postIds);
                foreach ($items as &$item) {
                    if ($item['type'] === 'post' && isset($previewMap[$item['id']])) {
                        $item['link_previews'] = $previewMap[$item['id']];
                    }
                }
                unset($item);
            }
        } catch (\Exception $e) {
            // Link preview loading should never break the feed
        }

        // Batch load quoted posts for post items (quote reposts)
        try {
            if (!empty($postIds)) {
                $quotedMap = DB::table('feed_posts')
                    ->whereIn('id', $postIds)
                    ->where('tenant_id', $tenantId)
                    ->whereNotNull('quoted_post_id')
                    ->pluck('quoted_post_id', 'id');

                if ($quotedMap->isNotEmpty()) {
                    $quotedPostIds = $quotedMap->values()->unique()->all();
                    $quotedPosts = DB::table('feed_posts')
                        ->join('users', 'feed_posts.user_id', '=', 'users.id')
                        ->whereIn('feed_posts.id', $quotedPostIds)
                        ->where('feed_posts.tenant_id', $tenantId)
                        ->select([
                            'feed_posts.id',
                            'feed_posts.content',
                            'feed_posts.image_url',
                            'feed_posts.created_at',
                            'feed_posts.user_id',
                            DB::raw("COALESCE(users.name, CONCAT(users.first_name, ' ', users.last_name)) as author_name"),
                            'users.avatar_url as author_avatar',
                        ])
                        ->get()
                        ->keyBy('id');

                    // Load media for quoted posts
                    $quotedMediaMap = [];
                    if ($quotedPosts->isNotEmpty()) {
                        $quotedMedia = DB::table('post_media')
                            ->whereIn('post_id', $quotedPostIds)
                            ->where('tenant_id', $tenantId)
                            ->orderBy('display_order')
                            ->get();
                        foreach ($quotedMedia as $m) {
                            $quotedMediaMap[$m->post_id][] = [
                                'id' => (int) $m->id,
                                'media_type' => $m->media_type,
                                'file_url' => $m->file_url,
                                'thumbnail_url' => $m->thumbnail_url,
                                'alt_text' => $m->alt_text,
                            ];
                        }
                    }

                    foreach ($items as &$item) {
                        if ($item['type'] === 'post' && isset($quotedMap[$item['id']])) {
                            $qpId = $quotedMap[$item['id']];
                            if (isset($quotedPosts[$qpId])) {
                                $qp = $quotedPosts[$qpId];
                                $qpContent = $this->truncateWithFlag($qp->content ?? '', 280);
                                $item['quoted_post'] = [
                                    'id' => (int) $qp->id,
                                    'content' => $qpContent['text'],
                                    'content_truncated' => $qpContent['truncated'],
                                    'image_url' => $qp->image_url,
                                    'created_at' => (string) $qp->created_at,
                                    'author' => [
                                        'id' => (int) $qp->user_id,
                                        'name' => $qp->author_name,
                                        'avatar_url' => $qp->author_avatar ?? '/assets/img/defaults/default_avatar.png',
                                    ],
                                    'media' => $quotedMediaMap[$qpId] ?? [],
                                ];
                            }
                        }
                    }
                    unset($item);
                }
            }
        } catch (\Exception $e) {
            // Quoted post loading should never break the feed
            Log::warning('FeedService: quoted post batch load failed: ' . $e->getMessage());
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
            ->where('tenant_id', $tenantId)
            ->get();

        $optionsByPoll = $options->groupBy('poll_id');

        // Load vote counts
        $voteCounts = DB::table('poll_votes')
            ->selectRaw('poll_id, option_id, COUNT(*) as cnt')
            ->whereIn('poll_id', $pollIds)
            ->where('tenant_id', $tenantId)
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
                ->where('tenant_id', $tenantId)
                ->pluck('option_id', 'poll_id');
            $userVotes = $votes->all();
        }

        // Load poll rows for is_active and question
        $polls = DB::table('polls')
            ->whereIn('id', $pollIds)
            ->where('tenant_id', $tenantId)
            ->get()
            ->keyBy('id');

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
                    'vote_count' => $count,
                ];
            }

            // Compute percentages after we know the total
            foreach ($formattedOptions as &$fopt) {
                $fopt['percentage'] = $totalVotes > 0
                    ? round(($fopt['vote_count'] / $totalVotes) * 100, 1)
                    : 0;
            }
            unset($fopt);

            $poll = $polls[$pollId] ?? null;

            $pollDataMap[$pollId] = [
                'id' => $pollId,
                'question' => $poll->question ?? '',
                'options' => $formattedOptions,
                'total_votes' => $totalVotes,
                'user_vote_option_id' => $userVotes[$pollId] ?? null,
                'is_active' => (bool) ($poll->is_active ?? true),
            ];
        }

        return $pollDataMap;
    }

    /**
     * Create a feed post.
     *
     * @return FeedPost|array FeedPost on success, error array on validation failure
     */
    public function createPost(int $userId, array $data): FeedPost|array
    {
        $tenantId = TenantContext::getId();
        // Server-side XSS prevention: sanitize HTML content before storage
        $content = \App\Helpers\HtmlSanitizer::sanitize(trim($data['content'] ?? ''));
        $image = $data['image_url'] ?? $data['image'] ?? null;
        $visibility = $data['visibility'] ?? 'public';
        $groupId = !empty($data['group_id']) ? (int) $data['group_id'] : null;

        if (empty($content) && empty($image)) {
            return ['error' => __('api_controllers_2.feed.content_or_image_required')];
        }

        // Determine publish status: scheduled, draft, or published
        $publishStatus = $data['publish_status'] ?? 'published';
        $scheduledAt = $data['scheduled_at'] ?? null;

        if ($publishStatus === 'scheduled' && empty($scheduledAt)) {
            return ['error' => 'Scheduled posts must have a scheduled_at date'];
        }

        if ($publishStatus === 'scheduled' && $scheduledAt) {
            $scheduledTime = \Carbon\Carbon::parse($scheduledAt);
            if ($scheduledTime->isPast()) {
                // If the scheduled time is in the past, publish immediately
                $publishStatus = 'published';
                $scheduledAt = null;
            }
        }

        // Validate quoted_post_id if provided (quote repost)
        $quotedPostId = !empty($data['quoted_post_id']) ? (int) $data['quoted_post_id'] : null;
        if ($quotedPostId) {
            $quotedExists = DB::table('feed_posts')
                ->where('id', $quotedPostId)
                ->where('tenant_id', $tenantId)
                ->exists();
            if (!$quotedExists) {
                return ['error' => __('api_controllers_2.feed.quoted_post_not_found')];
            }
        }

        $post = $this->feedPost->newInstance([
            'user_id'        => $userId,
            'tenant_id'      => $tenantId,
            'content'        => $content,
            'emoji'          => $data['emoji'] ?? null,
            'image_url'      => $image,
            'type'           => 'post',
            'parent_type'    => $data['parent_type'] ?? null,
            'parent_id'      => $data['parent_id'] ?? null,
            'visibility'     => $visibility,
            'group_id'       => $groupId,
            'publish_status' => $publishStatus,
            'scheduled_at'   => $scheduledAt,
            'quoted_post_id' => $quotedPostId,
        ]);

        $post->save();

        // Only record in feed_activity for immediately published posts;
        // scheduled/draft posts are added to feed_activity when published
        if ($publishStatus === 'published') {
            try {
                DB::table('feed_activity')->insertOrIgnore([
                    'tenant_id'   => $tenantId,
                    'user_id'     => $userId,
                    'source_type' => 'post',
                    'source_id'   => $post->id,
                    'group_id'    => $groupId,
                    'title'       => null,
                    'content'     => $content,
                    'image_url'   => $image,
                    'metadata'    => null,
                    'is_visible'  => true,
                    'created_at'  => $post->created_at,
                ]);
            } catch (\Exception $e) {
                Log::warning("FeedService::createPost feed_activity sync failed: " . $e->getMessage());
            }
        }

        // Process @mentions in post content (only for published posts)
        if ($publishStatus === 'published') {
            try {
                MentionService::processText($content, $post->id, 'post', $userId);
            } catch (\Exception $e) {
                Log::warning("FeedService::createPost mention processing failed: " . $e->getMessage());
            }
        }

        $freshPost = $post->fresh(['user', 'quotedPost', 'quotedPost.user']);

        // Append formatted quoted_post for API response
        if ($freshPost->quotedPost) {
            $qp = $freshPost->quotedPost;
            $qpContent = $this->truncateWithFlag($qp->content ?? '', 280);
            $freshPost->setAttribute('quoted_post', [
                'id' => (int) $qp->id,
                'content' => $qpContent['text'],
                'content_truncated' => $qpContent['truncated'],
                'image_url' => $qp->image_url,
                'created_at' => (string) $qp->created_at,
                'author' => [
                    'id' => (int) $qp->user_id,
                    'name' => $qp->user ? ($qp->user->name ?? ($qp->user->first_name . ' ' . $qp->user->last_name)) : 'Unknown',
                    'avatar_url' => $qp->user?->avatar_url ?? '/assets/img/defaults/default_avatar.png',
                ],
                'media' => [],
            ]);
        }

        return $freshPost;
    }

    /**
     * Update an existing feed post. Only the author may edit.
     *
     * @return array{success: bool, error?: string}
     */
    public function updatePost(int $postId, int $userId, array $data): array
    {
        $tenantId = TenantContext::getId();

        $post = $this->feedPost->newQuery()
            ->where('id', $postId)
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->first();

        if (!$post) {
            return ['success' => false, 'error' => __('api_controllers_2.feed.post_not_found_or_not_owned')];
        }

        $content = isset($data['content'])
            ? \App\Helpers\HtmlSanitizer::sanitize(trim($data['content']))
            : $post->content;

        if (empty($content) && empty($post->image_url)) {
            return ['success' => false, 'error' => __('api_controllers_2.feed.content_or_image_required')];
        }

        $post->content = $content;
        $post->updated_at = now();
        $post->save();

        // Sync feed_activity content
        try {
            DB::table('feed_activity')
                ->where('source_type', 'post')
                ->where('source_id', $postId)
                ->where('tenant_id', $tenantId)
                ->update(['content' => $content]);
        } catch (\Exception $e) {
            Log::warning("FeedService::updatePost feed_activity sync failed: " . $e->getMessage());
        }

        return ['success' => true];
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
        // Server-side XSS prevention: sanitize HTML content before storage
        $content = \App\Helpers\HtmlSanitizer::sanitize($rawContent);
        $imageUrl = $data['image_url'] ?? null;
        $visibility = $data['visibility'] ?? 'public';
        $groupId = (int) ($data['group_id'] ?? 0);

        $validVisibility = ['public', 'private', 'friends'];
        if (!in_array($visibility, $validVisibility, true)) {
            $visibility = 'public';
        }

        if (empty($content) && empty($imageUrl)) {
            $this->errors[] = ['code' => 'VALIDATION_ERROR', 'message' => __('api_controllers_2.feed.content_or_image_required'), 'field' => 'content'];
            return null;
        }

        // Validate group membership if posting to group
        if ($groupId > 0) {
            $tenantId = TenantContext::getId();
            $isMember = DB::selectOne(
                "SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ? AND tenant_id = ? AND status = 'active'",
                [$groupId, $userId, $tenantId]
            );
            if (!$isMember) {
                $this->errors[] = ['code' => 'FORBIDDEN', 'message' => __('api_controllers_2.feed.must_be_group_member')];
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
            $this->errors[] = ['code' => 'SERVER_ERROR', 'message' => __('api_controllers_2.feed.create_post_failed')];
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
                    WHERE p.id = ? AND p.tenant_id = ? AND (p.publish_status = 'published' OR p.publish_status IS NULL) AND (p.is_hidden = 0 OR p.is_hidden IS NULL)",
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
     * Publish all scheduled posts whose scheduled_at time has passed.
     *
     * Called every minute by the scheduler. Processes all tenants.
     *
     * @return int Number of posts published
     */
    public function publishScheduledPosts(): int
    {
        $posts = FeedPost::where('publish_status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->get();

        $count = 0;

        foreach ($posts as $post) {
            try {
                $post->update([
                    'publish_status' => 'published',
                    'created_at'     => now(), // Set created_at to publish time so it appears fresh in feed
                ]);

                // Insert into feed_activity so the post appears in the main feed
                DB::table('feed_activity')->insertOrIgnore([
                    'tenant_id'   => $post->tenant_id,
                    'user_id'     => $post->user_id,
                    'source_type' => 'post',
                    'source_id'   => $post->id,
                    'group_id'    => $post->group_id,
                    'title'       => null,
                    'content'     => $post->content,
                    'image_url'   => $post->image_url,
                    'metadata'    => null,
                    'is_visible'  => true,
                    'created_at'  => now(),
                ]);

                // Process @mentions now that the post is live
                try {
                    MentionService::processText($post->content ?? '', $post->id, 'post', $post->user_id);
                } catch (\Exception $e) {
                    Log::warning("publishScheduledPosts mention processing failed for post {$post->id}: " . $e->getMessage());
                }

                $count++;
            } catch (\Exception $e) {
                Log::error("publishScheduledPosts failed for post {$post->id}: " . $e->getMessage());
            }
        }

        if ($count > 0) {
            Log::info("publishScheduledPosts: published {$count} scheduled post(s)");
        }

        return $count;
    }

    /**
     * Get scheduled posts for a specific user.
     *
     * @return array{items: array}
     */
    public function getScheduledPosts(int $userId): array
    {
        $tenantId = TenantContext::getId();

        $posts = FeedPost::where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('publish_status', 'scheduled')
            ->orderBy('scheduled_at', 'asc')
            ->get();

        $items = [];
        foreach ($posts as $post) {
            $items[] = [
                'id'             => $post->id,
                'content'        => $post->content,
                'image_url'      => $post->image_url,
                'publish_status' => $post->publish_status,
                'scheduled_at'   => $post->scheduled_at?->toIso8601String(),
                'created_at'     => $post->created_at?->toIso8601String(),
            ];
        }

        return ['items' => $items];
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

        // Verify the target post belongs to this tenant before recording the like
        $postExists = DB::table('feed_posts')
            ->where('id', $postId)
            ->where('tenant_id', $tenantId)
            ->exists();

        if (! $postExists) {
            throw new \InvalidArgumentException('Post not found');
        }

        // Use atomic INSERT IGNORE + check affected rows to prevent duplicate likes
        // from concurrent requests (the uk_likes_user_target unique key enforces this)
        $existing = DB::table('likes')
            ->where('target_type', 'post')
            ->where('target_id', $postId)
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->first();

        if ($existing) {
            DB::table('likes')
                ->where('id', $existing->id)
                ->where('tenant_id', $tenantId)
                ->delete();
            $liked = false;
        } else {
            // Use INSERT IGNORE to gracefully handle race condition with unique constraint
            DB::statement(
                'INSERT IGNORE INTO likes (target_type, target_id, user_id, tenant_id, created_at) VALUES (?, ?, ?, ?, NOW())',
                ['post', $postId, $userId, $tenantId]
            );
            $liked = true;
        }

        $count = (int) DB::table('likes')
            ->where('target_type', 'post')
            ->where('target_id', $postId)
            ->where('tenant_id', $tenantId)
            ->count();

        return ['liked' => $liked, 'likes_count' => $count];
    }
}
