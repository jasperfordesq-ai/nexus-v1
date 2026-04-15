<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CookieConsentService — Laravel DI-based service for cookie consent management.
 *
 * Manages GDPR cookie consent recording and validation.
 * All queries are tenant-scoped to prevent cross-tenant data leakage.
 */
class CookieConsentService
{
    private const CONSENT_VERSION = '1.0';
    private const DEFAULT_VALIDITY_DAYS = 365;

    /**
     * Get the current consent status for a user or IP session.
     *
     * Called by CookieConsentController::show().
     * Tenant-scoped: only returns consent for the given tenant.
     */
    public function getConsent(?int $userId, int $tenantId, ?string $ipAddress = null): ?array
    {
        $query = DB::table('cookie_consents')
            ->where('tenant_id', $tenantId)
            ->whereNull('withdrawal_date')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('created_at');

        if ($userId) {
            $query->where('user_id', $userId);
        } elseif ($ipAddress) {
            $query->where('ip_address', $ipAddress);
        } else {
            return null;
        }

        $record = $query->first();

        return $record ? (array) $record : null;
    }

    /**
     * Store cookie consent preferences.
     *
     * Called by CookieConsentController::store().
     * Always records tenant_id, IP address, and user agent for GDPR audit trail.
     *
     * @param array{functional?: bool, analytics?: bool, marketing?: bool} $preferences
     */
    public function storeConsent(?int $userId, int $tenantId, ?string $ipAddress, array $preferences): array
    {
        $expiresAt = now()->addDays(self::DEFAULT_VALIDITY_DAYS);

        $record = [
            'user_id'         => $userId,
            'tenant_id'       => $tenantId,
            'session_id'      => session_id() ?: null,
            'essential'       => true,
            'functional'      => $preferences['functional'] ?? false,
            'analytics'       => $preferences['analytics'] ?? false,
            'marketing'       => $preferences['marketing'] ?? false,
            'consent_version' => self::CONSENT_VERSION,
            'consent_string'  => json_encode($preferences),
            'ip_address'      => $ipAddress,
            'user_agent'      => request()->userAgent(),
            'expires_at'      => $expiresAt,
            'created_at'      => now(),
            'updated_at'      => now(),
        ];

        $id = DB::table('cookie_consents')->insertGetId($record);

        return [
            'id' => $id,
            'consent' => [
                'essential'  => true,
                'functional' => $record['functional'],
                'analytics'  => $record['analytics'],
                'marketing'  => $record['marketing'],
                'created_at' => (string) $record['created_at'],
            ],
        ];
    }

    /**
     * Check if a user/IP has any valid (non-expired, non-withdrawn) consent.
     *
     * Called by CookieConsentController::check().
     */
    public function hasConsent(?int $userId, int $tenantId, ?string $ipAddress = null): bool
    {
        $consent = $this->getConsent($userId, $tenantId, $ipAddress);
        return $consent !== null;
    }

