<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\TokenService;

class TokenServiceTest extends TestCase
{
    protected function setUp(): void
    {
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
    }

    public function testGenerateTokenReturnsJWTFormat(): void
    {
        $token = TokenService::generateToken(1, 1, [], false);

        $parts = explode('.', $token);
        $this->assertCount(3, $parts, 'Token should have 3 parts: header.payload.signature');
    }

    public function testValidateTokenReturnsPayload(): void
    {
        $token = TokenService::generateToken(42, 5, [], false);
        $payload = TokenService::validateToken($token);

        $this->assertNotNull($payload);
        $this->assertEquals(42, $payload['user_id']);
        $this->assertEquals(5, $payload['tenant_id']);
        $this->assertEquals('access', $payload['type']);
    }

    public function testValidateTokenReturnsNullForInvalidToken(): void
    {
        $result = TokenService::validateToken('invalid.token.here');
        $this->assertNull($result);
    }

    public function testValidateTokenReturnsNullForTamperedToken(): void
    {
        $token = TokenService::generateToken(1, 1, [], false);
        // Tamper with the payload
        $parts = explode('.', $token);
        $parts[1] = base64_encode('{"user_id":999,"tenant_id":1,"type":"access","iat":' . time() . ',"exp":' . (time() + 3600) . '}');
        $tampered = implode('.', $parts);

        $result = TokenService::validateToken($tampered);
        $this->assertNull($result, 'Tampered token should fail validation');
    }

    public function testValidateTokenReturnsNullForMalformedToken(): void
    {
        $this->assertNull(TokenService::validateToken('not-a-jwt'));
        $this->assertNull(TokenService::validateToken('only.two'));
        $this->assertNull(TokenService::validateToken(''));
    }

    public function testGenerateRefreshTokenHasCorrectType(): void
    {
        $token = TokenService::generateRefreshToken(1, 1, false);
        $payload = TokenService::validateToken($token);

        $this->assertNotNull($payload);
        $this->assertEquals('refresh', $payload['type']);
        $this->assertArrayHasKey('jti', $payload);
        $this->assertNotEmpty($payload['jti']);
    }

    public function testValidateRefreshTokenRejectsAccessToken(): void
    {
        $accessToken = TokenService::generateToken(1, 1, [], false);
        $result = TokenService::validateRefreshToken($accessToken);

        $this->assertNull($result, 'validateRefreshToken should reject access tokens');
    }

    public function testIsExpiredReturnsFalseForFreshToken(): void
    {
        $token = TokenService::generateToken(1, 1, [], false);
        $this->assertFalse(TokenService::isExpired($token));
    }

    public function testIsExpiredReturnsTrueForMalformedToken(): void
    {
        $this->assertTrue(TokenService::isExpired('not-a-jwt'));
    }

    public function testGetExpirationReturnsTimestamp(): void
    {
        $token = TokenService::generateToken(1, 1, [], false);
        $exp = TokenService::getExpiration($token);

        $this->assertIsInt($exp);
        $this->assertGreaterThan(time(), $exp);
    }

    public function testGetExpirationReturnsNullForInvalidToken(): void
    {
        $this->assertNull(TokenService::getExpiration('invalid'));
    }

    public function testGetTimeRemainingReturnsPositiveForFreshToken(): void
    {
        $token = TokenService::generateToken(1, 1, [], false);
        $remaining = TokenService::getTimeRemaining($token);

        $this->assertGreaterThan(0, $remaining);
    }

    public function testGetTimeRemainingReturnsNegativeForInvalidToken(): void
    {
        $this->assertEquals(-1, TokenService::getTimeRemaining('invalid'));
    }

    public function testNeedsRefreshReturnsFalseForFreshToken(): void
    {
        $token = TokenService::generateToken(1, 1, [], false);
        $this->assertFalse(TokenService::needsRefresh($token));
    }

    public function testGetUserIdFromTokenReturnsCorrectId(): void
    {
        $token = TokenService::generateToken(42, 1, [], false);
        $this->assertEquals(42, TokenService::getUserIdFromToken($token));
    }

    public function testGetUserIdFromTokenReturnsNullForInvalid(): void
    {
        $this->assertNull(TokenService::getUserIdFromToken('invalid'));
    }

    public function testIsMobileRequestDetectsCapacitor(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Capacitor App';
        $this->assertTrue(TokenService::isMobileRequest());
    }

    public function testIsMobileRequestDetectsAndroid(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Linux; Android 12) AppleWebKit/537.36';
        $this->assertTrue(TokenService::isMobileRequest());
    }

    public function testIsMobileRequestDetectsIPhone(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0)';
        $this->assertTrue(TokenService::isMobileRequest());
    }

    public function testIsMobileRequestReturnsFalseForDesktop(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)';
        $this->assertFalse(TokenService::isMobileRequest());
    }

    public function testIsMobileRequestDetectsCapacitorHeader(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0)';
        $_SERVER['HTTP_X_CAPACITOR_APP'] = '1';
        $this->assertTrue(TokenService::isMobileRequest());
    }

    public function testGetAccessTokenExpiryWebVsMobile(): void
    {
        $webExpiry = TokenService::getAccessTokenExpiry(false);
        $mobileExpiry = TokenService::getAccessTokenExpiry(true);

        $this->assertEquals(7200, $webExpiry, 'Web token should expire in 2 hours');
        $this->assertEquals(31536000, $mobileExpiry, 'Mobile token should expire in 1 year');
        $this->assertGreaterThan($webExpiry, $mobileExpiry);
    }

    public function testGetRefreshTokenExpiryWebVsMobile(): void
    {
        $webExpiry = TokenService::getRefreshTokenExpiry(false);
        $mobileExpiry = TokenService::getRefreshTokenExpiry(true);

        $this->assertGreaterThan($webExpiry, $mobileExpiry);
    }

    public function testGenerateTokenIncludesPlatformClaim(): void
    {
        $webToken = TokenService::generateToken(1, 1, [], false);
        $webPayload = TokenService::validateToken($webToken);
        $this->assertEquals('web', $webPayload['platform']);

        $mobileToken = TokenService::generateToken(1, 1, [], true);
        $mobilePayload = TokenService::validateToken($mobileToken);
        $this->assertEquals('mobile', $mobilePayload['platform']);
    }

    public function testGenerateTokenIncludesAdditionalClaims(): void
    {
        $token = TokenService::generateToken(1, 1, ['role' => 'admin', 'custom' => 'value'], false);
        $payload = TokenService::validateToken($token);

        $this->assertEquals('admin', $payload['role']);
        $this->assertEquals('value', $payload['custom']);
    }

    public function testTokenContainsStandardJWTClaims(): void
    {
        $token = TokenService::generateToken(1, 1, [], false);
        $payload = TokenService::validateToken($token);

        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('exp', $payload);
        $this->assertArrayHasKey('nbf', $payload);
        $this->assertLessThanOrEqual(time(), $payload['iat']);
        $this->assertLessThanOrEqual(time(), $payload['nbf']);
    }
}
