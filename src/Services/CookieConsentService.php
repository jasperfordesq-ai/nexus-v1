<?php

declare(strict_types=1);

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\Auth;

/**
 * Cookie Consent Service
 *
 * Manages cookie consent recording, validation, and enforcement
 * for EU ePrivacy Directive and GDPR compliance.
 *
 * Features:
 * - Record user consent choices
 * - Validate consent (not expired, current version)
 * - Check category-specific permissions
 * - Tenant-aware operations
 * - Audit trail logging
 */
class CookieConsentService
{
    private const CONSENT_VERSION = '1.0';
    private const DEFAULT_VALIDITY_DAYS = 365;

    /**
     * Record new cookie consent
     *
     * @param array $data Consent data (functional, analytics, marketing)
     * @return array Created consent record
     */
    public static function recordConsent(array $data): array
    {
        $tenantId = TenantContext::getId();
        $userId = Auth::id();
        $sessionId = session_id();

        // Get tenant-specific settings
        $tenantSettings = self::getTenantSettings($tenantId);

        // Build consent categories based on tenant capabilities
        $categories = [
            'essential' => true, // Always true
            'functional' => $data['functional'] ?? false,
            'analytics' => ($tenantSettings['analytics_enabled'] && ($data['analytics'] ?? false)),
            'marketing' => ($tenantSettings['marketing_enabled'] && ($data['marketing'] ?? false))
        ];

        $validityDays = $tenantSettings['consent_validity_days'] ?? self::DEFAULT_VALIDITY_DAYS;

        // Calculate expiry date
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$validityDays} days"));

        // Insert consent record
        $stmt = Database::query(
            "INSERT INTO cookie_consents
             (session_id, user_id, tenant_id, essential, functional, analytics, marketing,
              ip_address, user_agent, consent_string, expires_at, consent_version, source,
              last_updated_by_user, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())",
            [
                $sessionId,
                $userId,
                $tenantId,
                $categories['essential'],
                $categories['functional'],
                $categories['analytics'],
                $categories['marketing'],
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                json_encode($categories),
                $expiresAt,
                self::CONSENT_VERSION,
                $data['source'] ?? 'web'
            ]
        );

        $consentId = (int) Database::lastInsertId();

        // Log to audit trail
        self::auditConsentChange($consentId, 'created', [], $categories);

        // Update daily statistics
        self::incrementDailyStats($tenantId, $categories);

