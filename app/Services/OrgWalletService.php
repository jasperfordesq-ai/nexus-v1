<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * OrgWalletService — Laravel DI-based service for organization wallet operations.
 *
 * Manages organization time-credit balances and inter-org transfers.
 */
class OrgWalletService
{
    /**
     * Get the balance for an organization.
     */
    public function getBalance(int $orgId, int $tenantId): array
    {
        $org = DB::table('organizations')
            ->where('id', $orgId)
            ->where('tenant_id', $tenantId)
            ->first(['id', 'name', 'balance']);

        if (! $org) {
            return ['balance' => 0.0, 'org_id' => $orgId];
        }

        return ['balance' => (float) $org->balance, 'org_id' => $orgId, 'name' => $org->name];
    }

    /**
     * Get transactions for an organization.
     */
    public function getTransactions(int $orgId, int $tenantId, int $limit = 20, int $offset = 0): array
    {
        $query = DB::table('org_transactions')
            ->where('org_id', $orgId)
            ->where('tenant_id', $tenantId);

        $total = $query->count();
        $items = $query->orderByDesc('created_at')
            ->offset($offset)
            ->limit(min($limit, 100))
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Transfer credits between organizations.
     */
    public function transfer(int $fromOrgId, int $toOrgId, float $amount, int $tenantId, ?string $note = null): bool
    {
        if ($amount <= 0 || $fromOrgId === $toOrgId) {
            return false;
        }

        return DB::transaction(function () use ($fromOrgId, $toOrgId, $amount, $tenantId, $note) {
            $from = DB::table('organizations')
                ->where('id', $fromOrgId)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->first();

            if (! $from || (float) $from->balance < $amount) {
                return false;
            }

            DB::table('organizations')->where('id', $fromOrgId)->decrement('balance', $amount);
            DB::table('organizations')->where('id', $toOrgId)->where('tenant_id', $tenantId)->increment('balance', $amount);

            $now = now();
            DB::table('org_transactions')->insert([
                ['org_id' => $fromOrgId, 'tenant_id' => $tenantId, 'type' => 'transfer_out', 'amount' => -$amount, 'related_org_id' => $toOrgId, 'note' => $note, 'created_at' => $now],
                ['org_id' => $toOrgId, 'tenant_id' => $tenantId, 'type' => 'transfer_in', 'amount' => $amount, 'related_org_id' => $fromOrgId, 'note' => $note, 'created_at' => $now],
            ]);

            return true;
        });
    }
}
