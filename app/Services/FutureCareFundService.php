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
 * Builds the "Future Care Fund" (Zeitvorsorge) summary for a member.
 *
 * Frames banked timebank hours as a 4th-pillar pension provision: hours
 * given to neighbours and partner organisations are saved into a personal
 * fund that can be redeemed for future care. Surfaces the reciprocity
 * balance — given vs received — as a friendly sociological metric.
 *
 * All queries are tenant-scoped via the supplied tenantId.
 */
class FutureCareFundService
{
    public function __construct(
        private readonly CaringCommunityWorkflowPolicyService $policyService,
    ) {
    }

    /**
     * Build a complete future-care-fund summary for the given member.
     *
     * @return array<string, mixed>
     */
    public function summary(int $tenantId, int $userId): array
    {
        $policy        = $this->policyService->get($tenantId);
        $hourValueChf  = (int) $policy['default_hour_value_chf'];

        $given    = $this->lifetimeGiven($tenantId, $userId);
        $received = $this->lifetimeReceived($tenantId, $userId);

        $lifetimeGiven    = round((float) $given['hours'], 2);
        $lifetimeReceived = round((float) $received['hours'], 2);
        $netBalance       = round($lifetimeGiven - $lifetimeReceived, 2);

        $reciprocityRatio = $lifetimeGiven > 0
            ? min(2.0, round($lifetimeReceived / $lifetimeGiven, 3))
            : ($lifetimeReceived > 0 ? 2.0 : 0.0);

        $monthRange = [
            date('Y-m-01'),
            date('Y-m-t'),
        ];
        $thisMonthGiven    = $this->givenInRange($tenantId, $userId, $monthRange[0], $monthRange[1]);
        $thisMonthReceived = $this->receivedInRange($tenantId, $userId, $monthRange[0], $monthRange[1]);

        $activeMonths = $this->countActiveMonths(
            (string) ($given['first_date'] ?? ''),
            (string) ($received['first_date'] ?? '')
        );

        $firstContribution = $this->earliestDate(
            (string) ($given['first_date'] ?? ''),
            (string) ($received['first_date'] ?? '')
        );

        return [
            'total_banked_hours'          => $lifetimeGiven,
            'hours_received'              => $lifetimeReceived,
            'net_balance'                 => $netBalance,
            'chf_value_estimate'          => round($netBalance * $hourValueChf, 2),
            'hour_value_chf'              => $hourValueChf,
            'lifetime_given'              => $lifetimeGiven,
            'lifetime_received'           => $lifetimeReceived,
            'reciprocity_ratio'           => $reciprocityRatio,
            'first_contribution_date'     => $firstContribution,
            'active_months'               => $activeMonths,
            'partner_organisations_helped' => $this->partnerOrganisationsHelped($tenantId, $userId),
            'this_month_hours_given'      => round((float) $thisMonthGiven, 2),
            'this_month_hours_received'   => round((float) $thisMonthReceived, 2),
            'by_year'                     => $this->byYear($tenantId, $userId),
        ];
    }

    /**
     * Lifetime hours the member has GIVEN — combines:
     *   - approved vol_logs where they were the volunteer (user_id)
     *   - completed transactions where they were the SENDER (paying with hours)
     *
     * @return array{hours: float, first_date: string|null}
     */
    private function lifetimeGiven(int $tenantId, int $userId): array
    {
        $totalHours = 0.0;
        $firstDates = [];

        if (Schema::hasTable('vol_logs')) {
            $row = DB::selectOne(
                "SELECT COALESCE(SUM(hours), 0) AS total_hours, MIN(date_logged) AS first_date
                 FROM vol_logs
                 WHERE tenant_id = ?
                   AND user_id = ?
                   AND status = 'approved'",
                [$tenantId, $userId]
            );
            if ($row) {
                $totalHours += (float) ($row->total_hours ?? 0);
                if (!empty($row->first_date)) {
                    $firstDates[] = (string) $row->first_date;
                }
            }
        }

        // Note: in this codebase a wallet "send" means the member spent hours
        // (consumed care). We therefore deliberately do NOT count outbound
        // transactions as "given" — care given is recorded through vol_logs
        // (by partner organisations and self-logged hours). Wallet receives
        // are counted in lifetimeReceived below.

        return [
            'hours'      => $totalHours,
            'first_date' => $this->minDate($firstDates),
        ];
    }

