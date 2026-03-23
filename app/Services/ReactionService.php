<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * ReactionService — Manages emoji reactions on posts and comments.
 *
 * Supports 8 reaction types: love, like, laugh, wow, sad, celebrate, clap, time_credit.
 * Each user can have at most one reaction per entity. Toggling the same type removes it;
 * choosing a different type replaces the existing one.
 */
class ReactionService
{
    /** Valid reaction types */
    public const VALID_TYPES = ['love', 'like', 'laugh', 'wow', 'sad', 'celebrate', 'clap', 'time_credit'];

    /** Valid entity types */
    private const ENTITY_MAP = [
        'post' => [
            'table' => 'post_reactions',
            'fk' => 'post_id',
        ],
        'comment' => [
            'table' => 'comment_reactions',
            'fk' => 'comment_id',
        ],
    ];

    /**
     * Toggle a reaction on a post or comment.
     *
     * - If the user has no reaction: adds the given type.
     * - If the user has the same reaction type: removes it.
     * - If the user has a different reaction type: updates to the new type.
     *
     * @return array{action: string, reaction_type: string|null, reactions: array}
     */
    public function toggleReaction(int $entityId, string $entityType, string $reactionType, int $userId): array
    {
        $tenantId = TenantContext::getId();
        $config = self::ENTITY_MAP[$entityType] ?? null;

        if (!$config) {
            throw new \InvalidArgumentException("Invalid entity type: {$entityType}");
        }

        $table = $config['table'];
        $fk = $config['fk'];

        // Check existing reaction
        $existing = DB::table($table)
            ->where('tenant_id', $tenantId)
            ->where($fk, $entityId)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            if ($existing->reaction_type === $reactionType) {
                // Same type: remove
                DB::table($table)
                    ->where('id', $existing->id)
                    ->where('tenant_id', $tenantId)
                    ->delete();

                $action = 'removed';
                $resultType = null;
            } else {
                // Different type: update
                DB::table($table)
                    ->where('id', $existing->id)
                    ->where('tenant_id', $tenantId)
                    ->update([
                        'reaction_type' => $reactionType,
                        'created_at' => now(),
                    ]);

                $action = 'updated';
                $resultType = $reactionType;
            }
        } else {
            // No existing reaction: insert
            DB::table($table)->insert([
                'tenant_id' => $tenantId,
                $fk => $entityId,
                'user_id' => $userId,
                'reaction_type' => $reactionType,
                'created_at' => now(),
            ]);

            $action = 'added';
            $resultType = $reactionType;
        }

        // Return updated reaction state
        $reactions = $this->getReactions($entityId, $entityType, $userId);

