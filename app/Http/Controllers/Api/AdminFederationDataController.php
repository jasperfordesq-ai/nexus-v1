<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * AdminFederationDataController
 *
 * Bulk export/import/purge of a tenant's federation data.
 * - Export streams a sanitized JSON snapshot (secrets are redacted).
 * - Import validates a JSON document and optionally performs a dry run.
 * - Purge removes federation_api_logs rows older than N days.
 *
 * All operations are scoped to the current tenant via TenantContext.
 */
class AdminFederationDataController extends BaseApiController
{
    protected bool $isV2Api = true;

    /** Fields that MUST NEVER leave the system, even to tenant admins. */
    private const EXTERNAL_PARTNER_SECRET_FIELDS = [
        'api_key',
        'signing_secret',
        'oauth_client_secret',
        'oauth_client_id',
        'oauth_token_url',
    ];

    private const ALLOWED_TABLES = [
        'federation_partnerships',
        'federation_external_partners',
        'federation_reputation',
        'federation_api_logs',
    ];

    /**
     * POST /api/v2/admin/federation/data/export
     *
     * Streams a JSON object containing all federation data owned by the
     * current tenant (partnerships, external_partners, reputation, api_logs
     * from the last 90 days). Secrets are redacted.
     */
    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $userId = $this->getUserId();

        Log::info('[FederationData] Export requested', [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
        ]);

        $filename = sprintf('federation_export_tenant_%d_%s.json', $tenantId, date('Y-m-d_His'));

        return new StreamedResponse(function () use ($tenantId): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, '{"meta":' . json_encode([
                'tenant_id' => $tenantId,
                'exported_at' => date('c'),
                'format_version' => 1,
            ]) . ',');

            $this->streamSection($out, 'partnerships', $this->fetchPartnerships($tenantId));
            fwrite($out, ',');
            $this->streamSection($out, 'external_partners', $this->fetchExternalPartners($tenantId));
            fwrite($out, ',');
            $this->streamSection($out, 'reputation', $this->fetchReputation($tenantId));
            fwrite($out, ',');
            $this->streamSection($out, 'api_logs', $this->fetchApiLogs($tenantId, 90));

