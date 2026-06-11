<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RetentionPolicyService — tenant-configurable data retention, archival
 * and disposal engine (IT-Data-03).
 *
 * Each supported data type maps to a tenant-scoped table plus the
 * timestamp column the retention window is measured against. Policies
 * are opt-in per tenant and per type (disabled = retain indefinitely,
 * matching previous platform behaviour). Enforcement deletes in bounded
 * batches so a huge backlog can never lock a table for minutes, and
 * every pass is recorded in tenant_retention_runs for compliance
 * evidence.
 *
 * v1 supports the 'delete' action on operational/log data types only.
 * User-generated content (messages, listings, posts) is intentionally
 * excluded until anonymize/archive actions exist — disposal of member
 * content deserves a softer treatment than DELETE.
 */
class RetentionPolicyService
{
    /** Disposal batch size — keeps each DELETE bounded and replication-friendly. */
    private const BATCH_SIZE = 5000;

    /** Cap on batches per (tenant, type) per run — backlog drains across runs. */
    private const MAX_BATCHES_PER_RUN = 20;

    public const MIN_RETENTION_DAYS = 30;
    public const MAX_RETENTION_DAYS = 3650;

    public const ACTIONS = ['delete'];

    /**
     * Registry of purgeable data types.
     * column = timestamp the retention window is measured against.
     */
    public const DATA_TYPES = [
        'activity_log' => [
            'table' => 'activity_log',
            'column' => 'created_at',
        ],
        'admin_audit_log' => [
            'table' => 'org_audit_log',
            'column' => 'created_at',
        ],
        'notifications' => [
            'table' => 'notifications',
            'column' => 'created_at',
        ],
        'email_log' => [
            'table' => 'email_log',
            'column' => 'created_at',
        ],
        // NOTE: login_attempts is deliberately NOT here. It is global
        // rate-limiting data with no tenant_id column, so a per-tenant
        // retention policy cannot scope it correctly; every registered
        // type MUST be a tenant-scoped table (see enforcePolicy guard).
    ];

    /**
     * All policies for a tenant, keyed by data type. Types without a row
     * are returned with defaults (disabled) so the admin UI can render
     * the full registry in one pass.
     *
     * @return array<string, array{data_type: string, retention_days: int, action: string, is_enabled: bool, updated_at: ?string}>
     */
    public static function getPolicies(int $tenantId): array
    {
        $rows = DB::table('tenant_retention_policies')
            ->where('tenant_id', $tenantId)
            ->get()
            ->keyBy('data_type');

        $policies = [];
        foreach (array_keys(self::DATA_TYPES) as $type) {
            $row = $rows->get($type);
            $policies[$type] = [
                'data_type' => $type,
                'retention_days' => $row ? (int) $row->retention_days : 365,
                'action' => $row ? (string) $row->action : 'delete',
                'is_enabled' => $row ? (bool) $row->is_enabled : false,
                'updated_at' => $row->updated_at ?? null,
            ];
        }

        return $policies;
    }

