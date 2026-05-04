<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
        'job'       => 'job_vacancies',
        'blog'      => 'blog_posts',
        'discussion' => 'group_discussions',
    ];

    public const COMMENTABLE_TYPES = [
        'post',
        'listing',
        'event',
        'goal',
        'poll',
        'review',
        'volunteer',
        'challenge',
        'resource',
        'job',
        'blog',
        'discussion',
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

    public static function isCommentable(string $targetType): bool
    {
        return in_array($targetType, self::COMMENTABLE_TYPES, true);
    }

    public static function canView(string $targetType, int $targetId, ?int $viewerId = null): bool
    {
        if ($targetId <= 0 || !isset(self::TABLES[$targetType])) {
            return false;
        }

        $tenantId = TenantContext::getId();
        if (!$tenantId) {
            return false;
        }

        try {
            if ($targetType === 'comment') {
                return self::canViewComment($targetId, $viewerId, $tenantId);
            }

            if ($targetType === 'post') {
                return self::canViewPost($targetId, $viewerId, $tenantId);
            }

            if (!self::exists($targetType, $targetId)) {
                return false;
            }

            $activity = DB::table('feed_activity')
                ->where('tenant_id', $tenantId)
                ->where('source_type', $targetType)
                ->where('source_id', $targetId)
                ->select(['group_id', 'is_visible', 'is_hidden'])
                ->first();

            if ($activity) {
                if ((int) ($activity->is_visible ?? 1) !== 1 || (int) ($activity->is_hidden ?? 0) === 1) {
                    return false;
                }

                return self::canViewGroup($activity->group_id !== null ? (int) $activity->group_id : null, $viewerId, $tenantId);
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning("[FeedItemTables] visibility check failed for {$targetType}: " . $e->getMessage());
            return false;
        }
    }

    public static function canViewProfile(int $profileUserId, ?int $viewerId = null): bool
    {
        $tenantId = TenantContext::getId();
        if (!$tenantId || $profileUserId <= 0) {
            return false;
        }

        if ($viewerId && $profileUserId === $viewerId) {
            return true;
        }

        $profile = DB::table('users')
            ->where('id', $profileUserId)
            ->where('tenant_id', $tenantId)
            ->select(['id', 'privacy_profile'])
            ->first();

        if (!$profile) {
            return false;
        }

        $privacy = $profile->privacy_profile ?? 'public';
        if ($privacy === 'public') {
            return true;
        }

        if (!$viewerId) {
            return false;
        }

        if ($privacy === 'members') {
            return true;
        }

        if ($privacy === 'connections') {
            return self::usersAreConnected($viewerId, $profileUserId, $tenantId);
        }

        return false;
    }

    public static function canPostInGroup(int $groupId, int $userId): bool
    {
        $tenantId = TenantContext::getId();
        if (!$tenantId || $groupId <= 0 || $userId <= 0) {
            return false;
        }

        $group = DB::table('groups')
            ->where('id', $groupId)
            ->where('tenant_id', $tenantId)
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhere('status', 'active');
            })
            ->select(['id', 'owner_id'])
            ->first();

        if (!$group) {
            return false;
        }

        if ((int) $group->owner_id === $userId) {
            return true;
        }

        return DB::table('group_members')
            ->where('tenant_id', $tenantId)
            ->where('group_id', $groupId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();
    }

    public static function canViewGroup(?int $groupId, ?int $viewerId, int $tenantId): bool
    {
        if (!$groupId) {
            return true;
        }

        $group = DB::table('groups')
            ->where('id', $groupId)
            ->where('tenant_id', $tenantId)
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhere('status', 'active');
            })
            ->select(['id', 'owner_id', 'visibility'])
            ->first();

        if (!$group) {
            return false;
        }

        if (($group->visibility ?? 'public') === 'public') {
            return true;
        }

        if (!$viewerId) {
            return false;
        }

        if ((int) $group->owner_id === $viewerId) {
            return true;
        }

        return DB::table('group_members')
            ->where('tenant_id', $tenantId)
            ->where('group_id', $groupId)
            ->where('user_id', $viewerId)
            ->where('status', 'active')
            ->exists();
    }

    private static function canViewComment(int $commentId, ?int $viewerId, int $tenantId): bool
    {
        $comment = DB::table('comments')
            ->where('id', $commentId)
            ->where('tenant_id', $tenantId)
            ->select(['target_type', 'target_id'])
            ->first();

        if (!$comment) {
            return false;
        }

        return self::canView((string) $comment->target_type, (int) $comment->target_id, $viewerId);
    }

    private static function canViewPost(int $postId, ?int $viewerId, int $tenantId): bool
    {
        $query = DB::table('feed_posts')
            ->where('id', $postId)
            ->where('tenant_id', $tenantId);

        if (Schema::hasColumn('feed_posts', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if (Schema::hasColumn('feed_posts', 'publish_status')) {
            $query->where(function ($q) {
                $q->whereNull('publish_status')
                    ->orWhere('publish_status', 'published');
            });
        }

        if (Schema::hasColumn('feed_posts', 'is_hidden')) {
            $query->where(function ($q) {
                $q->whereNull('is_hidden')
                    ->orWhere('is_hidden', 0);
            });
        }

        $post = $query->select(['id', 'user_id', 'group_id', 'visibility'])->first();
        if (!$post) {
            return false;
        }

        if (!self::canViewGroup($post->group_id !== null ? (int) $post->group_id : null, $viewerId, $tenantId)) {
            return false;
        }

        return self::canViewPostVisibility((int) $post->user_id, (string) ($post->visibility ?? 'public'), $viewerId, $tenantId);
    }

    private static function canViewPostVisibility(int $authorId, string $visibility, ?int $viewerId, int $tenantId): bool
    {
        if ($visibility === 'public') {
            return true;
        }

        if (!$viewerId) {
            return false;
        }

        if ($authorId === $viewerId) {
            return true;
        }

        if (in_array($visibility, ['friends', 'connections'], true)) {
            return self::usersAreConnected($viewerId, $authorId, $tenantId);
        }

        return false;
    }

    private static function usersAreConnected(int $viewerId, int $profileUserId, int $tenantId): bool
    {
        return DB::table('connections')
            ->where('tenant_id', $tenantId)
            ->where('status', 'accepted')
            ->where(function ($q) use ($viewerId, $profileUserId) {
                $q->where(function ($q2) use ($viewerId, $profileUserId) {
                    $q2->where('requester_id', $viewerId)
                        ->where('receiver_id', $profileUserId);
                })->orWhere(function ($q2) use ($viewerId, $profileUserId) {
                    $q2->where('requester_id', $profileUserId)
                        ->where('receiver_id', $viewerId);
                });
            })
            ->exists();
    }
}