            fwrite($out, '}');
            fclose($out);
        }, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * POST /api/v2/admin/federation/data/import (multipart: file, dry_run)
     */
    public function import(Request $request): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $userId = $this->getUserId();

        $dryRun = filter_var($request->input('dry_run', true), FILTER_VALIDATE_BOOLEAN);

        if (!$request->hasFile('file')) {
            return $this->respondWithError('NO_FILE', __('api.missing_required_field', ['field' => 'file']), 'file', 422);
        }

        $file = $request->file('file');
        if ($file === null || !$file->isValid()) {
            return $this->respondWithError('INVALID_FILE', __('api.invalid_file_upload'), 'file', 422);
        }

        $contents = @file_get_contents($file->getRealPath());
        if ($contents === false) {
            return $this->respondWithError('READ_FAILED', __('api.failed_to_read_file'), 'file', 400);
        }

        $data = json_decode($contents, true);
        if (!is_array($data)) {
            return $this->respondWithError('INVALID_JSON', __('api.invalid_json'), 'file', 400);
        }

        // Shape validation
        $errors = $this->validateImportShape($data);
        if (!empty($errors)) {
            return $this->respondWithErrors($errors, 422);
        }

        $summary = [
            'dry_run' => $dryRun,
            'partnerships' => ['new' => 0, 'skipped' => 0, 'invalid' => 0],
            'external_partners' => ['new' => 0, 'skipped' => 0, 'invalid' => 0],
        ];

        // Partnerships: only import rows where tenant_id or partner_tenant_id == current tenant.
        if (!empty($data['partnerships']) && is_array($data['partnerships'])) {
            foreach ($data['partnerships'] as $row) {
                if (!is_array($row) || !isset($row['tenant_id'], $row['partner_tenant_id'], $row['status'])) {
                    $summary['partnerships']['invalid']++;
                    continue;
                }
                $ownTenant = (int) $row['tenant_id'] === $tenantId;
                $isPartnerLinked = $ownTenant || (int) $row['partner_tenant_id'] === $tenantId;
                if (!$isPartnerLinked) {
                    $summary['partnerships']['skipped']++;
                    continue;
                }
                // Deduplicate on unique_partnership key
                try {
                    $exists = DB::selectOne(
                        'SELECT id FROM federation_partnerships WHERE tenant_id = ? AND partner_tenant_id = ?',
                        [(int) $row['tenant_id'], (int) $row['partner_tenant_id']]
                    );
                } catch (\Throwable) {
                    $summary['partnerships']['invalid']++;
                    continue;
                }
                if ($exists) {
                    $summary['partnerships']['skipped']++;
                    continue;
                }
                $summary['partnerships']['new']++;
                if (!$dryRun) {
                    try {
                        DB::insert(
                            'INSERT INTO federation_partnerships
                             (tenant_id, partner_tenant_id, status, federation_level,
                              messaging_enabled, transactions_enabled, profiles_enabled,
                              listings_enabled, events_enabled, groups_enabled, notes, created_at)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                            [
                                (int) $row['tenant_id'],
                                (int) $row['partner_tenant_id'],
                                in_array($row['status'], ['pending', 'active', 'suspended', 'terminated'], true) ? $row['status'] : 'pending',
                                (int) ($row['federation_level'] ?? 1),
                                (int) !empty($row['messaging_enabled']),
                                (int) !empty($row['transactions_enabled']),
                                (int) !empty($row['profiles_enabled']),
                                (int) !empty($row['listings_enabled']),
                                (int) !empty($row['events_enabled']),
                                (int) !empty($row['groups_enabled']),
                                isset($row['notes']) ? (string) $row['notes'] : null,
                            ]
                        );
                    } catch (\Throwable $e) {
                        $summary['partnerships']['new']--;
                        $summary['partnerships']['invalid']++;
                    }
                }
            }
        }

        // External partners: only import rows that belong to current tenant.
        if (!empty($data['external_partners']) && is_array($data['external_partners'])) {
            foreach ($data['external_partners'] as $row) {
                if (!is_array($row) || !isset($row['tenant_id'], $row['name'], $row['base_url'])) {
                    $summary['external_partners']['invalid']++;
                    continue;
                }
                if ((int) $row['tenant_id'] !== $tenantId) {
                    $summary['external_partners']['skipped']++;
                    continue;
                }
                try {
                    $exists = DB::selectOne(
                        'SELECT id FROM federation_external_partners WHERE tenant_id = ? AND base_url = ?',
                        [$tenantId, (string) $row['base_url']]
                    );
                } catch (\Throwable) {
                    $summary['external_partners']['invalid']++;
                    continue;
                }
                if ($exists) {
                    $summary['external_partners']['skipped']++;
                    continue;
                }
                $summary['external_partners']['new']++;
                if (!$dryRun) {
                    try {
                        DB::insert(
                            'INSERT INTO federation_external_partners
                             (tenant_id, name, description, base_url, api_path, status, created_by, created_at)
                             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
                            [
                                $tenantId,
                                (string) $row['name'],
                                isset($row['description']) ? (string) $row['description'] : null,
                                (string) $row['base_url'],
                                isset($row['api_path']) ? (string) $row['api_path'] : '/api/v1/federation',
                                'pending',
                                $userId,
                            ]
                        );
                    } catch (\Throwable $e) {
                        $summary['external_partners']['new']--;
                        $summary['external_partners']['invalid']++;
                    }
                }
            }
        }

        Log::info('[FederationData] Import complete', [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'dry_run' => $dryRun,
            'summary' => $summary,
        ]);

        return $this->respondWithData($summary);
    }

    /**
     * POST /api/v2/admin/federation/data/purge
     * Body: { "days": 365 }
     * Deletes federation_api_logs rows older than N days scoped to this tenant's api keys.
     */
    public function purge(Request $request): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $userId = $this->getUserId();

        $days = (int) $request->input('days', 365);
        if ($days < 30 || $days > 3650) {
            return $this->respondWithError('INVALID_DAYS', __('api.value_out_of_range', ['min' => 30, 'max' => 3650]), 'days', 422);
        }

        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $deleted = 0;

        try {
            // DELETE must be scoped to rows whose api_key belongs to this tenant.
            $deleted = DB::delete(
                'DELETE l FROM federation_api_logs l
                 INNER JOIN federation_api_keys k ON k.id = l.api_key_id
                 WHERE k.tenant_id = ? AND l.created_at < ?',
                [$tenantId, $cutoff]
            );
        } catch (\Throwable $e) {
            Log::warning('[FederationData] Purge failed', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            return $this->respondWithError('PURGE_FAILED', __('api.purge_failed'), null, 500);
        }

        Log::info('[FederationData] Purge complete', [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'days' => $days,
            'deleted' => $deleted,
        ]);

        return $this->respondWithData([
            'deleted' => $deleted,
            'cutoff' => $cutoff,
            'days' => $days,
        ]);
    }

    // ───────────────────────────────── helpers ─────────────────────────────────

    /**
     * @param resource $out
     * @param array<int,array<string,mixed>> $rows
     */
    private function streamSection($out, string $key, array $rows): void
    {
        fwrite($out, json_encode($key) . ':');
        fwrite($out, json_encode($rows, JSON_UNESCAPED_SLASHES));
    }

    /** @return array<int,array<string,mixed>> */
    private function fetchPartnerships(int $tenantId): array
    {
        if (!$this->tableExists('federation_partnerships')) {
            return [];
        }
        try {
            $rows = DB::select(
                'SELECT id, tenant_id, partner_tenant_id, status, federation_level,
                        messaging_enabled, transactions_enabled, profiles_enabled,
                        listings_enabled, events_enabled, groups_enabled,
                        requested_at, approved_at, terminated_at, notes,
                        created_at, updated_at
                 FROM federation_partnerships
                 WHERE tenant_id = ? OR partner_tenant_id = ?
                 ORDER BY id ASC',
                [$tenantId, $tenantId]
            );
            return array_map(static fn($r) => (array) $r, $rows);
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function fetchExternalPartners(int $tenantId): array
    {
        if (!$this->tableExists('federation_external_partners')) {
            return [];
        }
        try {
            $rows = DB::select(
                'SELECT * FROM federation_external_partners WHERE tenant_id = ? ORDER BY id ASC',
                [$tenantId]
            );
            $out = [];
            foreach ($rows as $r) {
                $arr = (array) $r;
                foreach (self::EXTERNAL_PARTNER_SECRET_FIELDS as $field) {
                    if (array_key_exists($field, $arr)) {
                        $arr[$field] = null; // redact secrets
                    }
                }
                $out[] = $arr;
            }
            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function fetchReputation(int $tenantId): array
    {
        if (!$this->tableExists('federation_reputation')) {
            return [];
        }
        try {
            $rows = DB::select(
                'SELECT id, user_id, home_tenant_id, trust_score, reliability_score,
                        responsiveness_score, review_score, total_transactions,
                        successful_transactions, reviews_received, reviews_given,
                        hours_given, hours_received, is_verified, share_reputation,
                        created_at, updated_at
                 FROM federation_reputation
                 WHERE home_tenant_id = ?
                 ORDER BY id ASC',
                [$tenantId]
            );
            return array_map(static fn($r) => (array) $r, $rows);
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function fetchApiLogs(int $tenantId, int $days): array
    {
        if (!$this->tableExists('federation_api_logs') || !$this->tableExists('federation_api_keys')) {
            return [];
        }
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        try {
            $rows = DB::select(
                'SELECT l.id, l.api_key_id, l.endpoint, l.method, l.ip_address,
                        l.signature_valid, l.auth_method, l.response_code,
                        l.response_time_ms, l.created_at
                 FROM federation_api_logs l
                 INNER JOIN federation_api_keys k ON k.id = l.api_key_id
                 WHERE k.tenant_id = ? AND l.created_at >= ?
                 ORDER BY l.id ASC',
                [$tenantId, $since]
            );
            return array_map(static fn($r) => (array) $r, $rows);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param array<string,mixed> $data
     * @return array<int,array{code:string,message:string,field:string}>
     */
    private function validateImportShape(array $data): array
    {
        $errors = [];
        $allowedKeys = ['meta', 'partnerships', 'external_partners', 'reputation', 'api_logs'];
        foreach ($data as $key => $value) {
            if (!in_array((string) $key, $allowedKeys, true)) {
                $errors[] = [
                    'code' => 'UNKNOWN_KEY',
                    'message' => "Unknown key: {$key}",
                    'field' => (string) $key,
                ];
            }
        }
        foreach (['partnerships', 'external_partners'] as $k) {
            if (isset($data[$k]) && !is_array($data[$k])) {
                $errors[] = [
                    'code' => 'INVALID_SHAPE',
                    'message' => "{$k} must be an array",
                    'field' => $k,
                ];
            }
        }
        return $errors;
    }

    private function tableExists(string $table): bool
    {
        if (!in_array($table, self::ALLOWED_TABLES, true)) {
            return false;
        }
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }
        try {
            DB::select("SELECT 1 FROM `{$table}` LIMIT 1");
            return $cache[$table] = true;
        } catch (\Throwable) {
            return $cache[$table] = false;
        }
    }

}
