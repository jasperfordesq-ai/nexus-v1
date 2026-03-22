<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * JobBiasAuditService — Generates hiring process analytics to detect potential bias.
 *
 * Focuses on process metrics (funnel, rejection rates, time-in-stage, source effectiveness)
 * rather than demographic data, to identify patterns that could indicate bias.
 * All queries are tenant-scoped.
 */
class JobBiasAuditService
{
    /** Pipeline stages in order */
    private const STAGES = ['applied', 'screening', 'interview', 'offer', 'accepted'];

    /**
     * Generate a comprehensive bias audit report.
     *
     * @param int $tenantId
     * @param int|null $jobId Optional specific job to audit
     * @param string|null $dateFrom Start date (Y-m-d)
     * @param string|null $dateTo End date (Y-m-d)
     * @return array Report data
     */
    public function generateReport(int $tenantId, ?int $jobId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $dateFrom = $dateFrom ?: date('Y-m-d', strtotime('-12 months'));
        $dateTo = $dateTo ?: date('Y-m-d');

        return [
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'total_applications' => $this->getTotalApplications($tenantId, $jobId, $dateFrom, $dateTo),
            'funnel' => $this->buildFunnel($tenantId, $jobId, $dateFrom, $dateTo),
            'rejection_rates' => $this->getRejectionRates($tenantId, $jobId, $dateFrom, $dateTo),
            'avg_time_in_stage' => $this->getAvgTimeInStage($tenantId, $jobId, $dateFrom, $dateTo),
            'skills_match_correlation' => $this->getSkillsMatchCorrelation($tenantId, $jobId, $dateFrom, $dateTo),
            'source_effectiveness' => $this->getSourceEffectiveness($tenantId, $jobId, $dateFrom, $dateTo),
            'hiring_velocity_days' => $this->getHiringVelocity($tenantId, $jobId, $dateFrom, $dateTo),
        ];
    }

    /**
     * Get total number of applications in the period.
     */
    private function getTotalApplications(int $tenantId, ?int $jobId, string $dateFrom, string $dateTo): int
    {
        $query = DB::table('job_vacancy_applications as a')
            ->join('job_vacancies as j', 'a.vacancy_id', '=', 'j.id')
            ->where('j.tenant_id', $tenantId)
            ->whereBetween('a.created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($jobId) {
            $query->where('a.vacancy_id', $jobId);
        }

        return (int) $query->count();
    }

