<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\CaringCommunity;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KpiBaselineService
{
    private const COMPARISON_KEYS = [
        'information_distribution_effort_hours',
        'volunteer_hours',
        'member_count',
        'recipient_count',
        'active_relationships',
        'total_exchanges',
        'avg_response_hours',
        'engagement_rate_pct',
        'satisfaction_score',
    ];

    public function isAvailable(): bool
    {
        return Schema::hasTable('caring_kpi_baselines');
    }

    /**
     * Gather live metrics for the given tenant.
     * All queries use tenant_id scoping. Missing tables return null/0 gracefully.
     */
    public function captureCurrentMetrics(int $tenantId): array
    {
        $rangeStart = now()->subDays(90)->toDateString();

        $volunteerHours = 0.0;
        if (Schema::hasTable('vol_logs')) {
            $volunteerHours = (float) DB::selectOne(
                "SELECT COALESCE(SUM(hours), 0) AS total
                 FROM vol_logs
                 WHERE tenant_id = ? AND status = 'approved' AND date_logged >= ?",
                [$tenantId, $rangeStart]
            )?->total ?? 0.0;
        }

        $memberCount = (int) DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->count();

        $participatingMembers = $this->countParticipatingMembers($tenantId, $rangeStart);
        $engagementRate = $memberCount > 0
            ? round(($participatingMembers / $memberCount) * 100.0, 1)
            : null;

        $recipientCount = null;
        $activeRelationships = null;
        if (Schema::hasTable('caring_support_relationships')) {
            $recipientCount = (int) DB::table('caring_support_relationships')
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->distinct('recipient_id')
                ->count('recipient_id');

            $activeRelationships = (int) DB::table('caring_support_relationships')
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->count();
        }

        $totalExchanges = null;
        if (Schema::hasTable('exchanges')) {
            $totalExchanges = (int) DB::table('exchanges')
                ->where('tenant_id', $tenantId)
                ->count();
        }

        return [
            'information_distribution_effort_hours' => null,
            'volunteer_hours' => $volunteerHours,
            'member_count' => $memberCount,
            'recipient_count' => $recipientCount,
            'active_relationships' => $activeRelationships,
            'total_exchanges' => $totalExchanges,
            'avg_response_hours' => null,
            'engagement_rate_pct' => $engagementRate,
            'satisfaction_score' => $this->averageSatisfactionScore($tenantId),
        ];
    }

    /**
     * Capture a baseline snapshot and persist it.
     *
     * @param array{start: string, end: string} $periodDates
     * @param array<string, mixed> $metricOverrides
     */
    public function captureBaseline(
        int $tenantId,
        string $label,
        array $periodDates,
        ?string $notes,
        int $adminUserId,
        array $metricOverrides = [],
    ): array {
        $metrics = $this->mergeMetricOverrides($this->captureCurrentMetrics($tenantId), $metricOverrides);
        $now = now();

        $id = DB::table('caring_kpi_baselines')->insertGetId([
            'tenant_id' => $tenantId,
            'label' => $label,
            'baseline_period' => json_encode($periodDates, JSON_UNESCAPED_UNICODE),
            'captured_at' => $now,
            'metrics' => json_encode($metrics, JSON_UNESCAPED_UNICODE),
            'notes' => $notes,
            'captured_by' => $adminUserId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->rowToArray(
            DB::table('caring_kpi_baselines')->where('id', $id)->first()
        );
    }

    /**
     * List all baselines for a tenant, newest first.
     */
    public function listBaselines(int $tenantId): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        $rows = DB::table('caring_kpi_baselines')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('captured_at')
            ->get();

        return $rows->map(fn ($row) => $this->rowToArray($row))->all();
    }

    /**
     * Compare a stored baseline with current live metrics.
     */
    public function compareWithBaseline(int $baselineId, int $tenantId): array
    {
        $row = DB::table('caring_kpi_baselines')
            ->where('id', $baselineId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$row) {
            return ['error' => 'baseline_not_found'];
        }

        $baseline = $this->rowToArray($row);
        $baselineMetrics = $baseline['metrics'];
        $currentMetrics = $this->captureCurrentMetrics($tenantId);

        $comparison = [];
        foreach (self::COMPARISON_KEYS as $key) {
            $bVal = isset($baselineMetrics[$key]) && $baselineMetrics[$key] !== null
                ? (float) $baselineMetrics[$key]
                : null;
            $cVal = isset($currentMetrics[$key]) && $currentMetrics[$key] !== null
                ? (float) $currentMetrics[$key]
                : null;

            $delta = ($bVal !== null && $cVal !== null) ? ($cVal - $bVal) : null;
            $pctChange = ($delta !== null && $bVal !== null && $bVal > 0.0)
                ? round(($delta / $bVal) * 100.0, 1)
                : null;

            $comparison[$key] = [
                'baseline' => $bVal,
                'current' => $cVal,
                'delta' => $delta !== null ? round($delta, 2) : null,
                'pct_change' => $pctChange,
            ];
        }

        return [
            'baseline' => $baseline,
            'current' => $currentMetrics,
            'comparison' => $comparison,
            'pilot_claim_targets' => $this->pilotClaimTargets($comparison),
        ];
    }

    private function rowToArray(object|null $row): array
    {
        if (!$row) {
            return [];
        }

        return [
            'id' => (int) $row->id,
            'tenant_id' => (int) $row->tenant_id,
            'label' => (string) $row->label,
            'baseline_period' => json_decode((string) $row->baseline_period, true),
            'captured_at' => (string) $row->captured_at,
            'metrics' => json_decode((string) $row->metrics, true),
            'notes' => $row->notes,
            'captured_by' => $row->captured_by ? (int) $row->captured_by : null,
            'created_at' => (string) $row->created_at,
            'updated_at' => (string) $row->updated_at,
        ];
    }

    private function mergeMetricOverrides(array $metrics, array $overrides): array
    {
        foreach (self::COMPARISON_KEYS as $key) {
            if (!array_key_exists($key, $overrides)) {
                continue;
            }

            $value = $overrides[$key];
            $metrics[$key] = is_numeric($value) ? (float) $value : null;
        }

        return $metrics;
    }

    private function countParticipatingMembers(int $tenantId, string $rangeStart): int
    {
        $ids = [];

        if (Schema::hasTable('vol_logs')) {
            foreach (DB::select(
                "SELECT DISTINCT user_id
                 FROM vol_logs
                 WHERE tenant_id = ? AND status = 'approved' AND date_logged >= ?",
                [$tenantId, $rangeStart]
            ) as $row) {
                if ($row->user_id) {
                    $ids[(int) $row->user_id] = true;
                }
            }
        }

        if (Schema::hasTable('transactions')) {
            foreach (DB::select(
                "SELECT DISTINCT sender_id, receiver_id
                 FROM transactions
                 WHERE tenant_id = ? AND status = 'completed' AND created_at >= ?",
                [$tenantId, $rangeStart]
            ) as $row) {
                if ($row->sender_id) {
                    $ids[(int) $row->sender_id] = true;
                }
                if ($row->receiver_id) {
                    $ids[(int) $row->receiver_id] = true;
                }
            }
        }

        return count($ids);
    }

    private function averageSatisfactionScore(int $tenantId): ?float
    {
        if (!Schema::hasTable('municipality_surveys')
            || !Schema::hasTable('municipality_survey_questions')
            || !Schema::hasTable('municipality_survey_responses')) {
            return null;
        }

        $questionIds = DB::table('municipality_survey_questions as q')
            ->join('municipality_surveys as s', 's.id', '=', 'q.survey_id')
            ->where('q.tenant_id', $tenantId)
            ->where('s.tenant_id', $tenantId)
            ->where('q.question_type', 'likert')
            ->where(function ($query) {
                $query->where('q.question_text', 'like', '%satisfaction%')
                    ->orWhere('q.question_text', 'like', '%satisfied%')
                    ->orWhere('q.question_text', 'like', '%zufrieden%')
                    ->orWhere('q.question_text', 'like', '%zufriedenheit%');
            })
            ->pluck('q.id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if ($questionIds === []) {
            return null;
        }

        $scores = [];
        $responses = DB::table('municipality_survey_responses')
            ->where('tenant_id', $tenantId)
            ->select('answers')
            ->get();

        foreach ($responses as $response) {
            $answers = json_decode((string) $response->answers, true);
            if (!is_array($answers)) {
                continue;
            }

            foreach ($questionIds as $questionId) {
                if (!array_key_exists($questionId, $answers)) {
                    continue;
                }
                $score = $this->normaliseLikertScore($answers[$questionId]);
                if ($score !== null) {
                    $scores[] = $score;
                }
            }
        }

        return $scores === [] ? null : round(array_sum($scores) / count($scores), 2);
    }

    private function normaliseLikertScore(mixed $value): ?float
    {
        if (is_numeric($value)) {
            $score = (float) $value;
            return $score >= 1.0 && $score <= 5.0 ? $score : null;
        }

        $normalised = mb_strtolower(trim((string) $value));
        return match ($normalised) {
            '1', 'very dissatisfied', 'sehr unzufrieden' => 1.0,
            '2', 'dissatisfied', 'eher unzufrieden' => 2.0,
            '3', 'neutral' => 3.0,
            '4', 'satisfied', 'eher zufrieden' => 4.0,
            '5', 'very satisfied', 'sehr zufrieden' => 5.0,
            default => null,
        };
    }

    private function pilotClaimTargets(array $comparison): array
    {
        return [
            [
                'key' => 'information_distribution_effort',
                'metric_key' => 'information_distribution_effort_hours',
                'target_pct_change' => -30.0,
                'direction' => 'decrease',
                'baseline' => $comparison['information_distribution_effort_hours']['baseline'] ?? null,
                'current' => $comparison['information_distribution_effort_hours']['current'] ?? null,
                'pct_change' => $comparison['information_distribution_effort_hours']['pct_change'] ?? null,
                'achieved' => $this->targetAchieved($comparison['information_distribution_effort_hours'] ?? [], -30.0, 'decrease'),
            ],
            [
                'key' => 'volunteer_engagement',
                'metric_key' => 'engagement_rate_pct',
                'target_pct_change' => 25.0,
                'direction' => 'increase',
                'baseline' => $comparison['engagement_rate_pct']['baseline'] ?? null,
                'current' => $comparison['engagement_rate_pct']['current'] ?? null,
                'pct_change' => $comparison['engagement_rate_pct']['pct_change'] ?? null,
                'achieved' => $this->targetAchieved($comparison['engagement_rate_pct'] ?? [], 25.0, 'increase'),
            ],
            [
                'key' => 'satisfaction',
                'metric_key' => 'satisfaction_score',
                'target_delta' => 0.01,
                'direction' => 'increase',
                'baseline' => $comparison['satisfaction_score']['baseline'] ?? null,
                'current' => $comparison['satisfaction_score']['current'] ?? null,
                'delta' => $comparison['satisfaction_score']['delta'] ?? null,
                'achieved' => $this->targetAchieved($comparison['satisfaction_score'] ?? [], 0.01, 'increase_delta'),
            ],
        ];
    }

    private function targetAchieved(array $metric, float $target, string $direction): bool
    {
        if ($direction === 'increase_delta') {
            return isset($metric['delta']) && $metric['delta'] !== null && (float) $metric['delta'] >= $target;
        }

        if (!isset($metric['pct_change']) || $metric['pct_change'] === null) {
            return false;
        }

        $change = (float) $metric['pct_change'];
        return $direction === 'decrease' ? $change <= $target : $change >= $target;
    }
}
