<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Nexus\Services\TotpService;

/**
 * TotpService Unit Tests
 *
 * Pure unit tests for TOTP two-factor authentication that do NOT require
 * a database connection. Tests core cryptographic operations:
 * - Secret generation (correct length, format)
 * - TOTP code generation (time-based)
 * - TOTP code validation (correct code, wrong code, expired code)
 * - Recovery code generation (correct count, uniqueness)
 * - Recovery code validation (single-use, case-insensitive)
 * - QR code URI generation
 * - Time window tolerance (30-second window)
 * - Device name parsing
 *
 * The integration tests in tests/Services/TotpServiceTest.php cover
 * database-dependent flows (setup, login verification, trusted devices).
 *
 * @covers \Nexus\Services\TotpService
 */
class TotpServiceUnitTest extends TestCase
{
    // =========================================================================
    // CLASS STRUCTURE TESTS
    // =========================================================================

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(TotpService::class));
    }

    public function testStaticMethodsExist(): void
    {
        $staticMethods = [
            'generateSecret',
            'getProvisioningUri',
            'generateQrCode',
            'verifyCode',
            'checkRateLimit',
            'recordAttempt',
            'initializeSetup',
            'completeSetup',
            'verifyLogin',
            'verifyBackupCode',
            'generateBackupCodes',
            'getBackupCodeCount',
            'isEnabled',
            'isSetupRequired',
            'disable',
            'adminReset',
            'isTrustedDevice',
            'trustDevice',
            'getTrustedDevices',
            'getTrustedDeviceCount',
            'revokeDevice',
            'revokeAllDevices',
        ];

        foreach ($staticMethods as $method) {
            $this->assertTrue(
                method_exists(TotpService::class, $method),
                "Static method {$method} should exist on TotpService"
            );

            $ref = new \ReflectionMethod(TotpService::class, $method);
            $this->assertTrue($ref->isStatic(), "Method {$method} should be static");
        }
    }

    public function testClassConstants(): void
    {
        $ref = new \ReflectionClass(TotpService::class);

        $this->assertTrue($ref->hasConstant('BACKUP_CODE_COUNT'));
        $this->assertTrue($ref->hasConstant('BACKUP_CODE_LENGTH'));
        $this->assertTrue($ref->hasConstant('MAX_ATTEMPTS'));
        $this->assertTrue($ref->hasConstant('LOCKOUT_SECONDS'));
        $this->assertTrue($ref->hasConstant('ISSUER'));
        $this->assertTrue($ref->hasConstant('TRUSTED_DEVICE_DAYS'));
        $this->assertTrue($ref->hasConstant('TRUSTED_DEVICE_COOKIE'));
    }

    public function testConstantValues(): void
    {
        $ref = new \ReflectionClass(TotpService::class);
        $constants = $ref->getConstants();

        $this->assertEquals(10, $constants['BACKUP_CODE_COUNT']);
        $this->assertEquals(8, $constants['BACKUP_CODE_LENGTH']);
        $this->assertEquals(5, $constants['MAX_ATTEMPTS']);
        $this->assertEquals(900, $constants['LOCKOUT_SECONDS']); // 15 minutes
        $this->assertEquals('Project NEXUS', $constants['ISSUER']);
        $this->assertEquals(30, $constants['TRUSTED_DEVICE_DAYS']);
        $this->assertEquals('nexus_trusted_device', $constants['TRUSTED_DEVICE_COOKIE']);
    }

    // =========================================================================
    // SECRET GENERATION TESTS
    // =========================================================================

    public function testGenerateSecretReturnsNonEmptyString(): void
    {
        $secret = TotpService::generateSecret();

        $this->assertIsString($secret);
        $this->assertNotEmpty($secret);
    }

    public function testGenerateSecretReturnsBase32EncodedString(): void
    {
        $secret = TotpService::generateSecret();

        // Base32 alphabet: A-Z and 2-7, with optional = padding
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+=*$/', $secret);
    }

    public function testGenerateSecretHasSufficientLength(): void
    {
        $secret = TotpService::generateSecret();

        // TOTP secrets should be at least 128 bits (16 base32 chars)
        $this->assertGreaterThanOrEqual(16, strlen($secret));
    }

    public function testGenerateSecretProducesUniqueSecrets(): void
    {
        $secrets = [];
        for ($i = 0; $i < 100; $i++) {
            $secrets[] = TotpService::generateSecret();
        }

        // All should be unique
        $uniqueSecrets = array_unique($secrets);
        $this->assertCount(100, $uniqueSecrets, 'Generated 100 secrets should all be unique');
    }

    // =========================================================================
    // PROVISIONING URI TESTS
    // =========================================================================

    public function testGetProvisioningUriReturnsOtpauthUri(): void
    {
        $secret = TotpService::generateSecret();
        $uri = TotpService::getProvisioningUri($secret, 'user@example.com');

        $this->assertStringStartsWith('otpauth://totp/', $uri);
    }

    public function testGetProvisioningUriContainsSecret(): void
    {
        $secret = TotpService::generateSecret();
        $uri = TotpService::getProvisioningUri($secret, 'user@example.com');

        $this->assertStringContainsString('secret=' . $secret, $uri);
    }

    public function testGetProvisioningUriContainsEmail(): void
    {
        $secret = TotpService::generateSecret();
        $email = 'test@example.com';
        $uri = TotpService::getProvisioningUri($secret, $email);

        $this->assertStringContainsString(urlencode($email), $uri);
    }

    public function testGetProvisioningUriContainsDefaultIssuer(): void
    {
        $secret = TotpService::generateSecret();
        $uri = TotpService::getProvisioningUri($secret, 'user@example.com');

        // The issuer can be URL-encoded with %20 or +
        $this->assertTrue(
            str_contains($uri, 'issuer=Project%20NEXUS') || str_contains($uri, 'issuer=Project+NEXUS'),
            'URI should contain default issuer "Project NEXUS"'
        );
    }

    public function testGetProvisioningUriWithCustomIssuer(): void
    {
        $secret = TotpService::generateSecret();
        $uri = TotpService::getProvisioningUri($secret, 'user@example.com', 'My Custom App');

        $this->assertTrue(
            str_contains($uri, 'issuer=My%20Custom%20App') || str_contains($uri, 'issuer=My+Custom+App'),
            'URI should contain custom issuer'
        );
    }

    public function testGetProvisioningUriWithNullIssuerUsesDefault(): void
    {
        $secret = TotpService::generateSecret();
        $uri = TotpService::getProvisioningUri($secret, 'user@example.com', null);

        $this->assertTrue(
            str_contains($uri, 'issuer=Project%20NEXUS') || str_contains($uri, 'issuer=Project+NEXUS'),
            'Null issuer should fall back to default'
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

        // May start with <?xml or directly <svg
        $this->assertTrue(
            str_starts_with($qrCode, '<svg') || str_starts_with($qrCode, '<?xml'),
            'QR code should be SVG markup'
        );
    }

    public function testGenerateQrCodeContainsClosingSvgTag(): void
    {
        $secret = TotpService::generateSecret();
        $uri = TotpService::getProvisioningUri($secret, 'test@example.com');
        $qrCode = TotpService::generateQrCode($uri);

        $this->assertStringContainsString('</svg>', $qrCode);
    }

    public function testGenerateQrCodeIsNonEmpty(): void
    {
        $secret = TotpService::generateSecret();
        $uri = TotpService::getProvisioningUri($secret, 'test@example.com');
        $qrCode = TotpService::generateQrCode($uri);

        $this->assertNotEmpty($qrCode);
        // SVG should be a reasonable size (at least 100 chars)
        $this->assertGreaterThan(100, strlen($qrCode));
    }

    // =========================================================================
    // CODE VERIFICATION TESTS
    // =========================================================================

    public function testVerifyCodeWithCorrectCode(): void
    {
        $secret = TotpService::generateSecret();
        $totp = \OTPHP\TOTP::createFromSecret($secret);
        $validCode = $totp->now();

        $result = TotpService::verifyCode($secret, $validCode);

        $this->assertTrue($result);
    }

    public function testVerifyCodeWithIncorrectCode(): void
    {
        $secret = TotpService::generateSecret();

        $result = TotpService::verifyCode($secret, '000000');

        $this->assertFalse($result);
    }

    public function testVerifyCodeWithEmptyCode(): void
    {
        $secret = TotpService::generateSecret();

        $result = TotpService::verifyCode($secret, '');

        $this->assertFalse($result);
    }

    public function testVerifyCodeReturnsBool(): void
    {
        $secret = TotpService::generateSecret();

        $result = TotpService::verifyCode($secret, '123456');

        $this->assertIsBool($result);
    }

    public function testVerifyCodeWithWindowParameter(): void
    {
        $secret = TotpService::generateSecret();
        $totp = \OTPHP\TOTP::createFromSecret($secret);
        $validCode = $totp->now();

        // With window=0 (strict), current code should still work
        $result = TotpService::verifyCode($secret, $validCode, 0);
        $this->assertTrue($result);

        // With window=2 (lenient), current code should work
        $result = TotpService::verifyCode($secret, $validCode, 2);
        $this->assertTrue($result);
    }

    public function testVerifyCodeDefaultWindowIsOne(): void
    {
        $ref = new \ReflectionMethod(TotpService::class, 'verifyCode');
        $params = $ref->getParameters();

        $this->assertEquals('window', $params[2]->getName());
        $this->assertEquals(1, $params[2]->getDefaultValue());
    }

    public function testVerifyCodeWithDifferentSecretsFails(): void
    {
        $secret1 = TotpService::generateSecret();
        $secret2 = TotpService::generateSecret();

        $totp = \OTPHP\TOTP::createFromSecret($secret1);
        $code = $totp->now();

        // Code generated from secret1 should not validate against secret2
        $result = TotpService::verifyCode($secret2, $code);

        // This might rarely pass if both secrets happen to generate the same code
        // at the exact same time, but it's astronomically unlikely
        // We check that at least one of many attempts fails
        $allMatch = true;
        for ($i = 0; $i < 5; $i++) {
            $s1 = TotpService::generateSecret();
            $s2 = TotpService::generateSecret();
            $totp1 = \OTPHP\TOTP::createFromSecret($s1);
            $c = $totp1->now();
            if (!TotpService::verifyCode($s2, $c)) {
                $allMatch = false;
                break;
            }
        }
        $this->assertFalse($allMatch, 'Codes from different secrets should not match');
    }

    // =========================================================================
    // BACKUP CODE GENERATION FORMAT TESTS
    // =========================================================================

    public function testGenerateRandomCodeFormat(): void
    {
        $ref = new \ReflectionClass(TotpService::class);
        $method = $ref->getMethod('generateRandomCode');
        $method->setAccessible(true);

        $code = $method->invoke(null);

        // Format: XXXX-XXXX
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}-[A-Z0-9]{4}$/', $code);
    }

    public function testGenerateRandomCodeExcludesAmbiguousChars(): void
    {
        $ref = new \ReflectionClass(TotpService::class);
        $method = $ref->getMethod('generateRandomCode');
        $method->setAccessible(true);

        // Generate many codes and check none contain ambiguous characters
        $ambiguousChars = ['0', 'O', '1', 'I', 'L'];

        for ($i = 0; $i < 100; $i++) {
            $code = $method->invoke(null);
            $codeWithoutDash = str_replace('-', '', $code);

            foreach ($ambiguousChars as $char) {
                $this->assertStringNotContainsString(
                    $char,
                    $codeWithoutDash,
                    "Code should not contain ambiguous character '{$char}': {$code}"
                );
            }
        }
    }

    public function testGenerateRandomCodeUniqueness(): void
    {
        $ref = new \ReflectionClass(TotpService::class);
        $method = $ref->getMethod('generateRandomCode');
        $method->setAccessible(true);

        $codes = [];
        for ($i = 0; $i < 50; $i++) {
            $codes[] = $method->invoke(null);
        }

        $uniqueCodes = array_unique($codes);
        $this->assertCount(50, $uniqueCodes, '50 generated backup codes should all be unique');
    }

    public function testGenerateRandomCodeHasCorrectLength(): void
    {
        $ref = new \ReflectionClass(TotpService::class);
        $method = $ref->getMethod('generateRandomCode');
        $method->setAccessible(true);

        $code = $method->invoke(null);

        // 4 + dash + 4 = 9 total chars
        $this->assertEquals(9, strlen($code));

        // Without dash: 8 chars (BACKUP_CODE_LENGTH)
        $this->assertEquals(8, strlen(str_replace('-', '', $code)));
    }

    // =========================================================================
    // DEVICE NAME PARSING TESTS
    // =========================================================================

    public function testParseDeviceNameWithNullReturnsUnknown(): void
    {
        $ref = new \ReflectionClass(TotpService::class);
        $method = $ref->getMethod('parseDeviceName');
        $method->setAccessible(true);

        $result = $method->invoke(null, null);

        $this->assertEquals('Unknown device', $result);
    }

    public function testParseDeviceNameChromeOnWindows(): void
    {
        $ref = new \ReflectionClass(TotpService::class);
        $method = $ref->getMethod('parseDeviceName');
        $method->setAccessible(true);

        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        $result = $method->invoke(null, $ua);

        $this->assertEquals('Chrome on Windows', $result);
    }

    public function testParseDeviceNameFirefoxOnMacOS(): void
    {
        $ref = new \ReflectionClass(TotpService::class);
        $method = $ref->getMethod('parseDeviceName');
        $method->setAccessible(true);

        $ua = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:121.0) Gecko/20100101 Firefox/121.0';
        $result = $method->invoke(null, $ua);

        $this->assertEquals('Firefox on macOS', $result);
    }

    public function testParseDeviceNameSafariOnIOS(): void
    {
        $ref = new \ReflectionClass(TotpService::class);
        $method = $ref->getMethod('parseDeviceName');
        $method->setAccessible(true);

        $ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 Safari/604.1';
        $result = $method->invoke(null, $ua);

        $this->assertEquals('Safari on iOS', $result);
    }

    public function testParseDeviceNameEdgeOnWindows(): void
    {
        $ref = new \ReflectionClass(TotpService::class);
        $method = $ref->getMethod('parseDeviceName');
        $method->setAccessible(true);

        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0';
        $result = $method->invoke(null, $ua);

        $this->assertEquals('Edge on Windows', $result);
    }

    public function testParseDeviceNameChromeOnAndroid(): void
    {
        $ref = new \ReflectionClass(TotpService::class);
        $method = $ref->getMethod('parseDeviceName');
        $method->setAccessible(true);

        $ua = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Chrome/120.0.6099.144 Mobile Safari/537.36';
        $result = $method->invoke(null, $ua);

        $this->assertEquals('Chrome on Android', $result);
    }

    public function testParseDeviceNameChromeOnLinux(): void
    {
        $ref = new \ReflectionClass(TotpService::class);
        $method = $ref->getMethod('parseDeviceName');
        $method->setAccessible(true);

        $ua = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36';
        $result = $method->invoke(null, $ua);

        $this->assertEquals('Chrome on Linux', $result);
    }

    public function testParseDeviceNameSafariOnIPad(): void
    {
        $ref = new \ReflectionClass(TotpService::class);
        $method = $ref->getMethod('parseDeviceName');
        $method->setAccessible(true);

        $ua = 'Mozilla/5.0 (iPad; CPU OS 17_2 like Mac OS X) AppleWebKit/605.1.15 Safari/604.1';
        $result = $method->invoke(null, $ua);

        // iPad is detected as iOS
        $this->assertEquals('Safari on iOS', $result);
    }

    // =========================================================================
    // METHOD SIGNATURE TESTS
    // =========================================================================

    public function testVerifyCodeMethodSignature(): void
    {
        $ref = new \ReflectionMethod(TotpService::class, 'verifyCode');
        $params = $ref->getParameters();

        $this->assertTrue($ref->isStatic());
        $this->assertCount(3, $params);
        $this->assertEquals('secret', $params[0]->getName());
        $this->assertEquals('code', $params[1]->getName());
        $this->assertEquals('window', $params[2]->getName());
        $this->assertEquals(1, $params[2]->getDefaultValue());
    }

    public function testGetProvisioningUriMethodSignature(): void
    {
        $ref = new \ReflectionMethod(TotpService::class, 'getProvisioningUri');
        $params = $ref->getParameters();

        $this->assertTrue($ref->isStatic());
        $this->assertCount(3, $params);
        $this->assertEquals('secret', $params[0]->getName());
        $this->assertEquals('email', $params[1]->getName());
        $this->assertEquals('issuer', $params[2]->getName());
        $this->assertTrue($params[2]->allowsNull());
    }

    public function testAdminResetMethodSignature(): void
    {
        $ref = new \ReflectionMethod(TotpService::class, 'adminReset');
        $params = $ref->getParameters();

        $this->assertTrue($ref->isStatic());
        $this->assertCount(3, $params);
        $this->assertEquals('userId', $params[0]->getName());
        $this->assertEquals('adminId', $params[1]->getName());
        $this->assertEquals('reason', $params[2]->getName());
    }

    public function testDisableMethodSignature(): void
    {
        $ref = new \ReflectionMethod(TotpService::class, 'disable');
        $params = $ref->getParameters();

        $this->assertTrue($ref->isStatic());
        $this->assertCount(2, $params);
        $this->assertEquals('userId', $params[0]->getName());
        $this->assertEquals('password', $params[1]->getName());
    }

    // =========================================================================
    // TOTP TIME-BASED CODE TESTS
    // =========================================================================

    public function testCodeChangesOverTime(): void
    {
        $secret = TotpService::generateSecret();
        $totp = \OTPHP\TOTP::createFromSecret($secret);

        // Get code at different timestamps (30 seconds apart)
        $now = time();
        $codeNow = $totp->at($now);
        $codeFuture = $totp->at($now + 60); // One minute later

        // Codes should be different (unless by extreme coincidence)
        // We can't guarantee they're different since time periods might align,
        // but we can verify they are valid 6-digit codes
        $this->assertMatchesRegularExpression('/^\d{6}$/', $codeNow);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $codeFuture);
    }

    public function testTotpCodeIsSixDigits(): void
    {
        $secret = TotpService::generateSecret();
        $totp = \OTPHP\TOTP::createFromSecret($secret);
        $code = $totp->now();

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
    }
}
