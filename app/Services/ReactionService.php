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
 *
 * Uses the unified `reactions` table with `target_type` / `target_id` / `emoji` columns.
 */
class ReactionService
{
    /** Valid reaction types */
    public const VALID_TYPES = ['love', 'like', 'laugh', 'wow', 'sad', 'celebrate', 'clap', 'time_credit'];

    /** Valid target types for the reactions table */
    private const VALID_TARGET_TYPES = ['post', 'comment'];

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

        if (!in_array($entityType, self::VALID_TARGET_TYPES, true)) {
            throw new \InvalidArgumentException("Invalid entity type: {$entityType}");
        }

        // Serialise concurrent toggles for the same (user, entity) to avoid duplicate inserts.
        // Belt-and-braces: a DB-level unique index `reactions_unique` on
        // (tenant_id, user_id, target_type, target_id) is also enforced — see
        // 2026_04_12_140000_add_unique_index_to_reactions migration.
        [$action, $resultType] = DB::transaction(function () use ($tenantId, $entityType, $entityId, $userId, $reactionType) {
            $existing = DB::table('reactions')
                ->where('tenant_id', $tenantId)
                ->where('target_type', $entityType)
                ->where('target_id', $entityId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($existing->emoji === $reactionType) {
                    // Same type: remove
                    DB::table('reactions')
                        ->where('id', $existing->id)
                        ->where('tenant_id', $tenantId)
                        ->delete();

                    return ['removed', null];
                }

                // Different type: update
                DB::table('reactions')
                    ->where('id', $existing->id)
                    ->where('tenant_id', $tenantId)
                    ->update([
                        'emoji' => $reactionType,
                        'created_at' => now(),
                    ]);

                return ['updated', $reactionType];
            }

            // No existing reaction: insert
            DB::table('reactions')->insert([
                'tenant_id' => $tenantId,
                'target_type' => $entityType,
                'target_id' => $entityId,
                'user_id' => $userId,
                'emoji' => $reactionType,
                'created_at' => now(),
            ]);

            return ['added', $reactionType];
        });

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

        if (!in_array($entityType, self::VALID_TARGET_TYPES, true)) {
            throw new \InvalidArgumentException("Invalid entity type: {$entityType}");
        }

        // Get grouped counts
        $rows = DB::table('reactions')
            ->where('tenant_id', $tenantId)
            ->where('target_type', $entityType)
            ->where('target_id', $entityId)
            ->select('emoji', DB::raw('COUNT(*) as count'))
            ->groupBy('emoji')
            ->get();

        $counts = [];
        $total = 0;
        foreach ($rows as $row) {
            $counts[$row->emoji] = (int) $row->count;
            $total += (int) $row->count;
        }

        // Get current user's reaction
        $userReaction = null;
        if ($userId) {
            $userRow = DB::table('reactions')
                ->where('tenant_id', $tenantId)
                ->where('target_type', $entityType)
                ->where('target_id', $entityId)
                ->where('user_id', $userId)
                ->first();

            if ($userRow) {
                $userReaction = $userRow->emoji;
            }
        }

        // Get a few recent reactor names for the summary
        $topReactors = DB::table('reactions as r')
            ->join('users as u', 'r.user_id', '=', 'u.id')
            ->where('r.tenant_id', $tenantId)
            ->where('r.target_type', $entityType)
            ->where('r.target_id', $entityId)
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

        if (!in_array($entityType, self::VALID_TARGET_TYPES, true)) {
            throw new \InvalidArgumentException("Invalid entity type: {$entityType}");
        }

        $query = DB::table('reactions as r')
            ->join('users as u', 'r.user_id', '=', 'u.id')
            ->where('r.tenant_id', $tenantId)
            ->where('r.target_type', $entityType)
            ->where('r.target_id', $entityId)
            ->where('r.emoji', $reactionType);

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

        // Get all reaction counts grouped by target_id and emoji
        $rows = DB::select(
            "SELECT target_id, emoji, COUNT(*) as count
             FROM reactions
             WHERE tenant_id = ? AND target_type = 'post' AND target_id IN ({$placeholders})
             GROUP BY target_id, emoji",
            array_merge([$tenantId], $postIds)
        );

        // Build result map
        $result = [];
        foreach ($postIds as $pid) {
            $result[$pid] = ['counts' => [], 'total' => 0, 'user_reaction' => null];
        }

        foreach ($rows as $row) {
            $pid = (int) $row->target_id;
            $result[$pid]['counts'][$row->emoji] = (int) $row->count;
            $result[$pid]['total'] += (int) $row->count;
        }

        // Get current user's reactions
        if ($userId) {
            $userRows = DB::select(
                "SELECT target_id, emoji
                 FROM reactions
                 WHERE tenant_id = ? AND target_type = 'post' AND target_id IN ({$placeholders}) AND user_id = ?",
                array_merge([$tenantId], $postIds, [$userId])
            );

            foreach ($userRows as $row) {
                $pid = (int) $row->target_id;
                $result[$pid]['user_reaction'] = $row->emoji;
            }
        }

        return $result;
    }
}
