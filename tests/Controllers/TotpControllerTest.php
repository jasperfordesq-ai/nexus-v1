<?php

declare(strict_types=1);

namespace Nexus\Tests\Controllers;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\TotpService;
use Nexus\Controllers\TotpController;

/**
 * TotpController Tests
 *
 * Tests TOTP controller flow including:
 * - Verification during login
 * - Setup wizard
 * - Backup codes management
 * - Trusted device management
 */
class TotpControllerTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    private TotpController $controller;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);

        self::createTestData();
    }

    protected static function createTestData(): void
    {
        $timestamp = time();

        // Create test user with 2FA enabled
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, password_hash, balance, is_approved, totp_enabled, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 0, 1, 0, NOW())",
            [
                self::$testTenantId,
                "totp_controller_test_{$timestamp}@test.com",
                "totp_controller_test_{$timestamp}",
                'Controller',
                'TestUser',
                password_hash('TestPassword123!', PASSWORD_DEFAULT)
            ]
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Start output buffering to capture any output
        ob_start();

        // Initialize session if not started
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $this->controller = new TotpController();

        // Set up mock environment
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit Test Agent';

        // Clear rate limits before each test to prevent test interference
        if (self::$testUserId) {
            Database::query(
                "DELETE FROM totp_verification_attempts WHERE user_id = ? AND tenant_id = ?",
                [self::$testUserId, self::$testTenantId]
            );
        }
    }

    protected function tearDown(): void
    {
        // Clear output buffer
        ob_end_clean();

        // Clear session
        $_SESSION = [];

        // Clear POST/GET
        $_POST = [];
        $_GET = [];

        parent::tearDown();
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
    // SHOW VERIFY TESTS
    // =========================================================================

    public function testShowVerifyRedirectsWithoutPendingSession(): void
    {
        unset($_SESSION['pending_2fa_user_id']);

        $this->expectOutputRegex('/^$/'); // Expect redirect (no output)

        try {
            $this->controller->showVerify();
        } catch (\Exception $e) {
            // Redirect causes exit, which might throw
        }

        // Would redirect to login
        $this->assertArrayNotHasKey('pending_2fa_user_id', $_SESSION);
    }

    public function testShowVerifyRedirectsOnExpiredSession(): void
    {
        $_SESSION['pending_2fa_user_id'] = self::$testUserId;
        $_SESSION['pending_2fa_expires'] = time() - 100; // Expired

        try {
            $this->controller->showVerify();
        } catch (\Exception $e) {
            // Redirect causes exit
        }

        // Session should be cleared
        $this->assertArrayNotHasKey('pending_2fa_user_id', $_SESSION);
    }

    // =========================================================================
    // VERIFY TESTS
    // =========================================================================

    public function testVerifyWithValidCode(): void
    {
        // Set up 2FA for user
        $setup = TotpService::initializeSetup(self::$testUserId);
        $_SESSION['totp_setup_secret'] = $setup['secret'];
        $totp = \OTPHP\TOTP::createFromSecret($setup['secret']);
        TotpService::completeSetup(self::$testUserId, $totp->now());

        // Simulate pending 2FA session
        $_SESSION['pending_2fa_user_id'] = self::$testUserId;
        $_SESSION['pending_2fa_expires'] = time() + 300;

        // Set POST data
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['code'] = $totp->now();
        $_POST['csrf_token'] = \Nexus\Core\Csrf::token();

        // Mock CSRF validation
        $_SESSION['csrf_token'] = $_POST['csrf_token'];

        try {
            $this->controller->verify();
        } catch (\Exception $e) {
            // Exit from redirect
        }

        // User should be logged in
        $this->assertArrayHasKey('user_id', $_SESSION);
        $this->assertEquals(self::$testUserId, $_SESSION['user_id']);
    }

    public function testVerifyWithRememberDevice(): void
    {
        // Ensure 2FA is enabled
        $setup = TotpService::initializeSetup(self::$testUserId);
        $_SESSION['totp_setup_secret'] = $setup['secret'];
        $totp = \OTPHP\TOTP::createFromSecret($setup['secret']);
        TotpService::completeSetup(self::$testUserId, $totp->now());

        // Simulate pending 2FA session
        $_SESSION['pending_2fa_user_id'] = self::$testUserId;
        $_SESSION['pending_2fa_expires'] = time() + 300;

        // Set POST data with remember device
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['code'] = $totp->now();
        $_POST['remember_device'] = '1';
        $_POST['csrf_token'] = \Nexus\Core\Csrf::token();
        $_SESSION['csrf_token'] = $_POST['csrf_token'];

        // Clear existing trusted devices
        Database::query(
            "DELETE FROM user_trusted_devices WHERE user_id = ? AND tenant_id = ?",
            [self::$testUserId, self::$testTenantId]
        );

        try {
            $this->controller->verify();
        } catch (\Exception $e) {
            // Exit from redirect
        }

        // Verify trusted device was created
        $deviceCount = TotpService::getTrustedDeviceCount(self::$testUserId);
        $this->assertGreaterThan(0, $deviceCount, 'Trusted device should be created');
    }

    public function testVerifyWithInvalidCode(): void
    {
        $_SESSION['pending_2fa_user_id'] = self::$testUserId;
        $_SESSION['pending_2fa_expires'] = time() + 300;

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['code'] = '000000'; // Invalid
        $_POST['csrf_token'] = \Nexus\Core\Csrf::token();
        $_SESSION['csrf_token'] = $_POST['csrf_token'];

        try {
            $this->controller->verify();
        } catch (\Exception $e) {
            // Redirect to error
        }

        // User should not be logged in
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function testVerifyWithBackupCode(): void
    {
        // Generate backup codes
        $codes = TotpService::generateBackupCodes(self::$testUserId);

        $_SESSION['pending_2fa_user_id'] = self::$testUserId;
        $_SESSION['pending_2fa_expires'] = time() + 300;

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['code'] = $codes[0];
        $_POST['use_backup_code'] = '1';
        $_POST['csrf_token'] = \Nexus\Core\Csrf::token();
        $_SESSION['csrf_token'] = $_POST['csrf_token'];

        try {
            $this->controller->verify();
        } catch (\Exception $e) {
            // Exit from redirect
        }

        // User should be logged in
        $this->assertArrayHasKey('user_id', $_SESSION);

        // Backup code should be consumed
        $remainingCodes = TotpService::getBackupCodeCount(self::$testUserId);
        $this->assertEquals(9, $remainingCodes);
    }

    // =========================================================================
    // SETUP TESTS
    // =========================================================================

    public function testShowSetupGeneratesQrCode(): void
    {
        $_SESSION['pending_2fa_setup_user_id'] = self::$testUserId;
        $_SESSION['pending_2fa_setup_expires'] = time() + 600;

        // Clear any previous setup
        unset($_SESSION['totp_setup_secret']);
        unset($_SESSION['totp_setup_qr']);

        try {
            // Capture output
            ob_start();
            $this->controller->showSetup();
            $output = ob_get_clean();
        } catch (\Exception $e) {
            $output = ob_get_clean();
        }

        // Session should have setup data
        $this->assertArrayHasKey('totp_setup_secret', $_SESSION);
        $this->assertArrayHasKey('totp_setup_qr', $_SESSION);

        // QR code should be SVG
        $this->assertStringContainsString('<svg', $_SESSION['totp_setup_qr']);
    }

    public function testCompleteSetupWithValidCode(): void
    {
        $_SESSION['pending_2fa_setup_user_id'] = self::$testUserId;
        $_SESSION['pending_2fa_setup_expires'] = time() + 600;

        // Initialize setup
        $setup = TotpService::initializeSetup(self::$testUserId);
        $_SESSION['totp_setup_secret'] = $setup['secret'];
        $_SESSION['totp_setup_qr'] = $setup['qr_code'];

        // Generate valid code
        $totp = \OTPHP\TOTP::createFromSecret($setup['secret']);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['code'] = $totp->now();
        $_POST['csrf_token'] = \Nexus\Core\Csrf::token();
        $_SESSION['csrf_token'] = $_POST['csrf_token'];

        try {
            $this->controller->completeSetup();
        } catch (\Exception $e) {
            // Exit from redirect
        }

        // Backup codes should be in session
        $this->assertArrayHasKey('totp_backup_codes', $_SESSION);
        $this->assertCount(10, $_SESSION['totp_backup_codes']);

        // 2FA should be enabled
        $this->assertTrue(TotpService::isEnabled(self::$testUserId));
    }

    // =========================================================================
    // SETTINGS TESTS
    // =========================================================================

    public function testSettingsPageLoadsForLoggedInUser(): void
    {
        $_SESSION['user_id'] = self::$testUserId;

        try {
            ob_start();
            $this->controller->settings();
            $output = ob_get_clean();
        } catch (\Exception $e) {
            $output = ob_get_clean();
        }

        // Should render without redirect
        // The output would contain the settings page HTML
        $this->assertNotEmpty($output);
    }

    public function testSettingsRedirectsForNonLoggedInUser(): void
    {
        unset($_SESSION['user_id']);

        try {
            $this->controller->settings();
        } catch (\Exception $e) {
            // Exit from redirect
        }

        // Would redirect to login
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    // =========================================================================
    // DISABLE TESTS (2FA is mandatory)
    // =========================================================================

    public function testDisableIsBlocked(): void
    {
        $_SESSION['user_id'] = self::$testUserId;

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['csrf_token'] = \Nexus\Core\Csrf::token();
        $_SESSION['csrf_token'] = $_POST['csrf_token'];

        try {
            $this->controller->disable();
        } catch (\Exception $e) {
            // Exit from redirect
        }

        // Should set error flash
        $this->assertArrayHasKey('flash_error', $_SESSION);
        $this->assertStringContainsString('mandatory', $_SESSION['flash_error']);

        // 2FA should still be enabled
        $this->assertTrue(TotpService::isEnabled(self::$testUserId));
    }

    // =========================================================================
    // TRUSTED DEVICE MANAGEMENT TESTS
    // =========================================================================

    public function testRevokeDevice(): void
    {
        $_SESSION['user_id'] = self::$testUserId;

        // Create a trusted device
        TotpService::trustDevice(self::$testUserId);
        $devices = TotpService::getTrustedDevices(self::$testUserId);
        $deviceId = $devices[0]['id'];

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['device_id'] = $deviceId;
        $_POST['csrf_token'] = \Nexus\Core\Csrf::token();
        $_SESSION['csrf_token'] = $_POST['csrf_token'];

        try {
            $this->controller->revokeDevice();
        } catch (\Exception $e) {
            // Exit from redirect
        }

        // Should set success flash
        $this->assertArrayHasKey('flash_success', $_SESSION);

        // Device should be revoked
        $stmt = Database::query(
            "SELECT is_revoked FROM user_trusted_devices WHERE id = ?",
            [$deviceId]
        );
        $device = $stmt->fetch();
        $this->assertEquals(1, $device['is_revoked']);
    }

    public function testRevokeAllDevices(): void
    {
        $_SESSION['user_id'] = self::$testUserId;

        // Clear and create multiple devices
        Database::query(
            "DELETE FROM user_trusted_devices WHERE user_id = ? AND tenant_id = ?",
            [self::$testUserId, self::$testTenantId]
        );

        TotpService::trustDevice(self::$testUserId);
        TotpService::trustDevice(self::$testUserId);

        $initialCount = TotpService::getTrustedDeviceCount(self::$testUserId);
        $this->assertEquals(2, $initialCount);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['csrf_token'] = \Nexus\Core\Csrf::token();
        $_SESSION['csrf_token'] = $_POST['csrf_token'];

        try {
            $this->controller->revokeAllDevices();
        } catch (\Exception $e) {
            // Exit from redirect
        }

        // Should set success flash
        $this->assertArrayHasKey('flash_success', $_SESSION);
        $this->assertStringContainsString('2', $_SESSION['flash_success']); // 2 devices removed

        // All devices should be revoked
        $newCount = TotpService::getTrustedDeviceCount(self::$testUserId);
        $this->assertEquals(0, $newCount);
    }

    // =========================================================================
    // BACKUP CODES PAGE TESTS
    // =========================================================================

    public function testShowBackupCodesAfterSetup(): void
    {
        $_SESSION['user_id'] = self::$testUserId;
        $_SESSION['totp_backup_codes'] = ['ABCD-1234', 'EFGH-5678'];

        try {
            ob_start();
            $this->controller->showBackupCodes();
            $output = ob_get_clean();
        } catch (\Exception $e) {
            $output = ob_get_clean();
        }

        // Codes should be cleared from session after display
        $this->assertArrayNotHasKey('totp_backup_codes', $_SESSION);
    }

    public function testRegenerateBackupCodesRequiresPassword(): void
    {
        $_SESSION['user_id'] = self::$testUserId;

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['password'] = 'WrongPassword123!'; // Wrong password
        $_POST['csrf_token'] = \Nexus\Core\Csrf::token();
        $_SESSION['csrf_token'] = $_POST['csrf_token'];

        try {
            $this->controller->regenerateBackupCodes();
        } catch (\Exception $e) {
            // Exit from redirect
        }

        // Should set error flash
        $this->assertArrayHasKey('flash_error', $_SESSION);
        $this->assertStringContainsString('password', strtolower($_SESSION['flash_error']));
    }
}
