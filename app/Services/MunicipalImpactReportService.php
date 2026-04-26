<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Builds municipal/KISS impact reporting from existing tenant-scoped activity.
 */
class MunicipalImpactReportService
{
    private const DEFAULT_HOUR_VALUE = 35.0;
    private const DEFAULT_SOCIAL_MULTIPLIER = 2.8;
    private const DEFAULT_CURRENCY = 'CHF';

    public function __construct(private readonly CaringCommunityWorkflowPolicyService $policyService)
    {
    }

    public function summary(int $tenantId, array $filters = []): array
    {
        $policy = $this->policyService->get($tenantId);
        $range = $this->normaliseDateRange($filters, $policy);
        $hourConfig = $this->hourValueConfig($tenantId, $policy);

        $timebank = $this->timebankSummary($tenantId, $range);
        $volunteering = $this->volunteeringSummary($tenantId, $range);
        $members = $this->memberSummary($tenantId, $range);
        $organisations = $this->organisationSummary($tenantId, $range);
        $requests = $this->requestSummary($tenantId, $range);
        $categories = $this->supportCategories($tenantId, $range);
        $trends = $this->periodTrends($tenantId, $range);

        $verifiedHours = $volunteering['approved_hours'] + $timebank['completed_hours'];
        $directValue = round($verifiedHours * $hourConfig['hour_value'], 2);
        $socialValue = round($directValue * $hourConfig['social_multiplier'], 2);

        return [
            'period' => $range,
            'currency' => $hourConfig['currency'],
            'hour_value' => $hourConfig['hour_value'],
            'social_multiplier' => $hourConfig['social_multiplier'],
            'policy' => [
                'default_period' => $policy['municipal_report_default_period'],
                'include_social_value_estimate' => (bool) $policy['include_social_value_estimate'],
                'default_hour_value_chf' => (int) $policy['default_hour_value_chf'],
            ],
            'stats' => [
                'verified_hours' => round($verifiedHours, 1),
                'volunteer_hours' => round($volunteering['approved_hours'], 1),
                'timebank_hours' => round($timebank['completed_hours'], 1),
                'pending_hours' => round($volunteering['pending_hours'], 1),
                'active_members' => $members['active_members'],
                'new_members' => $members['new_members'],
                'participating_members' => $members['participating_members'],
                'trusted_organisations' => $organisations['trusted_organisations'],
                'active_opportunities' => $organisations['active_opportunities'],
                'support_requests' => $requests['support_requests'],
                'support_offers' => $requests['support_offers'],
                'direct_value' => $directValue,
                'social_value' => $policy['include_social_value_estimate'] ? $socialValue : 0.0,
                'total_value' => round($directValue + ($policy['include_social_value_estimate'] ? $socialValue : 0.0), 2),
            ],
            'categories' => $categories,
            'trends' => $trends,
            'report_pack' => [
                'csv_export' => '/api/v2/admin/reports/municipal_impact/export?format=csv',
                'pdf_export' => '/api/v2/admin/reports/municipal_impact/export?format=pdf',
                'source_reports' => [
                    'hours' => '/admin/reports/hours',
                    'members' => '/admin/reports/members',
                    'impact' => '/admin/impact-report',
                    'volunteering' => '/admin/volunteering',
                ],
            ],
        ];
    }

