<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Tests\Services;

use Nexus\Tests\TestCase;
use App\Services\TokenService;

class TokenServiceTest extends TestCase
{
    private static function svc(): TokenService
    {
        return new TokenService();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Set APP_KEY for token signing
        $_ENV['APP_KEY'] = 'test-app-key-for-unit-testing-only';
        putenv('APP_KEY=test-app-key-for-unit-testing-only');
    }

    protected function tearDown(): void
    {
        unset($_ENV['APP_KEY']);
        putenv('APP_KEY');
        unset($_SERVER['HTTP_USER_AGENT']);
        unset($_SERVER['HTTP_X_CAPACITOR_APP']);
        unset($_SERVER['HTTP_X_NEXUS_MOBILE']);
        parent::tearDown();
    }

    public function testGenerateTokenReturnsJWTFormat(): void
    {
        $token = self::svc()->generateToken(1, 1, [], false);

        $parts = explode('.', $token);
        $this->assertCount(3, $parts, 'Token should have 3 parts: header.payload.signature');
    }

    public function testValidateTokenReturnsPayload(): void
    {
        $token = self::svc()->generateToken(42, 5, [], false);
        $payload = self::svc()->validateToken($token);

        $this->assertNotNull($payload);
        $this->assertEquals(42, $payload['user_id']);
        $this->assertEquals(5, $payload['tenant_id']);
        $this->assertEquals('access', $payload['type']);
    }

    public function testValidateTokenReturnsNullForInvalidToken(): void
    {
        $result = self::svc()->validateToken('invalid.token.here');
        $this->assertNull($result);
    }

    public function testValidateTokenReturnsNullForTamperedToken(): void
    {
        $token = self::svc()->generateToken(1, 1, [], false);
        // Tamper with the payload
        $parts = explode('.', $token);
        $parts[1] = base64_encode('{"user_id":999,"tenant_id":1,"type":"access","iat":' . time() . ',"exp":' . (time() + 3600) . '}');
        $tampered = implode('.', $parts);

        $result = self::svc()->validateToken($tampered);
        $this->assertNull($result, 'Tampered token should fail validation');
    }

    public function testValidateTokenReturnsNullForMalformedToken(): void
    {
        $this->assertNull(self::svc()->validateToken('not-a-jwt'));
        $this->assertNull(self::svc()->validateToken('only.two'));
        $this->assertNull(self::svc()->validateToken(''));
    }

    public function testGenerateRefreshTokenHasCorrectType(): void
    {
        $token = self::svc()->generateRefreshToken(1, 1, false);
        $payload = self::svc()->validateToken($token);

        $this->assertNotNull($payload);
        $this->assertEquals('refresh', $payload['type']);
        $this->assertArrayHasKey('jti', $payload);
        $this->assertNotEmpty($payload['jti']);
    }

    public function testValidateRefreshTokenRejectsAccessToken(): void
    {
        $accessToken = self::svc()->generateToken(1, 1, [], false);
        $result = self::svc()->validateRefreshToken($accessToken);

        $this->assertNull($result, 'validateRefreshToken should reject access tokens');
    }

    public function testIsExpiredReturnsFalseForFreshToken(): void
    {
        $token = self::svc()->generateToken(1, 1, [], false);
        $this->assertFalse(self::svc()->isExpired($token));
    }

    public function testIsExpiredReturnsTrueForMalformedToken(): void
    {
        $this->assertTrue(self::svc()->isExpired('not-a-jwt'));
    }

    public function testGetExpirationReturnsTimestamp(): void
    {
        $token = self::svc()->generateToken(1, 1, [], false);
        $exp = self::svc()->getExpiration($token);

        $this->assertIsInt($exp);
        $this->assertGreaterThan(time(), $exp);
    }

    public function testGetExpirationReturnsNullForInvalidToken(): void
    {
        $this->assertNull(self::svc()->getExpiration('invalid'));
    }

