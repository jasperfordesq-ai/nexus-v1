<?php
// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * CommentService — Laravel DI-based service for comment operations.
 *
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\CommentService.
 * Uses the comments table with polymorphic target_type/target_id columns.
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
        $rows = DB::table('comments as c')
            ->leftJoin('users as u', 'c.user_id', '=', 'u.id')
            ->where('c.target_type', $targetType)
            ->where('c.target_id', $targetId)
            ->select([
                'c.id', 'c.user_id', 'c.content', 'c.parent_id',
                'c.created_at', 'c.updated_at',
                DB::raw("COALESCE(u.first_name, 'Unknown') as author_name"),
                DB::raw("COALESCE(u.avatar_url, '/assets/img/defaults/default_avatar.png') as author_avatar"),
            ])
            ->orderBy('c.created_at')
            ->get()
            ->all();

        // Build threaded structure
        $byId = [];
        $topLevel = [];

        foreach ($rows as $row) {
            $row = (array) $row;
            $row['replies'] = [];
            $byId[$row['id']] = $row;
        }

        foreach ($byId as $id => &$comment) {
            if ($comment['parent_id'] && isset($byId[$comment['parent_id']])) {
                $byId[$comment['parent_id']]['replies'][] = &$comment;
            } else {
                $topLevel[] = &$comment;
            }
        }

        return $topLevel;
    }

    /**
     * Create a comment on an entity.
     */
    public function create(string $targetType, int $targetId, int $userId, array $data): int
    {
        return DB::table('comments')->insertGetId([
            'target_type' => $targetType,
            'target_id'   => $targetId,
            'user_id'     => $userId,
            'content'     => trim($data['content']),
            'parent_id'   => $data['parent_id'] ?? null,
            'tenant_id'   => $data['tenant_id'] ?? null,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    /**
     * Update a comment's content.
     */
    public function update(int $commentId, int $userId, string $content): bool
    {
        return (bool) DB::table('comments')
            ->where('id', $commentId)
            ->where('user_id', $userId)
            ->update([
                'content'    => trim($content),
                'updated_at' => now(),
            ]);
    }

    /**
     * Delete a comment (only by its author).
     */
    public function delete(int $commentId, int $userId): bool
    {
        return (bool) DB::table('comments')
            ->where('id', $commentId)
            ->where('user_id', $userId)
            ->delete();
    }
}
