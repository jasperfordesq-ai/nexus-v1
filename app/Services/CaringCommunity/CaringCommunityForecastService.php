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

/**
 * Forward-looking forecasting for caring-community coordinator dashboard.
 *
 * Tom Debus's "AI/Daten" pillar: predict regional care deficits before they
 * become municipal emergencies. We project the next 3 months of approved
 * hours, distinct active members, and distinct recipients reached using a
 * simple linear regression on the past 6 months of activity.
 *
 * No ML library required — closed-form least-squares fit + ±1 sd of
 * residuals as confidence band.
 */
class CaringCommunityForecastService
{
    private const HISTORY_MONTHS = 6;

    /**
     * Forecast approved volunteer hours per month.
     *
     * @return array{
     *     history: list<array{month: string, hours: float}>,
     *     forecast: list<array{month: string, hours: float, lower: float, upper: float}>,
     *     trend: 'growing'|'stable'|'declining',
     *     growth_rate_pct: float,
     *     confidence: 'high'|'medium'|'low',
     * }
     */
    public function forecastHours(int $monthsAhead = 3): array
    {
        $history = $this->loadHoursHistory();
        return $this->buildForecast($history, $monthsAhead, 'hours');
    }

    /**
     * Forecast distinct active members per month.
     *
     * @return array{
     *     history: list<array{month: string, hours: float}>,
     *     forecast: list<array{month: string, hours: float, lower: float, upper: float}>,
     *     trend: 'growing'|'stable'|'declining',
     *     growth_rate_pct: float,
     *     confidence: 'high'|'medium'|'low',
     * }
     */
    public function forecastMembers(int $monthsAhead = 3): array
    {
        $history = $this->loadMembersHistory();
        return $this->buildForecast($history, $monthsAhead, 'hours');
    }

    /**
     * Forecast distinct recipients reached per month.
     *
     * @return array{
     *     history: list<array{month: string, hours: float}>,
     *     forecast: list<array{month: string, hours: float, lower: float, upper: float}>,
     *     trend: 'growing'|'stable'|'declining',
     *     growth_rate_pct: float,
     *     confidence: 'high'|'medium'|'low',
     * }
     */
    public function forecastRecipients(int $monthsAhead = 3): array
    {
        $history = $this->loadRecipientsHistory();
        return $this->buildForecast($history, $monthsAhead, 'hours');
    }

    /**
     * @return list<array{month: string, hours: float}>
     */
    private function loadHoursHistory(): array
    {
        $tenantId = TenantContext::getId();
        $bins = $this->emptyBins();

        if (!Schema::hasTable('vol_logs')) {
            return $this->binsToList($bins);
        }

        $start = $this->historyStart();

        $rows = DB::select(
            "SELECT DATE_FORMAT(date_logged, '%Y-%m') AS bucket,
                    COALESCE(SUM(hours), 0) AS total
             FROM vol_logs
             WHERE tenant_id = ?
                AND status = 'approved'
                AND date_logged >= ?
             GROUP BY bucket",
            [$tenantId, $start]
        );

        foreach ($rows as $row) {
            $bucket = (string) $row->bucket;
            if (array_key_exists($bucket, $bins)) {
                $bins[$bucket] = round((float) $row->total, 2);
            }
        }

        return $this->binsToList($bins);
    }

    /**
     * @return list<array{month: string, hours: float}>
     */
    private function loadMembersHistory(): array
    {
        $tenantId = TenantContext::getId();
        $bins = $this->emptyBins();

        if (!Schema::hasTable('vol_logs')) {
            return $this->binsToList($bins);
        }

        $start = $this->historyStart();

        $rows = DB::select(
            "SELECT DATE_FORMAT(date_logged, '%Y-%m') AS bucket,
                    COUNT(DISTINCT user_id) AS total
             FROM vol_logs
             WHERE tenant_id = ?
                AND status = 'approved'
                AND date_logged >= ?
             GROUP BY bucket",
            [$tenantId, $start]
        );

        foreach ($rows as $row) {
            $bucket = (string) $row->bucket;
            if (array_key_exists($bucket, $bins)) {
                $bins[$bucket] = (float) (int) $row->total;
            }
        }

        return $this->binsToList($bins);
    }

    /**
     * @return list<array{month: string, hours: float}>
     */
    private function loadRecipientsHistory(): array
    {
        $tenantId = TenantContext::getId();
        $bins = $this->emptyBins();

        if (!Schema::hasTable('vol_logs') || !Schema::hasColumn('vol_logs', 'support_recipient_id')) {
            return $this->binsToList($bins);
        }

        $start = $this->historyStart();

        $rows = DB::select(
            "SELECT DATE_FORMAT(date_logged, '%Y-%m') AS bucket,
                    COUNT(DISTINCT support_recipient_id) AS total
             FROM vol_logs
             WHERE tenant_id = ?
                AND status = 'approved'
                AND support_recipient_id IS NOT NULL
                AND date_logged >= ?
             GROUP BY bucket",
            [$tenantId, $start]
        );

        foreach ($rows as $row) {
            $bucket = (string) $row->bucket;
            if (array_key_exists($bucket, $bins)) {
                $bins[$bucket] = (float) (int) $row->total;
            }
        }

        return $this->binsToList($bins);
    }

    /**
     * @return array<string, float>
     */
    private function emptyBins(): array
    {
        $bins = [];
        for ($i = self::HISTORY_MONTHS - 1; $i >= 0; $i--) {
            $bins[date('Y-m', strtotime("first day of -{$i} month"))] = 0.0;
        }
        return $bins;
    }