    /**
     * Lifetime hours the member has RECEIVED — combines:
     *   - completed transactions where they were the SENDER (they paid with
     *     hours, meaning they received help)
     *   - support relationships where they are the recipient (vol_logs
     *     attached to those relationships, where the supporter logged hours)
     *
     * @return array{hours: float, first_date: string|null}
     */
    private function lifetimeReceived(int $tenantId, int $userId): array
    {
        $totalHours = 0.0;
        $firstDates = [];

        // Transactions where the member spent hours (received care).
        $txRow = DB::selectOne(
            "SELECT COALESCE(SUM(amount), 0) AS total_hours, MIN(created_at) AS first_date
             FROM transactions
             WHERE tenant_id = ?
               AND sender_id = ?
               AND status = 'completed'",
            [$tenantId, $userId]
        );
        if ($txRow) {
            $totalHours += (float) ($txRow->total_hours ?? 0);
            if (!empty($txRow->first_date)) {
                $firstDates[] = substr((string) $txRow->first_date, 0, 10);
            }
        }

        // Support-relationship hours where the member is the RECIPIENT.
        if (
            Schema::hasTable('vol_logs')
            && Schema::hasColumn('vol_logs', 'caring_support_relationship_id')
            && Schema::hasTable('caring_support_relationships')
        ) {
            $row = DB::selectOne(
                "SELECT COALESCE(SUM(vl.hours), 0) AS total_hours, MIN(vl.date_logged) AS first_date
                 FROM vol_logs vl
                 INNER JOIN caring_support_relationships csr
                         ON csr.id = vl.caring_support_relationship_id
                        AND csr.tenant_id = vl.tenant_id
                 WHERE vl.tenant_id = ?
                   AND csr.recipient_id = ?
                   AND vl.status = 'approved'",
                [$tenantId, $userId]
            );
            if ($row) {
                $totalHours += (float) ($row->total_hours ?? 0);
                if (!empty($row->first_date)) {
                    $firstDates[] = (string) $row->first_date;
                }
            }
        }

        return [
            'hours'      => $totalHours,
            'first_date' => $this->minDate($firstDates),
        ];
    }

    private function givenInRange(int $tenantId, int $userId, string $startDate, string $endDate): float
    {
        if (!Schema::hasTable('vol_logs')) {
            return 0.0;
        }

        $row = DB::selectOne(
            "SELECT COALESCE(SUM(hours), 0) AS total_hours
             FROM vol_logs
             WHERE tenant_id = ?
               AND user_id = ?
               AND status = 'approved'
               AND date_logged BETWEEN ? AND ?",
            [$tenantId, $userId, $startDate, $endDate]
        );

        return (float) ($row->total_hours ?? 0);
    }

    private function receivedInRange(int $tenantId, int $userId, string $startDate, string $endDate): float
    {
        $total = 0.0;

        $txRow = DB::selectOne(
            "SELECT COALESCE(SUM(amount), 0) AS total_hours
             FROM transactions
             WHERE tenant_id = ?
               AND sender_id = ?
               AND status = 'completed'
               AND DATE(created_at) BETWEEN ? AND ?",
            [$tenantId, $userId, $startDate, $endDate]
        );
        if ($txRow) {
            $total += (float) ($txRow->total_hours ?? 0);
        }

        if (
            Schema::hasTable('vol_logs')
            && Schema::hasColumn('vol_logs', 'caring_support_relationship_id')
            && Schema::hasTable('caring_support_relationships')
        ) {
            $row = DB::selectOne(
                "SELECT COALESCE(SUM(vl.hours), 0) AS total_hours
                 FROM vol_logs vl
                 INNER JOIN caring_support_relationships csr
                         ON csr.id = vl.caring_support_relationship_id
                        AND csr.tenant_id = vl.tenant_id
                 WHERE vl.tenant_id = ?
                   AND csr.recipient_id = ?
                   AND vl.status = 'approved'
                   AND vl.date_logged BETWEEN ? AND ?",
                [$tenantId, $userId, $startDate, $endDate]
            );
            if ($row) {
                $total += (float) ($row->total_hours ?? 0);
            }
        }

        return $total;
    }

