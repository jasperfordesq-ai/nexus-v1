<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * TenantSettingsService - Read/write tenant_settings key-value store
 *
 * Provides a clean API for reading registration policy settings that are
 * stored in the `tenant_settings` table with `general.*` prefixed keys.
 *
 * CRITICAL: This service is the single source of truth for registration
 * enforcement. Both AuthController and RegistrationApiController MUST
 * use this service to check policies.
 */
class TenantSettingsService
{
    /** @var array<int, array<string, mixed>> In-memory cache per tenant per request */
    private static array $cache = [];

    /**
     * Get a single setting value for a tenant.
     *
     * @param int    $tenantId
     * @param string $key      e.g. 'registration_mode', 'email_verification', 'admin_approval'
     * @param mixed  $default  Returned when the key is not set
     * @return mixed
     */
    public static function get(int $tenantId, string $key, $default = null)
    {
        $all = self::getAllGeneral($tenantId);
        return $all[$key] ?? $default;
    }

    /**
     * Get a boolean setting (handles string 'true'/'false' from DB).
     *
     * @param int    $tenantId
     * @param string $key
     * @param bool   $default
     * @return bool
     */
    public static function getBool(int $tenantId, string $key, bool $default = false): bool
    {
        $value = self::get($tenantId, $key);
        if ($value === null) {
            return $default;
        }
        if (is_bool($value)) {
            return $value;
        }
        // Handle string representations from the DB
        return in_array(strtolower((string) $value), ['true', '1', 'yes'], true);
    }

    /**
     * Load all general.* settings for a tenant (cached per-request).
     *
     * @param int $tenantId
     * @return array<string, mixed>  Keys WITHOUT the 'general.' prefix
     */
    public static function getAllGeneral(int $tenantId): array
    {
        if (isset(self::$cache[$tenantId])) {
            return self::$cache[$tenantId];
        }

        $result = [];

        try {
            $rows = Database::query(
                "SELECT setting_key, setting_value FROM tenant_settings WHERE tenant_id = ? AND setting_key LIKE 'general.%'",
                [$tenantId]
            )->fetchAll();

            foreach ($rows as $row) {
                // Strip 'general.' prefix
                $key = substr($row['setting_key'], 8);
                $result[$key] = $row['setting_value'];
            }
        } catch (\Throwable $e) {
            // Table may not exist yet — return defaults
            error_log("[TenantSettingsService] Failed to read settings for tenant {$tenantId}: " . $e->getMessage());
        }

        self::$cache[$tenantId] = $result;
        return $result;
    }

    /**
     * Clear the in-memory cache (useful after writes).
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }

    // ─── Registration Policy Convenience Methods ────────────────────────

    /**
     * Is registration open for this tenant?
     * Default: true (open) if not explicitly set.
     */
    public static function isRegistrationOpen(int $tenantId): bool
    {
        $mode = self::get($tenantId, 'registration_mode', 'open');
        return strtolower((string) $mode) === 'open';
    }

    /**
     * Does this tenant require email verification before login?
     * Default: true (require verification) — secure by default.
     */
    public static function requiresEmailVerification(int $tenantId): bool
    {
        return self::getBool($tenantId, 'email_verification', true);
    }

    /**
     * Does this tenant require admin approval before login?
     * Default: true (require approval) — secure by default.
     */
    public static function requiresAdminApproval(int $tenantId): bool
    {
        return self::getBool($tenantId, 'admin_approval', true);
    }

    /**
     * Upsert a setting value for a tenant.
     *
     * @param int         $tenantId
     * @param string      $key       e.g. 'email_verification'
     * @param string|null $value
     * @param string      $type      One of: string, boolean, integer, float, json, array
     */
    public static function set(int $tenantId, string $key, ?string $value, string $type = 'string'): void
    {
        Database::query(
            "INSERT INTO tenant_settings (tenant_id, setting_key, setting_value, setting_type)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = VALUES(setting_type), updated_at = CURRENT_TIMESTAMP",
            [$tenantId, 'general.' . $key, $value, $type]
        );

        // Invalidate cache
        unset(self::$cache[$tenantId]);
    }

    /**
     * Check if a user passes all registration policy gates for their tenant.
     * Returns null if the user passes, or an error array if blocked.
     *
     * IMPORTANT: Call this from EVERY authentication entry point that issues
     * tokens or creates sessions (login, token refresh, social auth, WebAuthn,
     * 2FA completion, session restore). The login controller is NOT sufficient
     * alone — tokens from other flows bypass it.
     *
     * @param array $user  User row from DB (must include: role, is_super_admin,
     *                     is_tenant_super_admin, tenant_id, email_verified_at, is_approved)
     * @return array|null  Null = passes, or ['code' => ..., 'message' => ..., 'extra' => [...]]
     */
    public static function checkLoginGates(array $user): ?array
    {
        $isAdminRole = in_array($user['role'] ?? '', ['admin', 'tenant_admin', 'tenant_super_admin', 'super_admin'])
            || !empty($user['is_super_admin'])
            || !empty($user['is_tenant_super_admin']);

        // Admins/super-admins always bypass registration gates
        if ($isAdminRole) {
            return null;
        }

        $tenantId = (int) ($user['tenant_id'] ?? 0);

        // Gate 1: Email verification
        if (self::requiresEmailVerification($tenantId) && empty($user['email_verified_at'])) {
            return [
                'code' => 'AUTH_EMAIL_NOT_VERIFIED',
                'message' => 'Please verify your email address before logging in. Check your inbox for the verification link.',
                'extra' => ['requires_verification' => true, 'can_resend' => true],
            ];
        }

        // Gate 2: Identity verification (if tenant uses verified_identity or government_id mode)
        $verificationStatus = $user['verification_status'] ?? 'none';
        if ($verificationStatus === 'pending') {
            return [
                'code' => 'AUTH_PENDING_VERIFICATION',
                'message' => 'Your identity verification is in progress. Please complete the verification process to continue.',
                'extra' => ['pending_verification' => true, 'verification_status' => 'pending'],
            ];
        }
        if ($verificationStatus === 'failed') {
            return [
                'code' => 'AUTH_VERIFICATION_FAILED',
                'message' => 'Your identity verification did not pass. Please contact support or try again.',
                'extra' => ['verification_failed' => true, 'verification_status' => 'failed'],
            ];
        }

        // Gate 3: Admin approval
        if (self::requiresAdminApproval($tenantId) && empty($user['is_approved'])) {
            return [
                'code' => 'AUTH_ACCOUNT_PENDING_APPROVAL',
                'message' => 'Your account is pending approval by a community administrator. You will receive an email once approved.',
                'extra' => ['pending_approval' => true],
            ];
        }

        return null; // All gates passed
    }

    /**
     * Seed secure defaults for a tenant if settings don't exist.
     * Called during registration enforcement and by migration.
     *
     * @param int $tenantId
     */
    public static function seedDefaults(int $tenantId): void
    {
        $defaults = [
            'registration_mode'  => ['open', 'string'],
            'email_verification' => ['true', 'boolean'],
            'admin_approval'     => ['true', 'boolean'],
            'maintenance_mode'   => ['false', 'boolean'],
        ];

        foreach ($defaults as $key => [$value, $type]) {
            // Only seed if not already set (INSERT IGNORE)
            try {
                Database::query(
                    "INSERT IGNORE INTO tenant_settings (tenant_id, setting_key, setting_value, setting_type)
                     VALUES (?, ?, ?, ?)",
                    [$tenantId, 'general.' . $key, $value, $type]
                );
            } catch (\Throwable $e) {
                // Ignore duplicates
            }
        }
    }
}
