<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiErrorCodes;

/**
 * PollRankingService - Ranked-choice voting for polls
 *
 * Implements ranked-choice (instant-runoff) voting where voters rank
 * options by preference. Results are calculated using the IRV algorithm.
 *
 * @package Nexus\Services
 */
class PollRankingService
{
    /** @var array Collected errors */
    private static array $errors = [];

    public static function getErrors(): array
    {
        return self::$errors;
    }

    private static function clearErrors(): void
    {
        self::$errors = [];
    }

    private static function addError(string $code, string $message, ?string $field = null): void
    {
        $error = ['code' => $code, 'message' => $message];
        if ($field !== null) {
            $error['field'] = $field;
        }
        self::$errors[] = $error;
    }

    /**
     * Submit ranked votes for a poll
     *
     * @param int $pollId
     * @param int $userId
     * @param array $rankings Array of {option_id: int, rank: int}
     * @return bool
     */
    public static function submitRanking(int $pollId, int $userId, array $rankings): bool
    {
        self::clearErrors();

        $tenantId = TenantContext::getId();

        // Verify poll exists and is ranked type
        $poll = Database::query(
            "SELECT id, poll_type, expires_at FROM polls WHERE id = ? AND tenant_id = ?",
            [$pollId, $tenantId]
        )->fetch();

        if (!$poll) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Poll not found');
            return false;
        }

        if ($poll['poll_type'] !== 'ranked') {
            self::addError(ApiErrorCodes::VALIDATION_INVALID_VALUE, 'This poll does not support ranked-choice voting');
            return false;
        }

        // Check if poll is still open
        if ($poll['expires_at'] && strtotime($poll['expires_at']) <= time()) {
            self::addError(ApiErrorCodes::RESOURCE_CONFLICT, 'This poll is closed');
            return false;
        }

        // Check if user already ranked
        $existingRanking = Database::query(
            "SELECT id FROM poll_rankings WHERE poll_id = ? AND user_id = ?",
            [$pollId, $userId]
        )->fetch();

        if ($existingRanking) {
            self::addError(ApiErrorCodes::RESOURCE_CONFLICT, 'You have already submitted rankings for this poll');
            return false;
        }

        // Validate rankings
        if (empty($rankings) || !is_array($rankings)) {
            self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Rankings are required', 'rankings');
            return false;
        }

        // Get valid option IDs for this poll
        $options = Database::query(
            "SELECT id FROM poll_options WHERE poll_id = ?",
            [$pollId]
        )->fetchAll();

        $validOptionIds = array_map(function ($o) {
            return (int)$o['id'];
        }, $options);

        // Validate each ranking
        $seenRanks = [];
        $seenOptions = [];

        foreach ($rankings as $i => $ranking) {
            $optionId = (int)($ranking['option_id'] ?? 0);
            $rank = (int)($ranking['rank'] ?? 0);

            if (!in_array($optionId, $validOptionIds, true)) {
                self::addError(ApiErrorCodes::VALIDATION_INVALID_VALUE, "Invalid option ID: {$optionId}", "rankings.{$i}.option_id");
                return false;
            }

            if ($rank < 1 || $rank > count($validOptionIds)) {
                self::addError(ApiErrorCodes::VALIDATION_OUT_OF_RANGE, "Rank must be between 1 and " . count($validOptionIds), "rankings.{$i}.rank");
                return false;
            }

            if (in_array($rank, $seenRanks, true)) {
                self::addError(ApiErrorCodes::VALIDATION_DUPLICATE, "Duplicate rank: {$rank}", "rankings.{$i}.rank");
                return false;
            }

            if (in_array($optionId, $seenOptions, true)) {
                self::addError(ApiErrorCodes::VALIDATION_DUPLICATE, "Duplicate option: {$optionId}", "rankings.{$i}.option_id");
                return false;
            }

            $seenRanks[] = $rank;
            $seenOptions[] = $optionId;
        }

