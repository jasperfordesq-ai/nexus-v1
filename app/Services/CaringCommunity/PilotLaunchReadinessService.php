<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\CaringCommunity;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AG95 — Pilot Launch Readiness Dashboard.
 *
 * Aggregates the pilot-evaluation surfaces (AG80 disclosure pack, AG81 operating
 * policy, AG82 commercial boundary, AG83 pilot scoreboard baseline + quarterly
 * cadence, AG84 data quality, AG85 isolated-node decision gate, AG87 external
 * integrations) into a single go/no-go report so a coordinator can see whether
 * the pilot is ready to launch without clicking through seven separate admin
 * screens.
 *
 * Read-only. Each section reports a status and a short summary; the overall
 * readiness is the worst of the section statuses, with `ready` only achievable
 * when every required gate is closed.
 *
 * Section status values:
 *   - ready        Section is complete and ready for launch.
 *   - needs_review Section has captured data but a coordinator should review it.
 *   - not_started  Section has not been touched at all.
 *   - blocked      Section has explicit blockers / issues that need fixing.
 *
 * Overall readiness mirrors section statuses with the same vocabulary, so the
 * UI can render them with consistent severity colors.
 */
class PilotLaunchReadinessService
{
    private const STATUS_READY        = 'ready';
    private const STATUS_NEEDS_REVIEW = 'needs_review';
    private const STATUS_NOT_STARTED  = 'not_started';
    private const STATUS_BLOCKED      = 'blocked';

    /** Section keys, ordered as the UI renders them. */
    private const SECTION_KEYS = [
        'disclosure_pack',
        'operating_policy',
        'commercial_boundary',
        'pilot_scoreboard',
        'data_quality',
        'isolated_node',
        'external_integrations',
    ];

    public function __construct(
        private readonly PilotDisclosurePackService $disclosurePack,
        private readonly OperatingPolicyService $operatingPolicy,
        private readonly CommercialBoundaryService $commercialBoundary,
        private readonly PilotScoreboardService $pilotScoreboard,
        private readonly TenantDataQualityService $tenantDataQuality,
        private readonly IsolatedNodeReadinessService $isolatedNode,
        private readonly ExternalIntegrationBacklogService $externalIntegrations,
    ) {
    }

    /**
     * Build the full readiness report for a tenant.
     *
     * @return array{
     *   generated_at: string,
     *   overall: array{status: string, ready_section_count: int, total_section_count: int, summary: string},
     *   sections: list<array<string, mixed>>,
     *   isolated_node_required: bool,
     * }
     */
    public function report(int $tenantId): array
    {
        $isolatedNodeRequired = $this->isIsolatedNodeRequired($tenantId);

        $sections = [
            $this->disclosurePackSection($tenantId),
            $this->operatingPolicySection($tenantId),
            $this->commercialBoundarySection($tenantId),
            $this->pilotScoreboardSection($tenantId),
            $this->dataQualitySection($tenantId),
            $this->isolatedNodeSection($tenantId, $isolatedNodeRequired),
            $this->externalIntegrationsSection($tenantId),
        ];

        $overall = $this->computeOverallStatus($sections);

        // Compute can_launch: every section must be `ready`. We treat
        // `decided` as a synonym for `ready` should any future section adopt
        // that status name (the AG85 isolated-node gate uses both internally).
        //
        // Special case: the AG85 isolated-node section is informational for
        // hosted deployments (not_required). In that case it does not block
        // the launch even if the section reports `not_started`.
        $canLaunch = true;
        $blockers  = [];
        foreach ($sections as $section) {
            $status = (string) ($section['status'] ?? '');
            $key    = (string) ($section['key']    ?? '');

            // Informational hosted-mode isolated-node section is never a blocker.
            if ($key === 'isolated_node' && !$isolatedNodeRequired) {
                continue;
            }

            if (!in_array($status, [self::STATUS_READY, 'decided'], true)) {
                $canLaunch = false;
                $blockers[] = [
                    'key'    => $key,
                    'label'  => (string) ($section['label'] ?? ''),
                    'status' => $status,
                ];
            }
        }

        $launched = $this->getLaunchState($tenantId);

        return [
            'generated_at'           => now()->toIso8601String(),
            'overall'                => $overall,
            'sections'               => $sections,
            'isolated_node_required' => $isolatedNodeRequired,
            'can_launch'             => $canLaunch && $launched === null,
            'blockers'               => $blockers,
            'launched'               => $launched,
        ];
    }

