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
 *     'label'         => 'Duplicate email addresses',
 *     'severity'      => 'ok' | 'info' | 'warning' | 'danger',
 *     'count'         => int,
 *     'message'       => string,
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
     * @return array{rows: list<array<string,mixed>>, note?: string}
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
                    'note' => 'drilldown not available for this check',
                ];
        }
    }

    // -------------------------------------------------------------------
    // Checks
    // -------------------------------------------------------------------

    private function checkDuplicateEmails(int $tenantId): array
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'email')) {
            return $this->okRow('duplicate_emails', 'Duplicate email addresses', 'users table not present', false);
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

        return [
            'key'           => 'duplicate_emails',
            'label'         => 'Duplicate email addresses',
            'severity'      => $count > 0 ? self::SEVERITY_DANGER : self::SEVERITY_OK,
            'count'         => $count,
            'message'       => $count > 0
                ? 'Multiple users share the same email — merge or delete duplicates before launch.'
                : 'No duplicate emails detected.',
            'has_drilldown' => $count > 0,
        ];
    }

    private function checkDuplicatePhones(int $tenantId): array
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'phone')) {
            return $this->okRow('duplicate_phones', 'Duplicate phone numbers', 'phone column not present', false);
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

        return [
            'key'           => 'duplicate_phones',
            'label'         => 'Duplicate phone numbers',
            'severity'      => $count > 0 ? self::SEVERITY_WARNING : self::SEVERITY_OK,
            'count'         => $count,
            'message'       => $count > 0
                ? 'Several members share the same phone number — confirm whether they are different residents.'
                : 'No duplicate phone numbers detected.',
            'has_drilldown' => $count > 0,
        ];
    }

    private function checkMissingPreferredLanguage(int $tenantId): array
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'preferred_language')) {
            return $this->okRow('missing_preferred_language', 'Members without preferred language', 'preferred_language column not present', false);
        }

        $count = (int) DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->whereNull('preferred_language')->orWhere('preferred_language', '');
            })
            ->count();

        return [
            'key'           => 'missing_preferred_language',
            'label'         => 'Members without preferred language',
            'severity'      => $count > 0 ? self::SEVERITY_WARNING : self::SEVERITY_OK,
            'count'         => $count,
            'message'       => $count > 0
                ? 'These members will receive emails and notifications in the platform default — set their preferred language for proper localisation.'
                : 'Every member has a preferred language set.',
            'has_drilldown' => $count > 0,
        ];
    }

    private function checkMissingSubRegion(int $tenantId): array
    {
        // The users table on this schema does NOT carry a sub_region_id column.
        // Only run a meaningful check when both prerequisites exist; otherwise
        // surface an OK row with a clear note.
        if (!Schema::hasTable('caring_sub_regions')) {
            return $this->okRow('missing_sub_region', 'Members without sub-region', 'caring_sub_regions table not present on this tenant schema', false);
        }

        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'sub_region_id')) {
            return [
                'key'           => 'missing_sub_region',
                'label'         => 'Members without sub-region',
                'severity'      => self::SEVERITY_OK,
                'count'         => 0,
                'message'       => 'No users.sub_region_id column on this schema — sub-region linkage tracked elsewhere.',
                'has_drilldown' => false,
            ];
        }

        $hasAnyRegion = DB::table('caring_sub_regions')
            ->where('tenant_id', $tenantId)
            ->exists();

        if (!$hasAnyRegion) {
            return [
                'key'           => 'missing_sub_region',
                'label'         => 'Members without sub-region',
                'severity'      => self::SEVERITY_OK,
                'count'         => 0,
                'message'       => 'No sub-regions defined for this tenant — skipping check.',
                'has_drilldown' => false,
            ];
        }

        $count = (int) DB::table('users')
            ->where('tenant_id', $tenantId)
            ->whereNull('sub_region_id')
            ->count();

        return [
            'key'           => 'missing_sub_region',
            'label'         => 'Members without sub-region',
            'severity'      => $count > 0 ? self::SEVERITY_INFO : self::SEVERITY_OK,
            'count'         => $count,
            'message'       => $count > 0
                ? 'Members without a sub-region will not appear in neighbourhood-scoped feeds.'
                : 'All members are assigned to a sub-region.',
            'has_drilldown' => false,
        ];
    }

    private function checkMissingCoordinatorAssignment(int $tenantId): array
    {
        if (!Schema::hasTable('caring_support_relationships')) {
            return $this->okRow('missing_coordinator_assignment', 'Caring relationships without coordinator', 'caring_support_relationships table not present', false);
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
            return [
                'key'           => 'missing_coordinator_assignment',
                'label'         => 'Caring relationships without coordinator',
                'severity'      => self::SEVERITY_OK,
                'count'         => 0,
                'message'       => 'no coordinator column on this schema',
                'has_drilldown' => false,
            ];
        }

        $count = (int) DB::table('caring_support_relationships')
            ->where('tenant_id', $tenantId)
            ->whereNull($col)
            ->count();

        return [
            'key'           => 'missing_coordinator_assignment',
            'label'         => 'Caring relationships without coordinator',
            'severity'      => $count > 0 ? self::SEVERITY_WARNING : self::SEVERITY_OK,
            'count'         => $count,
            'message'       => $count > 0
                ? 'Assign a coordinator to each active caring relationship before going live.'
                : 'All caring relationships have a coordinator assigned.',
            'has_drilldown' => false,
        ];
    }

    private function checkUnverifiedOrganisations(int $tenantId): array
    {
        if (!Schema::hasTable('vol_organizations')) {
            return $this->okRow('unverified_organisations', 'Unverified organisations', 'vol_organizations table not present', false);
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
            return $this->okRow('unverified_organisations', 'Unverified organisations', 'no verification column on vol_organizations', false);
        }

        $severity = self::SEVERITY_OK;
        if ($count > 5) {
            $severity = self::SEVERITY_WARNING;
        } elseif ($count > 0) {
            $severity = self::SEVERITY_INFO;
        }

        return [
            'key'           => 'unverified_organisations',
            'label'         => 'Unverified organisations',
            'severity'      => $severity,
            'count'         => $count,
            'message'       => $count > 0
                ? 'Approve or reject each pending organisation so members trust the listings.'
                : 'All organisations have been reviewed.',
            'has_drilldown' => $count > 0,
        ];
    }

    private function checkSeedMarkerUsers(int $tenantId): array
    {
        if (!Schema::hasTable('users')) {
            return $this->okRow('seed_marker_users', 'Demo / seed marker accounts', 'users table not present', false);
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

        return [
            'key'           => 'seed_marker_users',
            'label'         => 'Demo / seed marker accounts',
            'severity'      => $count > 0 ? self::SEVERITY_DANGER : self::SEVERITY_OK,
            'count'         => $count,
            'message'       => $count > 0
                ? 'Demo or seed accounts are still present — they must be removed before onboarding real residents.'
                : 'No demo / seed marker accounts detected.',
            'has_drilldown' => $count > 0,
        ];
    }

    private function checkUnansweredHelpRequests(int $tenantId): array
    {
        if (!Schema::hasTable('caring_help_requests')) {
            return $this->okRow('unanswered_help_requests', 'Unanswered help requests (>30 days)', 'caring_help_requests table not present', false);
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

        return [
            'key'           => 'unanswered_help_requests',
            'label'         => 'Unanswered help requests (>30 days)',
            'severity'      => $severity,
            'count'         => $count,
            'message'       => $count > 0
                ? 'Old pending help requests damage trust — close, decline, or match each one.'
                : 'No stale help requests.',
            'has_drilldown' => $count > 0,
        ];
    }

    private function checkMembersWithoutRole(int $tenantId): array
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'role')) {
            return $this->okRow('members_without_role', 'Members without role', 'role column not present', false);
        }

        $count = (int) DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->whereNull('role')->orWhere('role', '');
            })
            ->count();

        return [
            'key'           => 'members_without_role',
            'label'         => 'Members without role',
            'severity'      => $count > 0 ? self::SEVERITY_WARNING : self::SEVERITY_OK,
            'count'         => $count,
            'message'       => $count > 0
                ? 'Assign every member a role (member / coordinator / admin) so permissions resolve correctly.'
                : 'Every member has a role assigned.',
            'has_drilldown' => false,
        ];
    }

    private function checkTenantSettingCompleteness(int $tenantId): array
    {
        if (!Schema::hasTable('tenant_settings')) {
            return $this->okRow('tenant_setting_completeness', 'Tenant settings completeness', 'tenant_settings table not present', false);
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

        return [
            'key'           => 'tenant_setting_completeness',
            'label'         => 'Tenant settings completeness',
            'severity'      => $missing > 0 ? self::SEVERITY_INFO : self::SEVERITY_OK,
            'count'         => $missing,
            'message'       => $missing > 0
                ? 'One or more pre-launch settings are missing — review the AG80 disclosure pack and AG81 operating policy admin pages.'
                : 'All pre-launch tenant settings are configured.',
            'has_drilldown' => false,
        ];
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
            'identifier' => 'user #' . (int) ($r->user_id ?? 0),
            'status'     => (string) ($r->status ?? ''),
            'created_at' => $r->created_at !== null ? (string) $r->created_at : null,
        ], $rows->all());
    }

    // -------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------

    /** Build a uniformly shaped OK / no-op check row. */
    private function okRow(string $key, string $label, string $message, bool $hasDrilldown): array
    {
        return [
            'key'           => $key,
            'label'         => $label,
            'severity'      => self::SEVERITY_OK,
            'count'         => 0,
            'message'       => $message,
            'has_drilldown' => $hasDrilldown,
        ];
    }
}
