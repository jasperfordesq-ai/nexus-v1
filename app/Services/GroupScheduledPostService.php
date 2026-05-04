<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;

/**
 * GroupScheduledPostService — Schedule posts/announcements for future publishing.
 */
class GroupScheduledPostService
{
    public static function schedule(int $groupId, int $userId, array $data): int
    {
        $tenantId = TenantContext::getId();
        return DB::table('group_scheduled_posts')->insertGetId([
            'tenant_id' => $tenantId,
            'group_id' => $groupId,
            'user_id' => $userId,
            'post_type' => $data['post_type'] ?? 'discussion',
            'title' => $data['title'] ?? null,
            'content' => $data['content'],
            'is_recurring' => $data['is_recurring'] ?? false,
            'recurrence_pattern' => $data['recurrence_pattern'] ?? null,
            'scheduled_at' => $data['scheduled_at'],
            'status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function getScheduled(int $groupId): array
    {
        $tenantId = TenantContext::getId();
        return DB::table('group_scheduled_posts as sp')
            ->join('users as u', 'sp.user_id', '=', 'u.id')
            ->where('sp.group_id', $groupId)
            ->where('sp.tenant_id', $tenantId)
            ->where('sp.status', 'scheduled')
            ->select('sp.*', 'u.name as author_name')
            ->orderBy('sp.scheduled_at')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->toArray();
    }

    public static function cancel(int $groupId, int $postId): bool
    {
        $tenantId = TenantContext::getId();
        return DB::table('group_scheduled_posts')
            ->where('id', $postId)
            ->where('group_id', $groupId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'scheduled')
            ->update(['status' => 'cancelled', 'updated_at' => now()]) > 0;
    }

    /**
     * Publish all due scheduled posts. Run from artisan scheduler.
     */
    public static function publishDue(): int
    {
        $due = DB::table('group_scheduled_posts')
            ->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->get();

        $published = 0;
        foreach ($due as $post) {
            try {
                TenantContext::setById($post->tenant_id);

                if ($post->post_type === 'announcement') {
                    DB::table('group_announcements')->insert([
                        'tenant_id' => $post->tenant_id,
                        'group_id' => $post->group_id,
                        'title' => $post->title ?? __('api.group_scheduled_announcement_title'),
                        'content' => $post->content,
                        'created_by' => $post->user_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $discussion = DB::table('group_discussions')->insertGetId([
                        'tenant_id' => $post->tenant_id,
                        'group_id' => $post->group_id,
                        'user_id' => $post->user_id,
                        'title' => $post->title ?? __('api.group_scheduled_post_title'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    DB::table('group_posts')->insert([
                        'tenant_id' => $post->tenant_id,
                        'discussion_id' => $discussion,
                        'user_id' => $post->user_id,
                        'content' => $post->content,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('group_scheduled_posts')
                    ->where('id', $post->id)
                    ->update(['status' => 'published', 'published_at' => now()]);

                // Handle recurring
                if ($post->is_recurring && $post->recurrence_pattern) {
                    $next = match ($post->recurrence_pattern) {
                        'daily' => now()->addDay(),
                        'weekly' => now()->addWeek(),
                        'monthly' => now()->addMonth(),
                        default => null,
                    };
                    if ($next) {
                        DB::table('group_scheduled_posts')->insert([
                            'tenant_id' => $post->tenant_id,
                            'group_id' => $post->group_id,
                            'user_id' => $post->user_id,
                            'post_type' => $post->post_type,
                            'title' => $post->title,
                            'content' => $post->content,
                            'is_recurring' => true,
                            'recurrence_pattern' => $post->recurrence_pattern,
                            'scheduled_at' => $next,
                            'status' => 'scheduled',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                $published++;
            } catch (\Throwable $e) {
                // Log but continue
            }
        }

        return $published;
    }
}
