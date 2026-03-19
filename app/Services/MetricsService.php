<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * MetricsService — Laravel DI-based service for platform metrics.
 *
 * Stores and retrieves aggregate metrics for analytics dashboards.
 * Designed for lightweight metric ingestion and summary queries.
 */
class MetricsService
{
    /**
     * Store a metric data point.
     */
    public function store(string $name, float $value, array $tags = []): void
    {
        DB::table('metrics')->insert([
            'name'        => $name,
            'value'       => $value,
            'tags'        => ! empty($tags) ? json_encode($tags) : null,
            'recorded_at' => now(),
            'created_at'  => now(),
        ]);
    }

    /**
     * Get a summary of a metric over a time range.
     *
     * @return array{count: int, sum: float, avg: float, min: float, max: float}
     */
    public function getSummary(string $name, ?string $from = null, ?string $to = null): array
    {
        $query = DB::table('metrics')->where('name', $name);

        if ($from) {
            $query->where('recorded_at', '>=', $from);
        }
        if ($to) {
            $query->where('recorded_at', '<=', $to);
        }

        $result = $query->selectRaw('
            COUNT(*) as count,
            COALESCE(SUM(value), 0) as sum,
            COALESCE(AVG(value), 0) as avg,
            COALESCE(MIN(value), 0) as min,
            COALESCE(MAX(value), 0) as max
        ')->first();

        return [
            'count' => (int) $result->count,
            'sum'   => (float) $result->sum,
            'avg'   => (float) $result->avg,
            'min'   => (float) $result->min,
            'max'   => (float) $result->max,
        ];
    }

    /**
     * Get recent metric values (time series).
     */
    public function getTimeSeries(string $name, int $limit = 100): array
    {
        return DB::table('metrics')
            ->where('name', $name)
            ->orderByDesc('recorded_at')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }
}
