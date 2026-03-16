<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * EndorsementService — Laravel DI-based service for skill endorsements.
 *
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\EndorsementService.
 * Manages LinkedIn-style skill endorsements between members.
 */
class EndorsementService
{
    /**
     * Endorse a member's skill.
     *
     * @return int|null Endorsement ID or null on failure.
     */
    public function endorse(int $endorserId, int $endorsedId, string $skillName, ?string $comment = null): ?int
    {
        if ($endorserId === $endorsedId) {
            return null;
        }

        $skillName = trim($skillName);
        if (empty($skillName) || strlen($skillName) > 100) {
            return null;
        }

        $exists = DB::table('skill_endorsements')
            ->where('endorser_id', $endorserId)
            ->where('endorsed_id', $endorsedId)
            ->where('skill_name', $skillName)
            ->exists();

        if ($exists) {
            return null;
        }

        return DB::table('skill_endorsements')->insertGetId([
            'endorser_id' => $endorserId,
            'endorsed_id' => $endorsedId,
            'skill_name'  => $skillName,
            'comment'     => $comment ? substr(trim($comment), 0, 500) : null,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    /**
     * Remove an endorsement.
     */
    public function removeEndorsement(int $endorserId, int $endorsedId, string $skillName): bool
    {
        return DB::table('skill_endorsements')
            ->where('endorser_id', $endorserId)
            ->where('endorsed_id', $endorsedId)
            ->where('skill_name', $skillName)
            ->delete() > 0;
    }

    /**
     * Get all endorsements for a user, grouped by skill.
     */
    public function getEndorsements(int $userId): array
    {
        $rows = DB::table('skill_endorsements as se')
            ->leftJoin('users as u', 'se.endorser_id', '=', 'u.id')
            ->where('se.endorsed_id', $userId)
            ->select('se.*', 'u.first_name', 'u.last_name', 'u.avatar_url')
            ->orderBy('se.skill_name')
            ->orderByDesc('se.created_at')
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $skill = $row->skill_name;
            if (! isset($grouped[$skill])) {
                $grouped[$skill] = ['skill_name' => $skill, 'count' => 0, 'endorsers' => []];
            }
            $grouped[$skill]['count']++;
            $grouped[$skill]['endorsers'][] = [
                'id'         => $row->endorser_id,
                'name'       => trim($row->first_name . ' ' . $row->last_name),
                'avatar_url' => $row->avatar_url,
                'comment'    => $row->comment,
            ];
        }

        return array_values($grouped);
    }

    /**
     * Check if a user has endorsed another's specific skill.
     */
    public function hasEndorsed(int $endorserId, int $endorsedId, string $skillName): bool
    {
        return DB::table('skill_endorsements')
            ->where('endorser_id', $endorserId)
            ->where('endorsed_id', $endorsedId)
            ->where('skill_name', $skillName)
            ->exists();
    }
}
