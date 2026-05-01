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

    /** Drift threshold (proportion) above which a category coefficient is flagged. */
    private const COEFFICIENT_DRIFT_FLAG = 0.15;

    /** Helpers active in this earlier window are candidates for the churn metric. */
    private const CHURN_PRIOR_WINDOW_DAYS_START = 90;
    private const CHURN_PRIOR_WINDOW_DAYS_END = 60;

    /** Helpers who haven't logged in this many days are considered "lapsed" for churn. */
    private const CHURN_LAPSED_DAYS = 30;

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

    // ─────────────────────────────────────────────────────────────────────────
    // Depth layer 1 — Sub-region demand vs. fulfilment
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Hours requested vs. fulfilled per sub-region over rolling 30 / 90 day windows.
     *
     * "Requested" is approximated as `caring_help_requests` rows whose author's
     * `users.location` matches the sub-region (by name or any postal code in
     * the sub-region's `postal_codes` JSON). "Fulfilled" is the matching
     * approved `vol_logs` total over the same window.
     *
     * Coverage ratio = fulfilled / requested. < 0.5 flags an under-supplied area.
     *
     * @return array{
     *     window_days: array{short:int, long:int},
     *     sub_regions: list<array{
     *         id:int, name:string, slug:string,
     *         requested_30d:float, fulfilled_30d:float, coverage_ratio_30d:float,
     *         requested_90d:float, fulfilled_90d:float, coverage_ratio_90d:float,
     *         flagged:bool
     *     }>,
     *     under_supplied_count:int,
     * }
     */
    public function subRegionDemand(): array
    {
        $tenantId = TenantContext::getId();
        $shortDays = 30;
        $longDays = 90;

        $empty = [
            'window_days' => ['short' => $shortDays, 'long' => $longDays],
            'sub_regions' => [],
            'under_supplied_count' => 0,
        ];

        if (
            !Schema::hasTable('caring_sub_regions')
            || !Schema::hasTable('vol_logs')
            || !Schema::hasTable('caring_help_requests')
        ) {
            return $empty;
        }

        $regions = DB::table('caring_sub_regions')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'postal_codes'])
            ->all();

        if (count($regions) === 0) {
            return $empty;
        }

        $shortStart = date('Y-m-d', strtotime("-{$shortDays} days"));
        $longStart = date('Y-m-d', strtotime("-{$longDays} days"));

        $rows = [];
        $underSupplied = 0;

        foreach ($regions as $region) {
            $postal = $this->decodePostalCodes($region->postal_codes ?? null);
            $name = (string) $region->name;

            $requested30 = $this->sumRequestedHoursForRegion($tenantId, $name, $postal, $shortStart);
            $requested90 = $this->sumRequestedHoursForRegion($tenantId, $name, $postal, $longStart);
            $fulfilled30 = $this->sumFulfilledHoursForRegion($tenantId, $name, $postal, $shortStart);
            $fulfilled90 = $this->sumFulfilledHoursForRegion($tenantId, $name, $postal, $longStart);

            $cov30 = $requested30 > 0.001 ? round($fulfilled30 / $requested30, 3) : 0.0;
            $cov90 = $requested90 > 0.001 ? round($fulfilled90 / $requested90, 3) : 0.0;

            // Only count "flagged" if there is actual demand in the long window.
            $flagged = $requested90 > 0.0 && $cov90 < 0.5;
            if ($flagged) {
                $underSupplied++;
            }

            $rows[] = [
                'id' => (int) $region->id,
                'name' => $name,
                'slug' => (string) $region->slug,
                'requested_30d' => round($requested30, 2),
                'fulfilled_30d' => round($fulfilled30, 2),
                'coverage_ratio_30d' => $cov30,
                'requested_90d' => round($requested90, 2),
                'fulfilled_90d' => round($fulfilled90, 2),
                'coverage_ratio_90d' => $cov90,
                'flagged' => $flagged,
            ];
        }

        return [
            'window_days' => ['short' => $shortDays, 'long' => $longDays],
            'sub_regions' => $rows,
            'under_supplied_count' => $underSupplied,
        ];
    }

    /**
     * @param list<string> $postalCodes
     */
    private function sumRequestedHoursForRegion(int $tenantId, string $name, array $postalCodes, string $sinceDate): float
    {
        // Approximation: each pending/matched help request is treated as 1 hour
        // of demand (no hours field on the table). This gives a usable proxy
        // until a future schema iteration adds explicit hours-needed.
        $query = DB::table('caring_help_requests as hr')
            ->join('users as u', function ($join) use ($tenantId): void {
                $join->on('u.id', '=', 'hr.user_id')->where('u.tenant_id', '=', $tenantId);
            })
            ->where('hr.tenant_id', $tenantId)
            ->where('hr.created_at', '>=', $sinceDate);

        $query->where(function ($q) use ($name, $postalCodes): void {
            $q->where('u.location', 'LIKE', '%' . $name . '%');
            foreach ($postalCodes as $code) {
                $q->orWhere('u.location', 'LIKE', '%' . $code . '%');
            }
        });

        return (float) $query->count('hr.id');
    }

    /**
     * @param list<string> $postalCodes
     */
    private function sumFulfilledHoursForRegion(int $tenantId, string $name, array $postalCodes, string $sinceDate): float
    {
        $hasRecipient = Schema::hasColumn('vol_logs', 'support_recipient_id');

        $query = DB::table('vol_logs as l')
            ->where('l.tenant_id', $tenantId)
            ->where('l.status', 'approved')
            ->where('l.date_logged', '>=', $sinceDate);

        // Match by either the helper's location OR the recipient's location.
        if ($hasRecipient) {
            $query->leftJoin('users as helper', function ($join) use ($tenantId): void {
                $join->on('helper.id', '=', 'l.user_id')->where('helper.tenant_id', '=', $tenantId);
            });
            $query->leftJoin('users as recip', function ($join) use ($tenantId): void {
                $join->on('recip.id', '=', 'l.support_recipient_id')->where('recip.tenant_id', '=', $tenantId);
            });
            $query->where(function ($q) use ($name, $postalCodes): void {
                $q->where('helper.location', 'LIKE', '%' . $name . '%')
                  ->orWhere('recip.location', 'LIKE', '%' . $name . '%');
                foreach ($postalCodes as $code) {
                    $q->orWhere('helper.location', 'LIKE', '%' . $code . '%')
                      ->orWhere('recip.location', 'LIKE', '%' . $code . '%');
                }
            });
        } else {
            $query->join('users as helper', function ($join) use ($tenantId): void {
                $join->on('helper.id', '=', 'l.user_id')->where('helper.tenant_id', '=', $tenantId);
            });
            $query->where(function ($q) use ($name, $postalCodes): void {
                $q->where('helper.location', 'LIKE', '%' . $name . '%');
                foreach ($postalCodes as $code) {
                    $q->orWhere('helper.location', 'LIKE', '%' . $code . '%');
                }
            });
        }

        return (float) ($query->sum('l.hours') ?? 0.0);
    }

    /**
     * @return list<string>
     */
    private function decodePostalCodes(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $code) {
            $code = trim((string) $code);
            if ($code !== '') {
                $out[] = $code;
            }
        }

        return $out;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Depth layer 2 — Helper churn
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * % of helpers who logged hours 60–90 days ago and have not logged any
     * approved hours in the last 30 days. Surfaces overall + per category.
     *
     * @return array{
     *     prior_window_days:array{start:int, end:int},
     *     lapsed_threshold_days:int,
     *     overall:array{prior_active:int, lapsed:int, churn_rate:float},
     *     by_category:list<array{
     *         category_id:int|null, category_name:string,
     *         prior_active:int, lapsed:int, churn_rate:float
     *     }>,
     *     lapsed_helper_ids:list<int>,
     * }
     */
    public function helperChurn(): array
    {
        $tenantId = TenantContext::getId();
        $empty = [
            'prior_window_days' => [
                'start' => self::CHURN_PRIOR_WINDOW_DAYS_START,
                'end' => self::CHURN_PRIOR_WINDOW_DAYS_END,
            ],
            'lapsed_threshold_days' => self::CHURN_LAPSED_DAYS,
            'overall' => ['prior_active' => 0, 'lapsed' => 0, 'churn_rate' => 0.0],
            'by_category' => [],
            'lapsed_helper_ids' => [],
        ];

        if (!Schema::hasTable('vol_logs')) {
            return $empty;
        }

        $priorStart = date('Y-m-d', strtotime('-' . self::CHURN_PRIOR_WINDOW_DAYS_START . ' days'));
        $priorEnd = date('Y-m-d', strtotime('-' . self::CHURN_PRIOR_WINDOW_DAYS_END . ' days'));
        $recentStart = date('Y-m-d', strtotime('-' . self::CHURN_LAPSED_DAYS . ' days'));

        $priorActive = DB::table('vol_logs')
            ->where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->whereBetween('date_logged', [$priorStart, $priorEnd])
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        if (count($priorActive) === 0) {
            return $empty;
        }

        $stillActive = DB::table('vol_logs')
            ->where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->where('date_logged', '>=', $recentStart)
            ->whereIn('user_id', $priorActive)
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $lapsed = array_values(array_diff($priorActive, $stillActive));
        $lapsedCount = count($lapsed);
        $priorCount = count($priorActive);
        $churnRate = $priorCount > 0 ? round($lapsedCount / $priorCount, 3) : 0.0;

        $byCategory = $this->churnByCategory($tenantId, $priorActive, $stillActive);

        return [
            'prior_window_days' => [
                'start' => self::CHURN_PRIOR_WINDOW_DAYS_START,
                'end' => self::CHURN_PRIOR_WINDOW_DAYS_END,
            ],
            'lapsed_threshold_days' => self::CHURN_LAPSED_DAYS,
            'overall' => [
                'prior_active' => $priorCount,
                'lapsed' => $lapsedCount,
                'churn_rate' => $churnRate,
            ],
            'by_category' => $byCategory,
            'lapsed_helper_ids' => $lapsed,
        ];
    }

    /**
     * @param list<int> $priorActive
     * @param list<int> $stillActive
     * @return list<array{category_id:int|null, category_name:string, prior_active:int, lapsed:int, churn_rate:float}>
     */
    private function churnByCategory(int $tenantId, array $priorActive, array $stillActive): array
    {
        if (
            count($priorActive) === 0
            || !Schema::hasTable('caring_support_relationships')
            || !Schema::hasTable('categories')
        ) {
            return [];
        }

        // Map each helper to the categories of their support relationships.
        $rels = DB::table('caring_support_relationships as r')
            ->leftJoin('categories as c', function ($join) use ($tenantId): void {
                $join->on('c.id', '=', 'r.category_id');
                if (Schema::hasColumn('categories', 'tenant_id')) {
                    $join->where('c.tenant_id', '=', $tenantId);
                }
            })
            ->where('r.tenant_id', $tenantId)
            ->whereIn('r.supporter_id', $priorActive)
            ->get(['r.supporter_id', 'r.category_id', 'c.name as category_name']);

        /** @var array<string, array{category_id:int|null, category_name:string, prior:array<int,bool>, still:array<int,bool>}> $buckets */
        $buckets = [];
        $stillSet = array_flip($stillActive);

        foreach ($rels as $rel) {
            $catId = $rel->category_id !== null ? (int) $rel->category_id : null;
            $key = $catId === null ? 'uncategorised' : (string) $catId;
            if (!isset($buckets[$key])) {
                $buckets[$key] = [
                    'category_id' => $catId,
                    'category_name' => (string) ($rel->category_name ?? 'Uncategorised'),
                    'prior' => [],
                    'still' => [],
                ];
            }
            $sid = (int) $rel->supporter_id;
            $buckets[$key]['prior'][$sid] = true;
            if (isset($stillSet[$sid])) {
                $buckets[$key]['still'][$sid] = true;
            }
        }

        $out = [];
        foreach ($buckets as $b) {
            $prior = count($b['prior']);
            $still = count($b['still']);
            $lapsed = max(0, $prior - $still);
            $rate = $prior > 0 ? round($lapsed / $prior, 3) : 0.0;
            $out[] = [
                'category_id' => $b['category_id'],
                'category_name' => $b['category_name'],
                'prior_active' => $prior,
                'lapsed' => $lapsed,
                'churn_rate' => $rate,
            ];
        }

        usort($out, static fn ($a, $b) => $b['churn_rate'] <=> $a['churn_rate']);
        return $out;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Depth layer 3 — Category coefficient drift
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * For each category, compares the admin-set `substitution_coefficient`
     * (the baseline) to an observed coefficient computed from the average
     * approved hours per active support relationship in that category.
     *
     * Drift = (observed_session_avg / expected_session_avg) - 1, where
     * expected_session_avg is `caring_support_relationships.expected_hours`
     * (averaged) and observed_session_avg is mean approved hours per log
     * in active relationships of the category. Categories whose absolute
     * drift > 15% are flagged.
     *
     * @return array{
     *     threshold:float,
     *     categories:list<array{
     *         category_id:int, category_name:string,
     *         baseline_coefficient:float, expected_session_hours:float,
     *         observed_session_hours:float, drift:float, flagged:bool, sample_size:int
     *     }>,
     *     drift_count:int,
     * }
     */
    public function categoryCoefficientDrift(): array
    {
        $tenantId = TenantContext::getId();
        $threshold = self::COEFFICIENT_DRIFT_FLAG;
        $empty = [
            'threshold' => $threshold,
            'categories' => [],
            'drift_count' => 0,
        ];

        if (
            !Schema::hasTable('categories')
            || !Schema::hasColumn('categories', 'substitution_coefficient')
            || !Schema::hasTable('caring_support_relationships')
            || !Schema::hasTable('vol_logs')
            || !Schema::hasColumn('vol_logs', 'caring_support_relationship_id')
        ) {
            return $empty;
        }

        $catQuery = DB::table('categories')
            ->select('id', 'name', 'substitution_coefficient');
        if (Schema::hasColumn('categories', 'tenant_id')) {
            $catQuery->where('tenant_id', $tenantId);
        }
        if (Schema::hasColumn('categories', 'is_active')) {
            $catQuery->where('is_active', 1);
        }
        $categories = $catQuery->orderBy('name')->get();

        $rows = [];
        $driftCount = 0;

        foreach ($categories as $cat) {
            $catId = (int) $cat->id;
            $baseline = round((float) $cat->substitution_coefficient, 4);

            $expected = (float) DB::table('caring_support_relationships')
                ->where('tenant_id', $tenantId)
                ->where('category_id', $catId)
                ->where('status', 'active')
                ->avg('expected_hours');

            $relIds = DB::table('caring_support_relationships')
                ->where('tenant_id', $tenantId)
                ->where('category_id', $catId)
                ->where('status', 'active')
                ->pluck('id')
                ->all();

            $observed = 0.0;
            $sample = 0;
            if (count($relIds) > 0) {
                $logQuery = DB::table('vol_logs')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'approved')
                    ->whereIn('caring_support_relationship_id', $relIds);
                $sample = (int) $logQuery->count();
                if ($sample > 0) {
                    $observed = (float) ($logQuery->avg('hours') ?? 0.0);
                }
            }

            // No expected baseline yet → skip drift comparison but still emit a row.
            if ($expected < 0.001 || $sample < 3) {
                $rows[] = [
                    'category_id' => $catId,
                    'category_name' => (string) $cat->name,
                    'baseline_coefficient' => $baseline,
                    'expected_session_hours' => round($expected, 2),
                    'observed_session_hours' => round($observed, 2),
                    'drift' => 0.0,
                    'flagged' => false,
                    'sample_size' => $sample,
                ];
                continue;
            }

            $drift = round(($observed / $expected) - 1.0, 3);
            $flagged = abs($drift) > $threshold;
            if ($flagged) {
                $driftCount++;
            }

            $rows[] = [
                'category_id' => $catId,
                'category_name' => (string) $cat->name,
                'baseline_coefficient' => $baseline,
                'expected_session_hours' => round($expected, 2),
                'observed_session_hours' => round($observed, 2),
                'drift' => $drift,
                'flagged' => $flagged,
                'sample_size' => $sample,
            ];
        }

        return [
            'threshold' => $threshold,
            'categories' => $rows,
            'drift_count' => $driftCount,
        ];
    }
}
