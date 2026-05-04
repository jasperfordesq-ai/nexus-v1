<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\CaringCommunity;

use App\Core\TenantContext;
use App\Services\CaringCommunityWorkflowPolicyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Proactive coordinator alerts — the "before it becomes a municipal emergency"
 * layer of Tom Debus's AI/Daten pillar. Each signal answers a coordinator
 * question that retrospective stats never did: who is slipping, who is
 * overloaded, where is supply running below demand.
 *
 * All signals are tenant-scoped, schema-guarded, and only included if their
 * count is non-zero so the dashboard stays focused.
 */
class CaringCommunityAlertService
{
    public function __construct(
        private readonly CaringCommunityWorkflowPolicyService $policyService,
    ) {
    }

    /**
     * @return list<array{
     *     id: string,
     *     severity: 'info'|'warning'|'critical',
     *     title: string,
     *     message: string,
     *     count: int,
     *     action_label: string|null,
     *     action_url: string|null,
     * }>
     */
    public function activeAlerts(): array
    {
        $tenantId = TenantContext::getId();
        $alerts = [];

        $alerts[] = $this->recipientsWithoutTandem($tenantId);
        $alerts[] = $this->inactiveMembers($tenantId);
        $alerts[] = $this->overdueReviews($tenantId);
        $alerts[] = $this->coordinatorsOverloaded($tenantId);
        $alerts[] = $this->retentionDropping($tenantId);
        $alerts[] = $this->overdueCheckIns($tenantId);
        $alerts[] = $this->lowSupply($tenantId);

        // Drop zero-count alerts so the dashboard surfaces only what's actionable.
        return array_values(array_filter(
            $alerts,
            static fn (?array $a): bool => $a !== null && ($a['count'] ?? 0) > 0,
        ));
    }

    /**
     * Users who received support hours in the last 6 months but have no
     * active recurring relationship — coordinator should pair them with
     * a regular supporter.
     *
     * @return array{id:string,severity:string,title:string,message:string,count:int,action_label:?string,action_url:?string}|null
     */
    private function recipientsWithoutTandem(int $tenantId): ?array
    {
        if (!Schema::hasTable('vol_logs') || !Schema::hasColumn('vol_logs', 'support_recipient_id')) {
            return null;
        }

        $row = DB::selectOne(
            "SELECT COUNT(DISTINCT vl.support_recipient_id) AS c
             FROM vol_logs vl
             WHERE vl.tenant_id = ?
                AND vl.status = 'approved'
                AND vl.support_recipient_id IS NOT NULL
                AND vl.date_logged >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                AND NOT EXISTS (
                    SELECT 1
                    FROM caring_support_relationships csr
                    WHERE csr.tenant_id = vl.tenant_id
                       AND csr.recipient_id = vl.support_recipient_id
                       AND csr.status = 'active'
                )",
            [$tenantId]
        );
        $count = (int) ($row->c ?? 0);

        return [
            'id' => 'recipients_without_tandem',
            'severity' => 'warning',
            'title' => __('caring_community.alerts.recipients_without_tandem_title'),
            'message' => __('caring_community.alerts.recipients_without_tandem_message'),
            'count' => $count,
            'action_label' => 'See suggestions',
            'action_url' => '/admin/caring-community/workflow#tandem-suggestions',
        ];
    }

    private function inactiveMembers(int $tenantId): ?array
    {
        if (!Schema::hasTable('vol_logs')) {
            return null;
        }

        $row = DB::selectOne(
            "SELECT COUNT(DISTINCT u.id) AS c
             FROM users u
             WHERE u.tenant_id = ?
                AND u.status = 'active'
                AND EXISTS (
                    SELECT 1 FROM vol_logs vl
                    WHERE vl.tenant_id = u.tenant_id
                       AND vl.user_id = u.id
                       AND vl.status = 'approved'
                       AND vl.date_logged >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                )
                AND NOT EXISTS (
                    SELECT 1 FROM vol_logs vl2
                    WHERE vl2.tenant_id = u.tenant_id
                       AND vl2.user_id = u.id
                       AND vl2.date_logged >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                )",
            [$tenantId]
        );
        $count = (int) ($row->c ?? 0);

        return [
            'id' => 'inactive_members',
            'severity' => 'info',
            'title' => __('caring_community.alerts.inactive_members_title'),
            'message' => __('caring_community.alerts.inactive_members_message'),
            'count' => $count,
            'action_label' => 'View members',
            'action_url' => '/admin/members',
        ];
    }

    private function overdueReviews(int $tenantId): ?array
    {
        if (!Schema::hasTable('vol_logs')) {
            return null;
        }

        $policy = $this->policyService->get($tenantId);
        $sla = max(1, (int) ($policy['review_sla_days'] ?? 7));

        $row = DB::selectOne(
            "SELECT COUNT(*) AS c
             FROM vol_logs
             WHERE tenant_id = ?
                AND status = 'pending'
                AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$tenantId, $sla]
        );
        $count = (int) ($row->c ?? 0);

