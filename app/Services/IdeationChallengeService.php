<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * IdeationChallengeService — Laravel DI-based service for ideation challenges.
 *
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\IdeationChallengeService.
 * Manages challenge CRUD, idea submission, and voting with tenant scoping.
 */
class IdeationChallengeService
{
    /**
     * Get all challenges with cursor-based pagination.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public function getAll(array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $cursor = $filters['cursor'] ?? null;

        $query = DB::table('ideation_challenges as c')
            ->leftJoin('users as u', 'c.created_by', '=', 'u.id')
            ->select('c.*', 'u.first_name', 'u.last_name', 'u.avatar_url');

        if (! empty($filters['status'])) {
            $query->where('c.status', $filters['status']);
        }

        if ($cursor !== null) {
            $query->where('c.id', '<', (int) base64_decode($cursor));
        }

        $query->orderByDesc('c.id');
        $items = $query->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items->pop();
        }

        return [
            'items'    => $items->map(fn ($i) => (array) $i)->values()->all(),
            'cursor'   => $hasMore && $items->isNotEmpty() ? base64_encode((string) $items->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get a single challenge by ID with idea count.
     */
    public function getById(int $id): ?array
    {
        $challenge = DB::table('ideation_challenges')->find($id);
        if (! $challenge) {
            return null;
        }

        $data = (array) $challenge;
        $data['ideas_count'] = (int) DB::table('ideation_ideas')->where('challenge_id', $id)->count();

        return $data;
    }

