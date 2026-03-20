<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * ImpactReportingService — Calculates Social Return on Investment (SROI),
 * community health metrics, and impact timelines for tenant communities.
 *
 * All methods are tenant-scoped.
 */
class ImpactReportingService
{
    private const DEFAULT_HOURLY_VALUE = 15.00;
    private const DEFAULT_SOCIAL_MULTIPLIER = 3.5;

    /**
     * Calculate Social Return on Investment (SROI).
     */
    public function calculateSROI(array $config = []): array
    {
        $tenantId = TenantContext::getId();
        $months = $config['months'] ?? 12;
        $hourlyValue = $config['hourly_value'] ?? self::DEFAULT_HOURLY_VALUE;
        $socialMultiplier = $config['social_multiplier'] ?? self::DEFAULT_SOCIAL_MULTIPLIER;

        $data = DB::table('transactions')
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', DB::raw("DATE_SUB(NOW(), INTERVAL {$months} MONTH)"))
            ->select([
                DB::raw('COALESCE(SUM(amount), 0) as total_hours'),
                DB::raw('COUNT(*) as total_transactions'),
                DB::raw('COUNT(DISTINCT sender_id) as unique_givers'),
                DB::raw('COUNT(DISTINCT receiver_id) as unique_receivers'),
            ])
            ->first();

        $totalHours = (float) $data->total_hours;
        $monetaryValue = $totalHours * $hourlyValue;
        $socialValue = $monetaryValue * $socialMultiplier;

        return [
            'total_hours' => round($totalHours, 1),
            'total_transactions' => (int) $data->total_transactions,
            'unique_givers' => (int) $data->unique_givers,
            'unique_receivers' => (int) $data->unique_receivers,
            'hourly_value' => $hourlyValue,
            'monetary_value' => round($monetaryValue, 2),
            'social_multiplier' => $socialMultiplier,
            'social_value' => round($socialValue, 2),
            'sroi_ratio' => $monetaryValue > 0 ? round($socialValue / $monetaryValue, 1) : 0,
            'period_months' => $months,
        ];
    }

    /**
     * Get community health metrics.
     */
    public function getCommunityHealthMetrics(): array
    {
        $tenantId = TenantContext::getId();

        $userStats = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->select([
                DB::raw('COUNT(*) as total_users'),
                DB::raw('SUM(CASE WHEN last_login_at >= DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 1 ELSE 0 END) as active_90d'),
                DB::raw('SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_30d'),
            ])
            ->first();

        $totalUsers = (int) $userStats->total_users;
        $active90d = (int) $userStats->active_90d;
        $new30d = (int) $userStats->new_30d;

        // Engagement: users who traded in last 30 days
        $activeTraders = (int) DB::table(DB::raw(
            "(SELECT sender_id as user_id FROM transactions WHERE tenant_id = {$tenantId} AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              UNION
              SELECT receiver_id as user_id FROM transactions WHERE tenant_id = {$tenantId} AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) t"
        ))->distinct()->count('user_id');

        // Reciprocity score
        $balanceStats = DB::table(DB::raw(
            "(SELECT u.id,
                COALESCE((SELECT SUM(amount) FROM transactions WHERE sender_id = u.id AND tenant_id = {$tenantId}), 0) as given,
                COALESCE((SELECT SUM(amount) FROM transactions WHERE receiver_id = u.id AND tenant_id = {$tenantId}), 0) as received
              FROM users u WHERE u.tenant_id = {$tenantId} AND u.status = 'active'
              HAVING given > 0 OR received > 0) user_balance"
        ))->select(DB::raw('AVG(ABS(given - received) / GREATEST(given + received, 1)) as imbalance_ratio'))->first();

        $imbalanceRatio = (float) ($balanceStats->imbalance_ratio ?? 0);
        $reciprocityScore = round(1 - $imbalanceRatio, 2);

        // New member activation
        $activated = 0;
        if ($new30d > 0) {
            $activated = (int) DB::table('users as u')
                ->where('u.tenant_id', $tenantId)
                ->where('u.created_at', '>=', DB::raw('DATE_SUB(NOW(), INTERVAL 30 DAY)'))
                ->whereExists(function ($q) use ($tenantId) {
                    $q->select(DB::raw(1))
                      ->from('transactions as t')
                      ->where('t.tenant_id', $tenantId)
                      ->where(function ($sq) {
                          $sq->whereColumn('t.sender_id', 'u.id')
                             ->orWhereColumn('t.receiver_id', 'u.id');
                      });
                })
                ->count();
        }

        // Network density
        $totalConnections = (int) DB::table('connections')
            ->where('tenant_id', $tenantId)
            ->where('status', 'accepted')
            ->count();
        $possibleConnections = max($totalUsers * ($totalUsers - 1) / 2, 1);

        return [
            'total_users' => $totalUsers,
            'active_users_90d' => $active90d,
            'new_users_30d' => $new30d,
            'active_traders_30d' => $activeTraders,
            'engagement_rate' => $totalUsers > 0 ? round($activeTraders / $totalUsers, 3) : 0,
            'retention_rate' => $totalUsers > 0 ? round($active90d / $totalUsers, 3) : 0,
            'reciprocity_score' => $reciprocityScore,
            'activation_rate' => $new30d > 0 ? round($activated / $new30d, 3) : 0,
            'network_density' => round($totalConnections / $possibleConnections, 4),
            'total_connections' => $totalConnections,
        ];
    }

    /**
     * Get impact timeline (monthly breakdown).
     */
    public function getImpactTimeline(int $months = 12): array
    {
        $tenantId = TenantContext::getId();

        $timeline = DB::table('transactions')
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', DB::raw("DATE_SUB(NOW(), INTERVAL {$months} MONTH)"))
            ->select([
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
                DB::raw('COALESCE(SUM(amount), 0) as hours_exchanged'),
                DB::raw('COUNT(*) as transactions'),
            ])
            ->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"))
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => [
                'month' => $row->month,
                'hours_exchanged' => round((float) $row->hours_exchanged, 1),
                'transactions' => (int) $row->transactions,
            ])
            ->all();

        // Monthly new users
        $usersByMonth = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', DB::raw("DATE_SUB(NOW(), INTERVAL {$months} MONTH)"))
            ->select([
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
                DB::raw('COUNT(*) as new_users'),
            ])
            ->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"))
            ->pluck('new_users', 'month')
            ->all();

        foreach ($timeline as &$entry) {
            $entry['new_users'] = $usersByMonth[$entry['month']] ?? 0;
        }

        return $timeline;
    }

    /**
     * Get tenant report configuration.
     */
    public function getReportConfig(): array
    {
        $tenantId = TenantContext::getId();

        $tenant = DB::table('tenants')
            ->where('id', $tenantId)
            ->select('name', 'slug', 'configuration')
            ->first();

        $config = json_decode($tenant->configuration ?? '{}', true) ?: [];
        $settings = $config['settings'] ?? [];

        return [
            'tenant_name' => $tenant->name ?? 'Community',
            'tenant_slug' => $tenant->slug ?? '',
            'logo_url' => $config['logo_url'] ?? null,
            'hourly_value' => (float) ($settings['impact_hourly_value'] ?? self::DEFAULT_HOURLY_VALUE),
            'social_multiplier' => (float) ($settings['impact_social_multiplier'] ?? self::DEFAULT_SOCIAL_MULTIPLIER),
        ];
    }
}
