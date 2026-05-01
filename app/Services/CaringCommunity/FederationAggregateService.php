<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services\CaringCommunity;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * FederationAggregateService — produces signed, opt-in aggregate reports for
 * cross-node federation discovery (Section AG9 of the Caring Community
 * architecture).
 *
 * Privacy posture:
 *  - No PII ever leaves the tenant.
 *  - Member counts are bucketed, not raw.
 *  - Partner organisations report only a count, never names.
 *  - Top categories are capped at 10.
 *  - Each tenant has its own HMAC-SHA256 signing secret; remote consumers
 *    verify against that secret to detect tampering or impersonation.
 *  - Every served query is logged for 12 months for audit.
 */
class FederationAggregateService
{
    public const ALGORITHM = 'HMAC-SHA256';
    private const LOG_RETENTION_DAYS = 365;

    /**
     * Build the aggregate payload for the *current* TenantContext over the given period.
     *
     * @return array{
     *   period: array{from: string, to: string},
     *   tenant: array{slug: string, name: string},
     *   hours: array{total_approved: float, by_month: array<int,array{month:string,hours:float}>, by_category: array<int,array{category:string,hours:float,count:int}>},
     *   members: array{bracket: string},
     *   partner_orgs: array{count: int},
     *   generated_at: string
     * }
     */
    public function compute(string $periodFrom, string $periodTo): array
    {
        $tenantId = (int) TenantContext::getId();
        $tenant = $this->resolveTenantMeta($tenantId);

        $totalApproved = 0.0;
        $byMonth = [];
        $byCategory = [];

        if (Schema::hasTable('vol_logs') && Schema::hasColumn('vol_logs', 'status')) {
            // Total approved hours
            $row = DB::selectOne(
                "SELECT COALESCE(SUM(hours), 0) AS total
                   FROM vol_logs
                  WHERE tenant_id = ?
                    AND status = 'approved'
                    AND date_logged BETWEEN ? AND ?",
                [$tenantId, $periodFrom, $periodTo]
            );
            $totalApproved = (float) ($row->total ?? 0);

            // By month
            $monthRows = DB::select(
                "SELECT DATE_FORMAT(date_logged, '%Y-%m') AS month,
                        COALESCE(SUM(hours), 0) AS hours
                   FROM vol_logs
                  WHERE tenant_id = ?
                    AND status = 'approved'
                    AND date_logged BETWEEN ? AND ?
                  GROUP BY DATE_FORMAT(date_logged, '%Y-%m')
                  ORDER BY month ASC",
                [$tenantId, $periodFrom, $periodTo]
            );
            foreach ($monthRows as $r) {
                $byMonth[] = [
                    'month' => (string) $r->month,
                    'hours' => round((float) $r->hours, 2),
                ];
            }

            // By category — vol_logs joins vol_opportunities → categories
            if (Schema::hasTable('vol_opportunities') && Schema::hasTable('categories')) {
                $catRows = DB::select(
                    "SELECT COALESCE(c.name, 'uncategorised') AS category,
                            COALESCE(SUM(vl.hours), 0)        AS hours,
                            COUNT(*)                          AS cnt
                       FROM vol_logs vl
                  LEFT JOIN vol_opportunities vo ON vo.id = vl.opportunity_id
                  LEFT JOIN categories c         ON c.id = vo.category_id
                      WHERE vl.tenant_id = ?
                        AND vl.status = 'approved'
                        AND vl.date_logged BETWEEN ? AND ?
                   GROUP BY COALESCE(c.name, 'uncategorised')
                   ORDER BY hours DESC
                      LIMIT 10",
                    [$tenantId, $periodFrom, $periodTo]
                );
                foreach ($catRows as $r) {
                    $byCategory[] = [
                        'category' => (string) $r->category,
                        'hours'    => round((float) $r->hours, 2),
                        'count'    => (int) $r->cnt,
                    ];
                }
            }
        }

        // Member bracket — bucket the raw count.
        // Only count active members; banned/suspended/inactive accounts must
        // not inflate the bracket exposed to federation peers.
        $memberCount = 0;
        if (Schema::hasTable('users')) {
            try {
                if (Schema::hasColumn('users', 'status')) {
                    $row = DB::selectOne(
                        "SELECT COUNT(*) AS cnt FROM users
                          WHERE tenant_id = ? AND status = 'active'",
                        [$tenantId]
                    );
                } else {
                    $row = DB::selectOne(
                        "SELECT COUNT(*) AS cnt FROM users WHERE tenant_id = ?",
                        [$tenantId]
                    );
                }
                $memberCount = (int) ($row->cnt ?? 0);
            } catch (\Throwable $e) {
                $memberCount = 0;
            }
        }
        $bracket = $this->bucketMemberCount($memberCount);

        // Partner orgs — count of approved vol_organizations only.
        // Excludes pending / suspended / rejected orgs to avoid misrepresenting
        // the cooperative's active partner footprint.
        $partnerOrgsCount = 0;
        if (Schema::hasTable('vol_organizations')) {
            try {
                if (Schema::hasColumn('vol_organizations', 'status')) {
                    $row = DB::selectOne(
                        "SELECT COUNT(*) AS cnt FROM vol_organizations
                          WHERE tenant_id = ?
                            AND status IN ('approved', 'active')",
                        [$tenantId]
                    );
                } else {
                    $row = DB::selectOne(
                        "SELECT COUNT(*) AS cnt FROM vol_organizations WHERE tenant_id = ?",
                        [$tenantId]
                    );
                }
                $partnerOrgsCount = (int) ($row->cnt ?? 0);
            } catch (\Throwable $e) {
                $partnerOrgsCount = 0;
            }
        }

        return [
            'period' => [
                'from' => $periodFrom,
                'to'   => $periodTo,
            ],
            'tenant' => [
                'slug' => (string) ($tenant['slug'] ?? ''),
                'name' => (string) ($tenant['name'] ?? ''),
            ],
            'hours' => [
                'total_approved' => round($totalApproved, 2),
                'by_month'       => $byMonth,
                'by_category'    => $byCategory,
            ],
            'members' => [
                'bracket' => $bracket,
            ],
            'partner_orgs' => [
                'count' => $partnerOrgsCount,
            ],
            'generated_at' => gmdate('c'),
        ];
    }

