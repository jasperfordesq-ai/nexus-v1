<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * PollExportService — Eloquent-based service for poll export.
 *
 * Replaces the legacy DI wrapper that delegated to
 */
class PollExportService
{
    /**
     * Export poll results as CSV string.
     *
     * @return string|null CSV content, or null if poll not found / not authorized
     */
    public function exportToCsv(int $pollId, int $userId): ?string
    {
        $poll = DB::table('polls')->where('tenant_id', TenantContext::getId())->where('id', $pollId)->first();

        if (! $poll) {
            return null;
        }

        // Only creator or admin can export
        if ((int) $poll->user_id !== $userId) {
            return null;
        }

        $options = DB::table('poll_options')
            ->where('poll_id', $pollId)
            ->get();

        $lines = [];
        $lines[] = implode(',', ['Option', 'Votes']);

        foreach ($options as $option) {
            $votes = DB::table('poll_votes')
                ->where('option_id', $option->id)
                ->count();
            // CSV injection prevention: prefix formula-trigger characters
            $text = $option->option_text;
            if (is_string($text) && preg_match('/^[=+\-@\t\r]/', $text)) {
                $text = "'" . $text;
            }
            $lines[] = implode(',', [
                '"' . str_replace('"', '""', $text) . '"',
                $votes,
            ]);
        }

        return implode("\n", $lines);
    }
}