        try {
            Database::beginTransaction();

            foreach ($rankings as $ranking) {
                Database::query(
                    "INSERT INTO poll_rankings (poll_id, user_id, option_id, `rank`, tenant_id, created_at)
                     VALUES (?, ?, ?, ?, ?, NOW())",
                    [$pollId, $userId, (int)$ranking['option_id'], (int)$ranking['rank'], $tenantId]
                );
            }

            // Also record a standard vote for the first-choice option (for compatibility)
            $firstChoice = null;
            foreach ($rankings as $ranking) {
                if ((int)$ranking['rank'] === 1) {
                    $firstChoice = (int)$ranking['option_id'];
                    break;
                }
            }

            if ($firstChoice) {
                Database::query(
                    "INSERT INTO poll_votes (poll_id, option_id, user_id) VALUES (?, ?, ?)",
                    [$pollId, $firstChoice, $userId]
                );
                Database::query(
                    "UPDATE poll_options SET votes = votes + 1 WHERE id = ?",
                    [$firstChoice]
                );
            }

            Database::commit();

            // Award gamification points
            try {
                if (class_exists('\Nexus\Models\Gamification')) {
                    \Nexus\Models\Gamification::awardPoints($userId, 3, 'Ranked-choice vote in poll');
                }
            } catch (\Throwable $e) {
                // Gamification is optional
            }

            return true;
        } catch (\Throwable $e) {
            Database::rollback();
            error_log("Poll ranking submission failed: " . $e->getMessage());
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to submit rankings');
            return false;
        }
    }

    /**
     * Calculate ranked-choice (instant-runoff) results for a poll
     *
     * Algorithm:
     * 1. Count first-choice votes for each option
     * 2. If an option has >50% of votes, it wins
     * 3. Otherwise, eliminate the option with fewest first-choice votes
     * 4. Redistribute eliminated option's voters to their next preference
     * 5. Repeat until a winner is found
     *
     * @param int $pollId
     * @return array Results with rounds, winner, and final standings
     */
    public static function calculateResults(int $pollId): array
    {
        $tenantId = TenantContext::getId();

        // Get all rankings grouped by user
        $allRankings = Database::query(
            "SELECT user_id, option_id, `rank`
             FROM poll_rankings
             WHERE poll_id = ? AND tenant_id = ?
             ORDER BY user_id, `rank` ASC",
            [$pollId, $tenantId]
        )->fetchAll();

        // Get option labels
        $options = Database::query(
            "SELECT id, label FROM poll_options WHERE poll_id = ?",
            [$pollId]
        )->fetchAll();

        $optionMap = [];
        foreach ($options as $opt) {
            $optionMap[(int)$opt['id']] = $opt['label'];
        }

        // Group ballots by user (ordered by rank)
        $ballots = [];
        foreach ($allRankings as $r) {
            $ballots[(int)$r['user_id']][] = (int)$r['option_id'];
        }

        if (empty($ballots)) {
            return [
                'total_voters' => 0,
                'rounds' => [],
                'winner' => null,
                'final_standings' => [],
            ];
        }

        $totalVoters = count($ballots);
        $eliminated = [];
        $rounds = [];

        // Run IRV rounds
        for ($round = 1; $round <= count($optionMap); $round++) {
            // Count first-choice votes (skipping eliminated)
            $counts = [];
            foreach ($optionMap as $id => $label) {
                if (!in_array($id, $eliminated, true)) {
                    $counts[$id] = 0;
                }
            }

            foreach ($ballots as $ballot) {
                foreach ($ballot as $optionId) {
                    if (!in_array($optionId, $eliminated, true)) {
                        $counts[$optionId]++;
                        break; // Count only the highest remaining preference
                    }
                }
            }

            // Build round data
            $roundData = [
                'round' => $round,
                'counts' => [],
            ];

            foreach ($counts as $id => $count) {
                $roundData['counts'][] = [
                    'option_id' => $id,
                    'label' => $optionMap[$id] ?? 'Unknown',
                    'votes' => $count,
                    'percentage' => $totalVoters > 0 ? round(($count / $totalVoters) * 100, 1) : 0,
                ];
            }

            // Sort by votes descending
            usort($roundData['counts'], function ($a, $b) {
                return $b['votes'] - $a['votes'];
            });

            $rounds[] = $roundData;

            // Check for a winner (>50%)
            $majority = ceil($totalVoters / 2);
            foreach ($counts as $id => $count) {
                if ($count > $totalVoters / 2) {
                    return [
                        'total_voters' => $totalVoters,
                        'rounds' => $rounds,
                        'winner' => [
                            'option_id' => $id,
                            'label' => $optionMap[$id] ?? 'Unknown',
                            'votes' => $count,
                            'percentage' => round(($count / $totalVoters) * 100, 1),
                        ],
                        'final_standings' => $roundData['counts'],
                    ];
                }
            }

            // No winner — eliminate the option with fewest votes
            if (!empty($counts)) {
                $minVotes = min($counts);
                foreach ($counts as $id => $count) {
                    if ($count === $minVotes) {
                        $eliminated[] = $id;
                        $roundData['eliminated'] = [
                            'option_id' => $id,
                            'label' => $optionMap[$id] ?? 'Unknown',
                        ];
                        break;
                    }
                }
            }

            // If only one option remains, it wins
            $remaining = array_diff(array_keys($optionMap), $eliminated);
            if (count($remaining) <= 1) {
                $winnerId = reset($remaining);
                return [
                    'total_voters' => $totalVoters,
                    'rounds' => $rounds,
                    'winner' => $winnerId ? [
                        'option_id' => $winnerId,
                        'label' => $optionMap[$winnerId] ?? 'Unknown',
                        'votes' => $counts[$winnerId] ?? 0,
                        'percentage' => $totalVoters > 0 ? round((($counts[$winnerId] ?? 0) / $totalVoters) * 100, 1) : 0,
                    ] : null,
                    'final_standings' => $roundData['counts'],
                ];
            }
        }

        // Should not reach here, but return what we have
        $lastRound = end($rounds);
        return [
            'total_voters' => $totalVoters,
            'rounds' => $rounds,
            'winner' => null,
            'final_standings' => $lastRound['counts'] ?? [],
        ];
    }

    /**
     * Get a user's rankings for a poll
     *
     * @param int $pollId
     * @param int $userId
     * @return array|null
     */
    public static function getUserRankings(int $pollId, int $userId): ?array
    {
        $rankings = Database::query(
            "SELECT option_id, `rank` FROM poll_rankings
             WHERE poll_id = ? AND user_id = ?
             ORDER BY `rank` ASC",
            [$pollId, $userId]
        )->fetchAll();

        if (empty($rankings)) {
            return null;
        }

        return array_map(function ($r) {
            return [
                'option_id' => (int)$r['option_id'],
                'rank' => (int)$r['rank'],
            ];
        }, $rankings);
    }
}
