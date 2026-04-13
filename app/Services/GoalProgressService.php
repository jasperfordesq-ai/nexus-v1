<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * GoalProgressService — Eloquent-based service for goal progress history.
 *
 * Replaces the legacy DI wrapper that delegated to
 */
class GoalProgressService
{
    /**
     * Get progress history for a goal with cursor pagination.
     */
    public function getProgressHistory(int $goalId, array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 50), 100);
        $cursor = $filters['cursor'] ?? null;

        $query = DB::table('goal_progress_history')
            ->where('goal_id', $goalId);

        if (! empty($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }

        if ($cursor !== null && ($cid = base64_decode($cursor, true)) !== false) {
            $query->where('id', '<', (int) $cid);
        }

        $query->orderByDesc('id');
        $rows = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        if ($hasMore) {
            $rows->pop();
        }

        $items = $rows->map(fn ($r) => array_merge((array) $r, ['type' => $r->event_type]))->all();

        return [
            'items'    => $items,
            'cursor'   => $hasMore && $rows->isNotEmpty() ? base64_encode((string) $rows->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get progress summary for a goal.
     */
    public function getSummary(int $goalId): array
    {
        $total = DB::table('goal_progress_history')
            ->where('goal_id', $goalId)
            ->count();

        $events = DB::table('goal_progress_history')
            ->where('goal_id', $goalId)
            ->selectRaw('event_type, COUNT(*) as count')
            ->groupBy('event_type')
            ->pluck('count', 'event_type')
            ->all();

        return [
            'total_events'   => $total,
            'events_by_type' => $events,
        ];
    }
}
