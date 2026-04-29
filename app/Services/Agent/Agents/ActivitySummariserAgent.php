<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\Agent\Agents;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AG61 — Weekly activity summariser.
 *
 * Aggregates the last 7 days of community activity (top contributors,
 * trending categories, anomalies), asks the LLM to write a friendly
 * narrative summary, and creates one proposal per admin/coordinator.
 */
final class ActivitySummariserAgent extends BaseAgent
{
    public function run(): array
    {
        if (!Schema::hasTable('users')) {
            return $this->emptyResult('users table missing');
        }

        $stats = $this->collectStats();

        if (empty($stats['top_contributors']) && $stats['total_sessions'] === 0) {
            return $this->emptyResult('no activity in the last 7 days');
        }

        // Find admins/coordinators to summarise to
        $coordinators = DB::table('users')
            ->where('tenant_id', $this->tenantId)
            ->where('status', 'active')
            ->whereIn('role', ['admin', 'coordinator', 'broker'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($coordinators)) {
            return $this->emptyResult('no admin recipients');
        }

        $narrative = $this->generateNarrative($stats);

        $created = 0;
        foreach ($coordinators as $coordId) {
            $this->createProposal(
                type: 'send_activity_summary',
                data: [
                    'title'           => 'Weekly Community Activity Summary',
                    'body'            => $narrative['body'],
                    'period_start'    => $stats['period_start'],
                    'period_end'      => $stats['period_end'],
                    'total_sessions'  => $stats['total_sessions'],
                    'total_hours'     => $stats['total_hours'],
                    'volunteer_count' => $stats['volunteer_count'],
                    'extra'           => ['type' => 'activity_summary'],
                ],
                reasoning: $narrative['reasoning'],
                confidence: 0.95,
                subjectUserId: $coordId,
            );
            $created++;
        }

        return [
            'proposals_created' => $created,
            'summary'           => "Drafted weekly summary for {$created} admin/coordinator(s).",
            'llm_input_tokens'  => $this->totalInputTokens,
            'llm_output_tokens' => $this->totalOutputTokens,
            'cost_cents'        => $this->estimateCostCents($this->totalInputTokens, $this->totalOutputTokens),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function collectStats(): array
    {
        $start = now()->subDays(7)->toDateString();
        $end   = now()->toDateString();

        $top = [];
        $totalSessions = 0;
        $totalHours = 0.0;
        $volunteerCount = 0;

        if (Schema::hasTable('vol_logs')) {
            $rows = DB::select(
                "SELECT user_id, COUNT(*) AS sessions, COALESCE(SUM(hours), 0) AS total_hours
                 FROM vol_logs
                 WHERE tenant_id = ? AND status = 'approved' AND date_logged >= ?
                 GROUP BY user_id
                 ORDER BY total_hours DESC
                 LIMIT 10",
                [$this->tenantId, $start],
            );
            foreach ($rows as $r) {
                $top[] = [
                    'user_id'  => (int) $r->user_id,
                    'sessions' => (int) $r->sessions,
                    'hours'    => (float) $r->total_hours,
                ];
                $totalSessions += (int) $r->sessions;
                $totalHours    += (float) $r->total_hours;
            }
            $volunteerCount = count($rows);
        }

        return [
            'period_start'      => $start,
            'period_end'        => $end,
            'top_contributors'  => $top,
            'total_sessions'    => $totalSessions,
            'total_hours'       => $totalHours,
            'volunteer_count'   => $volunteerCount,
        ];
    }

    /**
     * @param array<string,mixed> $stats
     * @return array{body:string, reasoning:string}
     */
    private function generateNarrative(array $stats): array
    {
        $fallbackBody = sprintf(
            'In the last 7 days, %d volunteers logged %d sessions totalling %.1f hours.',
            $stats['volunteer_count'],
            $stats['total_sessions'],
            $stats['total_hours'],
        );

        $messages = [
            [
                'role'    => 'system',
                'content' => 'You write short, encouraging weekly community activity summaries for timebank coordinators. 3 sentences max. No emojis, no markdown.',
            ],
            [
                'role'    => 'user',
                'content' => 'Summarise these stats: ' . json_encode($stats, JSON_UNESCAPED_UNICODE),
            ],
        ];

        $resp = $this->callLlm($messages, ['max_tokens' => 250]);
        $body = trim($resp['content']);

        return [
            'body'      => $body !== '' ? $body : $fallbackBody,
            'reasoning' => sprintf(
                'Weekly digest for %s..%s — %d sessions, %.1f hours, %d volunteers.',
                $stats['period_start'],
                $stats['period_end'],
                $stats['total_sessions'],
                $stats['total_hours'],
                $stats['volunteer_count'],
            ),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function emptyResult(string $reason): array
    {
        return [
            'proposals_created' => 0,
            'summary'           => "No proposals: {$reason}",
            'llm_input_tokens'  => 0,
            'llm_output_tokens' => 0,
            'cost_cents'        => 0,
        ];
    }
}