    /**
     * Build the application funnel showing counts at each stage.
     */
    private function buildFunnel(int $tenantId, ?int $jobId, string $dateFrom, string $dateTo): array
    {
        $total = $this->getTotalApplications($tenantId, $jobId, $dateFrom, $dateTo);
        if ($total === 0) {
            return array_map(fn($stage) => [
                'stage' => $stage,
                'count' => 0,
                'percentage' => 0,
            ], self::STAGES);
        }

        $funnel = [];

        foreach (self::STAGES as $stage) {
            $query = DB::table('job_vacancy_applications as a')
                ->join('job_vacancies as j', 'a.vacancy_id', '=', 'j.id')
                ->where('j.tenant_id', $tenantId)
                ->whereBetween('a.created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

            if ($jobId) {
                $query->where('a.vacancy_id', $jobId);
            }

            if ($stage === 'applied') {
                // All applications count as "applied"
                $count = $total;
            } else {
                // Count applications that reached this stage (current status or passed through it)
                $reachedStages = array_slice(self::STAGES, array_search($stage, self::STAGES));
                // Also include 'rejected' as they may have been rejected at this or later stage
                $count = (int) $query->where(function ($q) use ($stage, $reachedStages) {
                    $q->whereIn('a.status', $reachedStages)
                      ->orWhere(function ($q2) use ($stage) {
                          // Include rejected applications that reached this stage via history
                          $q2->where('a.status', 'rejected')
                             ->whereExists(function ($sub) use ($stage) {
                                 $sub->select(DB::raw(1))
                                     ->from('job_application_history')
                                     ->whereColumn('job_application_history.application_id', 'a.id')
                                     ->where('job_application_history.to_status', $stage);
                             });
                      });
                })->count();
            }

            $funnel[] = [
                'stage' => $stage,
                'count' => $count,
                'percentage' => round(($count / $total) * 100, 1),
            ];
        }

        return $funnel;
    }

    /**
     * Calculate rejection rates at each pipeline stage.
     */
    private function getRejectionRates(int $tenantId, ?int $jobId, string $dateFrom, string $dateTo): array
    {
        $rates = [];

        foreach (self::STAGES as $stage) {
            if ($stage === 'accepted') {
                continue; // No rejection from accepted
            }

            // Count rejections that happened from this stage
            $query = DB::table('job_application_history as h')
                ->join('job_vacancy_applications as a', 'h.application_id', '=', 'a.id')
                ->join('job_vacancies as j', 'a.vacancy_id', '=', 'j.id')
                ->where('j.tenant_id', $tenantId)
                ->where('h.from_status', $stage)
                ->where('h.to_status', 'rejected')
                ->whereBetween('a.created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

            if ($jobId) {
                $query->where('a.vacancy_id', $jobId);
            }

            $rejections = (int) $query->count();

            // Count total that entered this stage
            $enteredQuery = DB::table('job_application_history as h')
                ->join('job_vacancy_applications as a', 'h.application_id', '=', 'a.id')
                ->join('job_vacancies as j', 'a.vacancy_id', '=', 'j.id')
                ->where('j.tenant_id', $tenantId)
                ->where('h.to_status', $stage)
                ->whereBetween('a.created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

            if ($jobId) {
                $enteredQuery->where('a.vacancy_id', $jobId);
            }

            $entered = (int) $enteredQuery->count();

            // For 'applied' stage, use total applications as "entered"
            if ($stage === 'applied') {
                $entered = $this->getTotalApplications($tenantId, $jobId, $dateFrom, $dateTo);
            }

            $rates[$stage] = [
                'rejections' => $rejections,
                'total_at_stage' => $entered,
                'rate' => $entered > 0 ? round(($rejections / $entered) * 100, 1) : 0,
            ];
        }

        return $rates;
    }

    /**
     * Calculate average time (in days) candidates spend in each stage.
     */
    private function getAvgTimeInStage(int $tenantId, ?int $jobId, string $dateFrom, string $dateTo): array
    {
        $times = [];

        foreach (self::STAGES as $stage) {
            // Get average time between entering and leaving this stage
            $query = DB::table('job_application_history as h_enter')
                ->join('job_vacancy_applications as a', 'h_enter.application_id', '=', 'a.id')
                ->join('job_vacancies as j', 'a.vacancy_id', '=', 'j.id')
                ->join('job_application_history as h_exit', function ($join) use ($stage) {
                    $join->on('h_exit.application_id', '=', 'h_enter.application_id')
                         ->where('h_exit.from_status', '=', $stage)
                         ->whereColumn('h_exit.changed_at', '>', 'h_enter.changed_at');
                })
                ->where('j.tenant_id', $tenantId)
                ->where('h_enter.to_status', $stage)
                ->whereBetween('a.created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

            if ($jobId) {
                $query->where('a.vacancy_id', $jobId);
            }

            $avgDays = $query->selectRaw('AVG(DATEDIFF(h_exit.changed_at, h_enter.changed_at)) as avg_days')
                ->value('avg_days');

            $times[$stage] = round((float) ($avgDays ?? 0), 1);
        }

        return $times;
    }

    /**
     * Analyze correlation between skills match percentage and hiring outcome.
     */
    private function getSkillsMatchCorrelation(int $tenantId, ?int $jobId, string $dateFrom, string $dateTo): array
    {
        // Group applications by outcome and check skills match
        $query = DB::table('job_vacancy_applications as a')
            ->join('job_vacancies as j', 'a.vacancy_id', '=', 'j.id')
            ->join('users as u', 'a.user_id', '=', 'u.id')
            ->where('j.tenant_id', $tenantId)
            ->whereBetween('a.created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($jobId) {
            $query->where('a.vacancy_id', $jobId);
        }

        $outcomes = $query->select('a.status', DB::raw('COUNT(*) as count'))
            ->groupBy('a.status')
            ->get()
            ->keyBy('status');

        $accepted = (int) ($outcomes->get('accepted')?->count ?? 0);
        $rejected = (int) ($outcomes->get('rejected')?->count ?? 0);
        $total = $accepted + $rejected;

        return [
            'accepted_count' => $accepted,
            'rejected_count' => $rejected,
            'acceptance_rate' => $total > 0 ? round(($accepted / $total) * 100, 1) : 0,
            'note' => 'Skills match correlation requires detailed skills data tracking. This shows overall acceptance vs rejection rates.',
        ];
    }

    /**
     * Analyze which application sources produce the best outcomes.
     */
    private function getSourceEffectiveness(int $tenantId, ?int $jobId, string $dateFrom, string $dateTo): array
    {
        // Source is tracked via the 'source' field on applications if available
        // Fall back to categorizing as 'direct' vs 'referral' based on available data
        $query = DB::table('job_vacancy_applications as a')
            ->join('job_vacancies as j', 'a.vacancy_id', '=', 'j.id')
            ->where('j.tenant_id', $tenantId)
            ->whereBetween('a.created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($jobId) {
            $query->where('a.vacancy_id', $jobId);
        }

        // Check if referral data exists
        $hasReferrals = DB::table('job_referrals')
            ->join('job_vacancies as j', 'job_referrals.vacancy_id', '=', 'j.id')
            ->where('j.tenant_id', $tenantId)
            ->exists();

        $sources = [];

        if ($hasReferrals) {
            // Get referral applications
            $referralApps = (clone $query)
                ->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('job_referrals')
                        ->whereColumn('job_referrals.vacancy_id', 'a.vacancy_id')
                        ->whereColumn('job_referrals.referred_user_id', 'a.user_id');
                })
                ->select(
                    DB::raw('COUNT(*) as total'),
                    DB::raw('SUM(CASE WHEN a.status = "accepted" THEN 1 ELSE 0 END) as accepted')
                )
                ->first();

            $sources['referral'] = [
                'total_applications' => (int) ($referralApps->total ?? 0),
                'accepted' => (int) ($referralApps->accepted ?? 0),
                'acceptance_rate' => ($referralApps->total ?? 0) > 0
                    ? round((($referralApps->accepted ?? 0) / $referralApps->total) * 100, 1)
                    : 0,
            ];
        }

        // Direct applications (all that aren't referrals)
        $directApps = (clone $query)
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN a.status = "accepted" THEN 1 ELSE 0 END) as accepted')
            )
            ->first();

        $sources['direct'] = [
            'total_applications' => (int) ($directApps->total ?? 0),
            'accepted' => (int) ($directApps->accepted ?? 0),
            'acceptance_rate' => ($directApps->total ?? 0) > 0
                ? round((($directApps->accepted ?? 0) / $directApps->total) * 100, 1)
                : 0,
        ];

        return $sources;
    }

    /**
     * Calculate average hiring velocity (time-to-fill) in days.
     */
    private function getHiringVelocity(int $tenantId, ?int $jobId, string $dateFrom, string $dateTo): ?float
    {
        $query = DB::table('job_vacancy_applications as a')
            ->join('job_vacancies as j', 'a.vacancy_id', '=', 'j.id')
            ->where('j.tenant_id', $tenantId)
            ->where('a.status', 'accepted')
            ->whereBetween('a.created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        if ($jobId) {
            $query->where('a.vacancy_id', $jobId);
        }

        // Average days from job creation to acceptance
        $avgDays = $query->selectRaw('AVG(DATEDIFF(a.updated_at, j.created_at)) as avg_days')
            ->value('avg_days');

        return $avgDays !== null ? round((float) $avgDays, 1) : null;
    }
}
