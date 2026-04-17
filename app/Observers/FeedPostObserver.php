<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Observers;

use App\Models\FeedPost;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FeedPostObserver — keeps feed_activity visibility in sync with post soft-delete lifecycle.
 *
 * - deleted      → hides feed_activity rows (soft-delete: post still exists but is removed from feeds)
 * - restored     → restores feed_activity visibility (post un-deleted)
 * - forceDeleted → permanently removes feed_activity rows (hard delete)
 */
class FeedPostObserver
{
    public function deleted(FeedPost $post): void
    {
        try {
            DB::table('feed_activity')
                ->where('source_type', 'post')
                ->where('source_id', $post->id)
                ->where('tenant_id', $post->tenant_id)
                ->update(['is_visible' => false]);

            Log::debug('FeedPostObserver: hidden feed_activity for soft-deleted post', [
                'post_id'   => $post->id,
                'tenant_id' => $post->tenant_id,
            ]);
        } catch (\Throwable $e) {
            Log::error('FeedPostObserver::deleted failed', [
                'post_id' => $post->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    public function restored(FeedPost $post): void
    {
        try {
            // Fix 4: Only restore visibility if the post was NOT moderation-hidden.
            // If is_hidden = 1 the post was hidden by moderation before soft-delete;
            // restoring the soft-delete must not bypass that moderation decision.
            if ((int) $post->is_hidden === 1) {
                Log::debug('FeedPostObserver: skipping visibility restore for moderation-hidden post', [
                    'post_id'   => $post->id,
                    'tenant_id' => $post->tenant_id,
                ]);
                return;
            }

            DB::table('feed_activity')
                ->where('source_type', 'post')
                ->where('source_id', $post->id)
                ->where('tenant_id', $post->tenant_id)
                ->update(['is_visible' => true]);

            Log::debug('FeedPostObserver: restored feed_activity for un-deleted post', [
                'post_id'   => $post->id,
                'tenant_id' => $post->tenant_id,
            ]);
        } catch (\Throwable $e) {
            Log::error('FeedPostObserver::restored failed', [
                'post_id' => $post->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    public function forceDeleted(FeedPost $post): void
    {
        try {
            DB::table('feed_activity')
                ->where('source_type', 'post')
                ->where('source_id', $post->id)
                ->where('tenant_id', $post->tenant_id)
                ->delete();

            Log::debug('FeedPostObserver: removed feed_activity for force-deleted post', [
                'post_id'   => $post->id,
                'tenant_id' => $post->tenant_id,
            ]);
        } catch (\Throwable $e) {
            Log::error('FeedPostObserver::forceDeleted failed', [
                'post_id' => $post->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
