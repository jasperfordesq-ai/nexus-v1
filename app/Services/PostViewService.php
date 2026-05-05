<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Support\FeedItemTables;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PostViewService
{
    /**
     * Record a view for a post. Debounces same user/IP within 30 minutes.
     */
    public function recordView(int $postId, ?int $userId, string $ipHash): void
    {
        $tenantId = TenantContext::getId();

        if (!FeedItemTables::canView('post', $postId, $userId)) {
            return;
        }

        // Debounce: don't count same viewer within 30 minutes
        $cacheKey = $userId
            ? "post_view:{$tenantId}:{$postId}:u:{$userId}"
            : "post_view:{$tenantId}:{$postId}:ip:{$ipHash}";

        if (Cache::has($cacheKey)) {
            return;
        }

        try {
            if ($userId) {
                // Logged-in user: insert or ignore (unique constraint handles dedup)
                $inserted = DB::table('post_views')->insertOrIgnore([
                    'tenant_id' => $tenantId,
                    'post_id' => $postId,
                    'user_id' => $userId,
                    'ip_hash' => null,
                    'viewed_at' => now(),
                ]);
            } else {
                // Anonymous: insert or ignore by ip_hash
                $inserted = DB::table('post_views')->insertOrIgnore([
                    'tenant_id' => $tenantId,
                    'post_id' => $postId,
                    'user_id' => null,
                    'ip_hash' => $ipHash,
                    'viewed_at' => now(),
                ]);
            }

            // Increment the denormalized counter if a new row was inserted
            if ($inserted) {
                DB::table('feed_posts')
                    ->where('id', $postId)
                    ->where('tenant_id', $tenantId)
                    ->increment('views_count');
            }
        } catch (\Throwable $e) {
            // Silently fail — view tracking is non-critical
            \Log::debug('PostViewService: failed to record view', [
                'post_id' => $postId,
                'error' => $e->getMessage(),
            ]);
        }

        // Set debounce cache for 30 minutes
        Cache::put($cacheKey, true, now()->addMinutes(30));
    }

    /**
     * Get the view count for a post.
     */
    public function getViewCount(int $postId): int
    {
        $tenantId = TenantContext::getId();

        return (int) (DB::table('feed_posts')
            ->where('id', $postId)
            ->where('tenant_id', $tenantId)
            ->value('views_count') ?? 0);
    }
}