    /**
     * Bucket a raw member count. Never returns the raw integer.
     */
    public function bucketMemberCount(int $count): string
    {
        if ($count < 50)   return '<50';
        if ($count < 200)  return '50-200';
        if ($count < 1000) return '200-1000';
        return '>1000';
    }

    /**
     * Sign the payload with HMAC-SHA256, returning hex digest.
     */
    public function signPayload(array $payload, string $secret): string
    {
        // Canonical JSON — sort keys recursively for stable signatures
        $canonical = $this->canonicalJson($payload);
        return hash_hmac('sha256', $canonical, $secret);
    }

    /**
     * Generate a fresh 64-char hex signing secret.
     */
    public function generateSecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Rotate the signing secret for a tenant. Returns the new secret (caller
     * may need to redistribute to consumers).
     */
    public function rotateSecret(int $tenantId): string
    {
        $secret = $this->generateSecret();
        DB::table('federation_aggregate_consents')->updateOrInsert(
            ['tenant_id' => $tenantId],
            [
                'signing_secret'  => $secret,
                'last_rotated_at' => now(),
                'updated_at'      => now(),
                'created_at'      => now(),
            ]
        );
        return $secret;
    }

    /**
     * Log an aggregate query for the audit trail.
     */
    public function logQuery(
        int $tenantId,
        string $requesterOrigin,
        string $periodFrom,
        string $periodTo,
        array $fieldsReturned,
        string $signature
    ): void {
        try {
            DB::table('federation_aggregate_query_log')->insert([
                'tenant_id'          => $tenantId,
                'requester_origin'   => mb_substr($requesterOrigin, 0, 255),
                'period_from'        => $periodFrom,
                'period_to'          => $periodTo,
                'fields_returned'    => json_encode($fieldsReturned),
                'response_signature' => mb_substr($signature, 0, 128),
                'created_at'         => now(),
            ]);
        } catch (\Throwable $e) {
            // Logging must never break the public response.
            Log::warning('FederationAggregateService::logQuery failed', [
                'tenant_id' => $tenantId,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Delete query log records older than 12 months. Returns count deleted.
     */
    public function pruneOldLogs(): int
    {
        if (!Schema::hasTable('federation_aggregate_query_log')) {
            return 0;
        }
        $cutoff = now()->subDays(self::LOG_RETENTION_DAYS);
        return (int) DB::table('federation_aggregate_query_log')
            ->where('created_at', '<', $cutoff)
            ->delete();
    }

    /**
     * Read the consent record for a tenant (or null).
     *
     * @return array{enabled: bool, has_secret: bool, last_rotated_at: ?string}|null
     */
    public function getConsent(int $tenantId): ?array
    {
        $row = DB::table('federation_aggregate_consents')
            ->where('tenant_id', $tenantId)
            ->first();
        if (!$row) return null;
        return [
            'enabled'         => (bool) $row->enabled,
            'has_secret'      => !empty($row->signing_secret),
            'last_rotated_at' => $row->last_rotated_at,
        ];
    }

    /**
     * Read full consent record including secret. Internal use only.
     *
     * @return object|null
     */
    public function getConsentInternal(int $tenantId): ?object
    {
        $row = DB::table('federation_aggregate_consents')
            ->where('tenant_id', $tenantId)
            ->first();
        return $row ?: null;
    }

    /**
     * Toggle the enabled flag, generating a secret on first enable.
     *
     * @return array{enabled: bool, has_secret: bool, last_rotated_at: ?string}
     */
    public function setEnabled(int $tenantId, bool $enabled): array
    {
        $existing = DB::table('federation_aggregate_consents')
            ->where('tenant_id', $tenantId)
            ->first();

        if ($existing === null) {
            $secret = $enabled ? $this->generateSecret() : null;
            DB::table('federation_aggregate_consents')->insert([
                'tenant_id'       => $tenantId,
                'enabled'         => $enabled,
                'signing_secret'  => $secret,
                'last_rotated_at' => $enabled ? now() : null,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        } else {
            $update = [
                'enabled'    => $enabled,
                'updated_at' => now(),
            ];
            if ($enabled && empty($existing->signing_secret)) {
                $update['signing_secret']  = $this->generateSecret();
                $update['last_rotated_at'] = now();
            }
            DB::table('federation_aggregate_consents')
                ->where('tenant_id', $tenantId)
                ->update($update);
        }

        return $this->getConsent($tenantId) ?? [
            'enabled' => $enabled, 'has_secret' => $enabled, 'last_rotated_at' => null,
        ];
    }

    /**
     * Recent audit log entries for a tenant. Limited to last 90 days, 100 rows.
     *
     * @return array<int,array<string,mixed>>
     */
    public function recentAuditLog(int $tenantId, int $limit = 100): array
    {
        if (!Schema::hasTable('federation_aggregate_query_log')) {
            return [];
        }
        $rows = DB::table('federation_aggregate_query_log')
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subDays(90))
            ->orderByDesc('created_at')
            ->limit(max(1, min(500, $limit)))
            ->get();

        return $rows->map(function ($r) {
            return [
                'id'                 => (int) $r->id,
                'requester_origin'   => $r->requester_origin,
                'period_from'        => $r->period_from,
                'period_to'          => $r->period_to,
                'fields_returned'    => $this->safeJsonDecode((string) ($r->fields_returned ?? '[]')),
                'signature_snippet'  => mb_substr((string) $r->response_signature, 0, 16) . '…',
                'created_at'         => (string) $r->created_at,
            ];
        })->all();
    }

    /**
     * Return the canonical JSON representation used for signing.
     * Public so tests / external verifiers can recompute it.
     */
    public function canonicalJson(array $payload): string
    {
        $sorted = $this->ksortRecursive($payload);
        return json_encode($sorted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array<string,mixed> $array
     * @return array<string,mixed>
     */
    private function ksortRecursive(array $array): array
    {
        // Only ksort assoc arrays. Leave list arrays in their natural order.
        $isList = array_keys($array) === range(0, count($array) - 1);
        if (!$isList) {
            ksort($array);
        }
        foreach ($array as $k => $v) {
            if (is_array($v)) {
                $array[$k] = $this->ksortRecursive($v);
            }
        }
        return $array;
    }

    /**
     * @return array{slug: ?string, name: ?string}
     */
    private function resolveTenantMeta(int $tenantId): array
    {
        try {
            $row = DB::table('tenants')->where('id', $tenantId)->first(['slug', 'name']);
            if ($row) {
                return ['slug' => $row->slug, 'name' => $row->name];
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return ['slug' => null, 'name' => null];
    }

    private function safeJsonDecode(string $json): mixed
    {
        try {
            $decoded = json_decode($json, true);
            return $decoded ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
