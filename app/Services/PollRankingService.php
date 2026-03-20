<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * PollRankingService — Eloquent-based service for ranked-choice voting.
 *
 * Replaces the legacy DI wrapper that delegated to
 */
class PollRankingService
{
    /**
     * Submit ranked-choice votes for a poll.
     */
    public function submitRanking(int $pollId, int $userId, array $rankings): bool
    {
        $exists = DB::table('poll_rankings')
            ->where('poll_id', $pollId)
            ->where('user_id', $userId)
            ->exists();

        if ($exists) {
            return false;
        }

        DB::transaction(function () use ($pollId, $userId, $rankings) {
            foreach ($rankings as $ranking) {
                DB::table('poll_rankings')->insert([
                    'poll_id'    => $pollId,
                    'user_id'    => $userId,
                    'option_id'  => (int) $ranking['option_id'],
                    'rank'       => (int) $ranking['rank'],
                    'created_at' => now(),
                ]);
            }
        });

        return true;
    }

    /**
     * Calculate IRV results for a ranked poll.
     */
    public function calculateResults(int $pollId): array
    {
        $rankings = DB::table('poll_rankings')
            ->where('poll_id', $pollId)
            ->orderBy('user_id')
            ->orderBy('rank')
            ->get()
            ->groupBy('user_id');

        $options = DB::table('poll_options')
            ->where('poll_id', $pollId)
            ->pluck('option_text', 'id')
            ->all();

        // Simple first-choice tally (simplified IRV)
        $tally = [];
        foreach ($options as $optId => $text) {
            $tally[$optId] = ['option_id' => $optId, 'text' => $text, 'votes' => 0];
        }

        foreach ($rankings as $userRankings) {
            $first = $userRankings->sortBy('rank')->first();
            if ($first && isset($tally[$first->option_id])) {
                $tally[$first->option_id]['votes']++;
            }
        }

        usort($tally, fn ($a, $b) => $b['votes'] <=> $a['votes']);

        return [
            'total_voters' => $rankings->count(),
            'results'      => array_values($tally),
        ];
    }

    /**
     * Get a user's rankings for a poll.
     */
    public function getUserRankings(int $pollId, int $userId): ?array
    {
        $rankings = DB::table('poll_rankings')
            ->where('poll_id', $pollId)
            ->where('user_id', $userId)
            ->orderBy('rank')
            ->get()
            ->all();

        return empty($rankings) ? null : array_map(fn ($r) => (array) $r, $rankings);
    }
}