    public function exportData(int $tenantId, array $filters = []): array
    {
        $summary = $this->summary($tenantId, $filters);
        $stats = $summary['stats'];

        $rows = [
            ['Reporting Period', $summary['period']['from'] . ' to ' . $summary['period']['to'], 'Municipal/KISS reporting window'],
            ['Verified Hours', $stats['verified_hours'], 'Approved volunteer hours plus completed timebank transactions'],
            ['Volunteer Hours', $stats['volunteer_hours'], 'Approved volunteering logs'],
            ['Timebank Hours', $stats['timebank_hours'], 'Completed member-to-member transactions'],
            ['Pending Hours', $stats['pending_hours'], 'Volunteer logs awaiting coordinator review'],
            ['Active Members', $stats['active_members'], 'Approved members active in the reporting period'],
            ['New Members', $stats['new_members'], 'Approved members who joined in the reporting period'],
            ['Participating Members', $stats['participating_members'], 'Distinct members with verified hours or completed transactions'],
            ['Trusted Organisations', $stats['trusted_organisations'], 'Approved or active partner organisations'],
            ['Active Opportunities', $stats['active_opportunities'], 'Open volunteering opportunities'],
            ['Support Requests', $stats['support_requests'], 'Active request listings'],
            ['Support Offers', $stats['support_offers'], 'Active offer listings'],
            ['Direct Value', $stats['direct_value'], $summary['currency'] . ' value at ' . $summary['hour_value'] . ' per hour'],
            ['Social Value', $stats['social_value'], 'Direct value multiplied by ' . $summary['social_multiplier']],
            ['Total Value', $stats['total_value'], 'Direct plus social value'],
        ];

        foreach ($summary['categories'] as $category) {
            $rows[] = [
                'Support Category: ' . $category['name'],
                $category['hours'],
                $category['count'] . ' verified exchanges or logs',
            ];
        }

        foreach ($summary['trends'] as $trend) {
            $rows[] = [
                'Monthly Trend: ' . $trend['period'],
                $trend['verified_hours'],
                $trend['participants'] . ' participants, ' . $trend['activities'] . ' activities',
            ];
        }

        return [
            'headers' => ['Metric', 'Value', 'Notes'],
            'rows' => $rows,
        ];
    }

    private function normaliseDateRange(array $filters, array $policy): array
    {
        $to = !empty($filters['date_to']) ? (string) $filters['date_to'] : date('Y-m-d');
        if (!empty($filters['date_from'])) {
            return ['from' => (string) $filters['date_from'], 'to' => $to];
        }

        $from = match ($policy['municipal_report_default_period'] ?? 'last_90_days') {
            'last_30_days' => date('Y-m-d', strtotime($to . ' -30 days')),
            'year_to_date' => date('Y-01-01', strtotime($to)),
            'previous_quarter' => $this->previousQuarterStart($to),
            default => date('Y-m-d', strtotime($to . ' -90 days')),
        };

        if (($policy['municipal_report_default_period'] ?? '') === 'previous_quarter') {
            $to = $this->previousQuarterEnd($to);
        }

        return ['from' => $from, 'to' => $to];
    }

    private function hourValueConfig(int $tenantId, array $policy): array
    {
        $config = Schema::hasTable('social_value_config')
            ? DB::table('social_value_config')->where('tenant_id', $tenantId)->first()
            : null;

        return [
            'hour_value' => (float) ($config->hour_value_amount ?? $policy['default_hour_value_chf'] ?? self::DEFAULT_HOUR_VALUE),
            'social_multiplier' => (float) ($config->social_multiplier ?? self::DEFAULT_SOCIAL_MULTIPLIER),
            'currency' => (string) ($config->hour_value_currency ?? self::DEFAULT_CURRENCY),
        ];
    }

    private function previousQuarterStart(string $date): string
    {
        $timestamp = strtotime($date) ?: time();
        $quarter = (int) ceil((int) date('n', $timestamp) / 3);
        $previousQuarter = $quarter === 1 ? 4 : $quarter - 1;
        $year = (int) date('Y', $timestamp) - ($quarter === 1 ? 1 : 0);
        $month = (($previousQuarter - 1) * 3) + 1;

        return sprintf('%04d-%02d-01', $year, $month);
    }

    private function previousQuarterEnd(string $date): string
    {
        return date('Y-m-t', strtotime($this->previousQuarterStart($date) . ' +2 months'));
    }

    private function timebankSummary(int $tenantId, array $range): array
    {
        if (!Schema::hasTable('transactions')) {
            return ['completed_hours' => 0.0];
        }

        $row = DB::selectOne(
            "SELECT COALESCE(SUM(amount), 0) AS completed_hours
             FROM transactions
             WHERE tenant_id = ? AND status = 'completed' AND DATE(created_at) BETWEEN ? AND ?",
            [$tenantId, $range['from'], $range['to']]
        );

        return ['completed_hours' => (float) ($row->completed_hours ?? 0)];
    }

