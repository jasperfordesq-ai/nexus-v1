<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TenantBillingService — Billing snapshot and plan assignment for the super admin.
 *
 * Provides per-tenant user counts (including subtree descendants), current plan
 * lookups, over-limit detection, and atomic plan upserts with activity logging.
 */
class TenantBillingService
{
    /**
     * Count all active users in a tenant AND all its descendants using the
     * materialised path column.
     *
     * Path for tenant 5 is like `/1/5/`, so descendants start with `/1/5/`.
     */
    public static function getSubtreeUserCount(int $tenantId): int
    {
        // Step 1: get the path for this tenant.
        $row = DB::selectOne('SELECT path FROM tenants WHERE id = ?', [$tenantId]);
        if (!$row) {
            return 0;
        }

        $path = $row->path;

        // Step 2: count active users in this tenant + all descendants.
        $result = DB::selectOne(
            'SELECT COUNT(*) AS cnt
             FROM users u
             INNER JOIN tenants t ON u.tenant_id = t.id
             WHERE (t.id = ? OR t.path LIKE ?)
               AND u.status = ?',
            [$tenantId, $path . '%', 'active']
        );

        return (int) ($result->cnt ?? 0);
    }

    /**
     * Return a billing snapshot for all non-master tenants (tenant_id != 1).
     *
     * Each row includes own_user_count, subtree_user_count, current_plan,
     * suggested_plan, and over_limit flag.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getBillingSnapshot(): array
    {
        // Fetch all non-master tenants ordered by depth then name.
        $tenants = DB::select(
            'SELECT id, name, slug, depth, parent_id, allows_subtenants
             FROM tenants
             WHERE id != 1
             ORDER BY depth ASC, name ASC'
        );

        if (empty($tenants)) {
            return [];
        }

        $tenantIds = array_map(fn($t) => (int) $t->id, $tenants);

        // Batch own_user_count: direct users per tenant.
        $placeholders = implode(',', array_fill(0, count($tenantIds), '?'));
        $ownCounts = DB::select(
            "SELECT tenant_id, COUNT(*) AS cnt
             FROM users
             WHERE tenant_id IN ({$placeholders}) AND status = 'active'
             GROUP BY tenant_id",
            $tenantIds
        );
        $ownCountMap = [];
        foreach ($ownCounts as $row) {
            $ownCountMap[(int) $row->tenant_id] = (int) $row->cnt;
        }

        // Fetch active plan assignments for these tenants.
        $assignments = DB::select(
            "SELECT tpa.tenant_id, pp.name AS plan_name, pp.slug AS plan_slug,
                    pp.max_users, pp.price_yearly
             FROM tenant_plan_assignments tpa
             INNER JOIN pay_plans pp ON pp.id = tpa.pay_plan_id
             WHERE tpa.tenant_id IN ({$placeholders})
               AND tpa.status = 'active'",
            $tenantIds
        );
        $planMap = [];
        foreach ($assignments as $row) {
            $planMap[(int) $row->tenant_id] = [
                'name'        => $row->plan_name,
                'slug'        => $row->plan_slug,
                'max_users'   => $row->max_users !== null ? (int) $row->max_users : null,
                'price_yearly'=> (float) $row->price_yearly,
            ];
        }

        $snapshot = [];
        foreach ($tenants as $tenant) {
            $id              = (int) $tenant->id;
            $ownCount        = $ownCountMap[$id] ?? 0;
            $subtreeCount    = self::getSubtreeUserCount($id);
            $currentPlan     = $planMap[$id] ?? null;

            // Suggested plan based on subtree user count.
            $suggested = match (true) {
                $subtreeCount <= 50   => 'seed',
                $subtreeCount <= 250  => 'community',
                $subtreeCount <= 1000 => 'regional',
                default               => 'network',
            };

            // over_limit: false when max_users is null (unlimited).
            $overLimit = false;
            if ($currentPlan !== null && $currentPlan['max_users'] !== null) {
                $overLimit = $subtreeCount > $currentPlan['max_users'];
            }

            $snapshot[] = [
                'id'                  => $id,
                'name'                => $tenant->name,
                'slug'                => $tenant->slug,
                'depth'               => (int) $tenant->depth,
                'parent_id'           => $tenant->parent_id !== null ? (int) $tenant->parent_id : null,
                'allows_subtenants'   => (bool) $tenant->allows_subtenants,
                'own_user_count'      => $ownCount,
                'subtree_user_count'  => $subtreeCount,
                'current_plan'        => $currentPlan,
                'suggested_plan'      => $suggested,
                'over_limit'          => $overLimit,
            ];
        }

        return $snapshot;
    }

    /**
     * Upsert a plan assignment for a tenant.
     *
     * If an active assignment already exists, update it; otherwise insert a new row.
     * Logs to activity_log if that table exists.
     */
    public static function assignPlan(
        int $tenantId,
        int $planId,
        ?string $expiresAt,
        ?string $notes,
        int $assignedBy
    ): void {
        $hasNotes = Schema::hasColumn('tenant_plan_assignments', 'notes');

        $existing = DB::selectOne(
            "SELECT id FROM tenant_plan_assignments WHERE tenant_id = ? AND status = 'active' LIMIT 1",
            [$tenantId]
        );

        $now = now();

        if ($existing) {
            // Update existing active assignment.
            $updates  = ['pay_plan_id' => $planId, 'expires_at' => $expiresAt, 'updated_at' => $now];
            if ($hasNotes) {
                $updates['notes'] = $notes;
            }
            DB::table('tenant_plan_assignments')
                ->where('id', $existing->id)
                ->update($updates);
        } else {
            // Insert new assignment.
            $insert = [
                'tenant_id'  => $tenantId,
                'pay_plan_id'=> $planId,
                'status'     => 'active',
                'starts_at'  => $now,
                'expires_at' => $expiresAt,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if ($hasNotes) {
                $insert['notes'] = $notes;
            }
            DB::table('tenant_plan_assignments')->insert($insert);
        }

        // Log to activity_log if table exists.
        if (Schema::hasTable('activity_log')) {
            DB::table('activity_log')->insert([
                'tenant_id'   => $tenantId,
                'user_id'     => $assignedBy,
                'action'      => 'billing.plan_assigned',
                'entity_type' => 'tenant',
                'entity_id'   => $tenantId,
                'details'     => json_encode(['plan_id' => $planId, 'notes' => $notes]),
                'created_at'  => $now,
            ]);
        }
    }
}
