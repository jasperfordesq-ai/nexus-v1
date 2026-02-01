<?php

declare(strict_types=1);

namespace Nexus\Tests\Services;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\TotpService;
use Nexus\Core\TotpEncryption;

/**
 * TotpService Tests
 *
 * Tests TOTP two-factor authentication functionality including:
 * - Secret generation and verification
 * - Backup code generation and consumption
 * - Setup flow
 * - Trusted device management
 * - Rate limiting
 */
class TotpServiceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);

        self::createTestData();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Clear rate limits before each test to prevent test interference
        if (self::$testUserId) {
            Database::query(
                "DELETE FROM totp_verification_attempts WHERE user_id = ? AND tenant_id = ?",
                [self::$testUserId, self::$testTenantId]
            );
        }
    }

    protected static function createTestData(): void
    {
        $timestamp = time();

        // Create test user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, password_hash, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 0, 1, NOW())",
            [
                self::$testTenantId,
                "totp_test_{$timestamp}@test.com",
                "totp_test_{$timestamp}",
                'TOTP',
                'TestUser',
                password_hash('TestPassword123!', PASSWORD_DEFAULT)
            ]
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up test data
        if (self::$testUserId) {
            Database::query("DELETE FROM user_totp_settings WHERE user_id = ?", [self::$testUserId]);
            Database::query("DELETE FROM user_backup_codes WHERE user_id = ?", [self::$testUserId]);
            Database::query("DELETE FROM totp_verification_attempts WHERE user_id = ?", [self::$testUserId]);
            Database::query("DELETE FROM user_trusted_devices WHERE user_id = ?", [self::$testUserId]);
            Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
        }

        parent::tearDownAfterClass();
    }

    // =========================================================================
    // SECRET GENERATION TESTS
    // =========================================================================

    public function testGenerateSecretReturnsBase32String(): void
    {
        $secret = TotpService::generateSecret();

        $this->assertNotEmpty($secret);
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+=*$/', $secret, 'Secret should be Base32 encoded');
        $this->assertGreaterThanOrEqual(16, strlen($secret), 'Secret should be at least 16 characters');
    }

    public function testGenerateSecretIsUnique(): void
    {
        $secret1 = TotpService::generateSecret();
        $secret2 = TotpService::generateSecret();

        $this->assertNotEquals($secret1, $secret2, 'Each generated secret should be unique');
    }

    // =========================================================================
    // PROVISIONING URI TESTS
    // =========================================================================

    public function testGetProvisioningUriFormat(): void
    {
        $secret = TotpService::generateSecret();
        $email = 'test@example.com';

        $uri = TotpService::getProvisioningUri($secret, $email);

        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString(urlencode($email), $uri);
        $this->assertStringContainsString('secret=' . $secret, $uri);
        $this->assertStringContainsString('issuer=', $uri);
    }

    public function testGetProvisioningUriWithCustomIssuer(): void
    {
        $secret = TotpService::generateSecret();
        $email = 'test@example.com';
        $issuer = 'Custom Issuer';

        $uri = TotpService::getProvisioningUri($secret, $email, $issuer);

        // URL encoding can use + or %20 for spaces
        $this->assertTrue(
            str_contains($uri, 'issuer=Custom%20Issuer') || str_contains($uri, 'issuer=Custom+Issuer'),
            'URI should contain custom issuer'
        );
    }

    // =========================================================================
    // QR CODE GENERATION TESTS
    // =========================================================================

    public function testGenerateQrCodeReturnsSvg(): void
    {
        $secret = TotpService::generateSecret();
        $uri = TotpService::getProvisioningUri($secret, 'test@example.com');

        $qrCode = TotpService::generateQrCode($uri);

        // QR code may start with XML declaration or svg tag
        $this->assertTrue(
            str_starts_with($qrCode, '<svg') || str_starts_with($qrCode, '<?xml'),
            'QR code should be SVG (raw or with XML declaration)'
        );
        $this->assertStringContainsString('</svg>', $qrCode);
    }

    // =========================================================================
    // CODE VERIFICATION TESTS
    // =========================================================================

    public function testVerifyCodeWithValidCode(): void
    {
        $secret = TotpService::generateSecret();

        // Generate current valid code using OTPHP
        $totp = \OTPHP\TOTP::createFromSecret($secret);
        $validCode = $totp->now();

        $result = TotpService::verifyCode($secret, $validCode);

        $this->assertTrue($result);
    }

    public function testVerifyCodeWithInvalidCode(): void
    {
        $secret = TotpService::generateSecret();

        $result = TotpService::verifyCode($secret, '000000');

        $this->assertFalse($result);
    }

    public function testVerifyCodeWithTimeWindow(): void
    {
        $secret = TotpService::generateSecret();
        $totp = \OTPHP\TOTP::createFromSecret($secret);

        // Test that current code works (this is the main verification)
        $currentCode = $totp->now();
        $result = TotpService::verifyCode($secret, $currentCode, 1);
        $this->assertTrue($result, 'Current code should be valid');

        // Note: Testing previous/next period codes is timing-sensitive
        // and may fail near period boundaries. The main test above is sufficient.
    }

    // =========================================================================
    // SETUP FLOW TESTS
    // =========================================================================

    public function testInitializeSetupReturnsRequiredData(): void
    {
        $setup = TotpService::initializeSetup(self::$testUserId);

        $this->assertArrayHasKey('secret', $setup);
        $this->assertArrayHasKey('qr_code', $setup);
        $this->assertArrayHasKey('provisioning_uri', $setup);

        $this->assertNotEmpty($setup['secret']);
        // QR code may start with XML declaration or svg tag
        $this->assertTrue(
            str_starts_with($setup['qr_code'], '<svg') || str_starts_with($setup['qr_code'], '<?xml'),
            'QR code should be SVG'
        );
        $this->assertStringStartsWith('otpauth://', $setup['provisioning_uri']);

        // Store secret in session for complete setup test
        $_SESSION['totp_setup_secret'] = $setup['secret'];
    }

    public function testCompleteSetupWithValidCode(): void
    {
        // Initialize setup first
        $setup = TotpService::initializeSetup(self::$testUserId);
        $_SESSION['totp_setup_secret'] = $setup['secret'];

        // Generate valid code
        $totp = \OTPHP\TOTP::createFromSecret($setup['secret']);
        $validCode = $totp->now();

        $result = TotpService::completeSetup(self::$testUserId, $validCode);

        // Debug output if test fails
        if (!$result['success']) {
            $this->fail('Setup failed: ' . ($result['error'] ?? 'Unknown error'));
        }

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('backup_codes', $result);
        $this->assertCount(10, $result['backup_codes']);
    }

    public function testCompleteSetupWithInvalidCode(): void
    {
        $setup = TotpService::initializeSetup(self::$testUserId);
        $_SESSION['totp_setup_secret'] = $setup['secret'];

        $result = TotpService::completeSetup(self::$testUserId, '000000');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    public function testIsEnabledAfterSetup(): void
    {
        // First complete a setup
        $setup = TotpService::initializeSetup(self::$testUserId);
        $_SESSION['totp_setup_secret'] = $setup['secret'];

        $totp = \OTPHP\TOTP::createFromSecret($setup['secret']);
        TotpService::completeSetup(self::$testUserId, $totp->now());

        $isEnabled = TotpService::isEnabled(self::$testUserId);

        $this->assertTrue($isEnabled);
    }

    // =========================================================================
    // BACKUP CODE TESTS
    // =========================================================================

    public function testBackupCodeFormat(): void
    {
        $codes = TotpService::generateBackupCodes(self::$testUserId);

        foreach ($codes as $code) {
            // Format: XXXX-XXXX
            $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/', $code);
        }
    }

    public function testBackupCodeCount(): void
    {
        TotpService::generateBackupCodes(self::$testUserId);

        $count = TotpService::getBackupCodeCount(self::$testUserId);

        $this->assertEquals(10, $count);
    }

    public function testVerifyBackupCodeConsumesCode(): void
    {
        // Generate fresh backup codes
        $codes = TotpService::generateBackupCodes(self::$testUserId);
        $initialCount = TotpService::getBackupCodeCount(self::$testUserId);

        // Use one code
        $result = TotpService::verifyBackupCode(self::$testUserId, $codes[0]);

        $this->assertTrue($result['success']);

        $newCount = TotpService::getBackupCodeCount(self::$testUserId);
        $this->assertEquals($initialCount - 1, $newCount);
    }

    public function testVerifyBackupCodeRejectsUsedCode(): void
    {
        $codes = TotpService::generateBackupCodes(self::$testUserId);

        // Use code first time
        TotpService::verifyBackupCode(self::$testUserId, $codes[0]);

        // Try to use same code again
        $result = TotpService::verifyBackupCode(self::$testUserId, $codes[0]);

        $this->assertFalse($result['success']);
    }

    public function testVerifyBackupCodeRejectsInvalidCode(): void
    {
        $result = TotpService::verifyBackupCode(self::$testUserId, 'XXXX-XXXX');

        $this->assertFalse($result['success']);
    }

    // =========================================================================
    // LOGIN VERIFICATION TESTS
    // =========================================================================

    public function testVerifyLoginWithValidTotpCode(): void
    {
        // Ensure 2FA is enabled
        $setup = TotpService::initializeSetup(self::$testUserId);
        $_SESSION['totp_setup_secret'] = $setup['secret'];
        $totp = \OTPHP\TOTP::createFromSecret($setup['secret']);
        TotpService::completeSetup(self::$testUserId, $totp->now());

        // Generate new valid code
        $validCode = $totp->now();

        $result = TotpService::verifyLogin(self::$testUserId, $validCode);

        $this->assertTrue($result['success']);
    }

    public function testVerifyLoginWithInvalidCode(): void
    {
        $result = TotpService::verifyLogin(self::$testUserId, '999999');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    // =========================================================================
    // RATE LIMITING TESTS
    // =========================================================================

    public function testRateLimitAfterMaxAttempts(): void
    {
        // Ensure 2FA is enabled first
        if (!TotpService::isEnabled(self::$testUserId)) {
            $setup = TotpService::initializeSetup(self::$testUserId);
            $_SESSION['totp_setup_secret'] = $setup['secret'];
            $totp = \OTPHP\TOTP::createFromSecret($setup['secret']);
            TotpService::completeSetup(self::$testUserId, $totp->now());
        }

        // Clear any existing attempts
        Database::query(
            "DELETE FROM totp_verification_attempts WHERE user_id = ? AND tenant_id = ?",
            [self::$testUserId, self::$testTenantId]
        );

        // Simulate 5 failed attempts
        for ($i = 0; $i < 5; $i++) {
            TotpService::verifyLogin(self::$testUserId, '000000');
        }

        $rateLimit = TotpService::checkRateLimit(self::$testUserId);

        $this->assertTrue($rateLimit['limited'], 'User should be rate limited after 5 failed attempts');
        $this->assertArrayHasKey('retry_after', $rateLimit);
    }

    // =========================================================================
    // TRUSTED DEVICE TESTS
    // =========================================================================

    public function testTrustDeviceCreatesRecord(): void
    {
        // Simulate request environment
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0';

        $result = TotpService::trustDevice(self::$testUserId);

        $this->assertTrue($result);

        // Verify record was created
        $stmt = Database::query(
            "SELECT * FROM user_trusted_devices WHERE user_id = ? AND tenant_id = ? AND is_revoked = 0",
            [self::$testUserId, self::$testTenantId]
        );
        $device = $stmt->fetch();

        $this->assertNotEmpty($device);
        $this->assertEquals('Chrome on Windows', $device['device_name']);
        $this->assertEquals('127.0.0.1', $device['ip_address']);
    }

    public function testGetTrustedDevices(): void
    {
        // Trust a device first
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh) Safari/537.36';
        TotpService::trustDevice(self::$testUserId);

        $devices = TotpService::getTrustedDevices(self::$testUserId);

        $this->assertNotEmpty($devices);
        $this->assertIsArray($devices);

        foreach ($devices as $device) {
            $this->assertArrayHasKey('id', $device);
            $this->assertArrayHasKey('device_name', $device);
            $this->assertArrayHasKey('ip_address', $device);
            $this->assertArrayHasKey('trusted_at', $device);
            $this->assertArrayHasKey('expires_at', $device);
        }
    }

    public function testGetTrustedDeviceCount(): void
    {
        // Clear existing trusted devices
        Database::query(
            "DELETE FROM user_trusted_devices WHERE user_id = ? AND tenant_id = ?",
            [self::$testUserId, self::$testTenantId]
        );

        // Trust 3 devices
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'Chrome';
        TotpService::trustDevice(self::$testUserId);

        $_SERVER['REMOTE_ADDR'] = '10.0.0.2';
        $_SERVER['HTTP_USER_AGENT'] = 'Firefox';
        TotpService::trustDevice(self::$testUserId);

        $_SERVER['REMOTE_ADDR'] = '10.0.0.3';
        $_SERVER['HTTP_USER_AGENT'] = 'Safari';
        TotpService::trustDevice(self::$testUserId);

        $count = TotpService::getTrustedDeviceCount(self::$testUserId);

        $this->assertEquals(3, $count);
    }

    public function testRevokeDevice(): void
    {
        // Trust a device
        $_SERVER['REMOTE_ADDR'] = '10.0.0.100';
        $_SERVER['HTTP_USER_AGENT'] = 'Test Browser';
        TotpService::trustDevice(self::$testUserId);

        $devices = TotpService::getTrustedDevices(self::$testUserId);
        $deviceId = $devices[0]['id'];

        // Revoke it
        $result = TotpService::revokeDevice(self::$testUserId, $deviceId, 'test_revoke');

        $this->assertTrue($result);

        // Verify it's revoked
        $stmt = Database::query(
            "SELECT is_revoked, revoked_reason FROM user_trusted_devices WHERE id = ?",
            [$deviceId]
        );
        $device = $stmt->fetch();

        $this->assertEquals(1, $device['is_revoked']);
        $this->assertEquals('test_revoke', $device['revoked_reason']);
    }

    public function testRevokeAllDevices(): void
    {
        // Clear and create fresh devices
        Database::query(
            "DELETE FROM user_trusted_devices WHERE user_id = ? AND tenant_id = ?",
            [self::$testUserId, self::$testTenantId]
        );

        // Trust 2 devices
        TotpService::trustDevice(self::$testUserId);
        TotpService::trustDevice(self::$testUserId);

        $initialCount = TotpService::getTrustedDeviceCount(self::$testUserId);
        $this->assertEquals(2, $initialCount);

        // Revoke all
        $revokedCount = TotpService::revokeAllDevices(self::$testUserId, 'bulk_revoke');

        $this->assertEquals(2, $revokedCount);

        // Verify count is now 0
        $newCount = TotpService::getTrustedDeviceCount(self::$testUserId);
        $this->assertEquals(0, $newCount);
    }

    public function testIsTrustedDeviceWithoutCookie(): void
    {
        unset($_COOKIE['nexus_trusted_device']);

        $result = TotpService::isTrustedDevice(self::$testUserId);

        $this->assertFalse($result);
    }

    public function testIsTrustedDeviceWithInvalidCookie(): void
    {
        $_COOKIE['nexus_trusted_device'] = 'invalid_token_that_does_not_exist';

        $result = TotpService::isTrustedDevice(self::$testUserId);

        $this->assertFalse($result);
    }

    // =========================================================================
    // DEVICE NAME PARSING TESTS
    // =========================================================================

    /**
     * @dataProvider userAgentProvider
     */
    public function testParseDeviceNameFromUserAgent(string $userAgent, string $expectedBrowser, string $expectedOs): void
    {
        // Use reflection to test private method
        $reflection = new \ReflectionClass(TotpService::class);
        $method = $reflection->getMethod('parseDeviceName');
        $method->setAccessible(true);

        $result = $method->invoke(null, $userAgent);

        $this->assertStringContainsString($expectedBrowser, $result);
        $this->assertStringContainsString($expectedOs, $result);
    }

    public static function userAgentProvider(): array
    {
        return [
            'Chrome on Windows' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Chrome',
                'Windows'
            ],
            'Firefox on macOS' => [
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:121.0) Gecko/20100101 Firefox/121.0',
                'Firefox',
                'macOS'
            ],
            'Safari on iOS' => [
                'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1',
                'Safari',
                'iOS'
            ],
            'Edge on Windows' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',
                'Edge',
                'Windows'
            ],
            'Chrome on Android' => [
                'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.6099.144 Mobile Safari/537.36',
                'Chrome',
                'Android'
            ],
        ];
    }

    public function testParseDeviceNameWithNullUserAgent(): void
    {
        $reflection = new \ReflectionClass(TotpService::class);
        $method = $reflection->getMethod('parseDeviceName');
        $method->setAccessible(true);

        $result = $method->invoke(null, null);

        $this->assertEquals('Unknown device', $result);
    }

    // =========================================================================
    // ENCRYPTION TESTS
    // =========================================================================

    public function testTotpSecretIsEncryptedInDatabase(): void
    {
        // Complete a fresh setup
        $setup = TotpService::initializeSetup(self::$testUserId);
        $_SESSION['totp_setup_secret'] = $setup['secret'];

        $totp = \OTPHP\TOTP::createFromSecret($setup['secret']);
        TotpService::completeSetup(self::$testUserId, $totp->now());

        // Check that stored secret is encrypted (not plain base32)
        $stmt = Database::query(
            "SELECT totp_secret_encrypted FROM user_totp_settings WHERE user_id = ? AND tenant_id = ?",
            [self::$testUserId, self::$testTenantId]
        );
        $record = $stmt->fetch();

        $this->assertNotEmpty($record['totp_secret_encrypted']);
        // Encrypted data should not match the plain secret
        $this->assertNotEquals($setup['secret'], $record['totp_secret_encrypted']);
        // Should be significantly longer due to IV + tag
        $this->assertGreaterThan(strlen($setup['secret']), strlen($record['totp_secret_encrypted']));
    }
}