    private function partnerOrganisationsHelped(int $tenantId, int $userId): int
    {
        if (!Schema::hasTable('vol_logs') || !Schema::hasColumn('vol_logs', 'organization_id')) {
            return 0;
        }

        $row = DB::selectOne(
            "SELECT COUNT(DISTINCT organization_id) AS org_count
             FROM vol_logs
             WHERE tenant_id = ?
               AND user_id = ?
               AND status = 'approved'
               AND organization_id IS NOT NULL",
            [$tenantId, $userId]
        );

        return (int) ($row->org_count ?? 0);
    }

    /**
     * @return list<array{year: int, hours_given: float, hours_received: float}>
     */
    private function byYear(int $tenantId, int $userId): array
    {
        $years = [];

        if (Schema::hasTable('vol_logs')) {
            $given = DB::select(
                "SELECT YEAR(date_logged) AS yr, COALESCE(SUM(hours), 0) AS hrs
                 FROM vol_logs
                 WHERE tenant_id = ?
                   AND user_id = ?
                   AND status = 'approved'
                 GROUP BY yr",
                [$tenantId, $userId]
            );
            foreach ($given as $row) {
                $yr = (int) $row->yr;
                $years[$yr] ??= ['year' => $yr, 'hours_given' => 0.0, 'hours_received' => 0.0];
                $years[$yr]['hours_given'] += (float) $row->hrs;
            }
        }

        $txRows = DB::select(
            "SELECT YEAR(created_at) AS yr, COALESCE(SUM(amount), 0) AS hrs
             FROM transactions
             WHERE tenant_id = ?
               AND sender_id = ?
               AND status = 'completed'
             GROUP BY yr",
            [$tenantId, $userId]
        );
        foreach ($txRows as $row) {
            $yr = (int) $row->yr;
            $years[$yr] ??= ['year' => $yr, 'hours_given' => 0.0, 'hours_received' => 0.0];
            $years[$yr]['hours_received'] += (float) $row->hrs;
        }

        if (
            Schema::hasTable('vol_logs')
            && Schema::hasColumn('vol_logs', 'caring_support_relationship_id')
            && Schema::hasTable('caring_support_relationships')
        ) {
            $rxRows = DB::select(
                "SELECT YEAR(vl.date_logged) AS yr, COALESCE(SUM(vl.hours), 0) AS hrs
                 FROM vol_logs vl
                 INNER JOIN caring_support_relationships csr
                         ON csr.id = vl.caring_support_relationship_id
                        AND csr.tenant_id = vl.tenant_id
                 WHERE vl.tenant_id = ?
                   AND csr.recipient_id = ?
                   AND vl.status = 'approved'
                 GROUP BY yr",
                [$tenantId, $userId]
            );
            foreach ($rxRows as $row) {
                $yr = (int) $row->yr;
                $years[$yr] ??= ['year' => $yr, 'hours_given' => 0.0, 'hours_received' => 0.0];
                $years[$yr]['hours_received'] += (float) $row->hrs;
            }
        }

        // Sort newest year first, round values.
        krsort($years);
        return array_map(static fn (array $y): array => [
            'year'           => $y['year'],
            'hours_given'    => round((float) $y['hours_given'], 2),
            'hours_received' => round((float) $y['hours_received'], 2),
        ], array_values($years));
    }

    private function countActiveMonths(string $givenFirst, string $receivedFirst): int
    {
        $first = $this->earliestDate($givenFirst, $receivedFirst);
        if ($first === null) {
            return 0;
        }

        $start = strtotime($first);
        if ($start === false) {
            return 0;
        }

        $now    = time();
        $months = (int) floor(($now - $start) / (60 * 60 * 24 * 30.4375));
        return max(0, $months);
    }

    /**
     * @param  list<string>  $dates
     */
    private function minDate(array $dates): ?string
    {
        $valid = array_filter($dates, static fn (string $d): bool => $d !== '');
        if (empty($valid)) {
            return null;
        }

        sort($valid);
        return $valid[0];
    }

    private function earliestDate(string $a, string $b): ?string
    {
        $candidates = array_filter([$a, $b], static fn (string $d): bool => $d !== '');
        if (empty($candidates)) {
            return null;
        }
        sort($candidates);
        return $candidates[0];
    }
}
