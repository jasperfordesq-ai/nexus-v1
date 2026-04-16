<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\Comment;
use App\Models\Notification;
use App\Models\User;
use App\Services\MentionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CommentService — Eloquent-based service for comment operations.
 *
 * Uses the comments table with polymorphic target_type/target_id columns.
 * Supports threaded comments via parent_id and emoji reactions.
 */
class CommentService
{
    private static array $availableReactions = ['👍', '❤️', '😂', '😮', '😢', '🎉'];

    /**
     * Get comments for a given entity, threaded by parent_id.
     *
     * @return array Top-level comments with nested replies
     */
    public static function getForEntity(string $targetType, int $targetId, int $currentUserId = 0): array
    {
        $rows = Comment::with(['user:id,first_name,last_name,avatar_url'])
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->orderBy('created_at')
            ->get();

        // Get reactions for all comment IDs
        $commentIds = $rows->pluck('id')->all();
        $reactions = [];
        $userReactions = [];

        if (!empty($commentIds)) {
            $tenantId = TenantContext::getId();

            $reactionRows = DB::table('reactions')
                ->where('tenant_id', $tenantId)
                ->where('target_type', 'comment')
                ->whereIn('target_id', $commentIds)
                ->selectRaw('target_id, emoji, COUNT(*) as count')
                ->groupBy('target_id', 'emoji')
                ->get();

            foreach ($reactionRows as $r) {
                $reactions[$r->target_id][$r->emoji] = (int) $r->count;
            }

            if ($currentUserId > 0) {
                $userReactionRows = DB::table('reactions')
                    ->where('tenant_id', $tenantId)
                    ->where('target_type', 'comment')
                    ->whereIn('target_id', $commentIds)
                    ->where('user_id', $currentUserId)
                    ->get();

                foreach ($userReactionRows as $r) {
                    $userReactions[$r->target_id][] = $r->emoji;
                }
            }
        }

        // Build threaded structure
        $byId = [];
        $topLevel = [];

        foreach ($rows as $comment) {
            $cid = $comment->id;
            $user = $comment->user;
            $item = [
                'id' => $cid,
                'content' => $comment->content ?? '',
                'created_at' => $comment->created_at?->toIso8601String(),
                'edited' => $comment->updated_at && $comment->updated_at->gt($comment->created_at),
                'is_own' => (int) $comment->user_id === $currentUserId,
                'author' => [
                    'id' => (int) $comment->user_id,
                    'name' => $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) : 'Unknown',
                    'avatar' => $user->avatar_url ?? null,
                ],
                'reactions' => $reactions[$cid] ?? (object) [],
                'user_reactions' => $userReactions[$cid] ?? [],
                'replies' => [],
            ];
            $byId[$cid] = $item;
        }

        foreach ($byId as $cid => &$c) {
            $comment = $rows->firstWhere('id', $cid);
            if ($comment->parent_id && isset($byId[$comment->parent_id])) {
                $byId[$comment->parent_id]['replies'][] = &$c;
            } else {
                $topLevel[] = &$c;
            }
        }