    public function testGetTimeRemainingReturnsPositiveForFreshToken(): void
    {
        $token = self::svc()->generateToken(1, 1, [], false);
        $remaining = self::svc()->getTimeRemaining($token);

        $this->assertGreaterThan(0, $remaining);
    }

    public function testGetTimeRemainingReturnsNegativeForInvalidToken(): void
    {
        $this->assertEquals(-1, self::svc()->getTimeRemaining('invalid'));
    }

    public function testNeedsRefreshReturnsFalseForFreshToken(): void
    {
        $token = self::svc()->generateToken(1, 1, [], false);
        $this->assertFalse(self::svc()->needsRefresh($token));
    }

    public function testGetUserIdFromTokenReturnsCorrectId(): void
    {
        $token = self::svc()->generateToken(42, 1, [], false);
        $this->assertEquals(42, self::svc()->getUserIdFromToken($token));
    }

    public function testGetUserIdFromTokenReturnsNullForInvalid(): void
    {
        $this->assertNull(self::svc()->getUserIdFromToken('invalid'));
    }

    public function testIsMobileRequestDetectsCapacitor(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Capacitor App';
        $this->assertTrue(self::svc()->isMobileRequest());
    }

    public function testIsMobileRequestDetectsAndroid(): void
    {
        // Generic Android UA is no longer trusted (spoofable) — only Capacitor UAs are
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Linux; Android 12) AppleWebKit/537.36';
        $this->assertFalse(self::svc()->isMobileRequest());
    }

    public function testIsMobileRequestDetectsIPhone(): void
    {
        // Generic iPhone UA is no longer trusted (spoofable) — only Capacitor UAs are
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0)';
        $this->assertFalse(self::svc()->isMobileRequest());
    }

    public function testIsMobileRequestReturnsFalseForDesktop(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)';
        $this->assertFalse(self::svc()->isMobileRequest());
    }

    public function testIsMobileRequestDetectsCapacitorHeader(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0)';
        $_SERVER['HTTP_X_CAPACITOR_APP'] = '1';
        $this->assertTrue(self::svc()->isMobileRequest());
    }

    public function testGetAccessTokenExpiryWebVsMobile(): void
    {
        $webExpiry = self::svc()->getAccessTokenExpiry(false);
        $mobileExpiry = self::svc()->getAccessTokenExpiry(true);

        $this->assertEquals(7200, $webExpiry, 'Web token should expire in 2 hours');
        $this->assertEquals(2592000, $mobileExpiry, 'Mobile token should expire in 30 days');
        $this->assertGreaterThan($webExpiry, $mobileExpiry);
    }

    public function testGetRefreshTokenExpiryWebVsMobile(): void
    {
        $webExpiry = self::svc()->getRefreshTokenExpiry(false);
        $mobileExpiry = self::svc()->getRefreshTokenExpiry(true);

        $this->assertGreaterThan($webExpiry, $mobileExpiry);
    }

    public function testGenerateTokenIncludesPlatformClaim(): void
    {
        $webToken = self::svc()->generateToken(1, 1, [], false);
        $webPayload = self::svc()->validateToken($webToken);
        $this->assertEquals('web', $webPayload['platform']);

        $mobileToken = self::svc()->generateToken(1, 1, [], true);
        $mobilePayload = self::svc()->validateToken($mobileToken);
        $this->assertEquals('mobile', $mobilePayload['platform']);
    }

    public function testGenerateTokenIncludesAdditionalClaims(): void
    {
        $token = self::svc()->generateToken(1, 1, ['role' => 'admin', 'custom' => 'value'], false);
        $payload = self::svc()->validateToken($token);

        $this->assertEquals('admin', $payload['role']);
        $this->assertEquals('value', $payload['custom']);
    }

    public function testTokenContainsStandardJWTClaims(): void
    {
        $token = self::svc()->generateToken(1, 1, [], false);
        $payload = self::svc()->validateToken($token);

        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('exp', $payload);
        $this->assertArrayHasKey('nbf', $payload);
        $this->assertLessThanOrEqual(time(), $payload['iat']);
        $this->assertLessThanOrEqual(time(), $payload['nbf']);
    }
}
