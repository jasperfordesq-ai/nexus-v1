<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\Poll;
use Illuminate\Support\Facades\DB;

/**
 * PollService — Eloquent-based service for poll operations.
 *
 * All queries are tenant-scoped automatically via the HasTenantScope trait.
 */
class PollService
{
    public function __construct(
        private readonly Poll $poll,
    ) {}

    /**
     * Get polls with cursor pagination.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public function getAll(array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $cursor = $filters['cursor'] ?? null;

        $query = $this->poll->newQuery()
            ->with(['user:id,first_name,last_name,avatar_url']);

        if (($filters['status'] ?? null) === 'open') {
            $query->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>', now());
            });
        } elseif (($filters['status'] ?? null) === 'closed') {
            $query->where('end_date', '<=', now());
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
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
     * Get a single poll by ID with vote counts and user-voted flag.
     */
    public function getById(int $id, ?int $currentUserId = null): ?array
    {
        $poll = $this->poll->newQuery()->with(['user'])->find($id);
        if (! $poll) {
            return null;
        }

        $data = $poll->toArray();

        $data['options'] = DB::table('poll_options')
            ->where('poll_id', $id)
            ->get()
            ->map(fn ($o) => [
                'id'    => $o->id,
                'text'  => $o->option_text,
                'votes' => (int) DB::table('poll_votes')->where('option_id', $o->id)->count(),
            ])->all();

        $data['total_votes'] = array_sum(array_column($data['options'], 'votes'));

        $data['has_voted'] = $currentUserId
            ? DB::table('poll_votes')->where('poll_id', $id)->where('user_id', $currentUserId)->exists()
            : false;

        $data['user_voted_option'] = null;
        if ($currentUserId) {
            $vote = DB::table('poll_votes')
                ->where('poll_id', $id)
                ->where('user_id', $currentUserId)
                ->first();
            $data['user_voted_option'] = $vote ? (int) $vote->option_id : null;
        }

        $data['poll_type'] = $data['poll_type'] ?? 'standard';

        return $data;
    }

    /**
     * Create a new poll with options.
     */
    public function create(int $userId, array $data): Poll
    {
        return DB::transaction(function () use ($userId, $data) {
            $poll = $this->poll->newInstance([
                'user_id'     => $userId,
                'question'    => trim($data['question']),
                'description' => trim($data['description'] ?? ''),
                'end_date'    => $data['expires_at'] ?? $data['end_date'] ?? null,
                'is_active'   => true,
                'category'    => $data['category'] ?? null,
                'poll_type'   => $data['poll_type'] ?? 'standard',
            ]);
            $poll->save();

            if (! empty($data['options'])) {
                foreach ($data['options'] as $text) {
                    DB::table('poll_options')->insert([
                        'poll_id'     => $poll->id,
                        'option_text' => trim($text),
                        'created_at'  => now(),
                    ]);
                }
            }

            return $poll->fresh(['user']);
        });
    }

    /**
     * Update a poll (owner only, no votes yet).
     */
    public function update(int $id, int $userId, array $data): ?Poll
    {
        $poll = $this->poll->newQuery()->find($id);

        if (! $poll || (int) $poll->user_id !== $userId) {
            return null;
        }

        $allowed = ['question', 'description', 'end_date', 'expires_at', 'category'];
        $updates = collect($data)->only($allowed)->all();
        if (isset($updates['expires_at'])) {
            $updates['end_date'] = $updates['expires_at'];
            unset($updates['expires_at']);
        }
        $poll->fill($updates);
        $poll->save();

        return $poll->fresh(['user']);
    }

    /**
     * Delete a poll (owner only).
     */
    public function delete(int $id, int $userId): bool
    {
        $poll = $this->poll->newQuery()->find($id);

        if (! $poll || (int) $poll->user_id !== $userId) {
            return false;
        }

        return (bool) $poll->delete();
    }

    /**
     * Cast a vote on a poll option.
     *
     * @return bool true if vote was cast, false if already voted
     */
    public function vote(int $pollId, int $optionId, int $userId): bool
    {
        $alreadyVoted = DB::table('poll_votes')
            ->where('poll_id', $pollId)
            ->where('user_id', $userId)
            ->exists();

        if ($alreadyVoted) {
            return false;
        }

        DB::table('poll_votes')->insert([
            'poll_id'    => $pollId,
            'option_id'  => $optionId,
            'user_id'    => $userId,
            'created_at' => now(),
        ]);

        return true;
    }

    /**
     * Get validation errors — delegates to legacy PollService.
     */
    public function getErrors(): array
    {
        return \Nexus\Services\PollService::getErrors();
    }

    /**
     * Get distinct poll categories for the current tenant.
     */
    public function getCategories(): array
    {
        return $this->poll->newQuery()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category')
            ->sort()
            ->values()
            ->all();
    }
}
