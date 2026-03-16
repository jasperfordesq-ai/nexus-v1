<?php
// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\Goal;

/**
 * GoalService — Laravel DI-based service for goal operations.
 *
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\GoalService.
 * All queries are tenant-scoped automatically via the HasTenantScope trait.
 */
class GoalService
{
    public function __construct(
        private readonly Goal $goal,
    ) {}

    /**
     * Get goals with optional filtering and cursor pagination.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public function getAll(array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $cursor = $filters['cursor'] ?? null;

        $query = $this->goal->newQuery()
            ->with(['user:id,first_name,last_name,avatar_url']);

        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        } else {
            $query->where('is_public', true);
        }

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

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

    /**
     * Get a single goal by ID.
     */
    public function getById(int $id): ?Goal
    {
        return $this->goal->newQuery()
            ->with(['user:id,first_name,last_name,avatar_url', 'mentor:id,first_name,last_name'])
            ->find($id);
    }

    /**
     * Create a new goal.
     */
    public function create(int $userId, array $data): Goal
    {
        $goal = $this->goal->newInstance([
            'user_id'     => $userId,
            'title'       => trim($data['title']),
            'description' => trim($data['description'] ?? ''),
            'deadline'    => $data['deadline'] ?? null,
            'is_public'   => $data['is_public'] ?? true,
            'status'      => 'active',
        ]);

        $goal->save();

        return $goal->fresh(['user']);
    }

    /**
     * Update progress on a goal.
     */
    public function updateProgress(int $id, array $data): Goal
    {
        $goal = $this->goal->newQuery()->findOrFail($id);

        $allowed = ['title', 'description', 'deadline', 'is_public', 'status', 'mentor_id'];
        $goal->fill(collect($data)->only($allowed)->all());
        $goal->save();

        return $goal->fresh(['user']);
    }

    /**
     * Mark a goal as completed.
     */
    public function complete(int $id): Goal
    {
        $goal = $this->goal->newQuery()->findOrFail($id);
        $goal->status = 'completed';
        $goal->save();

        return $goal;
    }
}