        return [
            'id' => 'overdue_reviews',
            'severity' => 'warning',
            'title' => __('caring_community.alerts.overdue_reviews_title'),
            'message' => __('caring_community.alerts.overdue_reviews_message', ['sla' => $sla]),
            'count' => $count,
            'action_label' => 'Review now',
            'action_url' => '/admin/caring-community/workflow',
        ];
    }

    private function coordinatorsOverloaded(int $tenantId): ?array
    {
        if (!Schema::hasTable('vol_logs') || !Schema::hasColumn('vol_logs', 'assigned_to')) {
            return null;
        }

        $row = DB::selectOne(
            "SELECT COUNT(*) AS c FROM (
                SELECT assigned_to
                FROM vol_logs
                WHERE tenant_id = ?
                   AND status = 'pending'
                   AND assigned_to IS NOT NULL
                GROUP BY assigned_to
                HAVING COUNT(*) > 10
            ) t",
            [$tenantId]
        );
        $count = (int) ($row->c ?? 0);

        return [
            'id' => 'coordinators_overloaded',
            'severity' => 'critical',
            'title' => __('caring_community.alerts.coordinators_overloaded_title'),
            'message' => __('caring_community.alerts.coordinators_overloaded_message'),
            'count' => $count,
            'action_label' => 'Reassign reviews',
            'action_url' => '/admin/caring-community/workflow',
        ];
    }

    private function retentionDropping(int $tenantId): ?array
    {
        if (!Schema::hasTable('vol_logs')) {
            return null;
        }

        // Active members this month (so far)
        $currentRow = DB::selectOne(
            "SELECT COUNT(DISTINCT user_id) AS c
             FROM vol_logs
             WHERE tenant_id = ?
                AND status = 'approved'
                AND DATE_FORMAT(date_logged, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')",
            [$tenantId]
        );
        $current = (int) ($currentRow->c ?? 0);

        // Average of the prior 3 months
        $rows = DB::select(
            "SELECT DATE_FORMAT(date_logged, '%Y-%m') AS bucket,
                    COUNT(DISTINCT user_id) AS c
             FROM vol_logs
             WHERE tenant_id = ?
                AND status = 'approved'
                AND date_logged >= DATE_SUB(DATE_FORMAT(NOW(), '%Y-%m-01'), INTERVAL 3 MONTH)
                AND date_logged < DATE_FORMAT(NOW(), '%Y-%m-01')
             GROUP BY bucket",
            [$tenantId]
        );

        if (empty($rows)) {
            return null;
        }

        $vals = array_map(static fn ($r) => (int) $r->c, $rows);
        $avg = array_sum($vals) / count($vals);

        if ($avg < 1.0) {
            return null;
        }

        $threshold = $avg * 0.85;
        if ($current >= $threshold) {
            return null;
        }

        $drop = max(0, (int) round($threshold - $current));

        return [
            'id' => 'retention_dropping',
            'severity' => 'warning',
            'title' => 'Active member count is sliding',
            'message' => sprintf(
                'This month\'s active members (%d) are below 85%% of the recent 3-month average (%.0f). Consider an outreach nudge.',
                $current,
                $avg,
            ),
            'count' => $drop,
            'action_label' => 'Open reports',
            'action_url' => '/admin/reports/members',
        ];
    }

    private function overdueCheckIns(int $tenantId): ?array
    {
        if (!Schema::hasTable('caring_support_relationships')) {
            return null;
        }

        $row = DB::selectOne(
            "SELECT COUNT(*) AS c
             FROM caring_support_relationships
             WHERE tenant_id = ?
                AND status = 'active'
                AND next_check_in_at IS NOT NULL
                AND next_check_in_at < NOW()",
            [$tenantId]
        );
        $count = (int) ($row->c ?? 0);

        return [
            'id' => 'overdue_check_ins',
            'severity' => 'warning',
            'title' => __('caring_community.alerts.overdue_check_ins_title'),
            'message' => __('caring_community.alerts.overdue_check_ins_message'),
            'count' => $count,
            'action_label' => 'View tandems',
            'action_url' => '/admin/caring-community/workflow#support-relationships',
        ];
    }

    private function lowSupply(int $tenantId): ?array
    {
        if (!Schema::hasTable('listings')) {
            return null;
        }

        $row = DB::selectOne(
            "SELECT COUNT(*) AS c FROM (
                SELECT category_id,
                       SUM(CASE WHEN type IN ('offer', 'service') THEN 1 ELSE 0 END) AS offers,
                       SUM(CASE WHEN type IN ('request', 'need') THEN 1 ELSE 0 END) AS requests
                FROM listings
                WHERE tenant_id = ?
                   AND status = 'active'
                   AND category_id IS NOT NULL
                GROUP BY category_id
                HAVING offers < requests
            ) t",
            [$tenantId]
        );
        $count = (int) ($row->c ?? 0);

        return [
            'id' => 'low_supply',
            'severity' => 'info',
            'title' => __('caring_community.alerts.low_supply_title'),
            'message' => __('caring_community.alerts.low_supply_message'),
            'count' => $count,
            'action_label' => 'View listings',
            'action_url' => '/admin/listings',
        ];
    }
}
