<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Nexus\Services\TotpService as LegacyTotpService;

/**
 * TotpService — Laravel DI wrapper for legacy \Nexus\Services\TotpService.
 *
 * Delegates to the legacy static service which handles TOTP secrets,
 * backup codes, trusted devices, and rate limiting.
 */
class TotpService
{
    /**
     * Generate a new TOTP secret.
     */
    public function generateSecret(): string
    {
        return LegacyTotpService::generateSecret();
    }

    /**
     * Get the provisioning URI for authenticator apps.
     */
    public function getProvisioningUri(string $secret, string $email, ?string $issuer = null): string
    {
        return LegacyTotpService::getProvisioningUri($secret, $email, $issuer);
    }

    /**
     * Generate a QR code SVG for the provisioning URI.
     */
    public function generateQrCode(string $provisioningUri): string
    {
        return LegacyTotpService::generateQrCode($provisioningUri);
    }

    /**
     * Verify a TOTP code against a secret.
     */
    public function verifyCode(string $secret, string $code, int $window = 1): bool
    {
        return LegacyTotpService::verifyCode($secret, $code, $window);
    }

    /**
     * Check if user is rate limited for 2FA attempts.
     *
     * @return array{limited: bool, retry_after: int|null, message: string|null}
     */
    public function checkRateLimit(int $userId): array
    {
        return LegacyTotpService::checkRateLimit($userId);
    }

    /**
     * Check if user has 2FA enabled.
     */
    public function isEnabled(int $userId): bool
    {
        return LegacyTotpService::isEnabled($userId);
    }

    /**
     * Check if current device is trusted for this user.
     *
     * Legacy signature uses cookie-based detection (no deviceHash param).
     */
    public function isTrustedDevice(int $userId, ?string $deviceHash = null): bool
    {
        // Legacy service reads the cookie internally — deviceHash is ignored
        return LegacyTotpService::isTrustedDevice($userId);
    }

    /**
     * Trust the current device for this user.
     *
     * Legacy service sets a cookie and stores the hash internally.
     */
    public function trustDevice(int $userId, ?string $deviceHash = null): void
    {
        LegacyTotpService::trustDevice($userId);
    }

    /**
     * Verify 2FA during login.
     *
     * @return array{success: bool, error?: string}
     */
    public function verifyLogin(int $userId, string $code): array
    {
        return LegacyTotpService::verifyLogin($userId, $code);
    }

    /**
     * Verify a backup code during login.
     *
     * @return array{success: bool, error?: string, codes_remaining?: int}
     */
    public function verifyBackupCode(int $userId, string $code): array
    {
        return LegacyTotpService::verifyBackupCode($userId, $code);
    }

    /**
     * Check if user needs to set up 2FA (tenant requirement).
     */
    public function isSetupRequired(int $userId): bool
    {
        // Legacy uses userId to look up the user's totp_setup_required flag
        return LegacyTotpService::isSetupRequired($userId);
    }

    /**
     * Get the count of remaining unused backup codes.
     */
    public function getBackupCodeCount(int $userId): int
    {
        return LegacyTotpService::getBackupCodeCount($userId);
    }

    /**
     * Get count of active trusted devices for a user.
     */
    public function getTrustedDeviceCount(int $userId): int
    {
        return LegacyTotpService::getTrustedDeviceCount($userId);
    }

    /**
     * Initialize 2FA setup for a user (generates secret, stores as pending).
     *
     * @return array{secret: string, provisioning_uri: string, qr_code: string}
     */
    public function initializeSetup(int $userId): array
    {
        return LegacyTotpService::initializeSetup($userId);
    }

    /**
     * Complete 2FA setup after user verifies the code.
     *
     * @return array{success: bool, error?: string, backup_codes?: array}
     */
    public function completeSetup(int $userId, string $code): array
    {
        return LegacyTotpService::completeSetup($userId, $code);
    }

    /**
     * Disable 2FA for a user (requires password confirmation).
     *
     * @return array{success: bool, error?: string}
     */
    public function disable(int $userId, string $password = ''): array
    {
        return LegacyTotpService::disable($userId, $password);
    }

    /**
     * Generate backup codes for a user.
     *
     * @return array  List of plain-text backup codes
     */
    public function generateBackupCodes(int $userId): array
    {
        return LegacyTotpService::generateBackupCodes($userId);
    }

    /**
     * Get all trusted devices for a user.
     */
    public function getTrustedDevices(int $userId): array
    {
        return LegacyTotpService::getTrustedDevices($userId);
    }

    /**
     * Revoke a specific trusted device.
     */
    public function revokeDevice(int $userId, int $deviceId, string $reason = 'user_action'): bool
    {
        return LegacyTotpService::revokeDevice($userId, $deviceId, $reason);
    }

    /**
     * Revoke all trusted devices for a user.
     *
     * @return int  Number of devices revoked
     */
    public function revokeAllDevices(int $userId, string $reason = 'user_action'): int
    {
        return LegacyTotpService::revokeAllDevices($userId, $reason);
    }

    /**
     * Admin: Reset 2FA for a user (with audit logging).
     *
     * @return array{success: bool, error?: string}
     */
    public function adminReset(int $userId, int $adminId, string $reason): array
    {
        return LegacyTotpService::adminReset($userId, $adminId, $reason);
    }

    /**
     * Record a 2FA verification attempt.
     */
    public function recordAttempt(int $userId, bool $successful, string $type = 'totp', ?string $failureReason = null): void
    {
        LegacyTotpService::recordAttempt($userId, $successful, $type, $failureReason);
    }
}
