<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * AdminSettingsService — Laravel DI-based service for admin settings management.
 *
 * Manages tenant-scoped configuration, feature toggles, and preferences.
 */
class AdminSettingsService
{
    /**
     * Get all settings for a tenant.
     */
    public function getAll(int $tenantId): array
    {
        return DB::table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->pluck('setting_value', 'setting_key')
            ->all();
    }

    /**
     * Update one or more settings for a tenant.
     */
    public function update(int $tenantId, array $settings): bool
    {
        foreach ($settings as $key => $value) {
            DB::table('tenant_settings')->updateOrInsert(
                ['tenant_id' => $tenantId, 'setting_key' => $key],
                ['setting_value' => is_array($value) ? json_encode($value) : (string) $value, 'updated_at' => now()]
            );
        }

        return true;
    }

    /**
     * Get all feature flags for a tenant.
     */
    public function getFeatures(int $tenantId): array
    {
        $tenant = DB::table('tenants')->where('id', $tenantId)->first();
        if (! $tenant) {
            return [];
        }

        return json_decode($tenant->features ?? '{}', true) ?: [];
    }

    /**
     * Toggle a specific feature on or off.
     */
    public function toggleFeature(int $tenantId, string $feature, bool $enabled): bool
    {
        $tenant = DB::table('tenants')->where('id', $tenantId)->first();
        if (! $tenant) {
            return false;
        }

        $features = json_decode($tenant->features ?? '{}', true) ?: [];
        $features[$feature] = $enabled;

        return DB::table('tenants')
            ->where('id', $tenantId)
            ->update(['features' => json_encode($features), 'updated_at' => now()]) > 0;
    }
}
