<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Reads the canonical internal federation ledger.
 *
 * Same-platform cross-tenant transfers are recorded in `transactions` with
 * `is_federated = 1`; the older `federation_transactions` table is still read
 * for legacy/external records so admin totals do not go backwards.
 */
class FederationInternalLedgerService
{
    /** @var array<string,bool> */
    private static array $tableExists = [];

    public static function countForTenant(int $tenantId, ?string $since = null): int
    {
        $total = 0;

        if (self::tableExists('transactions')) {
            $sql = "SELECT COUNT(*) AS total FROM transactions
                    WHERE is_federated = 1
                      AND (sender_tenant_id = ? OR receiver_tenant_id = ?)";
            $params = [$tenantId, $tenantId];
            if ($since !== null) {
                $sql .= " AND created_at >= ?";
                $params[] = $since;
            }
            $row = DB::selectOne($sql, $params);
            $total += (int) ($row->total ?? 0);
        }

        if (self::tableExists('federation_transactions')) {
            $sql = "SELECT COUNT(*) AS total FROM federation_transactions
                    WHERE sender_tenant_id = ? OR receiver_tenant_id = ?";
            $params = [$tenantId, $tenantId];
            if ($since !== null) {
                $sql .= " AND created_at >= ?";
                $params[] = $since;
            }
            $row = DB::selectOne($sql, $params);
            $total += (int) ($row->total ?? 0);
        }

        return $total;
    }

    public static function countBetweenTenants(int $firstTenantId, int $secondTenantId, ?string $since = null): int
    {
        $total = 0;

        if (self::tableExists('transactions')) {
            $sql = "SELECT COUNT(*) AS total FROM transactions
                    WHERE is_federated = 1
                      AND (
                        (sender_tenant_id = ? AND receiver_tenant_id = ?)
                        OR (sender_tenant_id = ? AND receiver_tenant_id = ?)
                      )";
            $params = [$firstTenantId, $secondTenantId, $secondTenantId, $firstTenantId];
            if ($since !== null) {
                $sql .= " AND created_at >= ?";
                $params[] = $since;
            }
            $row = DB::selectOne($sql, $params);
            $total += (int) ($row->total ?? 0);
        }

        if (self::tableExists('federation_transactions')) {
            $sql = "SELECT COUNT(*) AS total FROM federation_transactions
                    WHERE (
                        (sender_tenant_id = ? AND receiver_tenant_id = ?)
                        OR (sender_tenant_id = ? AND receiver_tenant_id = ?)
                    )";
            $params = [$firstTenantId, $secondTenantId, $secondTenantId, $firstTenantId];
            if ($since !== null) {
                $sql .= " AND created_at >= ?";
                $params[] = $since;
            }
            $row = DB::selectOne($sql, $params);
            $total += (int) ($row->total ?? 0);
        }

        return $total;
    }

    public static function countCompletedBetweenTenants(int $firstTenantId, int $secondTenantId, ?string $since = null): int
    {
        $total = 0;

        if (self::tableExists('transactions')) {
            $sql = "SELECT COUNT(*) AS total FROM transactions
                    WHERE is_federated = 1
                      AND status = 'completed'
                      AND (
                        (sender_tenant_id = ? AND receiver_tenant_id = ?)
                        OR (sender_tenant_id = ? AND receiver_tenant_id = ?)
                      )";
            $params = [$firstTenantId, $secondTenantId, $secondTenantId, $firstTenantId];
            if ($since !== null) {
                $sql .= " AND created_at >= ?";
                $params[] = $since;
            }
            $row = DB::selectOne($sql, $params);
            $total += (int) ($row->total ?? 0);
        }

        if (self::tableExists('federation_transactions')) {
            $sql = "SELECT COUNT(*) AS total FROM federation_transactions
                    WHERE status = 'completed'
                      AND (
                        (sender_tenant_id = ? AND receiver_tenant_id = ?)
                        OR (sender_tenant_id = ? AND receiver_tenant_id = ?)
                    )";
            $params = [$firstTenantId, $secondTenantId, $secondTenantId, $firstTenantId];
            if ($since !== null) {
                $sql .= " AND created_at >= ?";
                $params[] = $since;
            }
            $row = DB::selectOne($sql, $params);
            $total += (int) ($row->total ?? 0);
        }

        return $total;
    }

