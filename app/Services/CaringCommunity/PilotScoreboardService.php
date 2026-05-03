<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\CaringCommunity;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AG83 — Pilot Success Scoreboard.
 *
 * Aggregates the 10 pilot metrics defined in the Caring Community evaluation roadmap,
 * with explicit pre-pilot baseline support and quarterly review cadence.
 * Reads only existing tenant-scoped data — never touches another tenant.
 *
 * Metrics:
 *   1. active_members            — distinct users with ≥1 approved log/transaction in window
 *   2. first_response_hours      — median hours from help-request creation to first action
 *   3. approved_hours            — sum of approved volunteer / care hours in window
 *   4. recurring_relationships   — caring_support_relationships marked active
 *   5. coordinator_workload_hrs  — average pending help-requests per active coordinator
 *   6. satisfaction_score        — 1–5 likert mean from municipality surveys
 *   7. social_isolation_pct      — share of members with zero connection signals (90d)
 *   8. comms_reach_pct           — share of members reached by latest tenant announcement
 *   9. business_participation    — distinct businesses (vol orgs + merchants) active in window
 *  10. cost_offset_chf           — total approved hours × CHF 35 × 2 (Age-Stiftung methodology)
 */
class PilotScoreboardService
{
    public const PRE_PILOT_LABEL = 'pre_pilot';

    private const SWISS_HOURLY_RATE_CHF = 35;
    private const PREVENTION_MULTIPLIER = 2;
    private const ROLLING_WINDOW_DAYS   = 90;

    /**
     * Live snapshot of all 10 pilot metrics for the current period.
     */
    public function captureCurrentMetrics(int $tenantId): array
    {
        $windowStart = now()->subDays(self::ROLLING_WINDOW_DAYS)->toDateTimeString();

        $approvedHours      = $this->approvedHoursInWindow($tenantId, $windowStart);
        $activeMembers      = $this->activeMembersInWindow($tenantId, $windowStart);
        $firstResponseHrs   = $this->medianFirstResponseHours($tenantId, $windowStart);
        $recurringRels      = $this->recurringRelationships($tenantId);
        $coordinatorWorkld  = $this->coordinatorWorkload($tenantId);
        $satisfactionScore  = $this->satisfactionScore($tenantId);
        $isolationPct       = $this->socialIsolationProxyPct($tenantId, $windowStart);
        $commsReachPct      = $this->communicationsReachPct($tenantId, $windowStart);
        $businessCount      = $this->businessParticipation($tenantId, $windowStart);
        $costOffsetChf      = round($approvedHours * self::SWISS_HOURLY_RATE_CHF * self::PREVENTION_MULTIPLIER, 2);

        return [
            'active_members'             => $activeMembers,
            'first_response_hours'       => $firstResponseHrs,
            'approved_hours'             => $approvedHours,
            'recurring_relationships'    => $recurringRels,
            'coordinator_workload_hrs'   => $coordinatorWorkld,
            'satisfaction_score'         => $satisfactionScore,
            'social_isolation_pct'       => $isolationPct,
            'comms_reach_pct'            => $commsReachPct,
            'business_participation'     => $businessCount,
            'cost_offset_chf'            => $costOffsetChf,
            'methodology' => [
                'window_days'           => self::ROLLING_WINDOW_DAYS,
                'hourly_rate_chf'       => self::SWISS_HOURLY_RATE_CHF,
                'prevention_multiplier' => self::PREVENTION_MULTIPLIER,
            ],
        ];
    }

    /**
     * Persist the current metrics as a pre-pilot baseline with the canonical label.
     */
    public function capturePrePilotBaseline(int $tenantId, int $adminUserId, ?string $notes): array
    {
        return $this->captureBaseline(
            $tenantId,
            self::PRE_PILOT_LABEL,
            $adminUserId,
            $notes,
            isPrePilot: true,
        );
    }