        // Return created consent
        return [
            'id' => $consentId,
            'essential' => $categories['essential'],
            'functional' => $categories['functional'],
            'analytics' => $categories['analytics'],
            'marketing' => $categories['marketing'],
            'expires_at' => $expiresAt,
            'version' => self::CONSENT_VERSION
        ];
    }

    /**
     * Get consent record by user or session
     *
     * @param int|null $userId User ID (if logged in)
     * @param string|null $sessionId Session ID (for anonymous)
     * @return array|null Consent record or null if not found
     */
    public static function getConsent(?int $userId = null, ?string $sessionId = null): ?array
    {
        $tenantId = TenantContext::getId();

        // Prefer user_id if available
        if ($userId) {
            $stmt = Database::query(
                "SELECT * FROM cookie_consents
                 WHERE user_id = ? AND tenant_id = ?
                 AND withdrawal_date IS NULL
                 ORDER BY created_at DESC
                 LIMIT 1",
                [$userId, $tenantId]
            );
        } elseif ($sessionId) {
            $stmt = Database::query(
                "SELECT * FROM cookie_consents
                 WHERE session_id = ? AND tenant_id = ?
                 AND withdrawal_date IS NULL
                 ORDER BY created_at DESC
                 LIMIT 1",
                [$sessionId, $tenantId]
            );
        } else {
            return null;
        }

        $consent = $stmt->fetch();

        if (!$consent) {
            return null;
        }

        // Check if expired
        if (!self::isConsentValid($consent)) {
            // Mark as expired
            self::markConsentExpired((int) $consent['id']);
            return null;
        }

        return $consent;
    }

    /**
     * Get current user's consent (convenience method)
     *
     * @return array|null Current consent or null
     */
    public static function getCurrentConsent(): ?array
    {
        $userId = Auth::id();
        $sessionId = session_id();

        return self::getConsent($userId, $sessionId);
    }

    /**
     * Check if user has consented to a specific category
     *
     * @param string $category Category to check (functional, analytics, marketing)
     * @return bool True if consented
     */
    public static function hasConsent(string $category): bool
    {
        // Essential is always allowed
        if ($category === 'essential') {
            return true;
        }

        $consent = self::getCurrentConsent();

        if (!$consent) {
            return false; // No consent = no permission
        }

        return (bool) ($consent[$category] ?? false);
    }

    /**
     * Update existing consent
     *
     * @param int $consentId Consent ID to update
     * @param array $categories New category values
     * @return bool Success status
     */
    public static function updateConsent(int $consentId, array $categories): bool
    {
        $tenantId = TenantContext::getId();

        // Get old values for audit
        $stmt = Database::query(
            "SELECT * FROM cookie_consents WHERE id = ? AND tenant_id = ?",
            [$consentId, $tenantId]
        );
        $oldConsent = $stmt->fetch();

        if (!$oldConsent) {
            return false;
        }

        $oldValues = [
            'functional' => (bool) $oldConsent['functional'],
            'analytics' => (bool) $oldConsent['analytics'],
            'marketing' => (bool) $oldConsent['marketing']
        ];

        // Update consent
        Database::query(
            "UPDATE cookie_consents
             SET functional = ?,
                 analytics = ?,
                 marketing = ?,
                 consent_string = ?,
                 last_updated_by_user = NOW(),
                 updated_at = NOW()
             WHERE id = ? AND tenant_id = ?",
            [
                $categories['functional'] ?? false,
                $categories['analytics'] ?? false,
                $categories['marketing'] ?? false,
                json_encode($categories),
                $consentId,
                $tenantId
            ]
        );

        $newValues = [
            'functional' => (bool) ($categories['functional'] ?? false),
            'analytics' => (bool) ($categories['analytics'] ?? false),
            'marketing' => (bool) ($categories['marketing'] ?? false)
        ];

        // Audit the change
        self::auditConsentChange($consentId, 'updated', $oldValues, $newValues);

        return true;
    }

    /**
     * Withdraw consent (user opts out)
     *
     * @param int $consentId Consent ID to withdraw
     * @return bool Success status
     */
    public static function withdrawConsent(int $consentId): bool
    {
        $tenantId = TenantContext::getId();

        // Get current values for audit
        $stmt = Database::query(
            "SELECT * FROM cookie_consents WHERE id = ? AND tenant_id = ?",
            [$consentId, $tenantId]
        );
        $consent = $stmt->fetch();

        if (!$consent) {
            return false;
        }

        // Mark as withdrawn
        Database::query(
            "UPDATE cookie_consents
             SET withdrawal_date = NOW(),
                 functional = 0,
                 analytics = 0,
                 marketing = 0,
                 updated_at = NOW()
             WHERE id = ? AND tenant_id = ?",
            [$consentId, $tenantId]
        );

        // Audit the withdrawal
        self::auditConsentChange($consentId, 'withdrawn', [
            'functional' => (bool) $consent['functional'],
            'analytics' => (bool) $consent['analytics'],
            'marketing' => (bool) $consent['marketing']
        ], [
            'functional' => false,
            'analytics' => false,
            'marketing' => false
        ]);

        return true;
    }

    /**
     * Check if consent is still valid (not expired, not withdrawn)
     *
     * @param array $consent Consent record
     * @return bool True if valid
     */
    public static function isConsentValid(array $consent): bool
    {
        // Check if withdrawn
        if (!empty($consent['withdrawal_date'])) {
            return false;
        }

        // Check if expired
        if (!empty($consent['expires_at'])) {
            $expiryTime = strtotime($consent['expires_at']);
            if ($expiryTime < time()) {
                return false;
            }
        }

        // Check version (if we've updated consent terms)
        if (($consent['consent_version'] ?? '1.0') !== self::CONSENT_VERSION) {
            return false;
        }

        return true;
    }

    /**
     * Mark consent as expired
     *
     * @param int $consentId Consent ID
     * @return void
     */
    private static function markConsentExpired(int $consentId): void
    {
        Database::query(
            "UPDATE cookie_consents
             SET withdrawal_date = NOW()
             WHERE id = ?",
            [$consentId]
        );

        self::auditConsentChange($consentId, 'expired', [], []);
    }

    /**
     * Get tenant-specific cookie settings
     *
     * @param int $tenantId Tenant ID
     * @return array Tenant settings
     */
    public static function getTenantSettings(int $tenantId): array
    {
        $stmt = Database::query(
            "SELECT * FROM tenant_cookie_settings WHERE tenant_id = ?",
            [$tenantId]
        );

        $settings = $stmt->fetch();

        // Return defaults if not found
        if (!$settings) {
            return [
                'analytics_enabled' => false,
                'marketing_enabled' => false,
                'consent_validity_days' => self::DEFAULT_VALIDITY_DAYS,
                'auto_block_scripts' => true,
                'strict_mode' => true,
                'show_reject_all' => true
            ];
        }

        return [
            'banner_message' => $settings['banner_message'],
            'analytics_enabled' => (bool) $settings['analytics_enabled'],
            'marketing_enabled' => (bool) $settings['marketing_enabled'],
            'analytics_provider' => $settings['analytics_provider'],
            'analytics_id' => $settings['analytics_id'],
            'consent_validity_days' => (int) $settings['consent_validity_days'],
            'auto_block_scripts' => (bool) $settings['auto_block_scripts'],
            'strict_mode' => (bool) $settings['strict_mode'],
            'show_reject_all' => (bool) $settings['show_reject_all']
        ];
    }

    /**
     * Update tenant cookie settings (admin only)
     *
     * @param int $tenantId Tenant ID
     * @param array $settings Settings to update
     * @return bool Success status
     */
    public static function updateTenantSettings(int $tenantId, array $settings): bool
    {
        $allowedFields = [
            'banner_message',
            'analytics_enabled',
            'marketing_enabled',
            'analytics_provider',
            'analytics_id',
            'consent_validity_days',
            'auto_block_scripts',
            'strict_mode',
            'show_reject_all'
        ];

        $updates = [];
        $params = [];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $settings)) {
                $updates[] = "{$field} = ?";
                $params[] = $settings[$field];
            }
        }

        if (empty($updates)) {
            return false;
        }

        $params[] = $tenantId;

        Database::query(
            "UPDATE tenant_cookie_settings
             SET " . implode(', ', $updates) . "
             WHERE tenant_id = ?",
            $params
        );

        return true;
    }

    /**
     * Log consent change to audit trail
     *
     * @param int $consentId Consent ID
     * @param string $action Action performed
     * @param array $oldValues Old values
     * @param array $newValues New values
     * @return void
     */
    private static function auditConsentChange(int $consentId, string $action, array $oldValues, array $newValues): void
    {
        try {
            Database::query(
                "INSERT INTO cookie_consent_audit
                 (consent_id, action, old_values, new_values, ip_address, user_agent, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())",
                [
                    $consentId,
                    $action,
                    !empty($oldValues) ? json_encode($oldValues) : null,
                    !empty($newValues) ? json_encode($newValues) : null,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $_SERVER['HTTP_USER_AGENT'] ?? null
                ]
            );
        } catch (\Exception $e) {
            // Don't fail the operation if audit logging fails
            error_log("Cookie consent audit logging failed: " . $e->getMessage());
        }
    }

    /**
     * Increment daily statistics
     *
     * @param int $tenantId Tenant ID
     * @param array $categories Consent categories
     * @return void
     */
    private static function incrementDailyStats(int $tenantId, array $categories): void
    {
        try {
            $isAcceptAll = $categories['functional'] && $categories['analytics'] && $categories['marketing'];
            $isRejectAll = !$categories['functional'] && !$categories['analytics'] && !$categories['marketing'];
            $isCustom = !$isAcceptAll && !$isRejectAll;

            Database::query(
                "INSERT INTO cookie_consent_stats
                 (tenant_id, stat_date, total_consents, accept_all_count, reject_all_count,
                  custom_count, functional_accepted, analytics_accepted, marketing_accepted)
                 VALUES (?, CURDATE(), 1, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    total_consents = total_consents + 1,
                    accept_all_count = accept_all_count + VALUES(accept_all_count),
                    reject_all_count = reject_all_count + VALUES(reject_all_count),
                    custom_count = custom_count + VALUES(custom_count),
                    functional_accepted = functional_accepted + VALUES(functional_accepted),
                    analytics_accepted = analytics_accepted + VALUES(analytics_accepted),
                    marketing_accepted = marketing_accepted + VALUES(marketing_accepted),
                    updated_at = NOW()",
                [
                    $tenantId,
                    $isAcceptAll ? 1 : 0,
                    $isRejectAll ? 1 : 0,
                    $isCustom ? 1 : 0,
                    $categories['functional'] ? 1 : 0,
                    $categories['analytics'] ? 1 : 0,
                    $categories['marketing'] ? 1 : 0
                ]
            );
        } catch (\Exception $e) {
            // Don't fail the operation if stats update fails
            error_log("Cookie consent stats update failed: " . $e->getMessage());
        }
    }

    /**
     * Get consent statistics for a tenant
     *
     * @param int $tenantId Tenant ID
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array Statistics
     */
    public static function getStatistics(int $tenantId, string $startDate, string $endDate): array
    {
        $stmt = Database::query(
            "SELECT * FROM cookie_consent_stats
             WHERE tenant_id = ?
             AND stat_date BETWEEN ? AND ?
             ORDER BY stat_date DESC",
            [$tenantId, $startDate, $endDate]
        );

        return $stmt->fetchAll();
    }

    /**
     * Get consent summary (for dashboard)
     *
     * @param int $tenantId Tenant ID
     * @return array Summary data
     */
    public static function getConsentSummary(int $tenantId): array
    {
        // Total consents
        $stmt = Database::query(
            "SELECT COUNT(*) AS total FROM cookie_consents WHERE tenant_id = ?",
            [$tenantId]
        );
        $total = (int) $stmt->fetch()['total'];

        // Active (non-expired, non-withdrawn) consents
        $stmt = Database::query(
            "SELECT COUNT(*) AS active FROM cookie_consents
             WHERE tenant_id = ?
             AND withdrawal_date IS NULL
             AND (expires_at IS NULL OR expires_at > NOW())",
            [$tenantId]
        );
        $active = (int) $stmt->fetch()['active'];

        // Acceptance rates
        $stmt = Database::query(
            "SELECT
                SUM(CASE WHEN functional = 1 THEN 1 ELSE 0 END) AS functional,
                SUM(CASE WHEN analytics = 1 THEN 1 ELSE 0 END) AS analytics,
                SUM(CASE WHEN marketing = 1 THEN 1 ELSE 0 END) AS marketing
             FROM cookie_consents
             WHERE tenant_id = ?
             AND withdrawal_date IS NULL",
            [$tenantId]
        );
        $rates = $stmt->fetch();

        return [
            'total_consents' => $total,
            'active_consents' => $active,
            'expired_consents' => $total - $active,
            'acceptance_rates' => [
                'functional' => $active > 0 ? round(((int) $rates['functional'] / $active) * 100, 1) : 0,
                'analytics' => $active > 0 ? round(((int) $rates['analytics'] / $active) * 100, 1) : 0,
                'marketing' => $active > 0 ? round(((int) $rates['marketing'] / $active) * 100, 1) : 0
            ]
        ];
    }

    /**
     * Clean expired consents (for cron job)
     *
     * @return int Number of consents cleaned
     */
    public static function cleanExpiredConsents(): int
    {
        $stmt = Database::query(
            "UPDATE cookie_consents
             SET withdrawal_date = NOW()
             WHERE expires_at < NOW()
             AND withdrawal_date IS NULL"
        );

        return $stmt->rowCount();
    }
}
