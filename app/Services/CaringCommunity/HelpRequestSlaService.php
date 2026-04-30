<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\CaringCommunity;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AG96 — Help Request SLA Breach Dashboard.
 *
 * Reads the SLA windows captured in AG81 OperatingPolicyService
 * (`sla_first_response_hours`, `sla_help_request_hours`) and surfaces help
 * requests that are at risk of breaching, currently breached, or recently
 * resolved within window. Read-only — no schema changes.
 *
 * "First response" semantics: status moves out of `pending`. We approximate
 * the time-to-first-response with `updated_at - created_at` for any request
 * whose status is no longer `pending`. Pending requests are still in flight.
 *
 * "Resolution" semantics: status reaches `closed`. Same proxy, with the same
 * caveat that the source of `updated_at` may be coarse — but it is the only
 * signal the existing schema gives us, and the operator can correct policy
 * via AG81 if the proxy is too generous or too strict for their pilot.
 */
class HelpRequestSlaService
{
    private const RISK_RATIO_AT_RISK = 0.75;

    /** Buckets returned for the dashboard view. */
    public const BUCKETS = ['breached', 'at_risk', 'on_track'];

    public function __construct(
        private readonly OperatingPolicyService $operatingPolicy,
    ) {
    }

    /**
     * Build the SLA dashboard for a tenant.
     *
     * @return array{
     *   policy: array{first_response_hours: int, resolution_hours: int, source: string},
     *   summary: array{
     *     pending: int,
     *     in_progress: int,
     *     first_response_breached: int,
     *     first_response_at_risk: int,
     *     resolution_breached: int,
     *     resolution_at_risk: int,
     *     resolved_within_window_24h: int,
     *   },
     *   open_requests: list<array<string, mixed>>,
     *   recently_resolved: list<array<string, mixed>>,
     *   generated_at: string,
     * }
     */
    public function dashboard(int $tenantId): array
    {
        $policy = $this->resolvePolicy($tenantId);
        $now = now();

        $summary = $this->emptySummary();
        $openRequests = [];
        $recentlyResolved = [];

        if (Schema::hasTable('caring_help_requests')) {
            [$summary, $openRequests, $recentlyResolved] = $this->collect(
                $tenantId,
                $policy,
                $now,
            );
        }

        return [
            'policy' => $policy,
            'summary' => $summary,
            'open_requests' => $openRequests,
            'recently_resolved' => $recentlyResolved,
            'generated_at' => $now->toIso8601String(),
        ];
    }