    /**
     * Create or update one policy. Returns an error string (translated)
     * or null on success.
     */
    public static function upsertPolicy(
        int $tenantId,
        string $dataType,
        int $retentionDays,
        bool $isEnabled,
        string $action = 'delete',
        ?int $updatedBy = null,
    ): ?string {
        if (!isset(self::DATA_TYPES[$dataType])) {
            return __('api.retention_unknown_data_type');
        }
        if (!in_array($action, self::ACTIONS, true)) {
            return __('api.retention_unknown_action');
        }
        if ($retentionDays < self::MIN_RETENTION_DAYS || $retentionDays > self::MAX_RETENTION_DAYS) {
            return __('api.retention_days_range', [
                'min' => self::MIN_RETENTION_DAYS,
                'max' => self::MAX_RETENTION_DAYS,
            ]);
        }

        DB::table('tenant_retention_policies')->updateOrInsert(
            ['tenant_id' => $tenantId, 'data_type' => $dataType],
            [
                'retention_days' => $retentionDays,
                'action' => $action,
                'is_enabled' => $isEnabled,
                'updated_by' => $updatedBy,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return null;
    }

    /**
     * Enforce every enabled policy for one tenant. Returns per-type
     * results: ['activity_log' => ['affected' => 123, 'status' => 'completed'], ...]
     *
     * @return array<string, array{affected: int, status: string}>
     */
    public static function enforceForTenant(int $tenantId): array
    {
        $policies = DB::table('tenant_retention_policies')
            ->where('tenant_id', $tenantId)
            ->where('is_enabled', true)
            ->get();

        $results = [];
        foreach ($policies as $policy) {
            $type = (string) $policy->data_type;
            if (!isset(self::DATA_TYPES[$type])) {
                continue; // registry shrank since the row was written
            }

            $days = max(self::MIN_RETENTION_DAYS, (int) $policy->retention_days);
            $results[$type] = self::enforcePolicy($tenantId, $type, $days, (string) $policy->action);
        }

        return $results;
    }

    /** @return array{affected: int, status: string} */
    private static function enforcePolicy(int $tenantId, string $dataType, int $retentionDays, string $action): array
    {
        $config = self::DATA_TYPES[$dataType];
        $cutoff = now()->subDays($retentionDays);

        $affected = 0;
        $status = 'completed';
        $error = null;

        // Fail safe rather than fail nightly: a registered type whose table
        // lacks tenant_id or the timestamp column could never be scoped
        // correctly. Skip it (status 'skipped', no DELETE) instead of
        // throwing an unscoped query every run.
        if (
            !\Schema::hasColumn($config['table'], 'tenant_id')
            || !\Schema::hasColumn($config['table'], $config['column'])
        ) {
            Log::warning('[Retention] skipped — table missing tenant_id/timestamp column', [
                'tenant_id' => $tenantId,
                'data_type' => $dataType,
                'table' => $config['table'],
            ]);
            self::recordRun($tenantId, $dataType, $action, $retentionDays, 0, 'skipped', 'missing_column');
            return ['affected' => 0, 'status' => 'skipped'];
        }

        try {
            for ($batch = 0; $batch < self::MAX_BATCHES_PER_RUN; $batch++) {
                // Every batch is tenant-scoped AND bounded.
                $deleted = DB::table($config['table'])
                    ->where('tenant_id', $tenantId)
                    ->where($config['column'], '<', $cutoff)
                    ->limit(self::BATCH_SIZE)
                    ->delete();

                $affected += $deleted;
                if ($deleted < self::BATCH_SIZE) {
                    break;
                }
            }

            if ($affected === self::BATCH_SIZE * self::MAX_BATCHES_PER_RUN) {
                $status = 'partial'; // backlog remains; next run continues
            }
        } catch (\Throwable $e) {
            $status = 'failed';
            $error = mb_substr($e->getMessage(), 0, 500);
            Log::warning('[Retention] enforcement failed', [
                'tenant_id' => $tenantId,
                'data_type' => $dataType,
                'error' => $e->getMessage(),
            ]);
        }

        self::recordRun($tenantId, $dataType, $action, $retentionDays, $affected, $status, $error);

        return ['affected' => $affected, 'status' => $status];
    }

    private static function recordRun(
        int $tenantId,
        string $dataType,
        string $action,
        int $retentionDays,
        int $affected,
        string $status,
        ?string $error,
    ): void {
        try {
            DB::table('tenant_retention_runs')->insert([
                'tenant_id' => $tenantId,
                'data_type' => $dataType,
                'action' => $action,
                'retention_days' => $retentionDays,
                'affected_rows' => $affected,
                'status' => $status,
                'error' => $error,
                'ran_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[Retention] failed to record run: ' . $e->getMessage(), [
                'tenant_id' => $tenantId,
                'data_type' => $dataType,
            ]);
        }
    }

    /**
     * Recent enforcement runs for the admin UI / compliance evidence.
     *
     * @return array<int, object>
     */
    public static function getRecentRuns(int $tenantId, int $limit = 50): array
    {
        return DB::table('tenant_retention_runs')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('ran_at')
            ->orderByDesc('id')
            ->limit(min(max($limit, 1), 200))
            ->get()
            ->all();
    }
}
