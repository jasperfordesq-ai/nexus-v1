<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * SocialValueService — Native Eloquent implementation for SROI (Social Return on Investment) reporting.
 *
 * Uses the `social_value_config` table for per-tenant configuration and calculates
 * social value from transactions, events, volunteer hours, and community engagement.
 */
class SocialValueService
{
    /**
     * Default SROI configuration values.
     */
    private const DEFAULTS = [
        'hour_value_currency' => 'GBP',
        'hour_value_amount'   => 15.00,
        'social_multiplier'   => 3.50,
        'reporting_period'    => 'annually',
    ];

    public function __construct()
    {
    }

    /**
     * Calculate the Social Return on Investment for a tenant.
     *
     * @param int   $tenantId
     * @param array $dateRange ['from' => 'Y-m-d', 'to' => 'Y-m-d']
     * @return array
     */
    public function calculateSROI(int $tenantId, array $dateRange = []): array
    {
        $config = $this->getConfig($tenantId);
        $hourValue = (float) $config['hour_value_amount'];
        $multiplier = (float) $config['social_multiplier'];
        $currency = $config['hour_value_currency'];

        // Build date conditions
        $dateConditions = $this->buildDateConditions($dateRange);
        $dateBindings = $this->buildDateBindings($dateRange);

        // 1. Total transaction hours (amount = hours in this timebank)
        $txQuery = "SELECT
                COALESCE(SUM(amount), 0) AS total_hours,
                COUNT(*) AS total_transactions
            FROM transactions
            WHERE tenant_id = ? AND status = 'completed'
            {$dateConditions}";
        $txResult = DB::selectOne($txQuery, array_merge([$tenantId], $dateBindings));
        $totalHours = (float) ($txResult->total_hours ?? 0);
        $totalTransactions = (int) ($txResult->total_transactions ?? 0);

        // 2. Unique active members (participated in transactions)
        $memberQuery = "SELECT COUNT(DISTINCT user_id) AS active_members FROM (
                SELECT sender_id AS user_id FROM transactions WHERE tenant_id = ? AND status = 'completed' {$dateConditions}
                UNION
                SELECT receiver_id AS user_id FROM transactions WHERE tenant_id = ? AND status = 'completed' {$dateConditions}
            ) AS active_users";
        $memberResult = DB::selectOne($memberQuery, array_merge(
            [$tenantId],
            $dateBindings,
            [$tenantId],
            $dateBindings
        ));
        $activeMembers = (int) ($memberResult->active_members ?? 0);

