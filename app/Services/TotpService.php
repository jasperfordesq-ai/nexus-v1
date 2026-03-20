<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OTPHP\TOTP;
use App\Core\TotpEncryption;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * TotpService — Laravel DI-based TOTP two-factor authentication service.
 *
 * Handles TOTP secrets, backup codes, trusted devices, and rate limiting.
 * Self-contained — no legacy delegation.
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
        $totp = TOTP::generate();
        return $totp->getSecret();
    }

    /**
     * Get the provisioning URI for authenticator apps.
     */
    public static function getProvisioningUri(string $secret, string $email, ?string $issuer = null): string
    {
        $totp = TOTP::createFromSecret($secret);
        $totp->setLabel($email);
        $totp->setIssuer($issuer ?? self::ISSUER);
        return $totp->getProvisioningUri();
    }

    /**
     * Generate a QR code SVG for the provisioning URI.
     */
    public static function generateQrCode(string $provisioningUri): string
    {
        $builder = new Builder(
            writer: new SvgWriter(),
            data: $provisioningUri,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 200,
            margin: 10
        );

        $result = $builder->build();
        return $result->getString();
    }

    /**
     * Verify a TOTP code against a secret.
     */
    public static function verifyCode(string $secret, string $code, int $window = 1): bool
    {
        $totp = TOTP::createFromSecret($secret);
        return $totp->verify($code, null, $window);
    }

    /**
     * Check if user is rate limited for 2FA attempts.
     *
     * @return array{limited: bool, retry_after: int|null, message: string|null}
     */
    public static function checkRateLimit(int $userId): array
    {
        $tenantId = TenantContext::getId();
        $cutoff = date('Y-m-d H:i:s', time() - self::LOCKOUT_SECONDS);

        $result = DB::selectOne(
            "SELECT COUNT(*) as attempts FROM totp_verification_attempts
             WHERE user_id = ? AND tenant_id = ? AND is_successful = 0 AND attempted_at > ?",
            [$userId, $tenantId, $cutoff]
        );
        $attempts = (int) ($result->attempts ?? 0);

        if ($attempts >= self::MAX_ATTEMPTS) {
            $oldest = DB::selectOne(
                "SELECT MIN(attempted_at) as oldest FROM totp_verification_attempts
                 WHERE user_id = ? AND tenant_id = ? AND is_successful = 0 AND attempted_at > ?",
                [$userId, $tenantId, $cutoff]
            );
            $oldestTime = strtotime($oldest->oldest ?? 'now');
            $retryAfter = $oldestTime + self::LOCKOUT_SECONDS - time();

            return [
                'limited' => true,
                'retry_after' => max(0, $retryAfter),
                'message' => "Too many failed attempts. Please try again in " . ceil($retryAfter / 60) . " minutes.",
            ];
        }

        return ['limited' => false, 'retry_after' => null, 'message' => null];
    }

    /**
     * Check if user has 2FA enabled.
     */
    public static function isEnabled(int $userId): bool
    {
        $tenantId = TenantContext::getId();

        $result = DB::selectOne(
            "SELECT is_enabled FROM user_totp_settings
             WHERE user_id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        );

        return (bool) ($result->is_enabled ?? false);
    }

    /**
     * Check if current device is trusted for this user.
     */
    public static function isTrustedDevice(int $userId, ?string $deviceHash = null): bool
    {
        $token = $_COOKIE[self::TRUSTED_DEVICE_COOKIE] ?? null;
        if (!$token) {
            return false;
        }

        $tenantId = TenantContext::getId();
        $tokenHash = hash('sha256', $token);

        $device = DB::selectOne(
            "SELECT id FROM user_trusted_devices
             WHERE user_id = ? AND tenant_id = ? AND device_token_hash = ?
             AND is_revoked = 0 AND expires_at > NOW()",
            [$userId, $tenantId, $tokenHash]
        );

        if ($device) {
            DB::update(
                "UPDATE user_trusted_devices SET last_used_at = NOW() WHERE id = ?",
                [$device->id]
            );
            return true;
        }

        return false;
    }

    /**
     * Trust the current device for this user.
     */
    public static function trustDevice(int $userId, ?string $deviceHash = null): void
    {
        $tenantId = TenantContext::getId();
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $ip = request()->ip();
        $userAgent = request()->userAgent();
        $deviceName = self::parseDeviceName($userAgent);
        $expiresAt = date('Y-m-d H:i:s', time() + (self::TRUSTED_DEVICE_DAYS * 24 * 60 * 60));

        try {
            DB::insert(
                "INSERT INTO user_trusted_devices
                 (user_id, tenant_id, device_token_hash, device_name, ip_address, user_agent, expires_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$userId, $tenantId, $tokenHash, $deviceName, $ip, $userAgent, $expiresAt]
            );

            $cookieExpires = time() + (self::TRUSTED_DEVICE_DAYS * 24 * 60 * 60);
            $secure = request()->isSecure();

            setcookie(
                self::TRUSTED_DEVICE_COOKIE,
                $token,
                [
                    'expires' => $cookieExpires,
                    'path' => '/',
                    'secure' => $secure,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]
            );
        } catch (\Exception $e) {
            Log::error("Failed to trust device for user $userId: " . $e->getMessage());
        }
    }

    /**
     * Verify 2FA during login.
     *
     * @return array{success: bool, error?: string}
     */
    public static function verifyLogin(int $userId, string $code): array
    {
        $tenantId = TenantContext::getId();

        $rateLimit = self::checkRateLimit($userId);
        if ($rateLimit['limited']) {
            return ['success' => false, 'error' => $rateLimit['message']];
        }

        $settings = DB::selectOne(
            "SELECT totp_secret_encrypted FROM user_totp_settings
             WHERE user_id = ? AND tenant_id = ? AND is_enabled = 1",
            [$userId, $tenantId]
        );

        if (!$settings) {
            return ['success' => false, 'error' => '2FA not enabled for this account.'];
        }

        try {
            $secret = TotpEncryption::decrypt($settings->totp_secret_encrypted);
        } catch (\Exception $e) {
            Log::error("TOTP decrypt error for user $userId: " . $e->getMessage());
            return ['success' => false, 'error' => 'Authentication error. Please contact support.'];
        }

        if (!self::verifyCode($secret, $code)) {
            self::recordAttempt($userId, false, 'totp', 'invalid_code');
            return ['success' => false, 'error' => 'Invalid code. Please try again.'];
        }

        DB::update(
            "UPDATE user_totp_settings SET
                last_verified_at = NOW(),
                verified_device_count = verified_device_count + 1
             WHERE user_id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        );

        self::recordAttempt($userId, true, 'totp');

        return ['success' => true];
    }

    /**
     * Verify a backup code during login.
     *
     * @return array{success: bool, error?: string, codes_remaining?: int}
     */
    public static function verifyBackupCode(int $userId, string $code): array
    {
        $tenantId = TenantContext::getId();

        $rateLimit = self::checkRateLimit($userId);
        if ($rateLimit['limited']) {
            return ['success' => false, 'error' => $rateLimit['message']];
        }

        $normalizedCode = strtoupper(str_replace(['-', ' '], '', $code));

        $codes = DB::select(
            "SELECT id, code_hash FROM user_backup_codes
             WHERE user_id = ? AND tenant_id = ? AND is_used = 0",
            [$userId, $tenantId]
        );

        $matchedCodeId = null;
        foreach ($codes as $backupCode) {
            if (password_verify($normalizedCode, $backupCode->code_hash)) {
                $matchedCodeId = $backupCode->id;
                break;
            }
        }

        if (!$matchedCodeId) {
            self::recordAttempt($userId, false, 'backup_code', 'invalid_backup_code');
            return ['success' => false, 'error' => 'Invalid backup code.'];
        }

        $ip = request()->ip();
        $userAgent = request()->userAgent();

        DB::update(
            "UPDATE user_backup_codes SET
                is_used = 1,
                used_at = NOW(),
                used_ip = ?,
                used_user_agent = ?
             WHERE id = ?",
            [$ip, $userAgent, $matchedCodeId]
        );

        $remaining = DB::selectOne(
            "SELECT COUNT(*) as remaining FROM user_backup_codes
             WHERE user_id = ? AND tenant_id = ? AND is_used = 0",
            [$userId, $tenantId]
        );

        self::recordAttempt($userId, true, 'backup_code');

        return ['success' => true, 'codes_remaining' => (int) ($remaining->remaining ?? 0)];
    }

    /**
     * Check if user needs to set up 2FA (tenant requirement).
     */
    public static function isSetupRequired(int $userId): bool
    {
        $user = DB::selectOne(
            "SELECT totp_setup_required FROM users WHERE id = ? AND tenant_id = ?",
            [$userId, TenantContext::getId()]
        );

        return (bool) ($user->totp_setup_required ?? true);
    }

    /**
     * Get the count of remaining unused backup codes.
     */
    public static function getBackupCodeCount(int $userId): int
    {
        $tenantId = TenantContext::getId();

        $result = DB::selectOne(
            "SELECT COUNT(*) as count FROM user_backup_codes
             WHERE user_id = ? AND tenant_id = ? AND is_used = 0",
            [$userId, $tenantId]
        );

        return (int) ($result->count ?? 0);
    }

    /**
     * Get count of active trusted devices for a user.
     */
    public static function getTrustedDeviceCount(int $userId): int
    {
        $tenantId = TenantContext::getId();

        $result = DB::selectOne(
            "SELECT COUNT(*) as count FROM user_trusted_devices
             WHERE user_id = ? AND tenant_id = ? AND is_revoked = 0 AND expires_at > NOW()",
            [$userId, $tenantId]
        );

        return (int) ($result->count ?? 0);
    }

    /**
     * Initialize 2FA setup for a user (generates secret, stores as pending).
     *
     * @return array{secret: string, provisioning_uri: string, qr_code: string}
     */
    public static function initializeSetup(int $userId): array
    {
        $tenantId = TenantContext::getId();

        $user = DB::selectOne("SELECT email FROM users WHERE id = ? AND tenant_id = ?", [$userId, $tenantId]);

        if (!$user) {
            throw new \RuntimeException('User not found');
        }

        $secret = self::generateSecret();
        $encryptedSecret = TotpEncryption::encrypt($secret);

        DB::insert(
            "INSERT INTO user_totp_settings
             (user_id, tenant_id, totp_secret_encrypted, is_enabled, is_pending_setup)
             VALUES (?, ?, ?, 0, 1)
             ON DUPLICATE KEY UPDATE
                totp_secret_encrypted = VALUES(totp_secret_encrypted),
                is_enabled = 0,
                is_pending_setup = 1,
                updated_at = NOW()",
            [$userId, $tenantId, $encryptedSecret]
        );

        $provisioningUri = self::getProvisioningUri($secret, $user->email);
        $qrCode = self::generateQrCode($provisioningUri);

        return [
            'secret' => $secret,
            'provisioning_uri' => $provisioningUri,
            'qr_code' => $qrCode,
        ];
    }

    /**
     * Complete 2FA setup after user verifies the code.
     *
     * @return array{success: bool, error?: string, backup_codes?: array}
     */
    public static function completeSetup(int $userId, string $code): array
    {
        $tenantId = TenantContext::getId();

        $rateLimit = self::checkRateLimit($userId);
        if ($rateLimit['limited']) {
            return ['success' => false, 'error' => $rateLimit['message']];
        }

        $settings = DB::selectOne(
            "SELECT totp_secret_encrypted FROM user_totp_settings
             WHERE user_id = ? AND tenant_id = ? AND is_pending_setup = 1",
            [$userId, $tenantId]
        );

        if (!$settings) {
            return ['success' => false, 'error' => '2FA setup not initialized. Please start over.'];
        }

        try {
            $secret = TotpEncryption::decrypt($settings->totp_secret_encrypted);
        } catch (\Exception $e) {
            Log::error("TOTP decrypt error for user $userId: " . $e->getMessage());
            return ['success' => false, 'error' => 'Encryption error. Please start setup again.'];
        }

        if (!self::verifyCode($secret, $code)) {
            self::recordAttempt($userId, false, 'totp', 'invalid_code_during_setup');
            return ['success' => false, 'error' => 'Invalid code. Please check the code and try again.'];
        }

        DB::beginTransaction();
        try {
            DB::update(
                "UPDATE user_totp_settings SET
                    is_enabled = 1,
                    is_pending_setup = 0,
                    enabled_at = NOW(),
                    last_verified_at = NOW(),
                    verified_device_count = verified_device_count + 1
                 WHERE user_id = ? AND tenant_id = ?",
                [$userId, $tenantId]
            );

            DB::update(
                "UPDATE users SET totp_enabled = 1, totp_setup_required = 0 WHERE id = ? AND tenant_id = ?",
                [$userId, $tenantId]
            );

            $backupCodes = self::generateBackupCodes($userId);

            self::recordAttempt($userId, true, 'totp');

            DB::commit();

            return ['success' => true, 'backup_codes' => $backupCodes];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("TOTP setup error for user $userId: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to enable 2FA. Please try again.'];
        }
    }

    /**
     * Disable 2FA for a user (requires password confirmation).
     *
     * @return array{success: bool, error?: string}
     */
    public static function disable(int $userId, string $password = ''): array
    {
        $tenantId = TenantContext::getId();

        $user = DB::selectOne("SELECT password_hash FROM users WHERE id = ? AND tenant_id = ?", [$userId, $tenantId]);
        if (!$user || !password_verify($password, $user->password_hash)) {
            return ['success' => false, 'error' => 'Invalid password.'];
        }

        DB::beginTransaction();
        try {
            DB::delete("DELETE FROM user_totp_settings WHERE user_id = ? AND tenant_id = ?", [$userId, $tenantId]);
            DB::delete("DELETE FROM user_backup_codes WHERE user_id = ? AND tenant_id = ?", [$userId, $tenantId]);
            DB::update("UPDATE users SET totp_enabled = 0, totp_setup_required = 1 WHERE id = ? AND tenant_id = ?", [$userId, $tenantId]);

            DB::commit();
            return ['success' => true];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("TOTP disable error for user $userId: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to disable 2FA.'];
        }
    }

    /**
     * Generate backup codes for a user.
     *
     * @return array  List of plain-text backup codes
     */
    public static function generateBackupCodes(int $userId): array
    {
        $tenantId = TenantContext::getId();

        DB::delete("DELETE FROM user_backup_codes WHERE user_id = ? AND tenant_id = ? AND is_used = 0", [$userId, $tenantId]);

        $codes = [];
        for ($i = 0; $i < self::BACKUP_CODE_COUNT; $i++) {
            $code = self::generateRandomCode();
            $normalizedCode = str_replace('-', '', $code);
            $hash = password_hash($normalizedCode, PASSWORD_DEFAULT);

            DB::insert(
                "INSERT INTO user_backup_codes (user_id, tenant_id, code_hash) VALUES (?, ?, ?)",
                [$userId, $tenantId, $hash]
            );

            $codes[] = $code;
        }

        return $codes;
    }

    /**
     * Get all trusted devices for a user.
     */
    public static function getTrustedDevices(int $userId): array
    {
        $tenantId = TenantContext::getId();

        $devices = DB::select(
            "SELECT id, device_name, ip_address, trusted_at, last_used_at, expires_at
             FROM user_trusted_devices
             WHERE user_id = ? AND tenant_id = ? AND is_revoked = 0 AND expires_at > NOW()
             ORDER BY last_used_at DESC",
            [$userId, $tenantId]
        );

        return array_map(fn ($d) => (array) $d, $devices);
    }

    /**
     * Revoke a specific trusted device.
     */
    public static function revokeDevice(int $userId, int $deviceId, string $reason = 'user_action'): bool
    {
        $tenantId = TenantContext::getId();

        $affected = DB::update(
            "UPDATE user_trusted_devices
             SET is_revoked = 1, revoked_at = NOW(), revoked_reason = ?
             WHERE id = ? AND user_id = ? AND tenant_id = ?",
            [$reason, $deviceId, $userId, $tenantId]
        );

        return $affected > 0;
    }

    /**
     * Revoke all trusted devices for a user.
     *
     * @return int  Number of devices revoked
     */
    public static function revokeAllDevices(int $userId, string $reason = 'user_action'): int
    {
        $tenantId = TenantContext::getId();

        $affected = DB::update(
            "UPDATE user_trusted_devices
             SET is_revoked = 1, revoked_at = NOW(), revoked_reason = ?
             WHERE user_id = ? AND tenant_id = ? AND is_revoked = 0",
            [$reason, $userId, $tenantId]
        );

        setcookie(self::TRUSTED_DEVICE_COOKIE, '', time() - 3600, '/');

        return $affected;
    }

    /**
     * Admin: Reset 2FA for a user (with audit logging).
     *
     * @return array{success: bool, error?: string}
     */
    public static function adminReset(int $userId, int $adminId, string $reason): array
    {
        $tenantId = TenantContext::getId();

        if (empty(trim($reason))) {
            return ['success' => false, 'error' => 'A reason is required for 2FA reset.'];
        }

        DB::beginTransaction();
        try {
            $ip = request()->ip();
            $userAgent = request()->userAgent();

            DB::insert(
                "INSERT INTO totp_admin_overrides
                 (user_id, admin_id, tenant_id, action_type, reason, ip_address, user_agent)
                 VALUES (?, ?, ?, 'reset', ?, ?, ?)",
                [$userId, $adminId, $tenantId, $reason, $ip, $userAgent]
            );

            DB::delete("DELETE FROM user_totp_settings WHERE user_id = ? AND tenant_id = ?", [$userId, $tenantId]);
            DB::delete("DELETE FROM user_backup_codes WHERE user_id = ? AND tenant_id = ?", [$userId, $tenantId]);
            DB::update("UPDATE users SET totp_enabled = 0, totp_setup_required = 1 WHERE id = ? AND tenant_id = ?", [$userId, $tenantId]);

            DB::commit();
            return ['success' => true];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Admin TOTP reset error for user $userId by admin $adminId: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to reset 2FA.'];
        }
    }

    /**
     * Record a 2FA verification attempt.
     */
    public static function recordAttempt(int $userId, bool $successful, string $type = 'totp', ?string $failureReason = null): void
    {
        $tenantId = TenantContext::getId();
        $ip = request()->ip();
        $userAgent = request()->userAgent();

        DB::insert(
            "INSERT INTO totp_verification_attempts
             (user_id, tenant_id, ip_address, user_agent, attempt_type, is_successful, failure_reason)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$userId, $tenantId, $ip, $userAgent, $type, $successful ? 1 : 0, $failureReason]
        );

        if ($successful) {
            DB::delete(
                "DELETE FROM totp_verification_attempts
                 WHERE user_id = ? AND tenant_id = ? AND is_successful = 0",
                [$userId, $tenantId]
            );
        }
    }

    // ─── Private helpers ────────────────────────────────────────────

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