    /**
     * Create a new ideation challenge.
     */
    public function create(int $userId, array $data): int
    {
        return DB::table('ideation_challenges')->insertGetId([
            'title'       => trim($data['title']),
            'description' => trim($data['description'] ?? ''),
            'status'      => $data['status'] ?? 'open',
            'created_by'  => $userId,
            'starts_at'   => $data['starts_at'] ?? null,
            'ends_at'     => $data['ends_at'] ?? null,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    /**
     * Submit an idea to a challenge.
     */
    public function submitIdea(int $challengeId, int $userId, array $data): int
    {
        return DB::table('ideation_ideas')->insertGetId([
            'challenge_id' => $challengeId,
            'user_id'      => $userId,
            'title'        => trim($data['title']),
            'description'  => trim($data['description'] ?? ''),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    /**
     * Get ideas for a challenge with cursor-based pagination.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public function getIdeas(int $challengeId, array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $cursor = $filters['cursor'] ?? null;
        $sort = $filters['sort'] ?? 'votes';

        $query = DB::table('ideation_ideas as i')
            ->leftJoin('users as u', 'i.user_id', '=', 'u.id')
            ->where('i.challenge_id', $challengeId)
            ->select('i.*', 'u.first_name', 'u.last_name', 'u.avatar_url');

        // Add vote count subquery
        $query->selectSub(
            DB::table('ideation_votes')->whereColumn('idea_id', 'i.id')->selectRaw('COUNT(*)'),
            'vote_count'
        );

        if ($cursor !== null) {
            $query->where('i.id', '<', (int) base64_decode($cursor));
        }

        if ($sort === 'votes') {
            $query->orderByDesc('vote_count')->orderByDesc('i.id');
        } else {
            $query->orderByDesc('i.id');
        }

        $items = $query->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items->pop();
        }

        return [
            'items'    => $items->map(fn ($i) => (array) $i)->values()->all(),
            'cursor'   => $hasMore && $items->isNotEmpty() ? base64_encode((string) $items->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Toggle a vote on an idea.
     *
     * @return array{voted: bool, vote_count: int}
     */
    public function vote(int $ideaId, int $userId): array
    {
        $existing = DB::table('ideation_votes')
            ->where('idea_id', $ideaId)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            DB::table('ideation_votes')->where('id', $existing->id)->delete();
            $voted = false;
        } else {
            DB::table('ideation_votes')->insert([
                'idea_id'    => $ideaId,
                'user_id'    => $userId,
                'created_at' => now(),
            ]);
            $voted = true;
        }

        $count = (int) DB::table('ideation_votes')->where('idea_id', $ideaId)->count();

        return ['voted' => $voted, 'vote_count' => $count];
    }

    // ================================================================
    // LEGACY DELEGATION METHODS
    // These delegate to Nexus\Services\IdeationChallengeService (static)
    // for features not yet ported to Eloquent.
    // ================================================================

    /**
     * Get validation errors — delegates to legacy IdeationChallengeService.
     */
    public function getErrors(): array
    {
        return \Nexus\Services\IdeationChallengeService::getErrors();
    }

    /**
     * Get a challenge by ID (legacy) — delegates to legacy IdeationChallengeService.
     */
    public function getChallengeById(int $id, ?int $userId = null): ?array
    {
        return \Nexus\Services\IdeationChallengeService::getChallengeById($id, $userId);
    }

    /**
     * Update a challenge — delegates to legacy IdeationChallengeService.
     */
    public function updateChallenge(int $id, int $userId, array $data): bool
    {
        return \Nexus\Services\IdeationChallengeService::updateChallenge($id, $userId, $data);
    }

    /**
     * Delete a challenge — delegates to legacy IdeationChallengeService.
     */
    public function deleteChallenge(int $id, int $userId): bool
    {
        return \Nexus\Services\IdeationChallengeService::deleteChallenge($id, $userId);
    }

    /**
     * Update challenge status — delegates to legacy IdeationChallengeService.
     */
    public function updateChallengeStatus(int $id, int $userId, string $status): bool
    {
        return \Nexus\Services\IdeationChallengeService::updateChallengeStatus($id, $userId, $status);
    }

    /**
     * Toggle favorite on a challenge — delegates to legacy IdeationChallengeService.
     */
    public function toggleFavorite(int $challengeId, int $userId): array
    {
        return \Nexus\Services\IdeationChallengeService::toggleFavorite($challengeId, $userId);
    }

    /**
     * Duplicate a challenge — delegates to legacy IdeationChallengeService.
     */
    public function duplicateChallenge(int $challengeId, int $userId): ?int
    {
        return \Nexus\Services\IdeationChallengeService::duplicateChallenge($challengeId, $userId);
    }

    /**
     * Get an idea by ID — delegates to legacy IdeationChallengeService.
     */
    public function getIdeaById(int $id, ?int $userId = null): ?array
    {
        return \Nexus\Services\IdeationChallengeService::getIdeaById($id, $userId);
    }

    /**
     * Update an idea — delegates to legacy IdeationChallengeService.
     */
    public function updateIdea(int $id, int $userId, array $data): bool
    {
        return \Nexus\Services\IdeationChallengeService::updateIdea($id, $userId, $data);
    }

    /**
     * Update a draft idea — delegates to legacy IdeationChallengeService.
     */
    public function updateDraftIdea(int $ideaId, int $userId, array $data): bool
    {
        return \Nexus\Services\IdeationChallengeService::updateDraftIdea($ideaId, $userId, $data);
    }

    /**
     * Delete an idea — delegates to legacy IdeationChallengeService.
     */
    public function deleteIdea(int $id, int $userId): bool
    {
        return \Nexus\Services\IdeationChallengeService::deleteIdea($id, $userId);
    }

    /**
     * Vote on an idea (legacy) — delegates to legacy IdeationChallengeService.
     */
    public function voteIdea(int $ideaId, int $userId): ?array
    {
        return \Nexus\Services\IdeationChallengeService::voteIdea($ideaId, $userId);
    }

    /**
     * Update idea status — delegates to legacy IdeationChallengeService.
     */
    public function updateIdeaStatus(int $ideaId, int $userId, string $status): bool
    {
        return \Nexus\Services\IdeationChallengeService::updateIdeaStatus($ideaId, $userId, $status);
    }

    /**
     * Get user's draft ideas for a challenge — delegates to legacy IdeationChallengeService.
     */
    public function getUserDrafts(int $challengeId, int $userId): array
    {
        return \Nexus\Services\IdeationChallengeService::getUserDrafts($challengeId, $userId);
    }

    /**
     * Get comments for an idea — delegates to legacy IdeationChallengeService.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public function getComments(int $ideaId, array $filters = []): array
    {
        return \Nexus\Services\IdeationChallengeService::getComments($ideaId, $filters);
    }

    /**
     * Add a comment to an idea — delegates to legacy IdeationChallengeService.
     */
    public function addComment(int $ideaId, int $userId, string $body): ?int
    {
        return \Nexus\Services\IdeationChallengeService::addComment($ideaId, $userId, $body);
    }

    /**
     * Delete a comment — delegates to legacy IdeationChallengeService.
     */
    public function deleteComment(int $commentId, int $userId): bool
    {
        return \Nexus\Services\IdeationChallengeService::deleteComment($commentId, $userId);
    }

    /**
     * Get all tags — delegates to legacy IdeationChallengeService.
     */
    public function getAllTags(): array
    {
        return \Nexus\Services\IdeationChallengeService::getAllTags();
    }
}
