<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\GoalCheckin;

/**
 * GoalCheckinService — Eloquent-based service for goal check-ins.
 *
 * Replaces the legacy DI wrapper that delegated to
 */
class GoalCheckinService
{
    /**
     * Create a check-in for a goal.
     */
    public function create(int $goalId, int $userId, array $data): GoalCheckin
    {
        return GoalCheckin::create([
            'goal_id'          => $goalId,
            'user_id'          => $userId,
            'progress_percent' => $data['progress_percent'] ?? null,
            'note'             => trim($data['note'] ?? ''),
            'mood'             => $data['mood'] ?? null,
        ]);
    }

    /**
     * Get check-ins for a goal with cursor pagination.
     */
    public function getByGoalId(int $goalId, array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $cursor = $filters['cursor'] ?? null;

        $query = GoalCheckin::where('goal_id', $goalId)
            ->with(['user:id,first_name,last_name,avatar_url']);

        if ($cursor !== null && ($cid = base64_decode($cursor, true)) !== false) {
            $query->where('id', '<', (int) $cid);
        }

        $query->orderByDesc('id');
        $items = $query->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items->pop();
        }

        return [
            'items'    => $items->toArray(),
            'cursor'   => $hasMore && $items->isNotEmpty() ? base64_encode((string) $items->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }
}
