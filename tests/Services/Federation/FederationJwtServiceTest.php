<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Services\Federation;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\FederationJwtService;

/**
 * FederationJwtService Tests
 *
 * Tests JWT token generation, validation, and encoding for the federation API.
 */
class FederationJwtServiceTest extends DatabaseTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        TenantContext::setById(2);
    }

    // ==========================================
    // Token Generation Tests
    // ==========================================

    public function testGenerateTokenReturnsNullForUnknownPlatform(): void
    {
        $result = FederationJwtService::generateToken(
            'nonexistent-platform-id',
            '123',
            2,
            ['read'],
            3600
        );

        $this->assertNull($result);
    }

    public function testGenerateTokenWithEmptyScopes(): void
    {
        $result = FederationJwtService::generateToken(
            'nonexistent-platform-id',
            '123',
            2,
            [],
            3600
        );

        // Should return null because the platform doesn't exist
        $this->assertNull($result);
    }

    // ==========================================
    // Token Validation Tests
    // ==========================================

    public function testValidateTokenReturnsNullForInvalidFormat(): void
    {
        $result = FederationJwtService::validateToken('not-a-valid-jwt');

        $this->assertNull($result);
    }

    public function testValidateTokenReturnsNullForEmptyString(): void
    {
        $result = FederationJwtService::validateToken('');

        $this->assertNull($result);
    }

    public function testValidateTokenReturnsNullForTwoParts(): void
    {
        $result = FederationJwtService::validateToken('header.payload');

        $this->assertNull($result);
    }

    public function testValidateTokenReturnsNullForFourParts(): void
    {
        $result = FederationJwtService::validateToken('a.b.c.d');

        $this->assertNull($result);
    }

    public function testValidateTokenReturnsNullForExpiredToken(): void
    {
        // Create a token-like string with expired exp claim
        $header = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload = base64_encode(json_encode([
            'iss' => 'test-platform',
            'sub' => '123',
            'exp' => time() - 3600, // Expired 1 hour ago
        ]));
        $signature = base64_encode('fake-signature');

        // Replace + with -, / with _, and remove =
        $header = rtrim(strtr($header, '+/', '-_'), '=');
        $payload = rtrim(strtr($payload, '+/', '-_'), '=');
        $signature = rtrim(strtr($signature, '+/', '-_'), '=');

        $token = "{$header}.{$payload}.{$signature}";

        $result = FederationJwtService::validateToken($token);
        $this->assertNull($result);
    }

    public function testValidateTokenReturnsNullForMissingRequiredClaims(): void
    {
        // Token with missing 'sub' claim
        $header = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload = base64_encode(json_encode([
            'iss' => 'test-platform',
            // No 'sub' claim
            'exp' => time() + 3600,
        ]));
        $signature = base64_encode('fake-signature');

        $header = rtrim(strtr($header, '+/', '-_'), '=');
        $payload = rtrim(strtr($payload, '+/', '-_'), '=');
        $signature = rtrim(strtr($signature, '+/', '-_'), '=');

        $token = "{$header}.{$payload}.{$signature}";

        $result = FederationJwtService::validateToken($token);
        $this->assertNull($result);
    }

    public function testValidateTokenReturnsNullForUnsupportedAlgorithm(): void
    {
        $header = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'none']));
        $payload = base64_encode(json_encode([
            'iss' => 'test-platform',
            'sub' => '123',
            'exp' => time() + 3600,
        ]));
        $signature = base64_encode('');

        $header = rtrim(strtr($header, '+/', '-_'), '=');
        $payload = rtrim(strtr($payload, '+/', '-_'), '=');
        $signature = rtrim(strtr($signature, '+/', '-_'), '=');

        $token = "{$header}.{$payload}.{$signature}";

        $result = FederationJwtService::validateToken($token);
        $this->assertNull($result);
    }

    public function testValidateTokenReturnsNullForInvalidHeader(): void
    {
        $header = rtrim(strtr(base64_encode('not-json'), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode(['iss' => 'test'])), '+/', '-_'), '=');
        $signature = rtrim(strtr(base64_encode('sig'), '+/', '-_'), '=');

        $result = FederationJwtService::validateToken("{$header}.{$payload}.{$signature}");
        $this->assertNull($result);
    }

    // ==========================================
    // handleTokenRequest Tests
    // ==========================================

    public function testHandleTokenRequestReturnsErrorForUnsupportedGrantType(): void
    {
        // Save original POST and clear
        $originalPost = $_POST;
        $_POST = ['grant_type' => 'unsupported_type'];

        $result = FederationJwtService::handleTokenRequest();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('unsupported_grant_type', $result['error']);

        $_POST = $originalPost;
    }

    public function testHandleTokenRequestReturnsErrorForMissingGrantType(): void
    {
        $originalPost = $_POST;
        $_POST = [];

        $result = FederationJwtService::handleTokenRequest();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('unsupported_grant_type', $result['error']);

        $_POST = $originalPost;
    }

    public function testHandleTokenRequestRefreshTokenNotSupported(): void
    {
        $originalPost = $_POST;
        $_POST = ['grant_type' => 'refresh_token'];

        $result = FederationJwtService::handleTokenRequest();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('unsupported_grant_type', $result['error']);

        $_POST = $originalPost;
    }

    public function testHandleTokenRequestClientCredentialsRequiresAuth(): void
    {
        $originalPost = $_POST;
        $originalServer = $_SERVER;

        $_POST = ['grant_type' => 'client_credentials'];
        // Remove auth headers
        unset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);

        $result = FederationJwtService::handleTokenRequest();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('invalid_client', $result['error']);

        $_POST = $originalPost;
        $_SERVER = $originalServer;
    }

    // ==========================================
    // Base64 URL Encoding Tests (via reflection)
    // ==========================================

    public function testBase64UrlEncodeDecode(): void
    {
        $reflection = new \ReflectionClass(FederationJwtService::class);

        $encode = $reflection->getMethod('base64UrlEncode');
        $encode->setAccessible(true);

        $decode = $reflection->getMethod('base64UrlDecode');
        $decode->setAccessible(true);

        $testData = 'Hello, World! This is a test string with special chars: +/=';

        $encoded = $encode->invoke(null, $testData);
        $decoded = $decode->invoke(null, $encoded);

        $this->assertEquals($testData, $decoded);
        // URL-safe encoding should not contain +, /, or =
        $this->assertStringNotContainsString('+', $encoded);
        $this->assertStringNotContainsString('/', $encoded);
        $this->assertStringNotContainsString('=', $encoded);
    }

    // ==========================================
    // Constants Tests
    // ==========================================

    public function testSupportedAlgorithmsIncludesHS256(): void
    {
        $reflection = new \ReflectionClass(FederationJwtService::class);
        $constant = $reflection->getConstant('SUPPORTED_ALGORITHMS');

        $this->assertIsArray($constant);
        $this->assertContains('HS256', $constant);
    }

    public function testSupportedAlgorithmsIncludesRS256(): void
    {
        $reflection = new \ReflectionClass(FederationJwtService::class);
        $constant = $reflection->getConstant('SUPPORTED_ALGORITHMS');

        $this->assertContains('RS256', $constant);
    }

    public function testDefaultTokenLifetime(): void
    {
        $reflection = new \ReflectionClass(FederationJwtService::class);
        $constant = $reflection->getConstant('DEFAULT_TOKEN_LIFETIME');

        $this->assertEquals(3600, $constant);
    }

    public function testMaxTokenLifetime(): void
    {
        $reflection = new \ReflectionClass(FederationJwtService::class);
        $constant = $reflection->getConstant('MAX_TOKEN_LIFETIME');

        $this->assertEquals(86400, $constant);
    }
}
