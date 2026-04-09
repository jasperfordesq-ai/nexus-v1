<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\Goal;
use App\Models\GoalCheckin;
use Illuminate\Support\Facades\DB;

/**
 * GoalService — Eloquent-based service for goal operations.
 *
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
            ->with(['user:id,first_name,last_name,avatar_url', 'mentor:id,first_name,last_name,avatar_url']);

        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        } else {
            $query->where('is_public', true);
        }

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['visibility']) && $filters['visibility'] !== 'all') {
            $query->where('is_public', $filters['visibility'] === 'public');
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
     * Get public goals available for buddy offers (excludes user's own).
     */
    public function getPublicForBuddy(int $userId, array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $cursor = $filters['cursor'] ?? null;

        $query = $this->goal->newQuery()
            ->with(['user:id,first_name,last_name,avatar_url', 'mentor:id,first_name,last_name,avatar_url'])
            ->where('is_public', true)
            ->where('status', 'active')
            ->where('user_id', '!=', $userId)
            ->whereNull('mentor_id');

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
     * Get goals where user is buddy/mentor.
     */
    public function getGoalsAsMentor(int $userId, array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $cursor = $filters['cursor'] ?? null;

        $query = $this->goal->newQuery()
            ->with(['user:id,first_name,last_name,avatar_url', 'mentor:id,first_name,last_name,avatar_url'])
            ->where('mentor_id', $userId);

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
            ->with(['user:id,first_name,last_name,avatar_url', 'mentor:id,first_name,last_name,avatar_url'])
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
     * Update a goal (only owner).
     */
    public function update(int $id, int $userId, array $data): ?Goal
    {
        $goal = $this->goal->newQuery()->find($id);

        if (! $goal || (int) $goal->user_id !== $userId) {
            return null;
        }

        $allowed = ['title', 'description', 'deadline', 'is_public', 'status'];
        $goal->fill(collect($data)->only($allowed)->all());
        $goal->save();

        return $goal->fresh(['user']);
    }

    /**
     * Delete a goal (only owner).
     */
    public function delete(int $id, int $userId): bool
    {
        $goal = $this->goal->newQuery()->find($id);

        if (! $goal || (int) $goal->user_id !== $userId) {
            return false;
        }

        return (bool) $goal->delete();
    }

    /**
     * Increment goal progress.
     */
    public function incrementProgress(int $id, int $userId, float $increment): ?Goal
    {
        $goal = $this->goal->newQuery()->find($id);

        if (! $goal || (int) $goal->user_id !== $userId) {
            return null;
        }

        $current = (float) ($goal->current_value ?? 0);
        $goal->current_value = $current + $increment;

        $target = (float) ($goal->target_value ?? 0);
        if ($target > 0 && $goal->current_value >= $target) {
            $goal->status = 'completed';
        }

        $goal->save();

        return $goal->fresh(['user']);
    }

    /**
     * Mark a goal as completed.
     */
    public function complete(int $id, int $userId): ?Goal
    {
        $goal = $this->goal->newQuery()->find($id);

        if (! $goal || (int) $goal->user_id !== $userId) {
            return null;
        }

        $target = (float) ($goal->target_value ?? 1);
        $goal->current_value = $target;
        $goal->status = 'completed';
        $goal->save();

        return $goal;
    }

    /**
     * Offer to be buddy for a goal.
     */
    public function offerBuddy(int $goalId, int $userId): ?Goal
    {
        $goal = $this->goal->newQuery()->find($goalId);

        if (! $goal || ! $goal->is_public || $goal->mentor_id !== null) {
            return null;
        }

        if ((int) $goal->user_id === $userId) {
            return null;
        }

        $goal->mentor_id = $userId;
        $goal->save();

        return $goal->fresh(['user', 'mentor']);
    }
}