        return [
            'action' => $action,
            'reaction_type' => $resultType,
            'reactions' => $reactions,
        ];
    }

    /**
     * Get grouped reaction counts for an entity, plus current user's reaction.
     *
     * @return array{counts: array<string, int>, total: int, user_reaction: string|null, top_reactors: array}
     */
    public function getReactions(int $entityId, string $entityType, ?int $userId = null): array
    {
        $tenantId = TenantContext::getId();
        $config = self::ENTITY_MAP[$entityType] ?? null;

        if (!$config) {
            throw new \InvalidArgumentException("Invalid entity type: {$entityType}");
        }

        $table = $config['table'];
        $fk = $config['fk'];

        // Get grouped counts
        $rows = DB::table($table)
            ->where('tenant_id', $tenantId)
            ->where($fk, $entityId)
            ->select('reaction_type', DB::raw('COUNT(*) as count'))
            ->groupBy('reaction_type')
            ->get();

        $counts = [];
        $total = 0;
        foreach ($rows as $row) {
            $counts[$row->reaction_type] = (int) $row->count;
            $total += (int) $row->count;
        }

        // Get current user's reaction
        $userReaction = null;
        if ($userId) {
            $userRow = DB::table($table)
                ->where('tenant_id', $tenantId)
                ->where($fk, $entityId)
                ->where('user_id', $userId)
                ->first();

            if ($userRow) {
                $userReaction = $userRow->reaction_type;
            }
        }

        // Get a few recent reactor names for the summary
        $topReactors = DB::table("{$table} as r")
            ->join('users as u', 'r.user_id', '=', 'u.id')
            ->where('r.tenant_id', $tenantId)
            ->where("r.{$fk}", $entityId)
            ->orderByDesc('r.created_at')
            ->limit(3)
            ->select('u.id', DB::raw("CONCAT(u.first_name, ' ', u.last_name) as name"), 'u.avatar_url')
            ->get()
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'name' => trim($r->name),
                'avatar_url' => $r->avatar_url,
            ])
            ->all();

        return [
            'counts' => $counts,
            'total' => $total,
            'user_reaction' => $userReaction,
            'top_reactors' => $topReactors,
        ];
    }

    /**
     * Get paginated list of users who reacted with a specific type.
     *
     * @return array{users: array, total: int, has_more: bool}
     */
    public function getReactors(int $entityId, string $entityType, string $reactionType, int $page = 1, int $perPage = 20): array
    {
        $tenantId = TenantContext::getId();
        $config = self::ENTITY_MAP[$entityType] ?? null;

        if (!$config) {
            throw new \InvalidArgumentException("Invalid entity type: {$entityType}");
        }

        $table = $config['table'];
        $fk = $config['fk'];

        $query = DB::table("{$table} as r")
            ->join('users as u', 'r.user_id', '=', 'u.id')
            ->where('r.tenant_id', $tenantId)
            ->where("r.{$fk}", $entityId)
            ->where('r.reaction_type', $reactionType);

        $total = $query->count();

        $users = (clone $query)
            ->orderByDesc('r.created_at')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->select(
                'u.id',
                DB::raw("CONCAT(u.first_name, ' ', u.last_name) as name"),
                'u.avatar_url',
                'r.created_at as reacted_at'
            )
            ->get()
            ->map(fn ($r) => [
                'id' => (int) $r->id,
                'name' => trim($r->name),
                'avatar_url' => $r->avatar_url,
                'reacted_at' => $r->reacted_at,
            ])
            ->all();

        return [
            'users' => $users,
            'total' => $total,
            'has_more' => ($page * $perPage) < $total,
        ];
    }

    /**
     * Get reaction data for multiple posts at once (for feed listing).
     *
     * @param int[] $postIds
     * @return array<int, array{counts: array, total: int, user_reaction: string|null}>
     */
    public function getReactionsForPosts(array $postIds, ?int $userId = null): array
    {
        if (empty($postIds)) {
            return [];
        }

        $tenantId = TenantContext::getId();
        $placeholders = implode(',', array_fill(0, count($postIds), '?'));

        // Get all reaction counts grouped by post_id and reaction_type
        $rows = DB::select(
            "SELECT post_id, reaction_type, COUNT(*) as count
             FROM post_reactions
             WHERE tenant_id = ? AND post_id IN ({$placeholders})
             GROUP BY post_id, reaction_type",
            array_merge([$tenantId], $postIds)
        );

        // Build result map
        $result = [];
        foreach ($postIds as $pid) {
            $result[$pid] = ['counts' => [], 'total' => 0, 'user_reaction' => null];
        }

        foreach ($rows as $row) {
            $pid = (int) $row->post_id;
            $result[$pid]['counts'][$row->reaction_type] = (int) $row->count;
            $result[$pid]['total'] += (int) $row->count;
        }

        // Get current user's reactions
        if ($userId) {
            $userRows = DB::select(
                "SELECT post_id, reaction_type
                 FROM post_reactions
                 WHERE tenant_id = ? AND post_id IN ({$placeholders}) AND user_id = ?",
                array_merge([$tenantId], $postIds, [$userId])
            );

            foreach ($userRows as $row) {
                $pid = (int) $row->post_id;
                $result[$pid]['user_reaction'] = $row->reaction_type;
            }
        }

        return $result;
    }
}