    private function volunteeringSummary(int $tenantId, array $range): array
    {
        if (!Schema::hasTable('vol_logs')) {
            return ['approved_hours' => 0.0, 'pending_hours' => 0.0];
        }

        $row = DB::selectOne(
            "SELECT
                COALESCE(SUM(CASE WHEN status = 'approved' THEN hours ELSE 0 END), 0) AS approved_hours,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN hours ELSE 0 END), 0) AS pending_hours
             FROM vol_logs
             WHERE tenant_id = ? AND date_logged BETWEEN ? AND ?",
            [$tenantId, $range['from'], $range['to']]
        );

        return [
            'approved_hours' => (float) ($row->approved_hours ?? 0),
            'pending_hours' => (float) ($row->pending_hours ?? 0),
        ];
    }

    private function memberSummary(int $tenantId, array $range): array
    {
        $activeMembers = (int) DB::selectOne(
            "SELECT COUNT(*) AS count
             FROM users
             WHERE tenant_id = ? AND is_approved = 1
                AND (last_active_at IS NULL OR DATE(last_active_at) BETWEEN ? AND ?)",
            [$tenantId, $range['from'], $range['to']]
        )->count;

        $newMembers = (int) DB::selectOne(
            "SELECT COUNT(*) AS count
             FROM users
             WHERE tenant_id = ? AND is_approved = 1 AND DATE(created_at) BETWEEN ? AND ?",
            [$tenantId, $range['from'], $range['to']]
        )->count;

        $participantIds = [];

        if (Schema::hasTable('vol_logs')) {
            $rows = DB::select(
                "SELECT DISTINCT user_id FROM vol_logs
                 WHERE tenant_id = ? AND status = 'approved' AND date_logged BETWEEN ? AND ?",
                [$tenantId, $range['from'], $range['to']]
            );
            foreach ($rows as $row) {
                $participantIds[(int) $row->user_id] = true;
            }
        }

        if (Schema::hasTable('transactions')) {
            $rows = DB::select(
                "SELECT DISTINCT sender_id, receiver_id FROM transactions
                 WHERE tenant_id = ? AND status = 'completed' AND DATE(created_at) BETWEEN ? AND ?",
                [$tenantId, $range['from'], $range['to']]
            );
            foreach ($rows as $row) {
                if ($row->sender_id) {
                    $participantIds[(int) $row->sender_id] = true;
                }
                if ($row->receiver_id) {
                    $participantIds[(int) $row->receiver_id] = true;
                }
            }
        }

        return [
            'active_members' => $activeMembers,
            'new_members' => $newMembers,
            'participating_members' => count($participantIds),
        ];
    }

    private function organisationSummary(int $tenantId, array $range): array
    {
        $trustedOrganisations = 0;
        $activeOpportunities = 0;

        if (Schema::hasTable('vol_organizations')) {
            $trustedOrganisations = (int) DB::selectOne(
                "SELECT COUNT(*) AS count FROM vol_organizations
                 WHERE tenant_id = ? AND status IN ('approved', 'active')",
                [$tenantId]
            )->count;
        }

        if (Schema::hasTable('vol_opportunities')) {
            $activeOpportunities = (int) DB::selectOne(
                "SELECT COUNT(*) AS count FROM vol_opportunities
                 WHERE tenant_id = ? AND status IN ('active', 'published', 'open')
                    AND DATE(created_at) <= ?",
                [$tenantId, $range['to']]
            )->count;
        }

        return [
            'trusted_organisations' => $trustedOrganisations,
            'active_opportunities' => $activeOpportunities,
        ];
    }

    private function requestSummary(int $tenantId, array $range): array
    {
        if (!Schema::hasTable('listings')) {
            return ['support_requests' => 0, 'support_offers' => 0];
        }

        $row = DB::selectOne(
            "SELECT
                SUM(CASE WHEN type IN ('request', 'need') THEN 1 ELSE 0 END) AS support_requests,
                SUM(CASE WHEN type IN ('offer', 'service') THEN 1 ELSE 0 END) AS support_offers
             FROM listings
             WHERE tenant_id = ? AND status = 'active' AND DATE(created_at) <= ?",
            [$tenantId, $range['to']]
        );

        return [
            'support_requests' => (int) ($row->support_requests ?? 0),
            'support_offers' => (int) ($row->support_offers ?? 0),
        ];
    }

    private function supportCategories(int $tenantId, array $range): array
    {
        $rows = [];

        if (Schema::hasTable('transactions')) {
            $rows = DB::select(
                "SELECT COALESCE(c.name, 'Uncategorized') AS name,
                        COALESCE(SUM(t.amount), 0) AS hours,
                        COUNT(*) AS count
                 FROM transactions t
                 LEFT JOIN listings l ON l.id = t.listing_id AND l.tenant_id = t.tenant_id
                 LEFT JOIN categories c ON c.id = l.category_id AND c.tenant_id = t.tenant_id
                 WHERE t.tenant_id = ? AND t.status = 'completed' AND DATE(t.created_at) BETWEEN ? AND ?
                 GROUP BY COALESCE(c.name, 'Uncategorized')
                 ORDER BY hours DESC
                 LIMIT 8",
                [$tenantId, $range['from'], $range['to']]
            );
        }

        return array_map(fn($row) => [
            'name' => (string) $row->name,
            'hours' => round((float) $row->hours, 1),
            'count' => (int) $row->count,
        ], $rows);
    }

    private function periodTrends(int $tenantId, array $range): array
    {
        $trendMap = [];

        if (Schema::hasTable('transactions')) {
            $rows = DB::select(
                "SELECT DATE_FORMAT(created_at, '%Y-%m') AS period,
                        COALESCE(SUM(amount), 0) AS hours,
                        COUNT(*) AS activities,
                        COUNT(DISTINCT sender_id) + COUNT(DISTINCT receiver_id) AS participants
                 FROM transactions
                 WHERE tenant_id = ? AND status = 'completed' AND DATE(created_at) BETWEEN ? AND ?
                 GROUP BY DATE_FORMAT(created_at, '%Y-%m')",
                [$tenantId, $range['from'], $range['to']]
            );
            foreach ($rows as $row) {
                $trendMap[$row->period] = [
                    'period' => $row->period,
                    'verified_hours' => (float) $row->hours,
                    'activities' => (int) $row->activities,
                    'participants' => (int) $row->participants,
                ];
            }
        }

        if (Schema::hasTable('vol_logs')) {
            $rows = DB::select(
                "SELECT DATE_FORMAT(date_logged, '%Y-%m') AS period,
                        COALESCE(SUM(hours), 0) AS hours,
                        COUNT(*) AS activities,
                        COUNT(DISTINCT user_id) AS participants
                 FROM vol_logs
                 WHERE tenant_id = ? AND status = 'approved' AND date_logged BETWEEN ? AND ?
                 GROUP BY DATE_FORMAT(date_logged, '%Y-%m')",
                [$tenantId, $range['from'], $range['to']]
            );
            foreach ($rows as $row) {
                $period = $row->period;
                $trendMap[$period] ??= [
                    'period' => $period,
                    'verified_hours' => 0.0,
                    'activities' => 0,
                    'participants' => 0,
                ];
                $trendMap[$period]['verified_hours'] += (float) $row->hours;
                $trendMap[$period]['activities'] += (int) $row->activities;
                $trendMap[$period]['participants'] += (int) $row->participants;
            }
        }

        ksort($trendMap);

        return array_map(fn($row) => [
            'period' => $row['period'],
            'verified_hours' => round((float) $row['verified_hours'], 1),
            'activities' => (int) $row['activities'],
            'participants' => (int) $row['participants'],
        ], array_values($trendMap));
    }
}