    private function historyStart(): string
    {
        $offset = self::HISTORY_MONTHS - 1;
        return date('Y-m-01', strtotime("first day of -{$offset} month"));
    }

    /**
     * @param array<string, float> $bins
     * @return list<array{month: string, hours: float}>
     */
    private function binsToList(array $bins): array
    {
        $out = [];
        foreach ($bins as $month => $value) {
            $out[] = ['month' => $month, 'hours' => round((float) $value, 2)];
        }
        return $out;
    }

    /**
     * Build a forecast envelope around a history series.
     *
     * @param list<array{month: string, hours: float}> $history
     * @return array{
     *     history: list<array{month: string, hours: float}>,
     *     forecast: list<array{month: string, hours: float, lower: float, upper: float}>,
     *     trend: 'growing'|'stable'|'declining',
     *     growth_rate_pct: float,
     *     confidence: 'high'|'medium'|'low',
     * }
     */
    private function buildForecast(array $history, int $monthsAhead, string $valueKey): array
    {
        $monthsAhead = max(1, min(12, $monthsAhead));

        // Build the regression input
        $points = [];
        foreach (array_values($history) as $idx => $row) {
            $points[] = [(float) $idx, (float) $row['hours']];
        }

        $values = array_column($points, 1);
        $meanY = count($values) > 0 ? array_sum($values) / count($values) : 0.0;
        $nonZeroCount = count(array_filter($values, static fn ($v) => $v > 0));

        // Insufficient data: less than 3 non-zero months → empty forecast
        if ($nonZeroCount < 3) {
            return [
                'history' => $history,
                'forecast' => [],
                'trend' => 'stable',
                'growth_rate_pct' => 0.0,
                'confidence' => 'low',
            ];
        }

        $regression = $this->linearRegression($points);
        $slope = $regression['slope'];
        $intercept = $regression['intercept'];
        $rSquared = $regression['r_squared'];

        // Residual standard deviation (population sd of residuals)
        $sumSqResid = 0.0;
        foreach ($points as [$x, $y]) {
            $predicted = $slope * $x + $intercept;
            $sumSqResid += ($y - $predicted) ** 2;
        }
        $residualSd = count($points) > 0 ? sqrt($sumSqResid / count($points)) : 0.0;

        // Trend label: growing if slope > 5% of meanY, declining if < -5%
        $threshold = abs($meanY) * 0.05;
        if ($threshold < 0.001) {
            $trend = 'stable';
        } elseif ($slope > $threshold) {
            $trend = 'growing';
        } elseif ($slope < -$threshold) {
            $trend = 'declining';
        } else {
            $trend = 'stable';
        }

        // Growth rate: slope per month as % of mean
        $growthRatePct = $meanY > 0.001 ? round(($slope / $meanY) * 100.0, 1) : 0.0;

        // Confidence based on r-squared
        if ($rSquared >= 0.7) {
            $confidence = 'high';
        } elseif ($rSquared >= 0.4) {
            $confidence = 'medium';
        } else {
            $confidence = 'low';
        }

        // Forecast next N months
        $forecast = [];
        $lastMonth = $history[count($history) - 1]['month'] ?? date('Y-m');
        for ($k = 1; $k <= $monthsAhead; $k++) {
            $x = (float) (count($points) - 1 + $k);
            $yHat = $slope * $x + $intercept;
            $yHat = max(0.0, $yHat);
            $lower = max(0.0, $yHat - $residualSd);
            $upper = $yHat + $residualSd;

            $forecast[] = [
                'month' => date('Y-m', strtotime("first day of +{$k} month", strtotime($lastMonth . '-01'))),
                'hours' => round($yHat, 2),
                'lower' => round($lower, 2),
                'upper' => round($upper, 2),
            ];
        }

        unset($valueKey); // future: differentiate label units; currently always 'hours' key

        return [
            'history' => $history,
            'forecast' => $forecast,
            'trend' => $trend,
            'growth_rate_pct' => $growthRatePct,
            'confidence' => $confidence,
        ];
    }

    /**
     * Closed-form simple linear regression.
     *
     * @param list<array{0: float, 1: float}> $points
     * @return array{slope: float, intercept: float, r_squared: float}
     */
    private function linearRegression(array $points): array
    {
        $n = count($points);
        if ($n < 2) {
            return ['slope' => 0.0, 'intercept' => 0.0, 'r_squared' => 0.0];
        }

        $xs = array_column($points, 0);
        $ys = array_column($points, 1);
        $meanX = array_sum($xs) / $n;
        $meanY = array_sum($ys) / $n;

        $sumXY = 0.0;
        $sumXX = 0.0;
        $sumYY = 0.0;
        foreach ($points as [$x, $y]) {
            $sumXY += ($x - $meanX) * ($y - $meanY);
            $sumXX += ($x - $meanX) ** 2;
            $sumYY += ($y - $meanY) ** 2;
        }

        if ($sumXX < 1e-12) {
            return ['slope' => 0.0, 'intercept' => $meanY, 'r_squared' => 0.0];
        }

        $slope = $sumXY / $sumXX;
        $intercept = $meanY - $slope * $meanX;
        $rSquared = $sumYY > 1e-12 ? ($slope * $sumXY) / $sumYY : 0.0;
        $rSquared = max(0.0, min(1.0, $rSquared));

        return [
            'slope' => $slope,
            'intercept' => $intercept,
            'r_squared' => $rSquared,
        ];
    }
}