    /**
     * Launch the pilot — gated by can_launch from report().
     *
     * Returns:
     *   - on success: ['launched_at' => string, 'launched_by_id' => int]
     *   - if already launched: ['error' => 'ALREADY_LAUNCHED', 'launched' => array]
     *   - if not ready: ['error' => 'CANNOT_LAUNCH', 'blockers' => array]
     */
    public function launchPilot(int $tenantId, int $userId): array
    {
        if (!Schema::hasTable('tenant_settings')) {
            return ['error' => 'STORAGE_UNAVAILABLE'];
        }

        $existing = $this->getLaunchState($tenantId);
        if ($existing !== null) {
            return [
                'error'    => 'ALREADY_LAUNCHED',
                'launched' => $existing,
            ];
        }

        $report = $this->report($tenantId);
        if (empty($report['can_launch'])) {
            return [
                'error'    => 'CANNOT_LAUNCH',
                'blockers' => $report['blockers'] ?? [],
            ];
        }

        $launchedAt = now();
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $tenantId, 'setting_key' => 'caring_community.pilot_launched_at'],
            [
                'setting_value' => $launchedAt->toIso8601String(),
                'setting_type'  => 'string',
                'category'      => 'caring_community',
                'description'   => 'AG95 pilot launch timestamp',
                'updated_at'    => now(),
            ],
        );
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $tenantId, 'setting_key' => 'caring_community.pilot_launched_by'],
            [
                'setting_value' => (string) $userId,
                'setting_type'  => 'integer',
                'category'      => 'caring_community',
                'description'   => 'AG95 pilot launch operator user id',
                'updated_at'    => now(),
            ],
        );

        return [
            'launched_at'    => $launchedAt->toIso8601String(),
            'launched_by_id' => $userId,
        ];
    }

    /**
     * Read the persisted launch state for a tenant.
     *
     * @return array{launched_at:string, launched_by_id:int}|null
     */
    private function getLaunchState(int $tenantId): ?array
    {
        if (!Schema::hasTable('tenant_settings')) {
            return null;
        }

        $rows = DB::table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->whereIn('setting_key', [
                'caring_community.pilot_launched_at',
                'caring_community.pilot_launched_by',
            ])
            ->pluck('setting_value', 'setting_key')
            ->all();

        $launchedAt = $rows['caring_community.pilot_launched_at'] ?? null;
        if ($launchedAt === null || $launchedAt === '') {
            return null;
        }

        $launchedById = $rows['caring_community.pilot_launched_by'] ?? null;

        return [
            'launched_at'    => (string) $launchedAt,
            'launched_by_id' => $launchedById !== null ? (int) $launchedById : 0,
        ];
    }

    // -----------------------------------------------------------------------
    // Section evaluators — each returns a structured row for the report
    // -----------------------------------------------------------------------

    private function disclosurePackSection(int $tenantId): array
    {
        $pack = $this->disclosurePack->get($tenantId);
        $env = $pack['pack'] ?? [];

        $controllerName  = (string) ($env['controller']['name'] ?? '');
        $controllerEmail = (string) ($env['controller']['contact_email'] ?? '');
        $dpo             = (string) ($env['controller']['data_protection_officer'] ?? '');
        $incidentEmail   = (string) ($env['incident_response']['contact_email'] ?? '');
        $isCustomised    = (bool) ($pack['is_customised'] ?? false);

        $missing = [];
        if ($controllerName === '')  { $missing[] = 'controller.name'; }
        if ($controllerEmail === '') { $missing[] = 'controller.contact_email'; }
        if ($dpo === '')             { $missing[] = 'controller.data_protection_officer'; }
        if ($incidentEmail === '')   { $missing[] = 'incident_response.contact_email'; }

        if (!$isCustomised) {
            return [
                'key'           => 'disclosure_pack',
                'label'         => 'AG80 — FADP/nDSG disclosure pack',
                'status'        => self::STATUS_NOT_STARTED,
                'summary'       => 'Pack still on platform defaults; no controller named.',
                'admin_path'    => '/admin/caring-community/disclosure-pack',
                'last_updated_at' => $pack['last_updated_at'] ?? null,
                'missing'       => $missing,
            ];
        }

        if ($missing !== []) {
            return [
                'key'           => 'disclosure_pack',
                'label'         => 'AG80 — FADP/nDSG disclosure pack',
                'status'        => self::STATUS_NEEDS_REVIEW,
                'summary'       => 'Controller / DPO / incident contact still incomplete.',
                'admin_path'    => '/admin/caring-community/disclosure-pack',
                'last_updated_at' => $pack['last_updated_at'] ?? null,
                'missing'       => $missing,
            ];
        }

        return [
            'key'           => 'disclosure_pack',
            'label'         => 'AG80 — FADP/nDSG disclosure pack',
            'status'        => self::STATUS_READY,
            'summary'       => 'Controller, DPO, and incident contact captured.',
            'admin_path'    => '/admin/caring-community/disclosure-pack',
            'last_updated_at' => $pack['last_updated_at'] ?? null,
            'missing'       => [],
        ];
    }

    private function operatingPolicySection(int $tenantId): array
    {
        $data = $this->operatingPolicy->get($tenantId);
        $policy = $data['policy'] ?? [];
        $lastUpdated = $data['last_updated_at'] ?? null;

        // The signed appendix URL and an explicit safeguarding owner are the
        // pilot-launch gates. Approval authority + SLA windows have sane
        // defaults so they only require review.
        $appendixSet  = !empty($policy['policy_appendix_url']);
        $safeguarding = (int) ($policy['safeguarding_escalation_user_id'] ?? 0);

        $missing = [];
        if (!$appendixSet)        { $missing[] = 'policy_appendix_url'; }
        if ($safeguarding <= 0)   { $missing[] = 'safeguarding_escalation_user_id'; }

        if ($lastUpdated === null) {
            return [
                'key'           => 'operating_policy',
                'label'         => 'AG81 — KISS operating policy',
                'status'        => self::STATUS_NOT_STARTED,
                'summary'       => 'Policy still on platform defaults — schedule the workshop.',
                'admin_path'    => '/admin/caring-community/operating-policy',
                'last_updated_at' => null,
                'missing'       => array_merge(['workshop_not_run'], $missing),
            ];
        }

        if ($missing !== []) {
            return [
                'key'           => 'operating_policy',
                'label'         => 'AG81 — KISS operating policy',
                'status'        => self::STATUS_NEEDS_REVIEW,
                'summary'       => 'Workshop run, but appendix URL or safeguarding owner missing.',
                'admin_path'    => '/admin/caring-community/operating-policy',
                'last_updated_at' => $lastUpdated,
                'missing'       => $missing,
            ];
        }

        return [
            'key'           => 'operating_policy',
            'label'         => 'AG81 — KISS operating policy',
            'status'        => self::STATUS_READY,
            'summary'       => 'Policy workshop complete; appendix linked and safeguarding owner assigned.',
            'admin_path'    => '/admin/caring-community/operating-policy',
            'last_updated_at' => $lastUpdated,
            'missing'       => [],
        ];
    }

    private function commercialBoundarySection(int $tenantId): array
    {
        $matrix = $this->commercialBoundary->matrix($tenantId);
        $lastUpdated = $matrix['last_updated_at'] ?? null;
        $overrides   = (int) ($matrix['overrides_count'] ?? 0);

        // The boundary map is informational — having defaults is fine. We mark
        // it ready once the admin has at least viewed and acknowledged it.
        // We approximate "acknowledged" as either an override applied or the
        // commercial-boundary acknowledgement flag set.
        $acknowledged = $this->boundaryAcknowledged($tenantId);

        if (!$acknowledged && $overrides === 0) {
            return [
                'key'           => 'commercial_boundary',
                'label'         => 'AG82 — Commercial boundary map',
                'status'        => self::STATUS_NEEDS_REVIEW,
                'summary'       => 'Default classifications in effect; admin has not acknowledged the matrix.',
                'admin_path'    => '/admin/caring-community/commercial-boundary',
                'last_updated_at' => $lastUpdated,
                'missing'       => ['acknowledgement'],
            ];
        }

        return [
            'key'           => 'commercial_boundary',
            'label'         => 'AG82 — Commercial boundary map',
            'status'        => self::STATUS_READY,
            'summary'       => $overrides > 0
                ? "{$overrides} override(s) applied; matrix reviewed."
                : 'Default matrix acknowledged.',
            'admin_path'    => '/admin/caring-community/commercial-boundary',
            'last_updated_at' => $lastUpdated,
            'missing'       => [],
        ];
    }

    private function pilotScoreboardSection(int $tenantId): array
    {
        $board = $this->pilotScoreboard->scoreboard($tenantId);
        $prePilot     = $board['pre_pilot_baseline'] ?? null;
        $quarterly    = $board['quarterly_review'] ?? [];
        $isOverdue    = (bool) ($quarterly['is_overdue'] ?? false);
        $nextDueAt    = $quarterly['next_due_at'] ?? null;

        if (!$prePilot) {
            return [
                'key'           => 'pilot_scoreboard',
                'label'         => 'AG83 — Pilot scoreboard baseline',
                'status'        => self::STATUS_NOT_STARTED,
                'summary'       => 'No pre-pilot baseline captured — without it, no before/after comparison is possible.',
                'admin_path'    => '/admin/caring-community/pilot-scoreboard',
                'last_updated_at' => null,
                'missing'       => ['pre_pilot_baseline'],
            ];
        }

        if ($isOverdue) {
            return [
                'key'           => 'pilot_scoreboard',
                'label'         => 'AG83 — Pilot scoreboard baseline',
                'status'        => self::STATUS_NEEDS_REVIEW,
                'summary'       => 'Pre-pilot baseline captured; quarterly review is overdue.',
                'admin_path'    => '/admin/caring-community/pilot-scoreboard',
                'last_updated_at' => $prePilot['captured_at'] ?? null,
                'missing'       => ['quarterly_review'],
                'extra'         => ['next_due_at' => $nextDueAt],
            ];
        }

        return [
            'key'           => 'pilot_scoreboard',
            'label'         => 'AG83 — Pilot scoreboard baseline',
            'status'        => self::STATUS_READY,
            'summary'       => 'Pre-pilot baseline captured; quarterly cadence on track.',
            'admin_path'    => '/admin/caring-community/pilot-scoreboard',
            'last_updated_at' => $prePilot['captured_at'] ?? null,
            'missing'       => [],
            'extra'         => ['next_due_at' => $nextDueAt],
        ];
    }

    private function dataQualitySection(int $tenantId): array
    {
        $report = $this->tenantDataQuality->runChecks($tenantId);
        $totals = $report['totals'] ?? [];
        $danger  = (int) ($totals['danger']  ?? 0);
        $warning = (int) ($totals['warning'] ?? 0);

        if ($danger > 0) {
            return [
                'key'           => 'data_quality',
                'label'         => 'AG84 — Tenant data quality',
                'status'        => self::STATUS_BLOCKED,
                'summary'       => "{$danger} blocking issue(s) — duplicate accounts or seed users still present.",
                'admin_path'    => '/admin/caring-community/data-quality',
                'last_updated_at' => $report['generated_at'] ?? null,
                'missing'       => ['danger_checks'],
                'extra'         => ['danger' => $danger, 'warning' => $warning],
            ];
        }

        if ($warning > 0) {
            return [
                'key'           => 'data_quality',
                'label'         => 'AG84 — Tenant data quality',
                'status'        => self::STATUS_NEEDS_REVIEW,
                'summary'       => "{$warning} warning(s) — review before launch.",
                'admin_path'    => '/admin/caring-community/data-quality',
                'last_updated_at' => $report['generated_at'] ?? null,
                'missing'       => ['warning_checks'],
                'extra'         => ['danger' => 0, 'warning' => $warning],
            ];
        }

        return [
            'key'           => 'data_quality',
            'label'         => 'AG84 — Tenant data quality',
            'status'        => self::STATUS_READY,
            'summary'       => 'All checks pass — data is ready for real residents.',
            'admin_path'    => '/admin/caring-community/data-quality',
            'last_updated_at' => $report['generated_at'] ?? null,
            'missing'       => [],
        ];
    }

    private function isolatedNodeSection(int $tenantId, bool $required): array
    {
        $node = $this->isolatedNode->get($tenantId);
        $gate = $node['gate'] ?? [];
        $closed   = (bool) ($gate['closed'] ?? false);
        $blockers = (array) ($gate['blockers'] ?? []);
        $decided  = (int) ($gate['decided_count'] ?? 0);
        $total    = (int) ($gate['total_count'] ?? 0);

        if (!$required) {
            // Hosted shared / hosted custom-domain deployments don't need the
            // gate to be closed before launching, so it stays informational.
            return [
                'key'           => 'isolated_node',
                'label'         => 'AG85 — Isolated-node decision gate',
                'status'        => $closed ? self::STATUS_READY : self::STATUS_NOT_STARTED,
                'summary'       => $closed
                    ? 'Gate closed (informational — deployment is hosted).'
                    : 'Not required for hosted deployments — gate is informational.',
                'admin_path'    => '/admin/caring-community/isolated-node',
                'last_updated_at' => $node['last_updated_at'] ?? null,
                'missing'       => [],
                'extra'         => [
                    'gate_closed'   => $closed,
                    'decided_count' => $decided,
                    'total_count'   => $total,
                    'blockers'      => $blockers,
                    'required'      => false,
                ],
            ];
        }

        if ($blockers !== []) {
            return [
                'key'           => 'isolated_node',
                'label'         => 'AG85 — Isolated-node decision gate',
                'status'        => self::STATUS_BLOCKED,
                'summary'       => count($blockers) . ' blocked decision(s) — canton deployment cannot launch.',
                'admin_path'    => '/admin/caring-community/isolated-node',
                'last_updated_at' => $node['last_updated_at'] ?? null,
                'missing'       => $blockers,
                'extra'         => [
                    'gate_closed'   => false,
                    'decided_count' => $decided,
                    'total_count'   => $total,
                    'blockers'      => $blockers,
                    'required'      => true,
                ],
            ];
        }

        if (!$closed) {
            return [
                'key'           => 'isolated_node',
                'label'         => 'AG85 — Isolated-node decision gate',
                'status'        => self::STATUS_NEEDS_REVIEW,
                'summary'       => "{$decided} of {$total} decisions made — canton deployment requires all decided.",
                'admin_path'    => '/admin/caring-community/isolated-node',
                'last_updated_at' => $node['last_updated_at'] ?? null,
                'missing'       => ['undecided_items'],
                'extra'         => [
                    'gate_closed'   => false,
                    'decided_count' => $decided,
                    'total_count'   => $total,
                    'blockers'      => $blockers,
                    'required'      => true,
                ],
            ];
        }

        return [
            'key'           => 'isolated_node',
            'label'         => 'AG85 — Isolated-node decision gate',
            'status'        => self::STATUS_READY,
            'summary'       => 'Every gate decision recorded — canton deployment ready.',
            'admin_path'    => '/admin/caring-community/isolated-node',
            'last_updated_at' => $node['last_updated_at'] ?? null,
            'missing'       => [],
            'extra'         => [
                'gate_closed'   => true,
                'decided_count' => $decided,
                'total_count'   => $total,
                'blockers'      => [],
                'required'      => true,
            ],
        ];
    }

    private function externalIntegrationsSection(int $tenantId): array
    {
        $list = $this->externalIntegrations->list($tenantId);
        $items = $list['items'] ?? [];
        $lastUpdated = $list['last_updated_at'] ?? null;

        $blockedCount   = 0;
        $proposedCount  = 0;
        $totalCount     = count($items);

        foreach ($items as $item) {
            $status = (string) ($item['status'] ?? '');
            if ($status === 'blocked')  { $blockedCount++; }
            if ($status === 'proposed') { $proposedCount++; }
        }

        if ($totalCount === 0) {
            return [
                'key'           => 'external_integrations',
                'label'         => 'AG87 — External integration backlog',
                'status'        => self::STATUS_NOT_STARTED,
                'summary'       => 'Backlog empty — seed defaults or confirm no partner integrations are needed.',
                'admin_path'    => '/admin/caring-community/external-integrations',
                'last_updated_at' => $lastUpdated,
                'missing'       => ['backlog_empty'],
                'extra'         => [
                    'total'    => 0,
                    'blocked'  => 0,
                    'proposed' => 0,
                ],
            ];
        }

        if ($blockedCount > 0) {
            return [
                'key'           => 'external_integrations',
                'label'         => 'AG87 — External integration backlog',
                'status'        => self::STATUS_BLOCKED,
                'summary'       => "{$blockedCount} integration(s) blocked — partner-dependent features cannot ship.",
                'admin_path'    => '/admin/caring-community/external-integrations',
                'last_updated_at' => $lastUpdated,
                'missing'       => ['blocked_integrations'],
                'extra'         => [
                    'total'    => $totalCount,
                    'blocked'  => $blockedCount,
                    'proposed' => $proposedCount,
                ],
            ];
        }

        return [
            'key'           => 'external_integrations',
            'label'         => 'AG87 — External integration backlog',
            'status'        => self::STATUS_READY,
            'summary'       => "{$totalCount} item(s) tracked, none blocked.",
            'admin_path'    => '/admin/caring-community/external-integrations',
            'last_updated_at' => $lastUpdated,
            'missing'       => [],
            'extra'         => [
                'total'    => $totalCount,
                'blocked'  => 0,
                'proposed' => $proposedCount,
            ],
        ];
    }

    /**
     * Mark or read the commercial-boundary acknowledgement flag. Stored under
     * `caring.launch_readiness.boundary_acknowledged` so the AG82 admin page
     * can also flip it without redirecting through this service.
     */
    public function acknowledgeBoundary(int $tenantId): array
    {
        if (!Schema::hasTable('tenant_settings')) {
            return ['error' => 'tenant_settings_unavailable'];
        }

        DB::table('tenant_settings')->updateOrInsert(
            [
                'tenant_id'   => $tenantId,
                'setting_key' => 'caring.launch_readiness.boundary_acknowledged',
            ],
            [
                'setting_value' => '1',
                'setting_type'  => 'boolean',
                'category'      => 'caring_community',
                'description'   => 'AG95 commercial-boundary acknowledgement',
                'updated_at'    => now(),
            ],
        );

        return ['acknowledged' => true];
    }

    private function boundaryAcknowledged(int $tenantId): bool
    {
        if (!Schema::hasTable('tenant_settings')) {
            return false;
        }

        $row = DB::table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->where('setting_key', 'caring.launch_readiness.boundary_acknowledged')
            ->first();

        return $row !== null && (string) ($row->setting_value ?? '') === '1';
    }

    private function isIsolatedNodeRequired(int $tenantId): bool
    {
        if (!Schema::hasTable('tenant_settings')) {
            return false;
        }

        $row = DB::table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->where('setting_key', 'caring.isolated_node.deployment_mode')
            ->first();

        if ($row === null || $row->setting_value === null) {
            return false;
        }

        $decoded = json_decode((string) $row->setting_value, true);
        $value = is_array($decoded) ? ($decoded['value'] ?? null) : null;

        return $value === 'canton_isolated_node';
    }

    /**
     * @param list<array<string, mixed>> $sections
     * @return array{status: string, ready_section_count: int, total_section_count: int, summary: string}
     */
    private function computeOverallStatus(array $sections): array
    {
        $total      = count($sections);
        $readyCount = 0;
        $hasBlocked = false;
        $hasReview  = false;
        $hasNotStarted = false;

        foreach ($sections as $section) {
            $status = (string) ($section['status'] ?? '');
            if ($status === self::STATUS_READY)        { $readyCount++; }
            if ($status === self::STATUS_BLOCKED)      { $hasBlocked = true; }
            if ($status === self::STATUS_NEEDS_REVIEW) { $hasReview  = true; }
            if ($status === self::STATUS_NOT_STARTED)  { $hasNotStarted = true; }
        }

        if ($hasBlocked) {
            return [
                'status'              => self::STATUS_BLOCKED,
                'ready_section_count' => $readyCount,
                'total_section_count' => $total,
                'summary'             => 'One or more sections are blocked — pilot launch is not safe.',
            ];
        }

        if ($readyCount === $total) {
            return [
                'status'              => self::STATUS_READY,
                'ready_section_count' => $readyCount,
                'total_section_count' => $total,
                'summary'             => 'All sections ready — pilot launch may proceed.',
            ];
        }

        if ($hasReview) {
            return [
                'status'              => self::STATUS_NEEDS_REVIEW,
                'ready_section_count' => $readyCount,
                'total_section_count' => $total,
                'summary'             => "{$readyCount} of {$total} ready — coordinator review needed before launch.",
            ];
        }

        if ($hasNotStarted) {
            return [
                'status'              => self::STATUS_NOT_STARTED,
                'ready_section_count' => $readyCount,
                'total_section_count' => $total,
                'summary'             => "{$readyCount} of {$total} ready — pilot evaluation has not been run end to end.",
            ];
        }

        return [
            'status'              => self::STATUS_NEEDS_REVIEW,
            'ready_section_count' => $readyCount,
            'total_section_count' => $total,
            'summary'             => "{$readyCount} of {$total} ready.",
        ];
    }
}
