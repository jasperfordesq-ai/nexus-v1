<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\CaringCommunity;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KpiBaselineService
{
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
        // Volunteer hours approved in the last 90 days
        $volunteerHours = (float) DB::selectOne(
            "SELECT COALESCE(SUM(hours), 0) AS total FROM vol_logs WHERE tenant_id = ? AND status = 'approved' AND logged_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)",
            [$tenantId]
        )?->total ?? 0.0;

        // Active member count
        $memberCount = (int) DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->count();

        // Caring support relationship metrics (guarded — table may not exist)
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

        // Total exchanges (guarded)
        $totalExchanges = null;
        if (Schema::hasTable('exchanges')) {
            $totalExchanges = (int) DB::table('exchanges')
                ->where('tenant_id', $tenantId)
                ->count();
        }

        return [
            'volunteer_hours'     => $volunteerHours,
            'member_count'        => $memberCount,
            'recipient_count'     => $recipientCount,
            'active_relationships' => $activeRelationships,
            'total_exchanges'     => $totalExchanges,
            'avg_response_hours'  => null, // placeholder — needs request timestamp data
            'engagement_rate_pct' => null, // placeholder — needs activity calculations
        ];
    }

    /**
     * Capture a baseline snapshot and persist it.
     *
     * @param  array{start: string, end: string} $periodDates
     */
    public function captureBaseline(
        int $tenantId,
        string $label,
        array $periodDates,
        ?string $notes,
        int $adminUserId,
    ): array {
        $metrics = $this->captureCurrentMetrics($tenantId);
        $now = now();

        $id = DB::table('caring_kpi_baselines')->insertGetId([
            'tenant_id'       => $tenantId,
            'label'           => $label,
            'baseline_period' => json_encode($periodDates, JSON_UNESCAPED_UNICODE),
            'captured_at'     => $now,
            'metrics'         => json_encode($metrics, JSON_UNESCAPED_UNICODE),
            'notes'           => $notes,
            'captured_by'     => $adminUserId,
            'created_at'      => $now,
            'updated_at'      => $now,
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
     *
     * Returns:
     * [
     *   'baseline' => [...],
     *   'current'  => [...],
     *   'comparison' => [
     *     'metric_key' => ['baseline' => v, 'current' => v, 'delta' => v, 'pct_change' => v|null],
     *     ...
     *   ]
     * ]
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
        $currentMetrics  = $this->captureCurrentMetrics($tenantId);

        $comparison = [];
        $keys = [
            'volunteer_hours',
            'member_count',
            'recipient_count',
            'active_relationships',
            'total_exchanges',
            'avg_response_hours',
            'engagement_rate_pct',
        ];

        foreach ($keys as $key) {
            $bVal = isset($baselineMetrics[$key]) && $baselineMetrics[$key] !== null
                ? (float) $baselineMetrics[$key]
                : null;
            $cVal = isset($currentMetrics[$key]) && $currentMetrics[$key] !== null
                ? (float) $currentMetrics[$key]
                : null;

            $delta     = ($bVal !== null && $cVal !== null) ? ($cVal - $bVal) : null;
            $pctChange = ($delta !== null && $bVal !== null && $bVal > 0.0)
                ? round(($delta / $bVal) * 100.0, 1)
                : null;

            $comparison[$key] = [
                'baseline'   => $bVal,
                'current'    => $cVal,
                'delta'      => $delta !== null ? round($delta, 2) : null,
                'pct_change' => $pctChange,
            ];
        }

        return [
            'baseline'   => $baseline,
            'current'    => $currentMetrics,
            'comparison' => $comparison,
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function rowToArray(object|null $row): array
    {
        if (!$row) {
            return [];
        }

        return [
            'id'              => (int) $row->id,
            'tenant_id'       => (int) $row->tenant_id,
            'label'           => (string) $row->label,
            'baseline_period' => json_decode((string) $row->baseline_period, true),
            'captured_at'     => (string) $row->captured_at,
            'metrics'         => json_decode((string) $row->metrics, true),
            'notes'           => $row->notes,
            'captured_by'     => $row->captured_by ? (int) $row->captured_by : null,
            'created_at'      => (string) $row->created_at,
            'updated_at'      => (string) $row->updated_at,
        ];
    }
}