    /**
     * Capture an arbitrary labelled baseline (used for quarterly review snapshots).
     */
    public function captureBaseline(
        int $tenantId,
        string $label,
        int $adminUserId,
        ?string $notes,
        bool $isPrePilot = false,
    ): array {
        if (!Schema::hasTable('caring_kpi_baselines')) {
            return ['error' => 'baselines_unavailable'];
        }

        $now     = now();
        $metrics = $this->captureCurrentMetrics($tenantId);

        $period = [
            'start' => $now->copy()->subDays(self::ROLLING_WINDOW_DAYS)->toDateString(),
            'end'   => $now->toDateString(),
        ];

        // Tag pilot scoreboard baselines so they appear distinct from AG66 KPI baselines.
        $envelope = [
            'kind'          => 'pilot_scoreboard',
            'is_pre_pilot'  => $isPrePilot,
            'metrics'       => $metrics,
        ];

        $id = DB::table('caring_kpi_baselines')->insertGetId([
            'tenant_id'        => $tenantId,
            'label'            => $label,
            'baseline_period'  => json_encode($period, JSON_UNESCAPED_UNICODE),
            'captured_at'      => $now,
            'metrics'          => json_encode($envelope, JSON_UNESCAPED_UNICODE),
            'notes'            => $notes,
            'captured_by'      => $adminUserId,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        return $this->rowToScoreboardBaseline(
            DB::table('caring_kpi_baselines')->where('id', $id)->first()
        );
    }

    /**
     * Compose the full scoreboard view: pre-pilot baseline (if any) + current + quarterly cadence.
     */
    public function scoreboard(int $tenantId): array
    {
        $current = $this->captureCurrentMetrics($tenantId);
        $prePilot = $this->latestBaseline($tenantId, self::PRE_PILOT_LABEL);
        $latestQuarterly = $this->latestQuarterlyReview($tenantId);

        $comparison = $prePilot ? $this->compareMetrics($prePilot['metrics'], $current) : null;
        $quarterlyDue = $this->quarterlyReviewDueAt($prePilot, $latestQuarterly);

        return [
            'current'              => $current,
            'pre_pilot_baseline'   => $prePilot,
            'latest_quarterly'     => $latestQuarterly,
            'comparison'           => $comparison,
            'quarterly_review'     => [
                'next_due_at'       => $quarterlyDue,
                'is_overdue'        => $quarterlyDue !== null && now()->greaterThan($quarterlyDue),
                'cadence_months'    => 3,
            ],
        ];
    }

    /**
     * List every pilot scoreboard baseline (excludes AG66 generic KPI baselines).
     */
    public function listBaselines(int $tenantId): array
    {
        if (!Schema::hasTable('caring_kpi_baselines')) {
            return [];
        }

        return DB::table('caring_kpi_baselines')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('captured_at')
            ->get()
            ->map(fn ($row) => $this->rowToScoreboardBaseline($row))
            ->filter(fn ($b) => $b !== null)
            ->values()
            ->all();
    }

    // ---------------------------------------------------------------------
    // Internal helpers
    // ---------------------------------------------------------------------

    private function rowToScoreboardBaseline(?object $row): ?array
    {
        if (!$row) {
            return null;
        }

        $envelope = json_decode((string) $row->metrics, true);
        if (!is_array($envelope) || ($envelope['kind'] ?? null) !== 'pilot_scoreboard') {
            return null;
        }

        return [
            'id'              => (int) $row->id,
            'label'           => (string) $row->label,
            'is_pre_pilot'    => (bool) ($envelope['is_pre_pilot'] ?? false),
            'baseline_period' => json_decode((string) $row->baseline_period, true),
            'captured_at'     => (string) $row->captured_at,
            'metrics'         => $envelope['metrics'] ?? [],
            'notes'           => $row->notes,
            'captured_by'     => $row->captured_by ? (int) $row->captured_by : null,
        ];
    }

    private function latestBaseline(int $tenantId, string $label): ?array
    {
        if (!Schema::hasTable('caring_kpi_baselines')) {
            return null;
        }

        $row = DB::table('caring_kpi_baselines')
            ->where('tenant_id', $tenantId)
            ->where('label', $label)
            ->orderByDesc('captured_at')
            ->first();

        return $this->rowToScoreboardBaseline($row);
    }

    private function latestQuarterlyReview(int $tenantId): ?array
    {
        if (!Schema::hasTable('caring_kpi_baselines')) {
            return null;
        }

        $row = DB::table('caring_kpi_baselines')
            ->where('tenant_id', $tenantId)
            ->where('label', '!=', self::PRE_PILOT_LABEL)
            ->orderByDesc('captured_at')
            ->first();

        return $this->rowToScoreboardBaseline($row);
    }

    private function quarterlyReviewDueAt(?array $prePilot, ?array $latestQuarterly): ?string
    {
        $anchor = $latestQuarterly['captured_at'] ?? $prePilot['captured_at'] ?? null;
        if ($anchor === null) {
            return null;
        }

        try {
            return now()->parse($anchor)->addMonths(3)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Compare two metric arrays. Output is keyed by metric.
     */
    private function compareMetrics(array $baseline, array $current): array
    {
        $out = [];
        foreach (['active_members','first_response_hours','approved_hours','recurring_relationships',
                  'coordinator_workload_hrs','satisfaction_score','social_isolation_pct','comms_reach_pct',
                  'business_participation','cost_offset_chf'] as $key) {
            $b = $this->floatOrNull($baseline[$key] ?? null);
            $c = $this->floatOrNull($current[$key] ?? null);
            $delta = ($b !== null && $c !== null) ? round($c - $b, 2) : null;
            $pctChange = ($delta !== null && $b !== null && $b !== 0.0)
                ? round(($delta / $b) * 100, 1)
                : null;

            $out[$key] = [
                'baseline'   => $b,
                'current'    => $c,
                'delta'      => $delta,
                'pct_change' => $pctChange,
            ];
        }
        return $out;
    }

    private function floatOrNull(mixed $v): ?float
    {
        return is_numeric($v) ? (float) $v : null;
    }

    private function approvedHoursInWindow(int $tenantId, string $windowStart): float
    {
        if (!Schema::hasTable('vol_logs')) {
            return 0.0;
        }
        return (float) DB::table('vol_logs')
            ->where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->where('created_at', '>=', $windowStart)
            ->sum('hours');
    }

    private function activeMembersInWindow(int $tenantId, string $windowStart): int
    {
        $ids = [];

        if (Schema::hasTable('vol_logs')) {
            $rows = DB::table('vol_logs')
                ->where('tenant_id', $tenantId)
                ->where('status', 'approved')
                ->where('created_at', '>=', $windowStart)
                ->select('user_id')
                ->distinct()
                ->get();
            foreach ($rows as $r) {
                if ($r->user_id) {
                    $ids[(int) $r->user_id] = true;
                }
            }
        }

        if (Schema::hasTable('transactions')) {
            $rows = DB::table('transactions')
                ->where('tenant_id', $tenantId)
                ->where('status', 'completed')
                ->where('created_at', '>=', $windowStart)
                ->select(['sender_id', 'receiver_id'])
                ->get();
            foreach ($rows as $r) {
                if (!empty($r->sender_id))   $ids[(int) $r->sender_id] = true;
                if (!empty($r->receiver_id)) $ids[(int) $r->receiver_id] = true;
            }
        }

        return count($ids);
    }

    private function medianFirstResponseHours(int $tenantId, string $windowStart): ?float
    {
        if (!Schema::hasTable('caring_help_requests')) {
            return null;
        }

        // Proxy: time from help_request creation to status change away from 'pending'.
        // We approximate with updated_at of any non-pending row created in the window.
        $rows = DB::table('caring_help_requests')
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $windowStart)
            ->where('status', '!=', 'pending')
            ->whereNotNull('updated_at')
            ->select(['created_at', 'updated_at'])
            ->get();

        $hours = [];
        foreach ($rows as $r) {
            if (!$r->created_at || !$r->updated_at) continue;
            try {
                $diff = strtotime((string) $r->updated_at) - strtotime((string) $r->created_at);
                if ($diff > 0) {
                    $hours[] = $diff / 3600.0;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        if ($hours === []) {
            return null;
        }
        sort($hours);
        $mid = (int) floor(count($hours) / 2);
        $median = (count($hours) % 2 === 0)
            ? ($hours[$mid - 1] + $hours[$mid]) / 2.0
            : $hours[$mid];

        return round($median, 2);
    }

    private function recurringRelationships(int $tenantId): int
    {
        if (!Schema::hasTable('caring_support_relationships')) {
            return 0;
        }
        return (int) DB::table('caring_support_relationships')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->count();
    }

    private function coordinatorWorkload(int $tenantId): ?float
    {
        if (!Schema::hasTable('users') || !Schema::hasTable('caring_help_requests')) {
            return null;
        }

        $coordinatorCount = 0;
        if (Schema::hasColumn('users', 'trust_tier')) {
            $coordinatorCount = (int) DB::table('users')
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->where('trust_tier', '>=', 4)
                ->count();
        }

        if ($coordinatorCount === 0) {
            // Fallback: admin role counts as coordinator
            $coordinatorCount = (int) DB::table('users')
                ->where('tenant_id', $tenantId)
                ->whereIn('role', ['admin', 'super_admin', 'tenant_super_admin'])
                ->count();
        }

        if ($coordinatorCount === 0) {
            return null;
        }

        $pendingRequests = (int) DB::table('caring_help_requests')
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->count();

        return round($pendingRequests / $coordinatorCount, 2);
    }

    private function satisfactionScore(int $tenantId): ?float
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
            ->where(function ($qb) {
                $qb->where('q.question_text', 'like', '%satisfaction%')
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
        foreach (DB::table('municipality_survey_responses')
            ->where('tenant_id', $tenantId)
            ->select('answers')
            ->get() as $response) {
            $answers = json_decode((string) $response->answers, true);
            if (!is_array($answers)) continue;
            foreach ($questionIds as $qid) {
                if (!array_key_exists($qid, $answers)) continue;
                $val = $answers[$qid];
                if (is_numeric($val)) {
                    $score = (float) $val;
                    if ($score >= 1.0 && $score <= 5.0) {
                        $scores[] = $score;
                    }
                }
            }
        }

        return $scores === [] ? null : round(array_sum($scores) / count($scores), 2);
    }

    private function socialIsolationProxyPct(int $tenantId, string $windowStart): ?float
    {
        if (!Schema::hasTable('users')) {
            return null;
        }

        $totalActive = (int) DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->count();

        if ($totalActive === 0) {
            return null;
        }

        // Build an "engaged user" set: any user with messages, transactions, or vol logs in window.
        $engaged = [];
        if (Schema::hasTable('messages')) {
            foreach (DB::table('messages')
                ->where('tenant_id', $tenantId)
                ->where('created_at', '>=', $windowStart)
                ->select(['sender_id', 'receiver_id'])
                ->get() as $r) {
                if (!empty($r->sender_id))   $engaged[(int) $r->sender_id]   = true;
                if (!empty($r->receiver_id)) $engaged[(int) $r->receiver_id] = true;
            }
        }
        if (Schema::hasTable('transactions')) {
            foreach (DB::table('transactions')
                ->where('tenant_id', $tenantId)
                ->where('created_at', '>=', $windowStart)
                ->select(['sender_id', 'receiver_id'])
                ->get() as $r) {
                if (!empty($r->sender_id))   $engaged[(int) $r->sender_id]   = true;
                if (!empty($r->receiver_id)) $engaged[(int) $r->receiver_id] = true;
            }
        }
        if (Schema::hasTable('vol_logs')) {
            foreach (DB::table('vol_logs')
                ->where('tenant_id', $tenantId)
                ->where('created_at', '>=', $windowStart)
                ->select('user_id')->distinct()->get() as $r) {
                if (!empty($r->user_id)) $engaged[(int) $r->user_id] = true;
            }
        }

        $isolated = $totalActive - count($engaged);
        if ($isolated < 0) $isolated = 0;
        return round(($isolated / $totalActive) * 100, 1);
    }

    private function communicationsReachPct(int $tenantId, string $windowStart): ?float
    {
        if (!Schema::hasTable('caring_emergency_alerts') || !Schema::hasTable('users')) {
            return null;
        }

        $totalActive = (int) DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->count();

        if ($totalActive === 0) {
            return null;
        }

        $latest = DB::table('caring_emergency_alerts')
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $windowStart)
            ->orderByDesc('created_at')
            ->first();

        if (!$latest) {
            return null;
        }

        // Reach proxy: total active members minus dismissed_count == seen but not dismissed.
        // Where push_sent + dismissed_count gives us a lower-bound on members aware.
        $awareLowerBound = max((int) ($latest->dismissed_count ?? 0), 0);
        if ($awareLowerBound === 0 && (int) ($latest->push_sent ?? 0) === 1) {
            // Push fired but no dismissals yet — we count it as reached (FCM delivered).
            $awareLowerBound = $totalActive;
        }

        return round(min($awareLowerBound / $totalActive, 1.0) * 100, 1);
    }

    private function businessParticipation(int $tenantId, string $windowStart): int
    {
        $ids = [];

        if (Schema::hasTable('vol_organizations')) {
            foreach (DB::table('vol_organizations')
                ->where('tenant_id', $tenantId)
                ->where(function ($qb) use ($windowStart) {
                    $qb->where('updated_at', '>=', $windowStart)
                        ->orWhere('created_at', '>=', $windowStart);
                })
                ->select('id')
                ->get() as $r) {
                $ids['vol_' . (int) $r->id] = true;
            }
        }

        if (Schema::hasTable('merchant_coupons')) {
            foreach (DB::table('merchant_coupons')
                ->where('tenant_id', $tenantId)
                ->where('created_at', '>=', $windowStart)
                ->select('seller_id')
                ->distinct()
                ->get() as $r) {
                if (!empty($r->seller_id)) {
                    $ids['merchant_' . (int) $r->seller_id] = true;
                }
            }
        }

        return count($ids);
    }
}
