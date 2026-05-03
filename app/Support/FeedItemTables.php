<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Core\TenantContext;

/**
 * FeedItemTables — canonical map of feed-surface target_type → backing table.
 *
 * Used by every controller that needs to verify a polymorphic
 * (target_type, target_id) pair exists in the current tenant before
 * writing to a polymorphic table (reactions, feed_hidden, etc.).
 *
 * MUST stay aligned with `ReactionService::VALID_TARGET_TYPES` and
 * `FeedActivityService::VALID_TYPES`.
 */
final class FeedItemTables
{
    /**
     * Polymorphic target_type → DB table name.
     *
     * Notification-style virtual feed types (badge_earned, level_up) are
     * intentionally absent — they aren't user-actionable rows that can
     * be hidden or reacted to.
     */
    public const TABLES = [
        'post'      => 'feed_posts',
        'comment'   => 'comments',
        'listing'   => 'listings',
        'event'     => 'events',
        'goal'      => 'goals',
        'poll'      => 'polls',
        'review'    => 'reviews',
        'volunteer' => 'vol_opportunities',
        'challenge' => 'ideation_challenges',
        'resource'  => 'resources',
        'job'       => 'jobs',
        'blog'      => 'blog_posts',
        'discussion' => 'group_discussions',
    ];

    /**
     * Verify a (target_type, target_id) pair resolves to a real row in the
     * current tenant. Fail-closed on unknown types or DB errors.
     */
    public static function exists(string $targetType, int $targetId): bool
    {
        if ($targetId <= 0) {
            return false;
        }

        $table = self::TABLES[$targetType] ?? null;
        if (!$table) {
            return false;
        }

        $tenantId = TenantContext::getId();
        if (!$tenantId) {
            return false;
        }

        try {
            return DB::table($table)
                ->where('id', $targetId)
                ->where('tenant_id', $tenantId)
                ->exists();
        } catch (\Throwable $e) {
            Log::warning("[FeedItemTables] existence check failed for {$targetType}: " . $e->getMessage());
            return false;
        }
    }
}