        // 3. Events held
        $eventQuery = "SELECT COUNT(*) AS total_events, COALESCE(SUM(
                CASE WHEN end_time IS NOT NULL AND start_time IS NOT NULL
                    THEN TIMESTAMPDIFF(HOUR, start_time, end_time) ELSE 0 END
            ), 0) AS event_hours
            FROM events
            WHERE tenant_id = ? AND status IN ('active', 'completed')
            {$this->buildDateConditions($dateRange, 'start_time')}";
        $eventResult = DB::selectOne($eventQuery, array_merge([$tenantId], $this->buildDateBindings($dateRange)));
        $totalEvents = (int) ($eventResult->total_events ?? 0);
        $eventHours = (float) ($eventResult->event_hours ?? 0);

        // 4. Listings created
        $listingQuery = "SELECT COUNT(*) AS total_listings
            FROM listings WHERE tenant_id = ? AND status = 'active'
            {$this->buildDateConditions($dateRange)}";
        $listingResult = DB::selectOne($listingQuery, array_merge([$tenantId], $this->buildDateBindings($dateRange)));
        $totalListings = (int) ($listingResult->total_listings ?? 0);

        // 5. Calculate monetary values
        $directValue = $totalHours * $hourValue;
        $socialValue = $directValue * $multiplier;
        $totalValue = $directValue + $socialValue;

        // 6. Hours by category breakdown
        $categoryQuery = "SELECT
                c.name AS category_name,
                COALESCE(SUM(t.amount), 0) AS hours,
                COUNT(*) AS transaction_count
            FROM transactions t
            LEFT JOIN listings l ON l.id = t.listing_id AND l.tenant_id = t.tenant_id
            LEFT JOIN categories c ON c.id = l.category_id AND c.tenant_id = t.tenant_id
            WHERE t.tenant_id = ? AND t.status = 'completed'
            {$this->buildDateConditions($dateRange, 't.created_at')}
            GROUP BY c.name
            ORDER BY hours DESC
            LIMIT 20";
        $categories = DB::select($categoryQuery, array_merge([$tenantId], $this->buildDateBindings($dateRange)));

        $categoryBreakdown = array_map(function ($row) use ($hourValue, $multiplier) {
            $hours = (float) $row->hours;
            $direct = $hours * $hourValue;
            return [
                'category'          => $row->category_name ?? 'Uncategorized',
                'hours'             => $hours,
                'transaction_count' => (int) $row->transaction_count,
                'direct_value'      => round($direct, 2),
                'social_value'      => round($direct * $multiplier, 2),
            ];
        }, $categories);

        // 7. Monthly trend (last 12 months)
        $trendQuery = "SELECT
                DATE_FORMAT(created_at, '%Y-%m') AS month,
                COALESCE(SUM(amount), 0) AS hours,
                COUNT(*) AS transactions
            FROM transactions
            WHERE tenant_id = ? AND status = 'completed'
                AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month ASC";
        $trends = DB::select($trendQuery, [$tenantId]);

        $monthlyTrend = array_map(function ($row) use ($hourValue, $multiplier) {
            $hours = (float) $row->hours;
            $direct = $hours * $hourValue;
            return [
                'month'        => $row->month,
                'hours'        => $hours,
                'transactions' => (int) $row->transactions,
                'direct_value' => round($direct, 2),
                'social_value' => round($direct * $multiplier, 2),
                'total_value'  => round($direct + ($direct * $multiplier), 2),
            ];
        }, $trends);

        return [
            'config'   => [
                'hour_value_currency' => $currency,
                'hour_value_amount'   => $hourValue,
                'social_multiplier'   => $multiplier,
                'reporting_period'    => $config['reporting_period'],
            ],
            'summary'  => [
                'total_hours'        => round($totalHours, 2),
                'total_transactions' => $totalTransactions,
                'active_members'     => $activeMembers,
                'total_events'       => $totalEvents,
                'event_hours'        => round($eventHours, 2),
                'total_listings'     => $totalListings,
                'direct_value'       => round($directValue, 2),
                'social_value'       => round($socialValue, 2),
                'total_value'        => round($totalValue, 2),
                'currency'           => $currency,
            ],
            'categories'    => $categoryBreakdown,
            'monthly_trend' => $monthlyTrend,
            'date_range'    => $dateRange,
        ];
    }

    /**
     * Get the SROI configuration for a tenant.
     */
    public function getConfig(int $tenantId): array
    {
        $row = DB::table('social_value_config')
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$row) {
            return self::DEFAULTS;
        }

        return [
            'hour_value_currency' => $row->hour_value_currency ?? self::DEFAULTS['hour_value_currency'],
            'hour_value_amount'   => (float) ($row->hour_value_amount ?? self::DEFAULTS['hour_value_amount']),
            'social_multiplier'   => (float) ($row->social_multiplier ?? self::DEFAULTS['social_multiplier']),
            'reporting_period'    => $row->reporting_period ?? self::DEFAULTS['reporting_period'],
        ];
    }

    /**
     * Save the SROI configuration for a tenant.
     */
    public function saveConfig(int $tenantId, array $config): bool
    {
        $data = [
            'hour_value_currency' => $config['hour_value_currency'] ?? self::DEFAULTS['hour_value_currency'],
            'hour_value_amount'   => (float) ($config['hour_value_amount'] ?? self::DEFAULTS['hour_value_amount']),
            'social_multiplier'   => (float) ($config['social_multiplier'] ?? self::DEFAULTS['social_multiplier']),
            'reporting_period'    => $config['reporting_period'] ?? self::DEFAULTS['reporting_period'],
            'updated_at'          => now(),
        ];

        $exists = DB::table('social_value_config')
            ->where('tenant_id', $tenantId)
            ->exists();

        if ($exists) {
            DB::table('social_value_config')
                ->where('tenant_id', $tenantId)
                ->update($data);
        } else {
            $data['tenant_id'] = $tenantId;
            $data['created_at'] = now();
            DB::table('social_value_config')->insert($data);
        }

        return true;
    }

    /**
     * Build SQL date condition fragment.
     */
    private function buildDateConditions(array $dateRange, string $column = 'created_at'): string
    {
        $conditions = '';
        if (!empty($dateRange['from'])) {
            $conditions .= " AND {$column} >= ?";
        }
        if (!empty($dateRange['to'])) {
            $conditions .= " AND {$column} <= ?";
        }
        return $conditions;
    }

    /**
     * Build bindings array for date conditions.
     */
    private function buildDateBindings(array $dateRange): array
    {
        $bindings = [];
        if (!empty($dateRange['from'])) {
            $bindings[] = $dateRange['from'];
        }
        if (!empty($dateRange['to'])) {
            $bindings[] = $dateRange['to'];
        }
        return $bindings;
    }
}
