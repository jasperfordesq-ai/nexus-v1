<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * TenantSettingsService — reads/writes tenant_settings (key-value) table,
 * enforces login gates, and checks registration policy.
 */
class TenantSettingsService
{
    private const CACHE_PREFIX = 'tenant_settings:';
    private const CACHE_TTL = 300; // 5 minutes

    public function __construct()
    {
    }

    /**
     * Get a single tenant setting value.
     */
    public static function get(int $tenantId, string $key, $default = null)
    {
        $settings = static::loadAll($tenantId);
        return $settings[$key] ?? $default;
    }

    /**
     * Get a tenant setting as a boolean.
     */
    public static function getBool(int $tenantId, string $key, bool $default = false): bool
    {
        $value = static::get($tenantId, $key);

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Set a tenant setting value.
     */
    public static function set(int $tenantId, string $key, string $value, string $type = 'string'): void
    {
        $existing = DB::selectOne(
            "SELECT id FROM tenant_settings WHERE tenant_id = ? AND setting_key = ?",
            [$tenantId, $key]
        );

        if ($existing) {
            DB::update(
                "UPDATE tenant_settings SET setting_value = ? WHERE tenant_id = ? AND setting_key = ?",
                [$value, $tenantId, $key]
            );
        } else {
            DB::insert(
                "INSERT INTO tenant_settings (tenant_id, setting_key, setting_value, setting_type) VALUES (?, ?, ?, ?)",
                [$tenantId, $key, $value, $type]
            );
        }

        static::clearCacheForTenant($tenantId);
    }

    /**
     * Get all settings for a tenant.
     */
    public static function getAllGeneral(int $tenantId): array
    {
        return static::loadAll($tenantId);
    }

    /**
     * Clear all cached settings.
     */
    public static function clearCache(): void
    {
        // Flush all tenant settings from cache
        // Since we can't enumerate all keys easily, use a tagged approach
        // or just clear specific known tenants. For now, flush the cache store.
        try {
            Cache::forget(self::CACHE_PREFIX . '*');
        } catch (\Throwable $e) {
            // Ignore cache errors
        }
    }

    /**
     * Clear cached settings for a specific tenant.
     */
    public static function clearCacheForTenant(int $tenantId): void
    {
        Cache::forget(self::CACHE_PREFIX . $tenantId);
    }

    /**
     * Check if registration is open for a tenant.
     *
     * Reads the `registration_mode` setting. Defaults to 'open' if not set.
     */
    public static function isRegistrationOpen(int $tenantId): bool
    {
        $mode = static::get($tenantId, 'registration_mode', 'open');
        return $mode === 'open';
    }

    /**
     * Check login gates for a user array.
     *
     * Admins and super admins always pass. Regular members may be blocked by:
     * - Pending/failed identity verification
     * - Unapproved account when admin_approval is required
     * - Unverified email when email_verification is required
     *
     * @param array $user User row (must include: role, is_super_admin, is_tenant_super_admin, tenant_id)
     * @return array|null Null = passes, or ['code' => ..., 'message' => ..., 'extra' => [...]]
     */
    public static function checkLoginGates(array $user): ?array
    {
        return static::checkLoginGatesForUser($user);
    }

    /**
     * Check login gates for a specific user array.
     *
     * @param array $user User row from DB
     * @return array|null Null = passes, or error array
     */
    public static function checkLoginGatesForUser(array $user): ?array
    {
        $role = $user['role'] ?? 'member';
        $isSuperAdmin = !empty($user['is_super_admin']);
        $isTenantSuperAdmin = !empty($user['is_tenant_super_admin']);

        // Admins and super admins always pass login gates
        if (in_array($role, ['admin', 'tenant_admin', 'super_admin', 'god'], true)
            || $isSuperAdmin
            || $isTenantSuperAdmin
        ) {
            return null;
        }

        $tenantId = (int)($user['tenant_id'] ?? 0);

        // Check identity verification status (if present)
        $verificationStatus = $user['verification_status'] ?? null;
        if ($verificationStatus === 'pending') {
            return [
                'code' => 'AUTH_PENDING_VERIFICATION',
                'message' => 'Your identity verification is pending. Please wait for approval.',
                'extra' => ['pending_verification' => true],
            ];
        }
        if ($verificationStatus === 'failed') {
            return [
                'code' => 'AUTH_VERIFICATION_FAILED',
                'message' => 'Your identity verification has failed. Please contact support.',
                'extra' => ['verification_failed' => true],
            ];
        }

        // Check email verification requirement
        if ($tenantId > 0 && static::getBool($tenantId, 'email_verification', false)) {
            if (empty($user['email_verified_at'])) {
                return [
                    'code' => 'AUTH_EMAIL_NOT_VERIFIED',
                    'message' => 'Please verify your email address before logging in.',
                    'extra' => ['email_not_verified' => true],
                ];
            }
        }

        // Check admin approval requirement
        if ($tenantId > 0 && static::getBool($tenantId, 'admin_approval', false)) {
            if (empty($user['is_approved'])) {
                return [
                    'code' => 'AUTH_ACCOUNT_PENDING_APPROVAL',
                    'message' => 'Your account is pending admin approval.',
                    'extra' => ['pending_approval' => true],
                ];
            }
        }

        return null;
    }

    /**
     * Load all settings for a tenant (with caching).
     */
    private static function loadAll(int $tenantId): array
    {
        $cacheKey = self::CACHE_PREFIX . $tenantId;

        try {
            return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($tenantId) {
                $rows = DB::select(
                    "SELECT setting_key, setting_value FROM tenant_settings WHERE tenant_id = ?",
                    [$tenantId]
                );

                $settings = [];
                foreach ($rows as $row) {
                    $settings[$row->setting_key] = $row->setting_value;
                }
                return $settings;
            });
        } catch (\Throwable $e) {
            // If DB/cache fails, return empty to avoid blocking login
            error_log('[TenantSettingsService] loadAll failed for tenant ' . $tenantId . ': ' . $e->getMessage());
            return [];
        }
    }
}