    /**
     * @return array{
     *   array<string, int>,
     *   list<array<string, mixed>>,
     *   list<array<string, mixed>>,
     * }
     */
    private function collect(int $tenantId, array $policy, Carbon $now): array
    {
        $firstResponseSec = max(1, $policy['first_response_hours'] * 3600);
        $resolutionSec    = max(1, $policy['resolution_hours'] * 3600);
        $resolvedWindow   = $now->copy()->subHours(72); // recently_resolved = last 72h
        $within24h        = 24 * 3600;

        $rows = DB::table('caring_help_requests')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        $summary = $this->emptySummary();
        $openRequests = [];
        $recentlyResolved = [];

        foreach ($rows as $row) {
            $status = (string) ($row->status ?? '');
            $created = $this->parseTimestamp($row->created_at ?? null);
            $updated = $this->parseTimestamp($row->updated_at ?? null);
            if ($created === null) {
                continue;
            }

            $ageSec = max(0, $now->diffInSeconds($created, false));
            // `diffInSeconds` returns a negative value when the second arg is
            // before the first; we always want a positive age.
            $ageSec = $created->lessThanOrEqualTo($now) ? $now->diffInSeconds($created) : 0;

            $rowView = [
                'id'             => (int) $row->id,
                'user_id'        => (int) ($row->user_id ?? 0),
                'what'           => (string) ($row->what ?? ''),
                'when_needed'    => (string) ($row->when_needed ?? ''),
                'status'         => $status,
                'created_at'     => $created->toIso8601String(),
                'updated_at'     => $updated?->toIso8601String(),
                'age_hours'      => round($ageSec / 3600, 1),
            ];

            if ($status === 'pending') {
                $summary['pending']++;

                $bucket = $this->bucket($ageSec, $firstResponseSec);
                $rowView['sla_dimension']  = 'first_response';
                $rowView['sla_target_hours'] = $policy['first_response_hours'];
                $rowView['sla_remaining_hours'] = round(max(0, $firstResponseSec - $ageSec) / 3600, 1);
                $rowView['sla_overage_hours']   = round(max(0, $ageSec - $firstResponseSec) / 3600, 1);
                $rowView['bucket'] = $bucket;

                if ($bucket === 'breached') {
                    $summary['first_response_breached']++;
                } elseif ($bucket === 'at_risk') {
                    $summary['first_response_at_risk']++;
                }

                $openRequests[] = $rowView;
                continue;
            }

            // Anything beyond 'pending' counts as in-progress for SLA purposes
            // unless it's closed.
            if ($status === 'closed') {
                if ($updated && $updated->greaterThanOrEqualTo($resolvedWindow)) {
                    $turnaroundSec = max(0, $updated->diffInSeconds($created));
                    $rowView['turnaround_hours'] = round($turnaroundSec / 3600, 1);
                    $rowView['within_resolution_sla'] = $turnaroundSec <= $resolutionSec;
                    $recentlyResolved[] = $rowView;

                    if ($turnaroundSec <= $within24h) {
                        $summary['resolved_within_window_24h']++;
                    }
                }
                continue;
            }

            // In-progress (matched or other non-pending non-closed status):
            // measure against resolution SLA.
            $summary['in_progress']++;
            $bucket = $this->bucket($ageSec, $resolutionSec);
            $rowView['sla_dimension']  = 'resolution';
            $rowView['sla_target_hours'] = $policy['resolution_hours'];
            $rowView['sla_remaining_hours'] = round(max(0, $resolutionSec - $ageSec) / 3600, 1);
            $rowView['sla_overage_hours']   = round(max(0, $ageSec - $resolutionSec) / 3600, 1);
            $rowView['bucket'] = $bucket;

            if ($bucket === 'breached') {
                $summary['resolution_breached']++;
            } elseif ($bucket === 'at_risk') {
                $summary['resolution_at_risk']++;
            }

            $openRequests[] = $rowView;
        }

        // Sort open requests: breached first, then at_risk, then on_track,
        // then by overage / age desc within bucket.
        usort($openRequests, function (array $a, array $b): int {
            $bucketRank = ['breached' => 0, 'at_risk' => 1, 'on_track' => 2];
            $rankA = $bucketRank[$a['bucket']] ?? 3;
            $rankB = $bucketRank[$b['bucket']] ?? 3;
            if ($rankA !== $rankB) {
                return $rankA <=> $rankB;
            }
            $overageDiff = ($b['sla_overage_hours'] ?? 0) <=> ($a['sla_overage_hours'] ?? 0);
            if ($overageDiff !== 0) {
                return $overageDiff;
            }
            return ($b['age_hours'] ?? 0) <=> ($a['age_hours'] ?? 0);
        });

        return [$summary, $openRequests, $recentlyResolved];
    }

    private function bucket(int $ageSec, int $targetSec): string
    {
        if ($ageSec >= $targetSec) {
            return 'breached';
        }
        if ($ageSec >= (int) round($targetSec * self::RISK_RATIO_AT_RISK)) {
            return 'at_risk';
        }
        return 'on_track';
    }

    /**
     * Resolve the SLA windows for this tenant.
     *
     * Falls back to OperatingPolicyService schema defaults when the tenant has
     * not yet run the AG81 workshop. The dashboard always renders against
     * something rather than refusing to load.
     *
     * @return array{first_response_hours: int, resolution_hours: int, source: string}
     */
    private function resolvePolicy(int $tenantId): array
    {
        $data = $this->operatingPolicy->get($tenantId);
        $policy = $data['policy'] ?? [];
        $lastUpdated = $data['last_updated_at'] ?? null;

        return [
            'first_response_hours' => (int) ($policy['sla_first_response_hours'] ?? 24),
            'resolution_hours'     => (int) ($policy['sla_help_request_hours'] ?? 72),
            'source' => $lastUpdated === null ? 'platform_defaults' : 'tenant_policy',
        ];
    }

    private function parseTimestamp(mixed $raw): ?Carbon
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        try {
            return Carbon::parse((string) $raw);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, int>
     */
    private function emptySummary(): array
    {
        return [
            'pending'                    => 0,
            'in_progress'                => 0,
            'first_response_breached'    => 0,
            'first_response_at_risk'     => 0,
            'resolution_breached'        => 0,
            'resolution_at_risk'         => 0,
            'resolved_within_window_24h' => 0,
        ];
    }
}