        return $topLevel;
    }

    /**
     * Count all comments including replies.
     */
    public static function countAll(array $comments): int
    {
        $count = count($comments);
        foreach ($comments as $c) {
            if (!empty($c['replies'])) {
                $count += self::countAll($c['replies']);
            }
        }
        return $count;
    }

    /**
     * Create a comment on an entity.
     */
    public static function create(string $targetType, int $targetId, int $userId, int $tenantId, array $data): Comment
    {
        // Server-side XSS prevention: sanitize HTML content before storage
        $content = \App\Helpers\HtmlSanitizer::sanitize(trim($data['content']));

        $parentId = $data['parent_id'] ?? null;

        // Check post visibility — prevent commenting on deleted/hidden posts
        if ($targetType === 'post' || $targetType === 'feed_post') {
            $post = DB::selectOne(
                'SELECT id FROM feed_posts WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL',
                [$targetId, $tenantId]
            );
            if (!$post) {
                throw new \InvalidArgumentException('Target post not found or has been deleted');
            }
        }

        // M4: Wrap depth check + insert in a transaction to prevent TOCTOU race
        // where two concurrent requests both pass the depth check before either inserts.
        return DB::transaction(function () use ($targetType, $targetId, $userId, $tenantId, $content, $parentId, $data) {
        // Enforce nesting depth limit to prevent infinite recursion attacks
        if (!empty($parentId)) {
            $depth = 0;
            $currentId = (int)$parentId;
            while ($currentId && $depth < 15) {
                $parent = DB::selectOne('SELECT parent_id FROM comments WHERE id = ? AND tenant_id = ?', [$currentId, $tenantId]);
                if (!$parent) break;
                $currentId = (int)$parent->parent_id;
                $depth++;
            }
            if ($depth >= 10) {
                throw new \InvalidArgumentException('Comment nesting too deep');
            }
        }

        $comment = Comment::create([
            'target_type' => $targetType,
            'target_id' => $targetId,
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'content' => $content,
            'parent_id' => $parentId,
        ]);

        // Process @mentions in comment content
        try {
            MentionService::processText($content, $comment->id, 'comment', $userId);
        } catch (\Exception $e) {
            Log::warning("CommentService::create mention processing failed: " . $e->getMessage());
        }

        // Notify the content owner that someone commented on their content (platform + email)
        try {
            $ownerId = self::resolveContentOwnerId($targetType, $targetId, $tenantId);

            if ($ownerId && $ownerId !== $userId) {
                SocialNotificationService::notifyComment(
                    $ownerId,
                    $userId,
                    $targetType,
                    $targetId,
                    $content
                );
            }
        } catch (\Throwable $e) {
            Log::warning('CommentService::create content owner notification failed', [
                'target_type' => $targetType,
                'target_id' => $targetId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }

        return $comment;
        }); // end DB::transaction
    }

    /**
     * Update a comment's content (owner only).
     */
    public static function update(int $commentId, int $userId, string $content): bool
    {
        $comment = Comment::where('id', $commentId)
            ->where('user_id', $userId)
            ->first();

        if (!$comment) {
            return false;
        }

        // Server-side XSS prevention: sanitize HTML content before storage
        $trimmedContent = \App\Helpers\HtmlSanitizer::sanitize(trim($content));
        $comment->content = $trimmedContent;
        $comment->save();

        // Re-process @mentions
        try {
            MentionService::deleteMentionsForEntity($commentId, 'comment');
            MentionService::processText($trimmedContent, $commentId, 'comment', $userId);
        } catch (\Exception $e) {
            Log::warning("CommentService::update mention re-processing failed: " . $e->getMessage());
        }

        return true;
    }

    /**
     * Delete a comment (owner only).
     */
    public static function delete(int $commentId, int $userId): bool
    {
        return (bool) Comment::where('id', $commentId)
            ->where('user_id', $userId)
            ->delete();
    }

    /**
     * Fetch threaded comments for a target.
     *
     * @return array Threaded comments array
     */
    public static function fetchComments(string $targetType, int $targetId, int $currentUserId = 0): array
    {
        $tenantId = TenantContext::getId();

        $allComments = DB::table('comments as c')
            ->leftJoin('users as u', 'c.user_id', '=', 'u.id')
            ->where('c.target_type', $targetType)
            ->where('c.target_id', $targetId)
            ->where('c.tenant_id', $tenantId)
            ->select([
                'c.id', 'c.user_id', 'c.content', 'c.parent_id', 'c.created_at', 'c.updated_at',
                DB::raw("COALESCE(u.name, u.first_name, 'Unknown') as author_name"),
                DB::raw("COALESCE(u.avatar_url, '/assets/img/defaults/default_avatar.png') as author_avatar"),
            ])
            ->orderBy('c.created_at')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $commentIds = array_column($allComments, 'id');
        $reactionsByComment = [];
        $userReactionsByComment = [];

        if (!empty($commentIds)) {
            // Get reaction counts grouped by emoji (tenant-scoped)
            $reactionRows = DB::table('reactions')
                ->where('tenant_id', $tenantId)
                ->where('target_type', 'comment')
                ->whereIn('target_id', $commentIds)
                ->selectRaw('target_id, emoji, COUNT(*) as count')
                ->groupBy('target_id', 'emoji')
                ->get();

            foreach ($reactionRows as $row) {
                $reactionsByComment[$row->target_id][$row->emoji] = (int) $row->count;
            }

            // Get current user's reactions (tenant-scoped)
            if ($currentUserId) {
                $userReactionRows = DB::table('reactions')
                    ->where('tenant_id', $tenantId)
                    ->where('target_type', 'comment')
                    ->whereIn('target_id', $commentIds)
                    ->where('user_id', $currentUserId)
                    ->get();

                foreach ($userReactionRows as $row) {
                    $userReactionsByComment[$row->target_id][] = $row->emoji;
                }
            }
        }

        // Build threaded structure
        $commentsById = [];
        $rootComments = [];

        foreach ($allComments as &$comment) {
            // Ensure created_at includes timezone for correct client-side relative time
            if (!empty($comment['created_at']) && !str_contains($comment['created_at'], 'T')) {
                $comment['created_at'] = \Carbon\Carbon::parse($comment['created_at'], 'UTC')->toIso8601String();
            }
            if (!empty($comment['updated_at']) && !str_contains($comment['updated_at'], 'T')) {
                $comment['updated_at'] = \Carbon\Carbon::parse($comment['updated_at'], 'UTC')->toIso8601String();
            }
            $comment['reactions'] = $reactionsByComment[$comment['id']] ?? [];
            $comment['user_reactions'] = $userReactionsByComment[$comment['id']] ?? [];
            $comment['is_owner'] = ($currentUserId && (int) $comment['user_id'] === $currentUserId);
            $comment['is_edited'] = ($comment['updated_at'] !== $comment['created_at']);
            $comment['replies'] = [];
            $commentsById[$comment['id']] = &$comment;
        }
        unset($comment);

        foreach ($allComments as &$comment) {
            if ($comment['parent_id'] && isset($commentsById[$comment['parent_id']])) {
                $commentsById[$comment['parent_id']]['replies'][] = &$commentsById[$comment['id']];
            } else {
                $rootComments[] = &$commentsById[$comment['id']];
            }
        }

        return $rootComments;
    }

    /**
     * Add a comment or reply.
     */
    public static function addComment(int $userId, int $tenantId, string $targetType, int $targetId, string $content, ?int $parentId = null): array
    {
        // Server-side XSS prevention: sanitize HTML content before storage
        $content = \App\Helpers\HtmlSanitizer::sanitize(trim($content));
        if (empty($content)) {
            return ['success' => false, 'error' => __('api.comment_cannot_be_empty')];
        }

        // If replying, verify parent exists
        if ($parentId) {
            $parentExists = DB::table('comments')
                ->where('id', $parentId)
                ->where('tenant_id', $tenantId)
                ->exists();

            if (!$parentExists) {
                return ['success' => false, 'error' => __('api.parent_comment_not_found')];
            }
        }

        // Insert comment
        $commentId = DB::table('comments')->insertGetId([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'parent_id' => $parentId,
            'content' => $content,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Process @mentions
        $mentions = self::extractMentions($content);
        if (!empty($mentions)) {
            self::saveMentions($commentId, $mentions, $userId, $tenantId);
        }

        // Get the created comment with author info
        $comment = DB::table('comments as c')
            ->leftJoin('users as u', 'c.user_id', '=', 'u.id')
            ->where('c.id', $commentId)
            ->select([
                'c.*',
                DB::raw("COALESCE(u.name, u.first_name, 'Unknown') as author_name"),
                DB::raw("COALESCE(u.avatar_url, '/assets/img/defaults/default_avatar.png') as author_avatar"),
            ])
            ->first();

        return [
            'success' => true,
            'status' => 'success',
            'comment' => $comment ? (array) $comment : null,
            'is_reply' => $parentId !== null,
        ];
    }

    /**
     * Delete a comment (owner or super admin).
     */
    public static function deleteComment(int $commentId, int $userId, bool $isSuperAdmin = false): array
    {
        $tenantId = TenantContext::getId();

        $comment = DB::table('comments')
            ->where('id', $commentId)
            ->where('tenant_id', $tenantId)
            ->select(['id', 'user_id'])
            ->first();

        if (!$comment) {
            return ['success' => false, 'error' => __('api.comment_not_found')];
        }

        if ((int) $comment->user_id !== $userId && !$isSuperAdmin) {
            return ['success' => false, 'error' => __('api.comment_unauthorized')];
        }

        DB::table('comments')
            ->where('id', $commentId)
            ->where('tenant_id', $tenantId)
            ->delete();

        return ['success' => true, 'status' => 'success', 'message' => __('svc_notifications_2.comment.deleted')];
    }

    /**
     * Edit a comment (owner only).
     */
    public static function editComment(int $commentId, int $userId, string $newContent): array
    {
        $tenantId = TenantContext::getId();
        // Server-side XSS prevention: sanitize HTML content before storage
        $newContent = \App\Helpers\HtmlSanitizer::sanitize(trim($newContent));

        if (empty($newContent)) {
            return ['success' => false, 'error' => __('api.comment_cannot_be_empty')];
        }

        $comment = DB::table('comments')
            ->where('id', $commentId)
            ->where('tenant_id', $tenantId)
            ->select(['id', 'user_id', 'target_type', 'target_id'])
            ->first();

        if (!$comment) {
            return ['success' => false, 'error' => __('api.comment_not_found')];
        }

        if ((int) $comment->user_id !== $userId) {
            return ['success' => false, 'error' => __('api.comment_unauthorized')];
        }

        DB::table('comments')
            ->where('id', $commentId)
            ->where('tenant_id', $tenantId)
            ->update(['content' => $newContent, 'updated_at' => now()]);

        // Re-process mentions
        DB::table('mentions')
            ->where('comment_id', $commentId)
            ->where('tenant_id', $tenantId)
            ->delete();

        $mentions = self::extractMentions($newContent);
        if (!empty($mentions)) {
            self::saveMentions($commentId, $mentions, $userId, $tenantId);
        }

        return [
            'success' => true,
            'status' => 'success',
            'content' => $newContent,
            'is_edited' => true,
        ];
    }

    /**
     * Get validation errors (interface compatibility).
     */
    public static function getErrors(): array
    {
        return [];
    }

    /**
     * Get the list of available reaction emojis.
     */
    public static function getAvailableReactions(): array
    {
        return self::$availableReactions;
    }

    /**
     * Search users for @mention autocomplete.
     */
    public static function searchUsersForMention(string $query, int $tenantId, int $limit = 10): array
    {
        $searchTerm = '%' . $query . '%';

        return DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', $searchTerm)
                  ->orWhere('first_name', 'LIKE', $searchTerm)
                  ->orWhere('username', 'LIKE', $searchTerm);
            })
            ->select(['id', 'name', 'first_name', 'avatar_url'])
            ->limit($limit)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * Toggle an emoji reaction on a comment.
     */
    public static function toggleReaction(int $userId, int $tenantId, int $commentId, string $emoji): array
    {
        $existing = DB::table('reactions')
            ->where('tenant_id', $tenantId)
            ->where('target_type', 'comment')
            ->where('target_id', $commentId)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            if ($existing->emoji === $emoji) {
                // Same type: remove
                DB::table('reactions')
                    ->where('id', $existing->id)
                    ->where('tenant_id', $tenantId)
                    ->delete();
                $action = 'removed';
            } else {
                // Different type: update
                DB::table('reactions')
                    ->where('id', $existing->id)
                    ->where('tenant_id', $tenantId)
                    ->update(['emoji' => $emoji, 'created_at' => now()]);
                $action = 'updated';
            }
        } else {
            DB::table('reactions')->insert([
                'tenant_id'   => $tenantId,
                'target_type' => 'comment',
                'target_id'   => $commentId,
                'user_id'     => $userId,
                'emoji'       => $emoji,
                'created_at'  => now(),
            ]);
            $action = 'added';
        }

        // Aggregate updated reactions for this comment
        $reactionCounts = DB::table('reactions')
            ->where('tenant_id', $tenantId)
            ->where('target_type', 'comment')
            ->where('target_id', $commentId)
            ->selectRaw('emoji, COUNT(*) as count')
            ->groupBy('emoji')
            ->pluck('count', 'emoji')
            ->all();

        return [
            'action' => $action,
            'reactions' => empty($reactionCounts) ? (object) [] : $reactionCounts,
        ];
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Extract @mentions from content.
     */
    private static function extractMentions(string $content): array
    {
        preg_match_all('/@(\w+)/', $content, $matches);
        return array_unique($matches[1] ?? []);
    }

    /**
     * Resolve the owner (user_id / author_id) of the content being commented on.
     *
     * Maps target_type to its database table and owner column, then queries for
     * the owner. Returns null when the target type is unknown or the row is missing.
     */
    private static function resolveContentOwnerId(string $targetType, int $targetId, int $tenantId): ?int
    {
        // Map target_type → [table, owner_column]
        $map = [
            'blog_post'  => ['blog_posts',    'author_id'],
            'blog'       => ['blog_posts',    'author_id'],
            'listing'    => ['listings',       'user_id'],
            'feed_post'  => ['feed_activity',  'user_id'],
            'post'       => ['feed_activity',  'user_id'],
            'event'      => ['events',         'user_id'],
        ];

        $entry = $map[$targetType] ?? null;
        if (! $entry) {
            return null;
        }

        [$table, $ownerColumn] = $entry;

        $row = DB::table($table)
            ->where('id', $targetId)
            ->where('tenant_id', $tenantId)
            ->select([$ownerColumn])
            ->first();

        return $row ? (int) $row->{$ownerColumn} : null;
    }

    /**
     * Return a human-readable label for a comment target type.
     */
    private static function contentTypeLabel(string $targetType): string
    {
        return match ($targetType) {
            'blog_post', 'blog' => 'post',
            'listing'           => 'listing',
            'feed_post', 'post' => 'post',
            'event'             => 'event',
            default             => 'content',
        };
    }

    /**
     * Save mentions to database and notify users.
     */
    private static function saveMentions(int $commentId, array $usernames, int $mentioningUserId, int $tenantId): void
    {
        // Look up the comment's target so mention notifications link to the right content
        $comment = DB::table('comments')
            ->where('id', $commentId)
            ->where('tenant_id', $tenantId)
            ->select(['target_type', 'target_id', 'content'])
            ->first();

        foreach ($usernames as $username) {
            // Escape LIKE wildcards so a malicious @mention like "@%_" can't
            // broadly match every user and spam unrelated people with mention
            // notifications.
            $likeName = '%' . addcslashes($username, '\\%_') . '%';
            $user = DB::table('users')
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($username, $likeName) {
                    $q->where('username', $username)
                      ->orWhere('name', 'LIKE', $likeName)
                      ->orWhere('first_name', $username);
                })
                ->select(['id'])
                ->first();

            if ($user && (int) $user->id !== $mentioningUserId) {
                try {
                    DB::table('mentions')->insert([
                        'comment_id' => $commentId,
                        'mentioned_user_id' => $user->id,
                        'mentioning_user_id' => $mentioningUserId,
                        'tenant_id' => $tenantId,
                        'created_at' => now(),
                    ]);

                    if ($comment) {
                        SocialNotificationService::notifyComment(
                            $user->id,
                            $mentioningUserId,
                            $comment->target_type,
                            (int) $comment->target_id,
                            $comment->content ?? ''
                        );
                    }
                } catch (\Exception $e) {
                    // Ignore duplicate mention errors
                }
            }
        }
    }
}
