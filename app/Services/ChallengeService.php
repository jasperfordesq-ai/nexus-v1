<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * ChallengeService — Laravel DI-based service for gamification challenges.
 *
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\ChallengeService.
 * Manages challenge creation, listing, and member claim/completion workflows.
 */
class ChallengeService
{
    /**
     * Get all challenges for a tenant.
     */
    public function getAll(int $tenantId, array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $offset = max(0, (int) ($filters['offset'] ?? 0));

        $query = DB::table('challenges')->where('tenant_id', $tenantId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        $total = $query->count();
        $items = $query->orderByDesc('created_at')
            ->offset($offset)->limit($limit)
            ->get()->map(fn ($r) => (array) $r)->all();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Get a single challenge by ID.
     */
    public function getById(int $id, int $tenantId): ?array
    {
        $row = DB::table('challenges')->where('id', $id)->where('tenant_id', $tenantId)->first();
        return $row ? (array) $row : null;
    }

    /**
     * Create a new challenge.
     */
    public function create(int $tenantId, array $data): ?int
    {
        return DB::table('challenges')->insertGetId([
            'tenant_id'   => $tenantId,
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'category'    => $data['category'] ?? 'general',
            'xp_reward'   => max(0, (int) ($data['xp_reward'] ?? 10)),
            'status'      => 'active',
            'starts_at'   => $data['starts_at'] ?? now(),
            'ends_at'     => $data['ends_at'] ?? null,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    /**
     * Claim (complete) a challenge for a user.
     */
    public function claim(int $challengeId, int $userId, int $tenantId): bool
    {
        $challenge = DB::table('challenges')
            ->where('id', $challengeId)
            ->where('tenant_id', $tenantId)
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
}
