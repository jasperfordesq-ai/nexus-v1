<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

/**
 * TotpService — Thin delegate forwarding to \App\Services\TotpService.
 *
 * The full implementation now lives in the App namespace.
 * This file exists for backwards compatibility only.
 *
 * @see \App\Services\TotpService
 */
class TotpService
{
    private static function app(): \App\Services\TotpService
    {
        return new \App\Services\TotpService();
    }

    public static function generateSecret(): string
    {
        return self::app()->generateSecret();
    }

    public static function getProvisioningUri(string $secret, string $email, ?string $issuer = null): string
    {
        return self::app()->getProvisioningUri($secret, $email, $issuer);
    }

    public static function generateQrCode(string $provisioningUri): string
    {
        return self::app()->generateQrCode($provisioningUri);
    }

    public static function verifyCode(string $secret, string $code, int $window = 1): bool
    {
        return self::app()->verifyCode($secret, $code, $window);
    }

    public static function checkRateLimit(int $userId): array
    {
        return self::app()->checkRateLimit($userId);
    }

    public static function recordAttempt(int $userId, bool $successful, string $type = 'totp', ?string $failureReason = null): void
    {
        self::app()->recordAttempt($userId, $successful, $type, $failureReason);
    }

    public static function initializeSetup(int $userId): array
    {
        return self::app()->initializeSetup($userId);
    }

    public static function completeSetup(int $userId, string $code): array
    {
        return self::app()->completeSetup($userId, $code);
    }

    public static function verifyLogin(int $userId, string $code): array
    {
        return self::app()->verifyLogin($userId, $code);
    }

    public static function verifyBackupCode(int $userId, string $code): array
    {
        return self::app()->verifyBackupCode($userId, $code);
    }

    public static function generateBackupCodes(int $userId): array
    {
        return self::app()->generateBackupCodes($userId);
    }

    public static function getBackupCodeCount(int $userId): int
    {
        return self::app()->getBackupCodeCount($userId);
    }

    public static function isEnabled(int $userId): bool
    {
        return self::app()->isEnabled($userId);
    }

    public static function isSetupRequired(int $userId): bool
    {
        return self::app()->isSetupRequired($userId);
    }

    public static function disable(int $userId, string $password): array
    {
        return self::app()->disable($userId, $password);
    }

    public static function adminReset(int $userId, int $adminId, string $reason): array
    {
        return self::app()->adminReset($userId, $adminId, $reason);
    }

    public static function isTrustedDevice(int $userId): bool
    {
        return self::app()->isTrustedDevice($userId);
    }

    public static function trustDevice(int $userId): bool
    {
        self::app()->trustDevice($userId);
        return true;
    }

    public static function getTrustedDevices(int $userId): array
    {
        return self::app()->getTrustedDevices($userId);
    }

    public static function getTrustedDeviceCount(int $userId): int
    {
        return self::app()->getTrustedDeviceCount($userId);
    }

    public static function revokeDevice(int $userId, int $deviceId, string $reason = 'user_action'): bool
    {
        return self::app()->revokeDevice($userId, $deviceId, $reason);
    }

    public static function revokeAllDevices(int $userId, string $reason = 'user_action'): int
    {
        return self::app()->revokeAllDevices($userId, $reason);
    }
}
