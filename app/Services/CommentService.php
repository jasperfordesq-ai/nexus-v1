<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\Comment;
use Illuminate\Support\Facades\DB;

/**
 * CommentService — Eloquent-based service for comment operations.
 *
 * Uses the comments table with polymorphic target_type/target_id columns.
 * Supports threaded comments via parent_id and emoji reactions.
 */
class CommentService
{
    /**
     * Get comments for a given entity, threaded by parent_id.
     *
     * @return array Top-level comments with nested replies
     */
    public function getForEntity(string $targetType, int $targetId, int $currentUserId = 0): array
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

        if (! empty($commentIds)) {
            // Aggregate reactions
            $reactionRows = DB::table('comment_reactions')
                ->whereIn('comment_id', $commentIds)
                ->selectRaw('comment_id, emoji, COUNT(*) as count')
                ->groupBy('comment_id', 'emoji')
                ->get();

            foreach ($reactionRows as $r) {
                $reactions[$r->comment_id][$r->emoji] = (int) $r->count;
            }

            // User's own reactions
            if ($currentUserId > 0) {
                $userReactionRows = DB::table('comment_reactions')
                    ->whereIn('comment_id', $commentIds)
                    ->where('user_id', $currentUserId)
                    ->get();

                foreach ($userReactionRows as $r) {
                    $userReactions[$r->comment_id][] = $r->emoji;
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
                'id'             => $cid,
                'content'        => $comment->content ?? '',
                'created_at'     => (string) $comment->created_at,
                'edited'         => $comment->updated_at && $comment->updated_at->gt($comment->created_at),
                'is_own'         => (int) $comment->user_id === $currentUserId,
                'author'         => [
                    'id'     => (int) $comment->user_id,
                    'name'   => $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) : 'Unknown',
                    'avatar' => $user->avatar_url ?? null,
                ],
                'reactions'      => $reactions[$cid] ?? (object) [],
                'user_reactions' => $userReactions[$cid] ?? [],
                'replies'        => [],
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
    public function countAll(array $comments): int
    {
        $count = count($comments);
        foreach ($comments as $c) {
            if (! empty($c['replies'])) {
                $count += $this->countAll($c['replies']);
            }
        }
        return $count;
    }

    /**
     * Create a comment on an entity.
     */
    public function create(string $targetType, int $targetId, int $userId, int $tenantId, array $data): Comment
    {
        return Comment::create([
            'target_type' => $targetType,
            'target_id'   => $targetId,
            'user_id'     => $userId,
            'tenant_id'   => $tenantId,
            'content'     => trim($data['content']),
            'parent_id'   => $data['parent_id'] ?? null,
        ]);
    }

    /**
     * Update a comment's content (owner only).
     */
    public function update(int $commentId, int $userId, string $content): bool
    {
        $comment = Comment::where('id', $commentId)
            ->where('user_id', $userId)
            ->first();

        if (! $comment) {
            return false;
        }

        $comment->content = trim($content);
        $comment->save();

        return true;
    }

    /**
     * Delete a comment (owner only).
     */
    public function delete(int $commentId, int $userId): bool
    {
        return (bool) Comment::where('id', $commentId)
            ->where('user_id', $userId)
            ->delete();
    }

    /**
     * Toggle an emoji reaction on a comment.
     */
    public function toggleReaction(int $userId, int $tenantId, int $commentId, string $emoji): array
    {
        $existing = DB::table('comment_reactions')
            ->where('comment_id', $commentId)
            ->where('user_id', $userId)
            ->where('emoji', $emoji)
            ->first();

        if ($existing) {
            DB::table('comment_reactions')->where('id', $existing->id)->delete();
            $action = 'removed';
        } else {
            DB::table('comment_reactions')->insert([
                'comment_id' => $commentId,
                'user_id'    => $userId,
                'tenant_id'  => $tenantId,
                'emoji'      => $emoji,
                'created_at' => now(),
            ]);
            $action = 'added';
        }

        // Aggregate updated reactions for this comment
        $reactionCounts = DB::table('comment_reactions')
            ->where('comment_id', $commentId)
            ->selectRaw('emoji, COUNT(*) as count')
            ->groupBy('emoji')
            ->pluck('count', 'emoji')
            ->all();

        return [
            'action'    => $action,
            'reactions' => empty($reactionCounts) ? (object) [] : $reactionCounts,
        ];
    }
}
