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
 * AG84 — Tenant Data-Quality and Seed-to-Real Migration Service.
 *
 * Read-only audit run by a coordinator before onboarding real residents.
 * Surfaces issues that must be resolved before a Swiss pilot (KISS / Cham /
 * Zug) can launch with real data: duplicate accounts, missing language
 * preferences, unverified organisations, demo-seed marker rows, missing
 * coordinator assignments, and tenant-setting completeness.
 *
 * All checks are scoped by tenant and guarded with Schema::hasTable() /
 * Schema::hasColumn() so they degrade gracefully on partial schemas.
 *
 * Each check returns a structured row:
 *   [
 *     'key'           => 'duplicate_emails',
 *     'label_code'    => 'duplicate_emails',
 *     'severity'      => 'ok' | 'info' | 'warning' | 'danger',
 *     'count'         => int,
 *     'message_code'  => 'duplicate_emails.found',
 *     'message_params'=> array<string, int|string>,
 *     'has_drilldown' => bool,
 *   ]
 */
class TenantDataQualityService
{
    /** Allowed severity levels (in increasing order of urgency). */
    private const SEVERITY_OK      = 'ok';
    private const SEVERITY_INFO    = 'info';
    private const SEVERITY_WARNING = 'warning';
    private const SEVERITY_DANGER  = 'danger';

    /** Tenant-setting keys probed by `tenant_setting_completeness`. */
    private const SETTING_KEYS = [
        'caring.disclosure_pack',
        'caring.operating_policy',
    ];

    /**
     * Run every readiness check for a tenant and return a structured report.
     *
     * @return array{
     *     generated_at: string,
     *     tenant_id: int,
     *     totals: array<string,int>,
     *     checks: list<array<string,mixed>>,
     * }
     */
    public function runChecks(int $tenantId): array
    {
        $checks = [
            $this->checkDuplicateEmails($tenantId),
            $this->checkDuplicatePhones($tenantId),
            $this->checkMissingPreferredLanguage($tenantId),
            $this->checkMissingSubRegion($tenantId),
            $this->checkMissingCoordinatorAssignment($tenantId),
            $this->checkUnverifiedOrganisations($tenantId),
            $this->checkSeedMarkerUsers($tenantId),
            $this->checkUnansweredHelpRequests($tenantId),
            $this->checkMembersWithoutRole($tenantId),
            $this->checkTenantSettingCompleteness($tenantId),
        ];

        $totals = [
            self::SEVERITY_OK      => 0,
            self::SEVERITY_INFO    => 0,
            self::SEVERITY_WARNING => 0,
            self::SEVERITY_DANGER  => 0,
        ];
        foreach ($checks as $check) {
            $sev = (string) ($check['severity'] ?? self::SEVERITY_OK);
            if (isset($totals[$sev])) {
                $totals[$sev]++;
            }
        }

        return [
            'generated_at' => gmdate('c'),
            'tenant_id'    => $tenantId,
            'totals'       => $totals,
            'checks'       => $checks,
        ];
    }

