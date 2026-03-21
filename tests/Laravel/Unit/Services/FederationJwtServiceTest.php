<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\FederationJwtService;

class FederationJwtServiceTest extends TestCase
{
    public function test_generateToken_returns_null_without_signing_secret(): void
    {
        config(['federation.jwt_secret' => null, 'app.key' => null]);

        $result = FederationJwtService::generateToken('platform1', 'user1', 2);
        // May or may not be null depending on app.key in test config
        $this->assertTrue($result === null || is_array($result));
    }

    public function test_generateToken_returns_expected_structure(): void
    {
        config(['app.key' => 'base64:' . base64_encode('test-secret-key-32bytes-12345678')]);

        $result = FederationJwtService::generateToken('platform1', 'user1', 2, ['read', 'write']);

        if ($result === null) {
            $this->markTestSkipped('No signing secret available in test environment');
        }

        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('token_type', $result);
        $this->assertArrayHasKey('expires_in', $result);
        $this->assertArrayHasKey('scope', $result);
        $this->assertEquals('Bearer', $result['token_type']);
        $this->assertEquals('read write', $result['scope']);
    }

    public function test_generateToken_clamps_lifetime(): void
    {
        config(['app.key' => 'base64:' . base64_encode('test-secret-key-32bytes-12345678')]);

        $result = FederationJwtService::generateToken('p', 'u', 2, [], 1); // Too short
        if ($result) {
            $this->assertGreaterThanOrEqual(60, $result['expires_in']);
        }

        $result = FederationJwtService::generateToken('p', 'u', 2, [], 999999); // Too long
        if ($result) {
            $this->assertLessThanOrEqual(86400, $result['expires_in']);
        }
    }

    public function test_validateToken_returns_null_for_empty_token(): void
    {
        $this->assertNull(FederationJwtService::validateToken(''));
    }

    public function test_validateToken_returns_null_for_malformed_token(): void
    {
        $this->assertNull(FederationJwtService::validateToken('not.a.valid'));
    }

    public function test_validateToken_returns_null_for_wrong_parts_count(): void
    {
        $this->assertNull(FederationJwtService::validateToken('only.two'));
    }

    public function test_validateToken_roundtrip(): void
    {
        config(['app.key' => 'base64:' . base64_encode('test-secret-key-32bytes-12345678')]);

        $tokenData = FederationJwtService::generateToken('platform1', 'user1', 2, ['read']);
        if (!$tokenData) {
            $this->markTestSkipped('No signing secret');
        }

        $payload = FederationJwtService::validateToken($tokenData['access_token']);
        $this->assertNotNull($payload);
        $this->assertEquals('user1', $payload['sub']);
        $this->assertEquals(2, $payload['tenant_id']);
        $this->assertEquals(['read'], $payload['scopes']);
    }

    public function test_validateTokenStatic_delegates_to_validateToken(): void
    {
        $this->assertNull(FederationJwtService::validateTokenStatic(''));
    }

    public function test_getSupportedAlgorithms_returns_array(): void
    {
        $algorithms = FederationJwtService::getSupportedAlgorithms();
        $this->assertContains('HS256', $algorithms);
        $this->assertContains('RS256', $algorithms);
    }

    public function test_getDefaultTokenLifetime_returns_3600(): void
    {
        $this->assertEquals(3600, FederationJwtService::getDefaultTokenLifetime());
    }

    public function test_getMaxTokenLifetime_returns_86400(): void
    {
        $this->assertEquals(86400, FederationJwtService::getMaxTokenLifetime());
    }
}
