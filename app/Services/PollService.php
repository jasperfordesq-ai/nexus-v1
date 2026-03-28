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
    public static function getAll(array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $cursor = $filters['cursor'] ?? null;

        $query = Poll::query()
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

        if (! empty($filters['event_id'])) {
            $query->where('event_id', (int) $filters['event_id']);
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
    public static function getById(int $id, ?int $currentUserId = null): ?array
    {
        $poll = Poll::query()->with(['user'])->find($id);
        if (! $poll) {
            return null;
        }

        $data = $poll->toArray();

        $optionRows = DB::table('poll_options')
            ->where('poll_id', $id)
            ->get()
            ->map(fn ($o) => [
                'id'         => $o->id,
                'text'       => $o->label ?? $o->option_text ?? '',
                'label'      => $o->label ?? $o->option_text ?? '',
                'vote_count' => (int) DB::table('poll_votes')->where('option_id', $o->id)->count(),
            ])->all();

        $totalVotes = array_sum(array_column($optionRows, 'vote_count'));

        $data['options'] = array_map(function (array $opt) use ($totalVotes) {
            $opt['percentage'] = $totalVotes > 0
                ? round(($opt['vote_count'] / $totalVotes) * 100, 1)
                : 0;
            return $opt;
        }, $optionRows);

        $data['total_votes'] = $totalVotes;

        $data['has_voted'] = $currentUserId
            ? DB::table('poll_votes')->where('poll_id', $id)->where('user_id', $currentUserId)->exists()
            : false;

        $votedOptionId = null;
        if ($currentUserId) {
            $vote = DB::table('poll_votes')
                ->where('poll_id', $id)
                ->where('user_id', $currentUserId)
                ->first();
            $votedOptionId = $vote ? (int) $vote->option_id : null;
        }
        $data['voted_option_id'] = $votedOptionId;
        $data['user_vote_option_id'] = $votedOptionId;

        $data['poll_type'] = $data['poll_type'] ?? 'standard';

        // Compute status for frontend
        $endDate = $poll->end_date ?? $poll->expires_at ?? null;
        $data['status'] = ($poll->is_active && (! $endDate || now()->lt($endDate))) ? 'open' : 'closed';
        $data['expires_at'] = $endDate ? (string) $endDate : null;

        // Normalise creator field from loaded user relation
        $user = $poll->user;
        $data['creator'] = $user ? [
            'id'         => (int) $user->id,
            'name'       => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
            'avatar_url' => $user->avatar_url ?? null,
        ] : ['id' => (int) $poll->user_id, 'name' => 'Unknown', 'avatar_url' => null];

        return $data;
    }

    /**
     * Create a new poll with options.
     */
    public static function create(int $userId, array $data): Poll
    {
        return DB::transaction(function () use ($userId, $data) {
            $poll = new Poll([
                'user_id'     => $userId,
                'event_id'    => $data['event_id'] ?? null,
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
                        'poll_id'    => $poll->id,
                        'label'      => trim($text),
                        'created_at' => now(),
                    ]);
                }
            }

            return $poll->fresh(['user']);
        });
    }

    /**
     * Update a poll (owner only, no votes yet).
     */
    public static function update(int $id, int $userId, array $data): ?Poll
    {
        $poll = Poll::query()->find($id);

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
    public static function delete(int $id, int $userId): bool
    {
        $poll = Poll::query()->find($id);

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
    public static function vote(int $pollId, int $optionId, int $userId): bool
    {
        // Use INSERT IGNORE to atomically prevent double-votes.
        // The idx_vote_unique (poll_id, user_id) constraint enforces uniqueness;
        // INSERT IGNORE silently skips if the row already exists.
        $affected = DB::affectingStatement(
            'INSERT IGNORE INTO poll_votes (poll_id, option_id, user_id, created_at) VALUES (?, ?, ?, NOW())',
            [$pollId, $optionId, $userId]
        );

        return $affected > 0;
    }

    /**
     * Get validation errors.
     *
     * The legacy PollService used a static error collector; in Laravel,
     * validation is handled by Laravel's Validator, so this returns an
     * empty array for backward-compatibility with any callers.
     */
    public static function getErrors(): array
    {
        return [];
    }

    /**
     * Get distinct poll categories for the current tenant.
     */
    public static function getCategories(): array
    {
        return Poll::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category')
            ->sort()
            ->values()
            ->all();
    }
}
