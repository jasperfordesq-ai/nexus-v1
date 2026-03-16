<?php
// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * TenantService — Laravel DI-based service for tenant/multi-tenancy operations.
 *
 * Provides tenant bootstrapping, listing, and settings retrieval.
 */
class TenantService
{
    public function __construct(
        private readonly Tenant $tenant,
    ) {}

    /**
     * Bootstrap a tenant by slug — loads the tenant record and its settings.
     *
     * @return array{tenant: array, settings: array}|null
     */
    public function bootstrap(string $slug): ?array
    {
        $tenant = $this->tenant->newQuery()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (! $tenant) {
            return null;
        }

        $settings = DB::table('tenant_settings')
            ->where('tenant_id', $tenant->id)
            ->pluck('value', 'key')
            ->all();

        return [
            'tenant'   => $tenant->toArray(),
            'settings' => $settings,
        ];
    }

    /**
     * Get all active tenants.
     */
    public function getAll(): Collection
    {
        return $this->tenant->newQuery()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get settings key-value map for a given tenant.
     */
    public function getSettings(int $tenantId): array
    {
        return DB::table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->pluck('value', 'key')
            ->all();
    }
}
