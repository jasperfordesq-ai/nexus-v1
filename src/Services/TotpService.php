<?php

namespace Nexus\Services;

use OTPHP\TOTP;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\TotpEncryption;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * TotpService - TOTP Two-Factor Authentication Service
 *
 * Handles all TOTP operations including secret generation, verification,
 * backup codes, and database operations.
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
     * Generate a new TOTP secret
     *
     * @return string Base32-encoded secret
     */
    public static function generateSecret(): string
    {
        $totp = TOTP::generate();
        return $totp->getSecret();
    }

    /**
     * Get the provisioning URI for authenticator apps
     *
     * @param string $secret The TOTP secret
     * @param string $email User's email address
     * @param string|null $issuer Optional custom issuer name
     * @return string otpauth:// URI
     */
    public static function getProvisioningUri(string $secret, string $email, ?string $issuer = null): string
    {
        $totp = TOTP::createFromSecret($secret);
        $totp->setLabel($email);
        $totp->setIssuer($issuer ?? self::ISSUER);
        return $totp->getProvisioningUri();
    }

    /**
     * Generate a QR code image for the provisioning URI
     *
     * @param string $provisioningUri The otpauth:// URI
     * @return string Raw SVG markup (to be embedded directly in HTML)
     */
    public static function generateQrCode(string $provisioningUri): string
    {
        // Use endroid/qr-code v6.1 API with constructor parameters
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
     * Verify a TOTP code
     *
     * @param string $secret The TOTP secret
     * @param string $code The 6-digit code to verify
     * @param int $window Number of periods to check before/after (default: 1)
     * @return bool True if code is valid
     */
    public static function verifyCode(string $secret, string $code, int $window = 1): bool
    {
        $totp = TOTP::createFromSecret($secret);
        return $totp->verify($code, null, $window);
    }

    /**
     * Check if user is rate limited for 2FA attempts
     *
     * @param int $userId
     * @return array{limited: bool, retry_after: int|null, message: string|null}
     */
    public static function checkRateLimit(int $userId): array
    {
        $tenantId = TenantContext::getId();
        $cutoff = date('Y-m-d H:i:s', time() - self::LOCKOUT_SECONDS);

        $stmt = Database::query(
            "SELECT COUNT(*) as attempts FROM totp_verification_attempts
             WHERE user_id = ? AND tenant_id = ? AND is_successful = 0 AND attempted_at > ?",
            [$userId, $tenantId, $cutoff]
        );
        $result = $stmt->fetch();
        $attempts = (int)($result['attempts'] ?? 0);

        if ($attempts >= self::MAX_ATTEMPTS) {
            // Find when the oldest attempt in the window was made
            $stmt = Database::query(
                "SELECT MIN(attempted_at) as oldest FROM totp_verification_attempts
                 WHERE user_id = ? AND tenant_id = ? AND is_successful = 0 AND attempted_at > ?",
                [$userId, $tenantId, $cutoff]
            );
            $oldest = $stmt->fetch();
            $oldestTime = strtotime($oldest['oldest'] ?? 'now');
            $retryAfter = $oldestTime + self::LOCKOUT_SECONDS - time();

            return [
                'limited' => true,
                'retry_after' => max(0, $retryAfter),
                'message' => "Too many failed attempts. Please try again in " . ceil($retryAfter / 60) . " minutes."
            ];
        }

        return ['limited' => false, 'retry_after' => null, 'message' => null];
    }

    /**
     * Record a 2FA verification attempt
     *
     * @param int $userId
     * @param bool $successful
     * @param string $type 'totp' or 'backup_code'
     * @param string|null $failureReason
     */
    public static function recordAttempt(int $userId, bool $successful, string $type = 'totp', ?string $failureReason = null): void
    {
        $tenantId = TenantContext::getId();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        Database::query(
            "INSERT INTO totp_verification_attempts
             (user_id, tenant_id, ip_address, user_agent, attempt_type, is_successful, failure_reason)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$userId, $tenantId, $ip, $userAgent, $type, $successful ? 1 : 0, $failureReason]
        );

        // On successful verification, clear failed attempts
        if ($successful) {
            Database::query(
                "DELETE FROM totp_verification_attempts
                 WHERE user_id = ? AND tenant_id = ? AND is_successful = 0",
                [$userId, $tenantId]
            );
        }
    }

    /**
     * Initialize 2FA setup for a user (generates secret, stores as pending)
     *
     * @param int $userId
     * @return array{secret: string, provisioning_uri: string, qr_code: string}
     */
    public static function initializeSetup(int $userId): array
    {
        $tenantId = TenantContext::getId();
        $user = \Nexus\Models\User::findById($userId);

        if (!$user) {
            throw new \RuntimeException('User not found');
        }

        $secret = self::generateSecret();
        $encryptedSecret = TotpEncryption::encrypt($secret);

        // Upsert the pending setup
        Database::query(
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

        $provisioningUri = self::getProvisioningUri($secret, $user['email']);
        $qrCode = self::generateQrCode($provisioningUri);

        return [
            'secret' => $secret,
            'provisioning_uri' => $provisioningUri,
            'qr_code' => $qrCode
        ];
    }

    /**
     * Complete 2FA setup after user verifies the code
     *
     * @param int $userId
     * @param string $code The 6-digit code to verify
     * @return array{success: bool, error?: string, backup_codes?: array}
     */
    public static function completeSetup(int $userId, string $code): array
    {
        $tenantId = TenantContext::getId();

        // Check rate limit
        $rateLimit = self::checkRateLimit($userId);
        if ($rateLimit['limited']) {
            return ['success' => false, 'error' => $rateLimit['message']];
        }

        // Get pending setup
        $stmt = Database::query(
            "SELECT totp_secret_encrypted FROM user_totp_settings
             WHERE user_id = ? AND tenant_id = ? AND is_pending_setup = 1",
            [$userId, $tenantId]
        );
        $settings = $stmt->fetch();

        if (!$settings) {
            return ['success' => false, 'error' => '2FA setup not initialized. Please start over.'];
        }

        try {
            $secret = TotpEncryption::decrypt($settings['totp_secret_encrypted']);
        } catch (\Exception $e) {
            error_log("TOTP decrypt error for user $userId: " . $e->getMessage());
            return ['success' => false, 'error' => 'Encryption error. Please start setup again.'];
        }

        // Verify the code
        if (!self::verifyCode($secret, $code)) {
            self::recordAttempt($userId, false, 'totp', 'invalid_code_during_setup');
            return ['success' => false, 'error' => 'Invalid code. Please check the code and try again.'];
        }

        // Enable 2FA
        Database::beginTransaction();
        try {
            Database::query(
                "UPDATE user_totp_settings SET
                    is_enabled = 1,
                    is_pending_setup = 0,
                    enabled_at = NOW(),
                    last_verified_at = NOW(),
                    verified_device_count = verified_device_count + 1
                 WHERE user_id = ? AND tenant_id = ?",
                [$userId, $tenantId]
            );

            Database::query(
                "UPDATE users SET totp_enabled = 1, totp_setup_required = 0 WHERE id = ?",
                [$userId]
            );

            // Generate backup codes
            $backupCodes = self::generateBackupCodes($userId);

            self::recordAttempt($userId, true, 'totp');

            Database::commit();

            return ['success' => true, 'backup_codes' => $backupCodes];
        } catch (\Exception $e) {
            Database::rollback();
            error_log("TOTP setup error for user $userId: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to enable 2FA. Please try again.'];
        }
    }

    /**
     * Verify 2FA during login
     *
     * @param int $userId
     * @param string $code The 6-digit TOTP code
     * @return array{success: bool, error?: string}
     */
    public static function verifyLogin(int $userId, string $code): array
    {
        $tenantId = TenantContext::getId();

        // Check rate limit
        $rateLimit = self::checkRateLimit($userId);
        if ($rateLimit['limited']) {
            return ['success' => false, 'error' => $rateLimit['message']];
        }

        // Get user's TOTP settings
        $stmt = Database::query(
            "SELECT totp_secret_encrypted FROM user_totp_settings
             WHERE user_id = ? AND tenant_id = ? AND is_enabled = 1",
            [$userId, $tenantId]
        );
        $settings = $stmt->fetch();

        if (!$settings) {
            return ['success' => false, 'error' => '2FA not enabled for this account.'];
        }

        try {
            $secret = TotpEncryption::decrypt($settings['totp_secret_encrypted']);
        } catch (\Exception $e) {
            error_log("TOTP decrypt error for user $userId: " . $e->getMessage());
            return ['success' => false, 'error' => 'Authentication error. Please contact support.'];
        }

        // Verify the code
        if (!self::verifyCode($secret, $code)) {
            self::recordAttempt($userId, false, 'totp', 'invalid_code');
            return ['success' => false, 'error' => 'Invalid code. Please try again.'];
        }

        // Update last verified
        Database::query(
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
     * Verify a backup code during login
     *
     * @param int $userId
     * @param string $code The backup code (format: XXXX-XXXX)
     * @return array{success: bool, error?: string, codes_remaining?: int}
     */
    public static function verifyBackupCode(int $userId, string $code): array
    {
        $tenantId = TenantContext::getId();

        // Check rate limit
        $rateLimit = self::checkRateLimit($userId);
        if ($rateLimit['limited']) {
            return ['success' => false, 'error' => $rateLimit['message']];
        }

        // Normalize code (remove dashes, uppercase)
        $normalizedCode = strtoupper(str_replace(['-', ' '], '', $code));

        // Get all unused backup codes for this user
        $stmt = Database::query(
            "SELECT id, code_hash FROM user_backup_codes
             WHERE user_id = ? AND tenant_id = ? AND is_used = 0",
            [$userId, $tenantId]
        );
        $codes = $stmt->fetchAll();

        $matchedCodeId = null;
        foreach ($codes as $backupCode) {
            if (password_verify($normalizedCode, $backupCode['code_hash'])) {
                $matchedCodeId = $backupCode['id'];
                break;
            }
        }

        if (!$matchedCodeId) {
            self::recordAttempt($userId, false, 'backup_code', 'invalid_backup_code');
            return ['success' => false, 'error' => 'Invalid backup code.'];
        }

        // Mark code as used
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        Database::query(
            "UPDATE user_backup_codes SET
                is_used = 1,
                used_at = NOW(),
                used_ip = ?,
                used_user_agent = ?
             WHERE id = ?",
            [$ip, $userAgent, $matchedCodeId]
        );

        // Count remaining codes
        $stmt = Database::query(
            "SELECT COUNT(*) as remaining FROM user_backup_codes
             WHERE user_id = ? AND tenant_id = ? AND is_used = 0",
            [$userId, $tenantId]
        );
        $remaining = (int)($stmt->fetch()['remaining'] ?? 0);

        self::recordAttempt($userId, true, 'backup_code');

        return ['success' => true, 'codes_remaining' => $remaining];
    }

    /**
     * Generate backup codes for a user
     *
     * @param int $userId
     * @return array List of plain-text backup codes
     */
    public static function generateBackupCodes(int $userId): array
    {
        $tenantId = TenantContext::getId();

        // Delete existing unused codes
        Database::query(
            "DELETE FROM user_backup_codes WHERE user_id = ? AND tenant_id = ? AND is_used = 0",
            [$userId, $tenantId]
        );

        $codes = [];
        for ($i = 0; $i < self::BACKUP_CODE_COUNT; $i++) {
            $code = self::generateRandomCode();
            $normalizedCode = str_replace('-', '', $code);
            $hash = password_hash($normalizedCode, PASSWORD_DEFAULT);

            Database::query(
                "INSERT INTO user_backup_codes (user_id, tenant_id, code_hash) VALUES (?, ?, ?)",
                [$userId, $tenantId, $hash]
            );

            $codes[] = $code;
        }

        return $codes;
    }

    /**
     * Get the count of remaining backup codes for a user
     *
     * @param int $userId
     * @return int
     */
    public static function getBackupCodeCount(int $userId): int
    {
        $tenantId = TenantContext::getId();

        $stmt = Database::query(
            "SELECT COUNT(*) as count FROM user_backup_codes
             WHERE user_id = ? AND tenant_id = ? AND is_used = 0",
            [$userId, $tenantId]
        );

        return (int)($stmt->fetch()['count'] ?? 0);
    }

    /**
     * Check if user has 2FA enabled
     *
     * @param int $userId
     * @return bool
     */
    public static function isEnabled(int $userId): bool
    {
        $tenantId = TenantContext::getId();

        $stmt = Database::query(
            "SELECT is_enabled FROM user_totp_settings
             WHERE user_id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        );
        $settings = $stmt->fetch();

        return (bool)($settings['is_enabled'] ?? false);
    }

    /**
     * Check if user needs to set up 2FA
     *
     * @param int $userId
     * @return bool
     */
    public static function isSetupRequired(int $userId): bool
    {
        $stmt = Database::query(
            "SELECT totp_setup_required FROM users WHERE id = ?",
            [$userId]
        );
        $user = $stmt->fetch();

        return (bool)($user['totp_setup_required'] ?? true);
    }

    /**
     * Disable 2FA for a user (requires password confirmation)
     *
     * @param int $userId
     * @param string $password User's current password for confirmation
     * @return array{success: bool, error?: string}
     */
    public static function disable(int $userId, string $password): array
    {
        $tenantId = TenantContext::getId();

        // Verify password
        $user = \Nexus\Models\User::findById($userId);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Invalid password.'];
        }

        Database::beginTransaction();
        try {
            // Delete TOTP settings
            Database::query(
                "DELETE FROM user_totp_settings WHERE user_id = ? AND tenant_id = ?",
                [$userId, $tenantId]
            );

            // Delete backup codes
            Database::query(
                "DELETE FROM user_backup_codes WHERE user_id = ? AND tenant_id = ?",
                [$userId, $tenantId]
            );

            // Update user flags - they'll need to set up again
            Database::query(
                "UPDATE users SET totp_enabled = 0, totp_setup_required = 1 WHERE id = ?",
                [$userId]
            );

            Database::commit();

            return ['success' => true];
        } catch (\Exception $e) {
            Database::rollback();
            error_log("TOTP disable error for user $userId: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to disable 2FA.'];
        }
    }

    /**
     * Admin: Reset 2FA for a user (with audit logging)
     *
     * @param int $userId User to reset
     * @param int $adminId Admin performing the action
     * @param string $reason Reason for the reset
     * @return array{success: bool, error?: string}
     */
    public static function adminReset(int $userId, int $adminId, string $reason): array
    {
        $tenantId = TenantContext::getId();

        if (empty(trim($reason))) {
            return ['success' => false, 'error' => 'A reason is required for 2FA reset.'];
        }

        Database::beginTransaction();
        try {
            // Log the override
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            Database::query(
                "INSERT INTO totp_admin_overrides
                 (user_id, admin_id, tenant_id, action_type, reason, ip_address, user_agent)
                 VALUES (?, ?, ?, 'reset', ?, ?, ?)",
                [$userId, $adminId, $tenantId, $reason, $ip, $userAgent]
            );

            // Delete TOTP settings
            Database::query(
                "DELETE FROM user_totp_settings WHERE user_id = ? AND tenant_id = ?",
                [$userId, $tenantId]
            );

            // Delete backup codes
            Database::query(
                "DELETE FROM user_backup_codes WHERE user_id = ? AND tenant_id = ?",
                [$userId, $tenantId]
            );

            // Update user flags
            Database::query(
                "UPDATE users SET totp_enabled = 0, totp_setup_required = 1 WHERE id = ?",
                [$userId]
            );

            Database::commit();

            return ['success' => true];
        } catch (\Exception $e) {
            Database::rollback();
            error_log("Admin TOTP reset error for user $userId by admin $adminId: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to reset 2FA.'];
        }
    }

    /**
     * Generate a random backup code
     *
     * @return string Format: XXXX-XXXX
     */
    private static function generateRandomCode(): string
    {
        // Exclude ambiguous characters: 0, O, 1, I, L
        $chars = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
        $code = '';

        for ($i = 0; $i < self::BACKUP_CODE_LENGTH; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return substr($code, 0, 4) . '-' . substr($code, 4);
    }

    // =========================================================================
    // TRUSTED DEVICES ("Remember This Device")
    // =========================================================================

    /**
     * Check if current device is trusted for this user
     *
     * @param int $userId
     * @return bool
     */
    public static function isTrustedDevice(int $userId): bool
    {
        $token = $_COOKIE[self::TRUSTED_DEVICE_COOKIE] ?? null;
        if (!$token) {
            return false;
        }

        $tenantId = TenantContext::getId();
        $tokenHash = hash('sha256', $token);

        $stmt = Database::query(
            "SELECT id FROM user_trusted_devices
             WHERE user_id = ? AND tenant_id = ? AND device_token_hash = ?
             AND is_revoked = 0 AND expires_at > NOW()",
            [$userId, $tenantId, $tokenHash]
        );
        $device = $stmt->fetch();

        if ($device) {
            // Update last used timestamp
            Database::query(
                "UPDATE user_trusted_devices SET last_used_at = NOW() WHERE id = ?",
                [$device['id']]
            );
            return true;
        }

        return false;
    }

    /**
     * Trust the current device for this user
     *
     * @param int $userId
     * @return bool Success
     */
    public static function trustDevice(int $userId): bool
    {
        $tenantId = TenantContext::getId();

        // Generate secure random token
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $deviceName = self::parseDeviceName($userAgent);
        $expiresAt = date('Y-m-d H:i:s', time() + (self::TRUSTED_DEVICE_DAYS * 24 * 60 * 60));

        try {
            Database::query(
                "INSERT INTO user_trusted_devices
                 (user_id, tenant_id, device_token_hash, device_name, ip_address, user_agent, expires_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$userId, $tenantId, $tokenHash, $deviceName, $ip, $userAgent, $expiresAt]
            );

            // Set cookie
            $cookieExpires = time() + (self::TRUSTED_DEVICE_DAYS * 24 * 60 * 60);
            $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            $basePath = TenantContext::getBasePath() ?: '/';

            setcookie(
                self::TRUSTED_DEVICE_COOKIE,
                $token,
                [
                    'expires' => $cookieExpires,
                    'path' => $basePath,
                    'secure' => $secure,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]
            );

            return true;
        } catch (\Exception $e) {
            error_log("Failed to trust device for user $userId: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all trusted devices for a user
     *
     * @param int $userId
     * @return array
     */
    public static function getTrustedDevices(int $userId): array
    {
        $tenantId = TenantContext::getId();

        $stmt = Database::query(
            "SELECT id, device_name, ip_address, trusted_at, last_used_at, expires_at
             FROM user_trusted_devices
             WHERE user_id = ? AND tenant_id = ? AND is_revoked = 0 AND expires_at > NOW()
             ORDER BY last_used_at DESC",
            [$userId, $tenantId]
        );

        return $stmt->fetchAll() ?: [];
    }

    /**
     * Get count of trusted devices for a user
     *
     * @param int $userId
     * @return int
     */
    public static function getTrustedDeviceCount(int $userId): int
    {
        $tenantId = TenantContext::getId();

        $stmt = Database::query(
            "SELECT COUNT(*) as count FROM user_trusted_devices
             WHERE user_id = ? AND tenant_id = ? AND is_revoked = 0 AND expires_at > NOW()",
            [$userId, $tenantId]
        );

        return (int)($stmt->fetch()['count'] ?? 0);
    }

    /**
     * Revoke a specific trusted device
     *
     * @param int $userId
     * @param int $deviceId
     * @param string $reason
     * @return bool
     */
    public static function revokeDevice(int $userId, int $deviceId, string $reason = 'user_action'): bool
    {
        $tenantId = TenantContext::getId();

        $result = Database::query(
            "UPDATE user_trusted_devices
             SET is_revoked = 1, revoked_at = NOW(), revoked_reason = ?
             WHERE id = ? AND user_id = ? AND tenant_id = ?",
            [$reason, $deviceId, $userId, $tenantId]
        );

        return $result->rowCount() > 0;
    }

    /**
     * Revoke all trusted devices for a user
     *
     * @param int $userId
     * @param string $reason
     * @return int Number of devices revoked
     */
    public static function revokeAllDevices(int $userId, string $reason = 'user_action'): int
    {
        $tenantId = TenantContext::getId();

        $result = Database::query(
            "UPDATE user_trusted_devices
             SET is_revoked = 1, revoked_at = NOW(), revoked_reason = ?
             WHERE user_id = ? AND tenant_id = ? AND is_revoked = 0",
            [$reason, $userId, $tenantId]
        );

        // Also clear the cookie
        $basePath = TenantContext::getBasePath() ?: '/';
        setcookie(self::TRUSTED_DEVICE_COOKIE, '', time() - 3600, $basePath);

        return $result->rowCount();
    }

    /**
     * Parse user agent string to get a human-readable device name
     *
     * @param string|null $userAgent
     * @return string
     */
    private static function parseDeviceName(?string $userAgent): string
    {
        if (!$userAgent) {
            return 'Unknown device';
        }

        $browser = 'Unknown browser';
        $os = 'Unknown OS';

        // Detect browser
        if (strpos($userAgent, 'Firefox') !== false) {
            $browser = 'Firefox';
        } elseif (strpos($userAgent, 'Edg') !== false) {
            $browser = 'Edge';
        } elseif (strpos($userAgent, 'Chrome') !== false) {
            $browser = 'Chrome';
        } elseif (strpos($userAgent, 'Safari') !== false) {
            $browser = 'Safari';
        } elseif (strpos($userAgent, 'MSIE') !== false || strpos($userAgent, 'Trident') !== false) {
            $browser = 'Internet Explorer';
        }

        // Detect OS (order matters - check mobile OSes first as they contain desktop OS strings)
        if (strpos($userAgent, 'iPhone') !== false || strpos($userAgent, 'iPad') !== false) {
            $os = 'iOS';
        } elseif (strpos($userAgent, 'Android') !== false) {
            $os = 'Android';
        } elseif (strpos($userAgent, 'Windows') !== false) {
            $os = 'Windows';
        } elseif (strpos($userAgent, 'Mac') !== false) {
            $os = 'macOS';
        } elseif (strpos($userAgent, 'Linux') !== false) {
            $os = 'Linux';
        }

        return "$browser on $os";
    }
}
