<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Nexus\Services\TotpService as LegacyService;

/**
 * TotpService — Laravel DI wrapper for legacy TOTP service.
 *
 * Delegates to \Nexus\Services\TotpService.
 * All methods are static to match the legacy API that tests expect.
 */
class TotpService
{
    private const BACKUP_CODE_COUNT = 10;
    private const BACKUP_CODE_LENGTH = 8;
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_SECONDS = 900; // 15 minutes
    private const ISSUER = 'Project NEXUS';
    private const TRUSTED_DEVICE_DAYS = 30;
    private const TRUSTED_DEVICE_COOKIE = 'nexus_trusted_device';

    /**
     * Generate a new TOTP secret.
     */
    public static function generateSecret(): string
    {
        return LegacyService::generateSecret();
    }

    /**
     * Get the provisioning URI for authenticator apps.
     */
    public static function getProvisioningUri(string $secret, string $email, ?string $issuer = null): string
    {
        return LegacyService::getProvisioningUri($secret, $email, $issuer);
    }

    /**
     * Generate a QR code SVG for the provisioning URI.
     */
    public static function generateQrCode(string $provisioningUri): string
    {
        return LegacyService::generateQrCode($provisioningUri);
    }

    /**
     * Verify a TOTP code against a secret.
     */
    public static function verifyCode(string $secret, string $code, int $window = 1): bool
    {
        return LegacyService::verifyCode($secret, $code, $window);
    }

    /**
     * Check if user is rate limited for 2FA attempts.
     *
     * @return array{limited: bool, retry_after: int|null, message: string|null}
     */
    public static function checkRateLimit(int $userId): array
    {
        return LegacyService::checkRateLimit($userId);
    }

    /**
     * Check if user has 2FA enabled.
     */
    public static function isEnabled(int $userId): bool
    {
        return LegacyService::isEnabled($userId);
    }

    /**
     * Check if current device is trusted for this user.
     */
    public static function isTrustedDevice(int $userId): bool
    {
        return LegacyService::isTrustedDevice($userId);
    }

    /**
     * Trust the current device for this user.
     */
    public static function trustDevice(int $userId): bool
    {
        return LegacyService::trustDevice($userId);
    }

    /**
     * Verify 2FA during login.
     *
     * @return array{success: bool, error?: string}
     */
    public static function verifyLogin(int $userId, string $code): array
    {
        return LegacyService::verifyLogin($userId, $code);
    }

    /**
     * Verify a backup code during login.
     *
     * @return array{success: bool, error?: string, codes_remaining?: int}
     */
    public static function verifyBackupCode(int $userId, string $code): array
    {
        return LegacyService::verifyBackupCode($userId, $code);
    }

    /**
     * Check if user needs to set up 2FA (tenant requirement).
     */
    public static function isSetupRequired(int $userId): bool
    {
        return LegacyService::isSetupRequired($userId);
    }

    /**
     * Get the count of remaining unused backup codes.
     */
    public static function getBackupCodeCount(int $userId): int
    {
        return LegacyService::getBackupCodeCount($userId);
    }

    /**
     * Get count of active trusted devices for a user.
     */
    public static function getTrustedDeviceCount(int $userId): int
    {
        return LegacyService::getTrustedDeviceCount($userId);
    }

    /**
     * Initialize 2FA setup for a user (generates secret, stores as pending).
     *
     * @return array{secret: string, provisioning_uri: string, qr_code: string}
     */
    public static function initializeSetup(int $userId): array
    {
        return LegacyService::initializeSetup($userId);
    }

    /**
     * Complete 2FA setup after user verifies the code.
     *
     * @return array{success: bool, error?: string, backup_codes?: array}
     */
    public static function completeSetup(int $userId, string $code): array
    {
        return LegacyService::completeSetup($userId, $code);
    }

    /**
     * Disable 2FA for a user (requires password confirmation).
     *
     * @return array{success: bool, error?: string}
     */
    public static function disable(int $userId, string $password = ''): array
    {
        return LegacyService::disable($userId, $password);
    }

    /**
     * Generate backup codes for a user.
     *
     * @return array List of plain-text backup codes
     */
    public static function generateBackupCodes(int $userId): array
    {
        return LegacyService::generateBackupCodes($userId);
    }

    /**
     * Get all trusted devices for a user.
     */
    public static function getTrustedDevices(int $userId): array
    {
        return LegacyService::getTrustedDevices($userId);
    }

    /**
     * Revoke a specific trusted device.
     */
    public static function revokeDevice(int $userId, int $deviceId, string $reason = 'user_action'): bool
    {
        return LegacyService::revokeDevice($userId, $deviceId, $reason);
    }

    /**
     * Revoke all trusted devices for a user.
     *
     * @return int Number of devices revoked
     */
    public static function revokeAllDevices(int $userId, string $reason = 'user_action'): int
    {
        return LegacyService::revokeAllDevices($userId, $reason);
    }

    /**
     * Admin: Reset 2FA for a user (with audit logging).
     *
     * @return array{success: bool, error?: string}
     */
    public static function adminReset(int $userId, int $adminId, string $reason): array
    {
        return LegacyService::adminReset($userId, $adminId, $reason);
    }

    /**
     * Record a 2FA verification attempt.
     */
    public static function recordAttempt(int $userId, bool $successful, string $type = 'totp', ?string $failureReason = null): void
    {
        LegacyService::recordAttempt($userId, $successful, $type, $failureReason);
    }

    /**
     * Generate a random backup code (format: XXXX-XXXX).
     */
    private static function generateRandomCode(): string
    {
        $chars = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
        $code = '';

        for ($i = 0; $i < self::BACKUP_CODE_LENGTH; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return substr($code, 0, 4) . '-' . substr($code, 4);
    }

    /**
     * Parse user agent string to get a human-readable device name.
     */
    private static function parseDeviceName(?string $userAgent): string
    {
        if (!$userAgent) {
            return 'Unknown device';
        }

        $browser = 'Unknown browser';
        $os = 'Unknown OS';

        if (str_contains($userAgent, 'Firefox')) {
            $browser = 'Firefox';
        } elseif (str_contains($userAgent, 'Edg')) {
            $browser = 'Edge';
        } elseif (str_contains($userAgent, 'Chrome')) {
            $browser = 'Chrome';
        } elseif (str_contains($userAgent, 'Safari')) {
            $browser = 'Safari';
        } elseif (str_contains($userAgent, 'MSIE') || str_contains($userAgent, 'Trident')) {
            $browser = 'Internet Explorer';
        }

        if (str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad')) {
            $os = 'iOS';
        } elseif (str_contains($userAgent, 'Android')) {
            $os = 'Android';
        } elseif (str_contains($userAgent, 'Windows')) {
            $os = 'Windows';
        } elseif (str_contains($userAgent, 'Mac')) {
            $os = 'macOS';
        } elseif (str_contains($userAgent, 'Linux')) {
            $os = 'Linux';
        }

        return "$browser on $os";
    }
}
