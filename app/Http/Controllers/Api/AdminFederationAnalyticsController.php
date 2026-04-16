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

/**
 * AdminFederationAnalyticsController
 *
 * Exposes richer analytics for the federation admin dashboard:
 * KPI cards, daily API call volumes, top partners by activity,
 * and the most recent API errors.
 *
 * All queries are scoped to the current tenant via TenantContext.
 */
class AdminFederationAnalyticsController extends BaseApiController
{
    protected bool $isV2Api = true;

    private const ALLOWED_RANGES = ['7d' => 7, '30d' => 30, '90d' => 90];

    /**
     * GET /api/v2/admin/federation/analytics/overview
     */
    public function overview(Request $request): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $rangeKey = (string) $request->input('range', '30d');
        $days = self::ALLOWED_RANGES[$rangeKey] ?? 30;
        $since = date('Y-m-d 00:00:00', strtotime("-{$days} days"));

        return $this->respondWithData([
            'range_days' => $days,
            'kpis' => $this->kpis($tenantId, $since),
            'daily_calls' => $this->dailyCalls($tenantId, $days),
            'top_partners' => $this->topPartners($tenantId, $since),
            'recent_errors' => $this->recentErrors($tenantId),
        ]);
    }

    /**
     * @return array<string,int>
     */
    private function kpis(int $tenantId, string $since): array
    {
        $kpis = [
            'total_partnerships' => 0,
            'active_partnerships' => 0,
            'pending_partnerships' => 0,
            'external_partners' => 0,
            'federated_transactions' => 0,
            'federated_messages' => 0,
            'federated_listings' => 0,
            'inbound_reviews' => 0,
        ];

        if ($this->tableExists('federation_partnerships')) {
            try {
                $row = DB::selectOne(
                    'SELECT COUNT(*) AS total,
                            SUM(CASE WHEN status = \'active\' THEN 1 ELSE 0 END) AS active_count,
                            SUM(CASE WHEN status = \'pending\' THEN 1 ELSE 0 END) AS pending_count
                     FROM federation_partnerships
                     WHERE tenant_id = ? OR partner_tenant_id = ?',
                    [$tenantId, $tenantId]
                );
                $kpis['total_partnerships'] = (int) ($row->total ?? 0);
                $kpis['active_partnerships'] = (int) ($row->active_count ?? 0);
                $kpis['pending_partnerships'] = (int) ($row->pending_count ?? 0);
            } catch (\Throwable) {
                // ignore; keep defaults
            }
        }

        if ($this->tableExists('federation_external_partners')) {
            try {
                $row = DB::selectOne(
                    'SELECT COUNT(*) AS total FROM federation_external_partners WHERE tenant_id = ?',
                    [$tenantId]
                );
                $kpis['external_partners'] = (int) ($row->total ?? 0);
            } catch (\Throwable) {
                // ignore
            }
        }

        if ($this->tableExists('federation_transactions')) {
            try {
                $row = DB::selectOne(
                    'SELECT COUNT(*) AS total FROM federation_transactions
                     WHERE (sender_tenant_id = ? OR receiver_tenant_id = ?)
                       AND created_at >= ?',
                    [$tenantId, $tenantId, $since]
                );
                $kpis['federated_transactions'] = (int) ($row->total ?? 0);
            } catch (\Throwable) {
                // ignore
            }
        }

        if ($this->tableExists('federation_messages')) {
            try {
                $row = DB::selectOne(
                    'SELECT COUNT(*) AS total FROM federation_messages
                     WHERE (sender_tenant_id = ? OR receiver_tenant_id = ?)
                       AND created_at >= ?',
                    [$tenantId, $tenantId, $since]
                );
                $kpis['federated_messages'] = (int) ($row->total ?? 0);
            } catch (\Throwable) {
                // ignore
            }
        }

        // Federated listings: count listings that are flagged as
        // federated (or public to federation) — schema dependent.
        // Defensive: use listing_tenant_id link from transactions as a proxy.
        if ($this->tableExists('federation_transactions')) {
            try {
                $row = DB::selectOne(
                    'SELECT COUNT(DISTINCT listing_id) AS total
                     FROM federation_transactions
                     WHERE listing_id IS NOT NULL
                       AND (sender_tenant_id = ? OR receiver_tenant_id = ? OR listing_tenant_id = ?)',
                    [$tenantId, $tenantId, $tenantId]
                );
                $kpis['federated_listings'] = (int) ($row->total ?? 0);
            } catch (\Throwable) {
                // ignore
            }
        }

        if ($this->tableExists('federation_reputation')) {
            try {
                $row = DB::selectOne(
                    'SELECT COALESCE(SUM(reviews_received), 0) AS total
                     FROM federation_reputation
                     WHERE home_tenant_id = ?',
                    [$tenantId]
                );
                $kpis['inbound_reviews'] = (int) ($row->total ?? 0);
            } catch (\Throwable) {
                // ignore
            }
        }

        return $kpis;
    }

    /**
     * Return a dense array of {date, count} for the last $days days, aggregated from
     * federation_api_logs joined to federation_api_keys (for tenant scoping).
     *
     * @return array<int,array{date:string,count:int}>
     */
    private function dailyCalls(int $tenantId, int $days): array
    {
        $buckets = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $buckets[$d] = 0;
        }

        if (!$this->tableExists('federation_api_logs') || !$this->tableExists('federation_api_keys')) {
            return array_map(
                static fn(string $date, int $count): array => ['date' => $date, 'count' => $count],
                array_keys($buckets),
                array_values($buckets)
            );
        }

        try {
            $since = date('Y-m-d 00:00:00', strtotime("-{$days} days"));
            $rows = DB::select(
                'SELECT DATE(l.created_at) AS day, COUNT(*) AS total
                 FROM federation_api_logs l
                 INNER JOIN federation_api_keys k ON k.id = l.api_key_id
                 WHERE k.tenant_id = ? AND l.created_at >= ?
                 GROUP BY DATE(l.created_at)
                 ORDER BY day ASC',
                [$tenantId, $since]
            );
            foreach ($rows as $row) {
                $day = (string) $row->day;
                if (isset($buckets[$day])) {
                    $buckets[$day] = (int) $row->total;
                }
            }
        } catch (\Throwable) {
            // ignore; return zeros
        }

        $out = [];
        foreach ($buckets as $date => $count) {
            $out[] = ['date' => $date, 'count' => $count];
        }
        return $out;
    }

    /**
     * Top partners by activity (transactions + messages) in window.
     *
     * @return array<int,array{tenant_id:int,name:string,activity:int}>
     */
    private function topPartners(int $tenantId, string $since): array
    {
        if (!$this->tableExists('federation_partnerships')) {
            return [];
        }

        try {
            $rows = DB::select(
                'SELECT p.id AS partnership_id,
                        CASE WHEN p.tenant_id = ? THEN p.partner_tenant_id ELSE p.tenant_id END AS partner_id,
                        t.name AS partner_name,
                        (
                          (SELECT COUNT(*) FROM federation_transactions ft
                            WHERE ((ft.sender_tenant_id = ? AND ft.receiver_tenant_id = CASE WHEN p.tenant_id = ? THEN p.partner_tenant_id ELSE p.tenant_id END)
                                OR (ft.receiver_tenant_id = ? AND ft.sender_tenant_id = CASE WHEN p.tenant_id = ? THEN p.partner_tenant_id ELSE p.tenant_id END))
                              AND ft.created_at >= ?)
                          +
                          (SELECT COUNT(*) FROM federation_messages fm
                            WHERE ((fm.sender_tenant_id = ? AND fm.receiver_tenant_id = CASE WHEN p.tenant_id = ? THEN p.partner_tenant_id ELSE p.tenant_id END)
                                OR (fm.receiver_tenant_id = ? AND fm.sender_tenant_id = CASE WHEN p.tenant_id = ? THEN p.partner_tenant_id ELSE p.tenant_id END))
                              AND fm.created_at >= ?)
                        ) AS activity
                 FROM federation_partnerships p
                 LEFT JOIN tenants t ON t.id = CASE WHEN p.tenant_id = ? THEN p.partner_tenant_id ELSE p.tenant_id END
                 WHERE (p.tenant_id = ? OR p.partner_tenant_id = ?)
                   AND p.status = \'active\'
                 ORDER BY activity DESC
                 LIMIT 10',
                [
                    $tenantId,
                    $tenantId, $tenantId, $tenantId, $tenantId, $since,
                    $tenantId, $tenantId, $tenantId, $tenantId, $since,
                    $tenantId,
                    $tenantId, $tenantId,
                ]
            );

            $out = [];
            foreach ($rows as $row) {
                $out[] = [
                    'tenant_id' => (int) $row->partner_id,
                    'name' => (string) ($row->partner_name ?? 'Tenant #' . $row->partner_id),
                    'activity' => (int) $row->activity,
                ];
            }
            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Most recent API log entries with non-2xx response codes for this tenant.
     *
     * @return array<int,array{id:int,endpoint:string,method:string,response_code:int,created_at:string}>
     */
    private function recentErrors(int $tenantId): array
    {
        if (!$this->tableExists('federation_api_logs') || !$this->tableExists('federation_api_keys')) {
            return [];
        }

        try {
            $rows = DB::select(
                'SELECT l.id, l.endpoint, l.method, l.response_code, l.created_at, l.ip_address
                 FROM federation_api_logs l
                 INNER JOIN federation_api_keys k ON k.id = l.api_key_id
                 WHERE k.tenant_id = ?
                   AND (l.response_code IS NULL OR l.response_code >= 400)
                 ORDER BY l.created_at DESC
                 LIMIT 50',
                [$tenantId]
            );

            $out = [];
            foreach ($rows as $row) {
                $out[] = [
                    'id' => (int) $row->id,
                    'endpoint' => (string) $row->endpoint,
                    'method' => (string) $row->method,
                    'response_code' => (int) ($row->response_code ?? 0),
                    'ip_address' => (string) ($row->ip_address ?? ''),
                    'created_at' => (string) $row->created_at,
                ];
            }
            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    private const ALLOWED_TABLES = [
        'federation_partnerships', 'federation_external_partners', 'federation_transactions',
        'federation_messages', 'federation_reputation', 'federation_api_logs', 'federation_api_keys',
    ];

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
