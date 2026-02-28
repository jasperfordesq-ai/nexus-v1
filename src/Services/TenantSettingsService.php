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
