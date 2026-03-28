<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * FederationService — Laravel DI-based service for federation/multi-community operations.
 *
 * Manages cross-timebank directory lookups for federated networks.
 */
class FederationService
{
    /**
     * Get all federated timebanks visible to a tenant.
     */
    public function getTimebanks(int $tenantId): array
    {
        return DB::table('federation_tenant_whitelist as fw')
            ->join('tenants as t', 'fw.partner_tenant_id', '=', 't.id')
            ->where('fw.tenant_id', $tenantId)
            ->where('fw.status', 'active')
            ->select('t.id', 't.name', 't.slug', 't.city', 't.country', 'fw.approved_at')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * Get members from a federated timebank.
     */
    public function getMembers(int $tenantId, int $partnerTenantId, int $limit = 20): array
    {
        $whitelisted = DB::table('federation_tenant_whitelist')
            ->where('tenant_id', $tenantId)
            ->where('partner_tenant_id', $partnerTenantId)
            ->where('status', 'active')
            ->exists();

        if (! $whitelisted) {
            return [];
        }

        return DB::table('users as u')
            ->join('federation_user_settings as fus', 'u.id', '=', 'fus.user_id')
            ->where('u.tenant_id', $partnerTenantId)
            ->where('u.status', 'active')
            ->where('fus.federation_optin', 1)
            ->where('fus.appear_in_federated_search', 1)
            ->select('u.id', 'u.name', 'u.city', 'u.bio', 'u.avatar_url as avatar')
            ->limit(min($limit, 100))
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * Get listings from a federated timebank.
     */
    public function getListings(int $tenantId, int $partnerTenantId, int $limit = 20): array
    {
        $whitelisted = DB::table('federation_tenant_whitelist')
            ->where('tenant_id', $tenantId)
            ->where('partner_tenant_id', $partnerTenantId)
            ->where('status', 'active')
            ->exists();

        if (! $whitelisted) {
            return [];
        }

        return DB::table('listings')
            ->where('tenant_id', $partnerTenantId)
            ->where('status', 'active')
            ->where('federation_visible', true)
            ->select('id', 'title', 'description', 'type', 'category_id', 'created_at')
            ->orderByDesc('created_at')
            ->limit(min($limit, 100))
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }
}
