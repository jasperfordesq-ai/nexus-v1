<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\Challenge;
use App\Models\Notification;
use App\Models\UserChallengeProgress;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ChallengeService — Eloquent-based service for gamification challenges.
 *
 * Manages challenge creation, listing, and member claim/completion workflows.
 * All queries are tenant-scoped via HasTenantScope trait on models.
 */
class ChallengeService
{
    public function __construct(
        private readonly Challenge $challenge,
        private readonly UserChallengeProgress $progress,
        private readonly GamificationService $gamificationService,
    ) {}

    /**
     * Get all challenges for a tenant.
     */
    public function getAll(int $tenantId, array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $offset = max(0, (int) ($filters['offset'] ?? 0));

        $query = $this->challenge->newQuery();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        $total = $query->count();
        $items = $query->orderByDesc('created_at')
            ->offset($offset)->limit($limit)
            ->get()->toArray();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Get a single challenge by ID.
     */
    public function getById(int $id, int $tenantId): ?array
    {
        $challenge = $this->challenge->newQuery()->find($id);
        return $challenge ? $challenge->toArray() : null;
    }

    /**
     * Create a new challenge.
     */
    public function create(int $tenantId, array $data): ?int
    {
        $challenge = $this->challenge->newInstance([
            'title'          => $data['title'],
            'description'    => $data['description'] ?? null,
            'challenge_type' => $data['challenge_type'] ?? 'weekly',
            'action_type'    => $data['action_type'] ?? null,
            'target_count'   => $data['target_count'] ?? 1,
            'category'       => $data['category'] ?? 'general',
            'xp_reward'      => max(0, (int) ($data['xp_reward'] ?? 10)),
            'badge_reward'   => $data['badge_reward'] ?? null,
            'status'         => 'active',
            'is_active'      => true,
            'start_date'     => $data['start_date'] ?? $data['starts_at'] ?? now(),
            'end_date'       => $data['end_date'] ?? $data['ends_at'] ?? null,
            'starts_at'      => $data['starts_at'] ?? $data['start_date'] ?? now(),
            'ends_at'        => $data['ends_at'] ?? $data['end_date'] ?? null,
        ]);
        $challenge->save();

        return $challenge->id;
    }

    /**
     * Claim (complete) a challenge for a user.
     */
    public function claim(int $challengeId, int $userId, int $tenantId): bool
    {
        $challenge = $this->challenge->newQuery()
            ->where('id', $challengeId)
            ->where('status', 'active')
            ->first();

        if (! $challenge) {
            return false;
        }

        $alreadyClaimed = DB::table('challenge_claims')
            ->where('challenge_id', $challengeId)
            ->where('user_id', $userId)
            ->exists();

        if ($alreadyClaimed) {
            return false;
        }

        DB::table('challenge_claims')->insert([
            'challenge_id' => $challengeId,
            'user_id'      => $userId,
            'claimed_at'   => now(),
            'created_at'   => now(),
        ]);

        return true;
    }

    /**
     * Get active challenges for current tenant.
     */
    public function getActiveChallenges(): array
    {
        $today = now()->toDateString();

        return $this->challenge->newQuery()
            ->where('is_active', true)
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->orderBy('end_date')
            ->get()
            ->toArray();
    }

    /**
     * Get challenges with user progress.
     */
    public function getChallengesWithProgress(int $userId): array
    {
        $today = now()->toDateString();

        $challenges = $this->challenge->newQuery()
            ->where('is_active', true)
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->orderBy('end_date')
            ->get();

        // Get all progress for this user's active challenges
        $challengeIds = $challenges->pluck('id')->all();
        $progressMap = [];
        if (! empty($challengeIds)) {
            $progressMap = $this->progress->newQuery()
                ->where('user_id', $userId)
                ->whereIn('challenge_id', $challengeIds)
                ->get()
                ->keyBy('challenge_id')
                ->all();
        }

        $result = [];
        foreach ($challenges as $challenge) {
            $row = $challenge->toArray();
            $prog = $progressMap[$challenge->id] ?? null;

            $row['user_progress'] = $prog ? (int) $prog->current_count : 0;
            $row['completed_at'] = $prog?->completed_at;
            $row['reward_claimed'] = $prog ? (bool) $prog->reward_claimed : false;
            $row['progress_percent'] = $challenge->target_count > 0
                ? min(100, round(($row['user_progress'] / $challenge->target_count) * 100))
                : 0;
            $row['is_completed'] = $row['user_progress'] >= $challenge->target_count;
            $row['days_remaining'] = max(0, (strtotime($challenge->end_date) - time()) / 86400);
            $row['hours_remaining'] = max(0, (strtotime($challenge->end_date) - time()) / 3600);
            $row['reward_xp'] = $challenge->xp_reward ?? 0;

            $result[] = $row;
        }

        return $result;
    }

    /**
     * Update progress for a challenge action.
     */
    public function updateProgress(int $userId, string $actionType, int $increment = 1): array
    {
        $today = now()->toDateString();

        $challenges = $this->challenge->newQuery()
            ->where('is_active', true)
            ->where('action_type', $actionType)
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->get();

        $completed = [];

        foreach ($challenges as $challenge) {
            try {
                DB::transaction(function () use ($challenge, $userId, $increment, &$completed) {
                    $prog = $this->progress->newQuery()
                        ->where('user_id', $userId)
                        ->where('challenge_id', $challenge->id)
                        ->first();

                    if (! $prog) {
                        $prog = $this->progress->newInstance([
                            'user_id'      => $userId,
                            'challenge_id' => $challenge->id,
                            'current_count' => $increment,
                        ]);
                        $prog->save();
                        $newCount = $increment;
                    } else {
                        if ($prog->completed_at) {
                            return; // Already completed
                        }
                        $newCount = $prog->current_count + $increment;
                        $prog->current_count = $newCount;
                        $prog->save();
                    }

                    // Check if just completed
                    if ($newCount >= $challenge->target_count && ! $prog->completed_at) {
                        $prog->completed_at = now();
                        $prog->reward_claimed = true;
                        $prog->save();

                        $completed[] = $challenge->toArray();
                    }
                });

                // Award rewards outside transaction for completed challenges
                if (! empty($completed) && $completed[array_key_last($completed)]['id'] === $challenge->id) {
                    $this->awardChallengeReward($userId, $challenge);
                }
            } catch (\Throwable $e) {
                Log::error('ChallengeService::updateProgress error: ' . $e->getMessage());
            }
        }

        return $completed;
    }

    /**
     * Get a challenge by ID (legacy compatibility alias).
     */
    public function getLegacyById(int $id): ?array
    {
        $challenge = $this->challenge->newQuery()->find($id);
        return $challenge ? $challenge->toArray() : null;
    }

    /**
     * Award challenge completion rewards.
     */
    private function awardChallengeReward(int $userId, Challenge $challenge): void
    {
        if ($challenge->xp_reward > 0) {
            $this->gamificationService->awardXP(
                $userId,
                $challenge->xp_reward,
                'challenge_complete',
                "Challenge: {$challenge->title}"
            );
        }

        if (! empty($challenge->badge_reward)) {
            $this->gamificationService->awardBadgeByKey($userId, $challenge->badge_reward);
        }

        Notification::create([
            'user_id' => $userId,
            'type'    => 'achievement',
            'message' => "Challenge Complete! You finished '{$challenge->title}' and earned {$challenge->xp_reward} XP!",
            'link'    => '/achievements',
        ]);
    }
}
