<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * CookieConsentService — Laravel DI-based service for cookie consent management.
 *
 * Manages GDPR cookie consent recording and validation.
 */
class CookieConsentService
{
    private const CONSENT_VERSION = '1.0';
    private const DEFAULT_VALIDITY_DAYS = 365;

    /**
     * Get the current consent status for a user or session.
     */
    public function getConsent(?int $userId = null, ?string $sessionId = null): ?array
    {
        $query = DB::table('cookie_consents')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at');

        if ($userId) {
            $query->where('user_id', $userId);
        } elseif ($sessionId) {
            $query->where('session_id', $sessionId);
        } else {
            return null;
        }

        $record = $query->first();

        return $record ? (array) $record : null;
    }

    /**
     * Save cookie consent preferences.
     *
     * @param array{functional?: bool, analytics?: bool, marketing?: bool} $categories
     */
    public function saveConsent(array $categories, ?int $userId = null, ?string $sessionId = null): bool
    {
        $expiresAt = now()->addDays(self::DEFAULT_VALIDITY_DAYS);

        DB::table('cookie_consents')->insert([
            'user_id'         => $userId,
            'session_id'      => $sessionId ?? session_id(),
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
    public function checkCategory(string $category, ?int $userId = null, ?string $sessionId = null): bool
    {
        $consent = $this->getConsent($userId, $sessionId);

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
    public static function recordConsent(array $categories, ?int $userId = null, ?string $sessionId = null): bool
    {
        return (new self())->saveConsent($categories, $userId, $sessionId);
    }

    /**
     * Get the current valid consent for a user/session.
     */
    public static function getCurrentConsent(?int $userId = null, ?string $sessionId = null): ?array
    {
        return (new self())->getConsent($userId, $sessionId);
    }

    /**
     * Check if a user/session has any valid consent on record.
     */
    public static function hasConsent(?int $userId = null, ?string $sessionId = null): bool
    {
        $consent = (new self())->getConsent($userId, $sessionId);
        return $consent !== null;
    }

    /**
     * Update existing consent with new categories.
     */
    public static function updateConsent(array $categories, ?int $userId = null, ?string $sessionId = null): bool
    {
        return (new self())->saveConsent($categories, $userId, $sessionId);
    }

    /**
     * Withdraw consent by setting withdrawal_date.
     */
    public static function withdrawConsent(?int $userId = null, ?string $sessionId = null): bool
    {
        try {
            $query = DB::table('cookie_consents');

            if ($userId) {
                $query->where('user_id', $userId);
            } elseif ($sessionId) {
                $query->where('session_id', $sessionId);
            } else {
                return false;
            }

            $query->whereNull('withdrawal_date')
                ->update(['withdrawal_date' => now(), 'updated_at' => now()]);

            return true;
        } catch (\Throwable $e) {
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
            // Table may not exist
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
            return false;
        }
    }

    /**
     * Get consent statistics for a tenant.
     */
    public static function getStatistics(?int $tenantId = null): array
    {
        try {
            $query = DB::table('cookie_consents');

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
            return ['total' => 0, 'functional' => 0, 'analytics' => 0, 'marketing' => 0];
        }
    }

    /**
     * Get a summary of consent for a user/session.
     */
    public static function getConsentSummary(?int $userId = null, ?string $sessionId = null): array
    {
        $consent = (new self())->getConsent($userId, $sessionId);

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
            return 0;
        }
    }
}
