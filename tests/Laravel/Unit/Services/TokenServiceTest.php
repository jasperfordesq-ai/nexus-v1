<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\TokenService;

class TokenServiceTest extends TestCase
{
    private TokenService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:' . base64_encode(str_repeat('a', 32))]);
        $this->service = new TokenService();
    }

    public function test_generateToken_returns_jwt_format(): void
    {
        $token = $this->service->generateToken(1, 2, [], false);

        $parts = explode('.', $token);
        $this->assertCount(3, $parts);
    }

    public function test_validateToken_returns_payload_for_valid_token(): void
    {
        $token = $this->service->generateToken(1, 2, [], false);

        $payload = $this->service->validateToken($token);

        $this->assertNotNull($payload);
        $this->assertEquals(1, $payload['user_id']);
        $this->assertEquals(2, $payload['tenant_id']);
        $this->assertEquals('access', $payload['type']);
    }

    public function test_validateToken_returns_null_for_invalid_token(): void
    {
        $this->assertNull($this->service->validateToken('invalid.token.here'));
    }

    public function test_validateToken_returns_null_for_malformed_token(): void
    {
        $this->assertNull($this->service->validateToken('not-a-jwt'));
    }

    public function test_getAccessTokenExpiry_returns_web_expiry(): void
    {
        $expiry = $this->service->getAccessTokenExpiry(false);
        $this->assertEquals(7200, $expiry);
    }

    public function test_getAccessTokenExpiry_returns_mobile_expiry(): void
    {
        $expiry = $this->service->getAccessTokenExpiry(true);
        $this->assertEquals(2592000, $expiry);
    }

    public function test_getRefreshTokenExpiry_returns_web_expiry(): void
    {
        $expiry = $this->service->getRefreshTokenExpiry(false);
        $this->assertEquals(63072000, $expiry);
    }

    public function test_getRefreshTokenExpiry_returns_mobile_expiry(): void
    {
        $expiry = $this->service->getRefreshTokenExpiry(true);
        $this->assertEquals(157680000, $expiry);
    }

    public function test_isExpired_returns_false_for_fresh_token(): void
    {
        $token = $this->service->generateToken(1, 2, [], false);

        $this->assertFalse($this->service->isExpired($token));
    }

    public function test_isExpired_returns_true_for_malformed(): void
    {
        $this->assertTrue($this->service->isExpired('bad-token'));
    }

    public function test_getUserIdFromToken_extracts_user_id(): void
    {
        $token = $this->service->generateToken(42, 2, [], false);

        $this->assertEquals(42, $this->service->getUserIdFromToken($token));
    }

    public function test_getUserIdFromToken_returns_null_for_invalid(): void
    {
        $this->assertNull($this->service->getUserIdFromToken('bad'));
    }

    public function test_getExpiration_returns_timestamp(): void
    {
        $token = $this->service->generateToken(1, 2, [], false);

        $exp = $this->service->getExpiration($token);
        $this->assertIsInt($exp);
        $this->assertGreaterThan(time(), $exp);
    }

    public function test_needsRefresh_returns_false_for_fresh_token(): void
    {
        $token = $this->service->generateToken(1, 2, [], false);

        $this->assertFalse($this->service->needsRefresh($token));
    }

    public function test_generateRefreshToken_has_refresh_type(): void
    {
        $token = $this->service->generateRefreshToken(1, 2, false);
        $payload = $this->service->validateToken($token);

        $this->assertEquals('refresh', $payload['type']);
        $this->assertNotEmpty($payload['jti']);
    }

    public function test_validateRefreshToken_rejects_access_token(): void
    {
        $token = $this->service->generateToken(1, 2, [], false);

        $this->assertNull($this->service->validateRefreshToken($token));
    }

    public function test_generateImpersonationToken_has_correct_claims(): void
    {
        $token = $this->service->generateImpersonationToken(1, 2, 99);
        $payload = $this->service->validateToken($token);

        $this->assertEquals('impersonation', $payload['type']);
        $this->assertEquals(99, $payload['impersonated_by']);
        $this->assertEquals(1, $payload['user_id']);
    }

    public function test_getTimeRemaining_returns_positive_for_fresh_token(): void
    {
        $token = $this->service->generateToken(1, 2, [], false);

        $remaining = $this->service->getTimeRemaining($token);
        $this->assertGreaterThan(0, $remaining);
    }

    public function test_getTimeRemaining_returns_negative_one_for_invalid(): void
    {
        $this->assertEquals(-1, $this->service->getTimeRemaining('bad'));
    }
}
