<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Observers;

use App\Models\Comment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CommentObserver — keeps comments_count on feed_posts in sync with comment lifecycle.
 *
 * - deleted      → decrements comments_count on the parent post (soft-delete)
 * - restored     → re-increments comments_count when a comment is un-deleted
 * - forceDeleted → decrements comments_count when a comment is permanently removed
 *
 * Only operates on top-level comments targeting posts (target_type = 'post').
 * Replies (parent_id is set) are counted separately and not tracked here.
 */
class CommentObserver
{
    public function deleted(Comment $comment): void
    {
        if ($comment->target_type !== 'post' || $comment->parent_id !== null) {
            return;
        }

        try {
            DB::table('feed_posts')
                ->where('id', $comment->target_id)
                ->where('tenant_id', $comment->tenant_id)
                ->where('comments_count', '>', 0)
                ->decrement('comments_count');
        } catch (\Throwable $e) {
            Log::error('CommentObserver::deleted failed to decrement comments_count', [
                'comment_id' => $comment->id,
                'post_id'    => $comment->target_id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    public function restored(Comment $comment): void
    {
        if ($comment->target_type !== 'post' || $comment->parent_id !== null) {
            return;
        }

        try {
            DB::table('feed_posts')
                ->where('id', $comment->target_id)
                ->where('tenant_id', $comment->tenant_id)
                ->increment('comments_count');
        } catch (\Throwable $e) {
            Log::error('CommentObserver::restored failed to increment comments_count', [
                'comment_id' => $comment->id,
                'post_id'    => $comment->target_id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    public function forceDeleted(Comment $comment): void
    {
        // Same behaviour as soft-delete: decrement count on permanent removal
        $this->deleted($comment);
    }
}
