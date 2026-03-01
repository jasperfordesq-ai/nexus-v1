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
 * PollExportService - Export poll results to CSV
 *
 * Generates CSV exports containing poll question, options, vote counts,
 * percentages, and optionally voter names (unless anonymous).
 *
 * @package Nexus\Services
 */
class PollExportService
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
     * Export poll results to CSV format
     *
     * @param int $pollId
     * @param int $userId Requesting user (must be poll creator or admin)
     * @return string|null CSV content on success, null on failure
     */
    public static function exportToCsv(int $pollId, int $userId): ?string
    {
        self::clearErrors();

        $tenantId = TenantContext::getId();

        // Get poll data
        $poll = Database::query(
            "SELECT p.*, u.first_name as creator_first_name, u.last_name as creator_last_name
             FROM polls p
             LEFT JOIN users u ON p.user_id = u.id
             WHERE p.id = ? AND p.tenant_id = ?",
            [$pollId, $tenantId]
        )->fetch();

        if (!$poll) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Poll not found');
            return null;
        }

        // Only poll creator or admin can export
        if ((int)$poll['user_id'] !== $userId) {
            $user = Database::query("SELECT role FROM users WHERE id = ?", [$userId])->fetch();
            if (!$user || !in_array($user['role'], ['admin', 'super_admin', 'tenant_admin'])) {
                self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'Only the poll creator or admin can export results');
                return null;
            }
        }

        $isAnonymous = !empty($poll['is_anonymous']);

        // Get options with vote counts
        $options = Database::query(
            "SELECT id, label, votes FROM poll_options WHERE poll_id = ? ORDER BY id ASC",
            [$pollId]
        )->fetchAll();

        // Get total votes
        $totalVotes = 0;
        foreach ($options as $opt) {
            $totalVotes += (int)$opt['votes'];
        }

        // Build CSV
        $output = fopen('php://temp', 'r+');

        // Header: Poll info
        fputcsv($output, ['Poll Export']);
        fputcsv($output, ['Question', $poll['question']]);
        fputcsv($output, ['Description', $poll['description'] ?? '']);
        fputcsv($output, ['Created By', trim(($poll['creator_first_name'] ?? '') . ' ' . ($poll['creator_last_name'] ?? ''))]);
        fputcsv($output, ['Created At', $poll['created_at']]);
        fputcsv($output, ['Expires At', $poll['expires_at'] ?? $poll['end_date'] ?? 'Never']);
        fputcsv($output, ['Total Votes', $totalVotes]);
        fputcsv($output, ['Anonymous', $isAnonymous ? 'Yes' : 'No']);
        fputcsv($output, ['Poll Type', $poll['poll_type'] ?? 'standard']);
        fputcsv($output, []); // Blank line

        // Section: Results summary
        fputcsv($output, ['--- RESULTS SUMMARY ---']);
        fputcsv($output, ['Option', 'Votes', 'Percentage']);

        foreach ($options as $opt) {
            $percentage = $totalVotes > 0 ? round(((int)$opt['votes'] / $totalVotes) * 100, 1) : 0;
            fputcsv($output, [$opt['label'], $opt['votes'], $percentage . '%']);
        }

        fputcsv($output, []); // Blank line

        // Section: Individual votes
        fputcsv($output, ['--- INDIVIDUAL VOTES ---']);

        if ($isAnonymous) {
            fputcsv($output, ['Vote #', 'Option', 'Voted At']);

            $votes = Database::query(
                "SELECT v.option_id, o.label as option_label, v.created_at
                 FROM poll_votes v
                 LEFT JOIN poll_options o ON v.option_id = o.id
                 WHERE v.poll_id = ?
                 ORDER BY v.created_at ASC",
                [$pollId]
            )->fetchAll();

            $voteNum = 1;
            foreach ($votes as $vote) {
                fputcsv($output, [$voteNum++, $vote['option_label'] ?? 'Unknown', $vote['created_at'] ?? '']);
            }
        } else {
            fputcsv($output, ['Voter Name', 'Option', 'Voted At']);

            $votes = Database::query(
                "SELECT v.user_id, v.option_id, v.created_at,
                        u.first_name, u.last_name, o.label as option_label
                 FROM poll_votes v
                 LEFT JOIN users u ON v.user_id = u.id
                 LEFT JOIN poll_options o ON v.option_id = o.id
                 WHERE v.poll_id = ?
                 ORDER BY v.created_at ASC",
                [$pollId]
            )->fetchAll();

            foreach ($votes as $vote) {
                $voterName = trim(($vote['first_name'] ?? '') . ' ' . ($vote['last_name'] ?? ''));
                fputcsv($output, [$voterName ?: 'Unknown', $vote['option_label'] ?? 'Unknown', $vote['created_at'] ?? '']);
            }
        }

        // If ranked poll, add ranking data
        if (($poll['poll_type'] ?? 'standard') === 'ranked') {
            fputcsv($output, []); // Blank line
            fputcsv($output, ['--- RANKED-CHOICE RANKINGS ---']);

            if ($isAnonymous) {
                fputcsv($output, ['Voter #', 'Rank', 'Option']);
            } else {
                fputcsv($output, ['Voter Name', 'Rank', 'Option']);
            }

            $rankings = Database::query(
                "SELECT r.user_id, r.option_id, r.rank,
                        u.first_name, u.last_name, o.label as option_label
                 FROM poll_rankings r
                 LEFT JOIN users u ON r.user_id = u.id
                 LEFT JOIN poll_options o ON r.option_id = o.id
                 WHERE r.poll_id = ? AND r.tenant_id = ?
                 ORDER BY r.user_id, r.rank ASC",
                [$pollId, $tenantId]
            )->fetchAll();

            $voterNum = 0;
            $lastUserId = null;
            foreach ($rankings as $r) {
                if ($r['user_id'] !== $lastUserId) {
                    $voterNum++;
                    $lastUserId = $r['user_id'];
                }

                if ($isAnonymous) {
                    fputcsv($output, [$voterNum, $r['rank'], $r['option_label'] ?? 'Unknown']);
                } else {
                    $voterName = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
                    fputcsv($output, [$voterName ?: 'Unknown', $r['rank'], $r['option_label'] ?? 'Unknown']);
                }
            }
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
