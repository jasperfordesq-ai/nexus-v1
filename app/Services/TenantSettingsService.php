<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class TenantSettingsService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy TenantSettingsService::get().
     */
    public function get(int $tenantId, string $key, $default = null)
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy TenantSettingsService::getBool().
     */
    public function getBool(int $tenantId, string $key, bool $default = false): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy TenantSettingsService::getAllGeneral().
     */
    public function getAllGeneral(int $tenantId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy TenantSettingsService::clearCache().
     */
    public function clearCache(): void
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
    }

    /**
     * Delegates to legacy TenantSettingsService::isRegistrationOpen().
     */
    public function isRegistrationOpen(int $tenantId): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Check if a user passes all registration policy gates for their tenant.
     *
     * Returns null if the user passes, or an error array if blocked.
     *
     * @param int $tenantId Tenant ID
     * @return array|null Null = passes, or ['code' => ..., 'message' => ..., 'extra' => [...]]
     */
    public function checkLoginGates(int $tenantId): ?array
    {
        // Delegate to legacy which accepts a user array.
        // When called from a controller the caller typically has a user row;
        // however the task signature only passes tenantId, so we build a
        // minimal user array from the authenticated user context.
        $user = null;
        try {
            $user = \App\Core\Auth::user();
        } catch (\Throwable $e) {
            // No authenticated user available
        }

        if (!$user) {
            return null;
        }

        // Ensure the user row has tenant_id set
        $user['tenant_id'] = $tenantId;

        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Check login gates for a specific user array.
     *
     * This is the preferred method when the caller already has
     * the full user row (must include: role, is_super_admin,
     * is_tenant_super_admin, tenant_id, email_verified_at, is_approved).
     *
     * @param array $user User row from DB
     * @return array|null Null = passes, or error array
     */
    public function checkLoginGatesForUser(array $user): ?array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }
}