    /**
     * Save cookie consent preferences (legacy static-compatible method).
     *
     * @param array{functional?: bool, analytics?: bool, marketing?: bool} $categories
     */
    public function saveConsent(array $categories, ?int $userId = null, ?int $tenantId = null): bool
    {
        $expiresAt = now()->addDays(self::DEFAULT_VALIDITY_DAYS);

        DB::table('cookie_consents')->insert([
            'user_id'         => $userId,
            'tenant_id'       => $tenantId,
            'session_id'      => session_id() ?: null,
            'essential'       => true,
            'functional'      => $categories['functional'] ?? false,
            'analytics'       => $categories['analytics'] ?? false,
            'marketing'       => $categories['marketing'] ?? false,
            'consent_version' => self::CONSENT_VERSION,
            'consent_string'  => json_encode($categories),
            'ip_address'      => request()->ip(),
            'user_agent'      => request()->userAgent(),
            'expires_at'      => $expiresAt,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return true;
    }

    /**
     * Check if a specific consent category is granted.
     */
    public function checkCategory(string $category, ?int $userId = null, ?int $tenantId = null): bool
    {
        if (!$tenantId) {
            return $category === 'essential';
        }

        $consent = $this->getConsent($userId, $tenantId);

        if (! $consent) {
            return $category === 'essential';
        }

        return (bool) ($consent[$category] ?? false);
    }

    /**
     * Check if a consent record is currently valid.
     *
     * A consent is valid if:
     * - It has not been withdrawn (no withdrawal_date)
     * - It has not expired (expires_at is null or in the future)
     * - The consent_version matches the current CONSENT_VERSION
     */
    public static function isConsentValid(array $consent): bool
    {
        // Check if consent was withdrawn
        if (!empty($consent['withdrawal_date'])) {
            return false;
        }

        // Check expiry
        if (!empty($consent['expires_at']) && strtotime($consent['expires_at']) < time()) {
            return false;
        }

        // Check version (missing version defaults to current)
        $version = $consent['consent_version'] ?? self::CONSENT_VERSION;
        if ($version !== self::CONSENT_VERSION) {
            return false;
        }

        return true;
    }

    /**
     * Record cookie consent for a user/session.
     */
    public static function recordConsent(array $categories, ?int $userId = null, ?int $tenantId = null): bool
    {
        return (new self())->saveConsent($categories, $userId, $tenantId);
    }

    /**
     * Get the current valid consent for a user within a tenant.
     */
    public static function getCurrentConsent(?int $userId = null, ?int $tenantId = null): ?array
    {
        if (!$tenantId) {
            return null;
        }
        return (new self())->getConsent($userId, $tenantId);
    }

    /**
     * Withdraw consent by setting withdrawal_date.
     */
    public static function withdrawConsent(?int $userId = null, ?int $tenantId = null): bool
    {
        try {
            $query = DB::table('cookie_consents');

            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }

            if ($userId) {
                $query->where('user_id', $userId);
            } else {
                return false;
            }

            $query->whereNull('withdrawal_date')
                ->update(['withdrawal_date' => now(), 'updated_at' => now()]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('[CookieConsent] withdrawConsent failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get tenant-level cookie consent settings.
     */
    public static function getTenantSettings(int $tenantId): array
    {
        try {
            $row = DB::table('tenant_cookie_settings')
                ->where('tenant_id', $tenantId)
                ->first();

            if ($row) {
                return (array) $row;
            }
        } catch (\Throwable $e) {
            Log::debug('[CookieConsent] getTenantSettings failed (table may not exist): ' . $e->getMessage());
        }

        return [
            'tenant_id'         => $tenantId,
            'consent_required'  => true,
            'functional'        => true,
            'analytics'         => false,
            'marketing'         => false,
        ];
    }

    /**
     * Update tenant-level cookie consent settings.
     */
    public static function updateTenantSettings(int $tenantId, array $settings): bool
    {
        try {
            DB::table('tenant_cookie_settings')->updateOrInsert(
                ['tenant_id' => $tenantId],
                array_merge($settings, ['updated_at' => now()])
            );
            return true;
        } catch (\Throwable $e) {
            Log::warning('[CookieConsent] updateTenantSettings failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get consent statistics for a tenant.
     *
     * Tenant-scoped: only counts consent records for the specified tenant.
     */
    public static function getStatistics(?int $tenantId = null): array
    {
        try {
            $query = DB::table('cookie_consents');

            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }

            $total = $query->count();
            $functional = (clone $query)->where('functional', true)->count();
            $analytics = (clone $query)->where('analytics', true)->count();
            $marketing = (clone $query)->where('marketing', true)->count();

            return [
                'total'      => $total,
                'functional' => $functional,
                'analytics'  => $analytics,
                'marketing'  => $marketing,
            ];
        } catch (\Throwable $e) {
            Log::warning('[CookieConsent] getStatistics failed: ' . $e->getMessage());
            return ['total' => 0, 'functional' => 0, 'analytics' => 0, 'marketing' => 0];
        }
    }

    /**
     * Get a summary of consent for a user within a tenant.
     */
    public static function getConsentSummary(?int $userId = null, ?int $tenantId = null): array
    {
        if (!$tenantId) {
            return ['has_consent' => false, 'categories' => []];
        }

        $consent = (new self())->getConsent($userId, $tenantId);

        if (!$consent) {
            return ['has_consent' => false, 'categories' => []];
        }

        return [
            'has_consent' => true,
            'categories'  => [
                'essential'  => true,
                'functional' => (bool) ($consent['functional'] ?? false),
                'analytics'  => (bool) ($consent['analytics'] ?? false),
                'marketing'  => (bool) ($consent['marketing'] ?? false),
            ],
        ];
    }

    /**
     * Remove expired consent records.
     */
    public static function cleanExpiredConsents(): int
    {
        try {
            return DB::table('cookie_consents')
                ->where('expires_at', '<', now())
                ->delete();
        } catch (\Throwable $e) {
            Log::warning('[CookieConsent] cleanExpiredConsents failed: ' . $e->getMessage());
            return 0;
        }
    }
}