    /**
     * Return up to $limit affected rows for a single check, for the drill-down
     * modal in the admin UI. Always tenant-scoped.
     *
     * @return array{rows: list<array<string,mixed>>, note_code?: string}
     */
    public function affectedRows(int $tenantId, string $checkKey, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));

        switch ($checkKey) {
            case 'duplicate_emails':
                return ['rows' => $this->rowsDuplicateEmails($tenantId, $limit)];
            case 'duplicate_phones':
                return ['rows' => $this->rowsDuplicatePhones($tenantId, $limit)];
            case 'missing_preferred_language':
                return ['rows' => $this->rowsMissingPreferredLanguage($tenantId, $limit)];
            case 'seed_marker_users':
                return ['rows' => $this->rowsSeedMarkerUsers($tenantId, $limit)];
            case 'unverified_organisations':
                return ['rows' => $this->rowsUnverifiedOrganisations($tenantId, $limit)];
            case 'unanswered_help_requests':
                return ['rows' => $this->rowsUnansweredHelpRequests($tenantId, $limit)];
            default:
                return [
                    'rows' => [],
                    'note_code' => 'drilldown_not_available',
                ];
        }
    }

    // -------------------------------------------------------------------
    // Checks
    // -------------------------------------------------------------------

    private function checkDuplicateEmails(int $tenantId): array
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'email')) {
            return $this->checkRow('duplicate_emails', self::SEVERITY_OK, 0, 'duplicate_emails.schema_unavailable');
        }

        $count = (int) DB::table('users')
            ->where('tenant_id', $tenantId)
            ->whereRaw('LOWER(email) IN (
                SELECT lower_email FROM (
                    SELECT LOWER(email) AS lower_email
                    FROM users
                    WHERE tenant_id = ?
                      AND email IS NOT NULL
                      AND email <> \'\'
                    GROUP BY lower_email
                    HAVING COUNT(*) > 1
                ) dupes
            )', [$tenantId])
            ->count();

        return $this->checkRow(
            'duplicate_emails',
            $count > 0 ? self::SEVERITY_DANGER : self::SEVERITY_OK,
            $count,
            $count > 0 ? 'duplicate_emails.found' : 'duplicate_emails.clear',
            $count > 0,
        );
    }

    private function checkDuplicatePhones(int $tenantId): array
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'phone')) {
            return $this->checkRow('duplicate_phones', self::SEVERITY_OK, 0, 'duplicate_phones.schema_unavailable');
        }

        $count = (int) DB::table('users')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('phone')
            ->where('phone', '<>', '')
            ->whereIn('phone', function ($q) use ($tenantId) {
                $q->select('phone')
                    ->from('users')
                    ->where('tenant_id', $tenantId)
                    ->whereNotNull('phone')
                    ->where('phone', '<>', '')
                    ->groupBy('phone')
                    ->havingRaw('COUNT(*) > 1');
            })
            ->count();

        return $this->checkRow(
            'duplicate_phones',
            $count > 0 ? self::SEVERITY_WARNING : self::SEVERITY_OK,
            $count,
            $count > 0 ? 'duplicate_phones.found' : 'duplicate_phones.clear',
            $count > 0,
        );
    }

    private function checkMissingPreferredLanguage(int $tenantId): array
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'preferred_language')) {
            return $this->checkRow('missing_preferred_language', self::SEVERITY_OK, 0, 'missing_preferred_language.schema_unavailable');
        }

        $count = (int) DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->whereNull('preferred_language')->orWhere('preferred_language', '');
            })
            ->count();

        return $this->checkRow(
            'missing_preferred_language',
            $count > 0 ? self::SEVERITY_WARNING : self::SEVERITY_OK,
            $count,
            $count > 0 ? 'missing_preferred_language.found' : 'missing_preferred_language.clear',
            $count > 0,
        );
    }

    private function checkMissingSubRegion(int $tenantId): array
    {
        // The users table on this schema does NOT carry a sub_region_id column.
        // Only run a meaningful check when both prerequisites exist; otherwise
        // surface an OK row with a clear note.
        if (!Schema::hasTable('caring_sub_regions')) {
            return $this->checkRow('missing_sub_region', self::SEVERITY_OK, 0, 'missing_sub_region.regions_unavailable');
        }

        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'sub_region_id')) {
            return $this->checkRow('missing_sub_region', self::SEVERITY_OK, 0, 'missing_sub_region.column_unavailable');
        }

        $hasAnyRegion = DB::table('caring_sub_regions')
            ->where('tenant_id', $tenantId)
            ->exists();

        if (!$hasAnyRegion) {
            return $this->checkRow('missing_sub_region', self::SEVERITY_OK, 0, 'missing_sub_region.no_regions');
        }

        $count = (int) DB::table('users')
            ->where('tenant_id', $tenantId)
            ->whereNull('sub_region_id')
            ->count();

        return $this->checkRow(
            'missing_sub_region',
            $count > 0 ? self::SEVERITY_INFO : self::SEVERITY_OK,
            $count,
            $count > 0 ? 'missing_sub_region.found' : 'missing_sub_region.clear',
        );
    }

    private function checkMissingCoordinatorAssignment(int $tenantId): array
    {
        if (!Schema::hasTable('caring_support_relationships')) {
            return $this->checkRow('missing_coordinator_assignment', self::SEVERITY_OK, 0, 'missing_coordinator_assignment.table_unavailable');
        }

        // Schema on this tenant uses `coordinator_id`. If a future schema
        // renames the column to `coordinator_user_id`, fall through to the
        // alternative name; otherwise emit an OK row noting the absence.
        $col = null;
        if (Schema::hasColumn('caring_support_relationships', 'coordinator_id')) {
            $col = 'coordinator_id';
        } elseif (Schema::hasColumn('caring_support_relationships', 'coordinator_user_id')) {
            $col = 'coordinator_user_id';
        }

        if ($col === null) {
            return $this->checkRow('missing_coordinator_assignment', self::SEVERITY_OK, 0, 'missing_coordinator_assignment.column_unavailable');
        }

        $count = (int) DB::table('caring_support_relationships')
            ->where('tenant_id', $tenantId)
            ->whereNull($col)
            ->count();

        return $this->checkRow(
            'missing_coordinator_assignment',
            $count > 0 ? self::SEVERITY_WARNING : self::SEVERITY_OK,
            $count,
            $count > 0 ? 'missing_coordinator_assignment.found' : 'missing_coordinator_assignment.clear',
        );
    }

    private function checkUnverifiedOrganisations(int $tenantId): array
    {
        if (!Schema::hasTable('vol_organizations')) {
            return $this->checkRow('unverified_organisations', self::SEVERITY_OK, 0, 'unverified_organisations.table_unavailable');
        }

        // Prefer verified_at if present (newer schema). Otherwise fall back to
        // the status column and treat anything other than 'approved' /
        // 'verified' / 'active' as unverified.
        $count = 0;
        if (Schema::hasColumn('vol_organizations', 'verified_at')) {
            $count = (int) DB::table('vol_organizations')
                ->where('tenant_id', $tenantId)
                ->whereNull('verified_at')
                ->count();
        } elseif (Schema::hasColumn('vol_organizations', 'status')) {
            $count = (int) DB::table('vol_organizations')
                ->where('tenant_id', $tenantId)
                ->whereNotIn('status', ['approved', 'verified', 'active'])
                ->count();
        } else {
            return $this->checkRow('unverified_organisations', self::SEVERITY_OK, 0, 'unverified_organisations.verification_unavailable');
        }

        $severity = self::SEVERITY_OK;
        if ($count > 5) {
            $severity = self::SEVERITY_WARNING;
        } elseif ($count > 0) {
            $severity = self::SEVERITY_INFO;
        }

        return $this->checkRow(
            'unverified_organisations',
            $severity,
            $count,
            $count > 0 ? 'unverified_organisations.found' : 'unverified_organisations.clear',
            $count > 0,
        );
    }

    private function checkSeedMarkerUsers(int $tenantId): array
    {
        if (!Schema::hasTable('users')) {
            return $this->checkRow('seed_marker_users', self::SEVERITY_OK, 0, 'seed_marker_users.table_unavailable');
        }

        $hasName = Schema::hasColumn('users', 'name');
        $hasEmail = Schema::hasColumn('users', 'email');

        $query = DB::table('users')->where('tenant_id', $tenantId);

        $query->where(function ($q) use ($hasName, $hasEmail) {
            if ($hasEmail) {
                $q->orWhere('email', 'LIKE', '%@example.com');
                $q->orWhere('email', 'LIKE', '%@example.org');
                $q->orWhere('email', 'LIKE', '%@test.test');
            }
            if ($hasName) {
                $q->orWhere('name', 'LIKE', 'Test %');
                $q->orWhere('name', 'LIKE', 'Demo %');
            }
        });

        $count = (int) $query->count();

        return $this->checkRow(
            'seed_marker_users',
            $count > 0 ? self::SEVERITY_DANGER : self::SEVERITY_OK,
            $count,
            $count > 0 ? 'seed_marker_users.found' : 'seed_marker_users.clear',
            $count > 0,
        );
    }

    private function checkUnansweredHelpRequests(int $tenantId): array
    {
        if (!Schema::hasTable('caring_help_requests')) {
            return $this->checkRow('unanswered_help_requests', self::SEVERITY_OK, 0, 'unanswered_help_requests.table_unavailable');
        }

        $thirtyDaysAgo = gmdate('Y-m-d H:i:s', time() - (30 * 86400));

        $count = (int) DB::table('caring_help_requests')
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->where('created_at', '<', $thirtyDaysAgo)
            ->count();

        $severity = self::SEVERITY_OK;
        if ($count > 10) {
            $severity = self::SEVERITY_DANGER;
        } elseif ($count > 0) {
            $severity = self::SEVERITY_WARNING;
        }

        return $this->checkRow(
            'unanswered_help_requests',
            $severity,
            $count,
            $count > 0 ? 'unanswered_help_requests.found' : 'unanswered_help_requests.clear',
            $count > 0,
        );
    }

    private function checkMembersWithoutRole(int $tenantId): array
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'role')) {
            return $this->checkRow('members_without_role', self::SEVERITY_OK, 0, 'members_without_role.schema_unavailable');
        }

        $count = (int) DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->whereNull('role')->orWhere('role', '');
            })
            ->count();

        return $this->checkRow(
            'members_without_role',
            $count > 0 ? self::SEVERITY_WARNING : self::SEVERITY_OK,
            $count,
            $count > 0 ? 'members_without_role.found' : 'members_without_role.clear',
        );
    }

    private function checkTenantSettingCompleteness(int $tenantId): array
    {
        if (!Schema::hasTable('tenant_settings')) {
            return $this->checkRow('tenant_setting_completeness', self::SEVERITY_OK, 0, 'tenant_setting_completeness.table_unavailable');
        }

        $missing = 0;
        foreach (self::SETTING_KEYS as $key) {
            $exists = DB::table('tenant_settings')
                ->where('tenant_id', $tenantId)
                ->where(function ($q) use ($key) {
                    $q->where('setting_key', $key)
                      ->orWhere('setting_key', 'LIKE', $key . '.%');
                })
                ->exists();

            if (!$exists) {
                $missing++;
            }
        }

        return $this->checkRow(
            'tenant_setting_completeness',
            $missing > 0 ? self::SEVERITY_INFO : self::SEVERITY_OK,
            $missing,
            $missing > 0 ? 'tenant_setting_completeness.found' : 'tenant_setting_completeness.clear',
        );
    }

    // -------------------------------------------------------------------
    // Drill-down row queries
    // -------------------------------------------------------------------

    /** @return list<array<string,mixed>> */
    private function rowsDuplicateEmails(int $tenantId, int $limit): array
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'email')) {
            return [];
        }

        $rows = DB::table('users')
            ->select('id', 'email', 'name', 'created_at')
            ->where('tenant_id', $tenantId)
            ->whereRaw('LOWER(email) IN (
                SELECT lower_email FROM (
                    SELECT LOWER(email) AS lower_email
                    FROM users
                    WHERE tenant_id = ?
                      AND email IS NOT NULL
                      AND email <> \'\'
                    GROUP BY lower_email
                    HAVING COUNT(*) > 1
                ) dupes
            )', [$tenantId])
            ->orderBy('email')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return array_map(fn ($r) => [
            'id'         => (int) $r->id,
            'identifier' => (string) ($r->email ?? ''),
            'name'       => (string) ($r->name ?? ''),
            'created_at' => $r->created_at !== null ? (string) $r->created_at : null,
        ], $rows->all());
    }

    /** @return list<array<string,mixed>> */
    private function rowsDuplicatePhones(int $tenantId, int $limit): array
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'phone')) {
            return [];
        }

        $rows = DB::table('users')
            ->select('id', 'phone', 'name', 'created_at')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('phone')
            ->where('phone', '<>', '')
            ->whereIn('phone', function ($q) use ($tenantId) {
                $q->select('phone')
                    ->from('users')
                    ->where('tenant_id', $tenantId)
                    ->whereNotNull('phone')
                    ->where('phone', '<>', '')
                    ->groupBy('phone')
                    ->havingRaw('COUNT(*) > 1');
            })
            ->orderBy('phone')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return array_map(fn ($r) => [
            'id'         => (int) $r->id,
            'identifier' => (string) ($r->phone ?? ''),
            'name'       => (string) ($r->name ?? ''),
            'created_at' => $r->created_at !== null ? (string) $r->created_at : null,
        ], $rows->all());
    }

    /** @return list<array<string,mixed>> */
    private function rowsMissingPreferredLanguage(int $tenantId, int $limit): array
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'preferred_language')) {
            return [];
        }

        $rows = DB::table('users')
            ->select('id', 'email', 'name', 'created_at')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->whereNull('preferred_language')->orWhere('preferred_language', '');
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return array_map(fn ($r) => [
            'id'         => (int) $r->id,
            'identifier' => (string) ($r->email ?? ''),
            'name'       => (string) ($r->name ?? ''),
            'created_at' => $r->created_at !== null ? (string) $r->created_at : null,
        ], $rows->all());
    }

    /** @return list<array<string,mixed>> */
    private function rowsSeedMarkerUsers(int $tenantId, int $limit): array
    {
        if (!Schema::hasTable('users')) {
            return [];
        }

        $hasName = Schema::hasColumn('users', 'name');
        $hasEmail = Schema::hasColumn('users', 'email');

        $query = DB::table('users')
            ->select('id', 'email', 'name', 'created_at')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($hasName, $hasEmail) {
                if ($hasEmail) {
                    $q->orWhere('email', 'LIKE', '%@example.com');
                    $q->orWhere('email', 'LIKE', '%@example.org');
                    $q->orWhere('email', 'LIKE', '%@test.test');
                }
                if ($hasName) {
                    $q->orWhere('name', 'LIKE', 'Test %');
                    $q->orWhere('name', 'LIKE', 'Demo %');
                }
            })
            ->orderBy('id')
            ->limit($limit);

        $rows = $query->get();

        return array_map(fn ($r) => [
            'id'         => (int) $r->id,
            'identifier' => (string) ($r->email ?? ''),
            'name'       => (string) ($r->name ?? ''),
            'created_at' => $r->created_at !== null ? (string) $r->created_at : null,
        ], $rows->all());
    }

    /** @return list<array<string,mixed>> */
    private function rowsUnverifiedOrganisations(int $tenantId, int $limit): array
    {
        if (!Schema::hasTable('vol_organizations')) {
            return [];
        }

        $query = DB::table('vol_organizations')
            ->select('id', 'name', 'status', 'created_at')
            ->where('tenant_id', $tenantId);

        if (Schema::hasColumn('vol_organizations', 'verified_at')) {
            $query->whereNull('verified_at');
        } elseif (Schema::hasColumn('vol_organizations', 'status')) {
            $query->whereNotIn('status', ['approved', 'verified', 'active']);
        } else {
            return [];
        }

        $rows = $query->orderBy('id')->limit($limit)->get();

        return array_map(fn ($r) => [
            'id'         => (int) $r->id,
            'identifier' => (string) ($r->name ?? ''),
            'status'     => isset($r->status) ? (string) $r->status : null,
            'created_at' => $r->created_at !== null ? (string) $r->created_at : null,
        ], $rows->all());
    }

    /** @return list<array<string,mixed>> */
    private function rowsUnansweredHelpRequests(int $tenantId, int $limit): array
    {
        if (!Schema::hasTable('caring_help_requests')) {
            return [];
        }

        $thirtyDaysAgo = gmdate('Y-m-d H:i:s', time() - (30 * 86400));

        $rows = DB::table('caring_help_requests')
            ->select('id', 'user_id', 'status', 'created_at')
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->where('created_at', '<', $thirtyDaysAgo)
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        return array_map(fn ($r) => [
            'id'         => (int) $r->id,
            'identifier_code' => 'user_id',
            'identifier_params' => ['id' => (int) ($r->user_id ?? 0)],
            'status'     => (string) ($r->status ?? ''),
            'created_at' => $r->created_at !== null ? (string) $r->created_at : null,
        ], $rows->all());
    }

    // -------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------

    /** Build a uniformly shaped check row with frontend-owned copy. */
    private function checkRow(
        string $key,
        string $severity,
        int $count,
        string $messageCode,
        bool $hasDrilldown = false,
        array $messageParams = [],
    ): array
    {
        return [
            'key'           => $key,
            'label_code'    => $key,
            'severity'      => $severity,
            'count'         => $count,
            'message_code'  => $messageCode,
            'message_params'=> $messageParams,
            'has_drilldown' => $hasDrilldown,
        ];
    }
}