    public static function sumCompletedBetweenTenants(int $senderTenantId, int $receiverTenantId, ?string $since = null): float
    {
        $total = 0.0;

        if (self::tableExists('transactions')) {
            $sql = "SELECT COALESCE(SUM(amount), 0) AS total FROM transactions
                    WHERE is_federated = 1
                      AND sender_tenant_id = ?
                      AND receiver_tenant_id = ?
                      AND status = 'completed'";
            $params = [$senderTenantId, $receiverTenantId];
            if ($since !== null) {
                $sql .= " AND created_at >= ?";
                $params[] = $since;
            }
            $row = DB::selectOne($sql, $params);
            $total += (float) ($row->total ?? 0);
        }

        if (self::tableExists('federation_transactions')) {
            $sql = "SELECT COALESCE(SUM(amount), 0) AS total FROM federation_transactions
                    WHERE sender_tenant_id = ?
                      AND receiver_tenant_id = ?
                      AND status = 'completed'";
            $params = [$senderTenantId, $receiverTenantId];
            if ($since !== null) {
                $sql .= " AND created_at >= ?";
                $params[] = $since;
            }
            $row = DB::selectOne($sql, $params);
            $total += (float) ($row->total ?? 0);
        }

        return $total;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function recentBetweenTenants(int $firstTenantId, int $secondTenantId, int $limit = 50): array
    {
        $rows = [];
        $limit = max(1, min($limit, 200));

        if (self::tableExists('transactions')) {
            $rows = array_merge($rows, array_map(static fn($row): array => (array) $row, DB::select(
                "SELECT tx.id, tx.sender_tenant_id, tx.receiver_tenant_id,
                        tx.sender_id AS sender_user_id, tx.receiver_id AS receiver_user_id,
                        tx.amount, tx.description, tx.status, tx.created_at,
                        tx.updated_at AS completed_at,
                        st.name AS sender_tenant_name, rt.name AS receiver_tenant_name,
                        'transactions' AS ledger_source
                 FROM transactions tx
                 LEFT JOIN tenants st ON st.id = tx.sender_tenant_id
                 LEFT JOIN tenants rt ON rt.id = tx.receiver_tenant_id
                 WHERE tx.is_federated = 1
                   AND (
                        (tx.sender_tenant_id = ? AND tx.receiver_tenant_id = ?)
                        OR (tx.sender_tenant_id = ? AND tx.receiver_tenant_id = ?)
                   )
                 ORDER BY tx.created_at DESC
                 LIMIT {$limit}",
                [$firstTenantId, $secondTenantId, $secondTenantId, $firstTenantId]
            )));
        }

        if (self::tableExists('federation_transactions')) {
            $rows = array_merge($rows, array_map(static fn($row): array => (array) $row, DB::select(
                "SELECT ft.id, ft.sender_tenant_id, ft.receiver_tenant_id,
                        ft.sender_user_id, ft.receiver_user_id,
                        ft.amount, ft.description, ft.status,
                        ft.created_at, ft.completed_at,
                        st.name AS sender_tenant_name, rt.name AS receiver_tenant_name,
                        'federation_transactions' AS ledger_source
                 FROM federation_transactions ft
                 LEFT JOIN tenants st ON st.id = ft.sender_tenant_id
                 LEFT JOIN tenants rt ON rt.id = ft.receiver_tenant_id
                 WHERE (
                        (ft.sender_tenant_id = ? AND ft.receiver_tenant_id = ?)
                        OR (ft.sender_tenant_id = ? AND ft.receiver_tenant_id = ?)
                   )
                 ORDER BY ft.created_at DESC
                 LIMIT {$limit}",
                [$firstTenantId, $secondTenantId, $secondTenantId, $firstTenantId]
            )));
        }

        usort($rows, static fn(array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));

        return array_slice($rows, 0, $limit);
    }

    public static function countDistinctListingsForTenant(int $tenantId): int
    {
        $listingIds = [];

        if (self::tableExists('transactions')) {
            $rows = DB::select(
                "SELECT DISTINCT listing_id FROM transactions
                 WHERE is_federated = 1
                   AND listing_id IS NOT NULL
                   AND (sender_tenant_id = ? OR receiver_tenant_id = ?)",
                [$tenantId, $tenantId]
            );
            foreach ($rows as $row) {
                $listingIds['tx:' . (int) $row->listing_id] = true;
            }
        }

        if (self::tableExists('federation_transactions')) {
            $rows = DB::select(
                "SELECT DISTINCT listing_id FROM federation_transactions
                 WHERE listing_id IS NOT NULL
                   AND (sender_tenant_id = ? OR receiver_tenant_id = ? OR listing_tenant_id = ?)",
                [$tenantId, $tenantId, $tenantId]
            );
            foreach ($rows as $row) {
                $listingIds['ft:' . (int) $row->listing_id] = true;
            }
        }

        return count($listingIds);
    }

    private static function tableExists(string $table): bool
    {
        if (array_key_exists($table, self::$tableExists)) {
            return self::$tableExists[$table];
        }

        try {
            DB::select("SELECT 1 FROM `{$table}` LIMIT 1");
            return self::$tableExists[$table] = true;
        } catch (\Throwable) {
            return self::$tableExists[$table] = false;
        }
    }
}
