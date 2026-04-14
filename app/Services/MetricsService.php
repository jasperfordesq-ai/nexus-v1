<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * MetricsService — Platform metrics collection and reporting.
 *
 * Stores and retrieves aggregate event metrics for analytics dashboards.
 * All queries are scoped by tenant_id for data isolation.
 */
class MetricsService
{
    /**
     * Record a metric event (primary API used by MetricsController).
     */
    public function record(int $tenantId, string $event, array $data = []): void
    {
        DB::table('metrics')->insert([
            'tenant_id'  => $tenantId,
            'event'      => $event,
            'data'       => !empty($data) ? json_encode($data) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Get aggregated metrics summary for a tenant over a time period.
     *
     * @return array{period: string, total_events: int, events_by_type: array, start: string, end: string}
     */
    public function getSummary(int $tenantId, string $period = 'week', ?string $startDate = null, ?string $endDate = null): array
    {
        $start = $startDate ? \Carbon\Carbon::parse($startDate) : match ($period) {
            'day'   => now()->subDay(),
            'month' => now()->subMonth(),
            default => now()->subWeek(),
        };
        $end = $endDate ? \Carbon\Carbon::parse($endDate) : now();

        $query = DB::table('metrics')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$start, $end]);

        $totalEvents = (int) $query->count();

        $eventsByType = DB::table('metrics')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$start, $end])
            ->select('event', DB::raw('COUNT(*) as count'))
            ->groupBy('event')
            ->orderByDesc('count')
            ->limit(20)
            ->pluck('count', 'event')
            ->toArray();

        return [
            'period'         => $period,
            'total_events'   => $totalEvents,
            'events_by_type' => $eventsByType,
            'start'          => $start->toISOString(),
            'end'            => $end->toISOString(),
        ];
    }

    /**
     * Store a raw metric data point (legacy API — use record() for new code).
     */
    public function store(int $tenantId, string $name, float $value, array $tags = []): void
    {
        $this->record($tenantId, $name, ['value' => $value, 'tags' => $tags]);
    }

    /**
     * Get recent metric events as time series (tenant-scoped).
     */
    public function getTimeSeries(int $tenantId, string $event, int $limit = 100): array
    {
        return DB::table('metrics')
            ->where('tenant_id', $tenantId)
            ->where('event', $event)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }
}
