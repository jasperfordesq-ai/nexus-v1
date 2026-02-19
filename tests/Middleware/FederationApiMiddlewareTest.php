<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Middleware;

use Nexus\Tests\TestCase;
use Nexus\Middleware\FederationApiMiddleware;
use ReflectionClass;
use ReflectionMethod;

/**
 * FederationApiMiddlewareTest
 *
 * Tests the Federation API authentication middleware which handles:
 * - API key authentication (Bearer / X-API-Key headers)
 * - HMAC-SHA256 request signing
 * - JWT token authentication
 * - Rate limiting
 * - Permission checking
 *
 * SECURITY: These tests verify that federation partners are properly authenticated
 * and that replay attacks, invalid signatures, and permission bypasses are blocked.
 */
class FederationApiMiddlewareTest extends TestCase
{
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reflection = new ReflectionClass(FederationApiMiddleware::class);

        // Reset static state between tests
        $partnerProp = $this->reflection->getProperty('authenticatedPartner');
        $partnerProp->setAccessible(true);
        $partnerProp->setValue(null, null);

        $authMethodProp = $this->reflection->getProperty('authMethod');
        $authMethodProp->setAccessible(true);
        $authMethodProp->setValue(null, null);
    }

    protected function tearDown(): void
    {
        // Clean up $_SERVER superglobals
        unset($_SERVER['HTTP_AUTHORIZATION']);
        unset($_SERVER['HTTP_X_API_KEY']);
        unset($_SERVER['HTTP_X_FEDERATION_SIGNATURE']);
        unset($_SERVER['HTTP_X_FEDERATION_TIMESTAMP']);
        unset($_SERVER['HTTP_X_FEDERATION_PLATFORM_ID']);
        unset($_SERVER['HTTP_X_FEDERATION_TENANT_ID']);
        unset($_SERVER['REQUEST_METHOD']);
        unset($_SERVER['REQUEST_URI']);

        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // extractApiKey() tests
    // -----------------------------------------------------------------------

    /**
     * Test extractApiKey() extracts key from Bearer authorization header.
     */
    public function testExtractApiKeyWithBearerHeader(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer my-secret-api-key-12345';

        $method = $this->getPrivateStaticMethod('extractApiKey');
        $result = $method->invoke(null);

        $this->assertEquals('my-secret-api-key-12345', $result);
    }

    /**
     * Test extractApiKey() is case-insensitive for "Bearer" prefix.
     */
    public function testExtractApiKeyBearerIsCaseInsensitive(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'bearer test-key-lowercase';

        $method = $this->getPrivateStaticMethod('extractApiKey');
        $result = $method->invoke(null);

        $this->assertEquals('test-key-lowercase', $result);
    }

    /**
     * Test extractApiKey() works with X-API-Key header.
     */
    public function testExtractApiKeyWithXApiKeyHeader(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = 'x-api-key-value-67890';

        $method = $this->getPrivateStaticMethod('extractApiKey');
        $result = $method->invoke(null);

        $this->assertEquals('x-api-key-value-67890', $result);
    }

    /**
     * Test extractApiKey() prefers Bearer header over X-API-Key when both set.
     */
    public function testExtractApiKeyPrefersBearerOverXApiKey(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer bearer-key';
        $_SERVER['HTTP_X_API_KEY'] = 'x-api-key';

        $method = $this->getPrivateStaticMethod('extractApiKey');
        $result = $method->invoke(null);

        $this->assertEquals('bearer-key', $result);
    }

    /**
     * Test extractApiKey() returns null when no key is provided.
     */
    public function testExtractApiKeyReturnsNullWhenNoKeyProvided(): void
    {
        // Ensure no auth headers are set
        unset($_SERVER['HTTP_AUTHORIZATION']);
        unset($_SERVER['HTTP_X_API_KEY']);

        $method = $this->getPrivateStaticMethod('extractApiKey');
        $result = $method->invoke(null);

        $this->assertNull($result);
    }

    /**
     * Test extractApiKey() returns null for non-Bearer authorization header.
     */
    public function testExtractApiKeyReturnsNullForBasicAuth(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic dXNlcjpwYXNz';

        $method = $this->getPrivateStaticMethod('extractApiKey');
        $result = $method->invoke(null);

        $this->assertNull($result);
    }

    /**
     * SECURITY: Test that query parameters are NOT accepted for API keys.
     * API keys in URLs are a security risk (server logs, browser history, referrer headers).
     */
    public function testExtractApiKeyDoesNotAcceptQueryParameters(): void
    {
        $_GET['api_key'] = 'insecure-query-key';
        unset($_SERVER['HTTP_AUTHORIZATION']);
        unset($_SERVER['HTTP_X_API_KEY']);

        $method = $this->getPrivateStaticMethod('extractApiKey');
        $result = $method->invoke(null);

        $this->assertNull($result, 'API keys should NEVER be accepted via query parameters');

        unset($_GET['api_key']);
    }

    // -----------------------------------------------------------------------
    // hasHmacSignature() tests
    // -----------------------------------------------------------------------

    /**
     * Test hasHmacSignature() returns true when all 3 required headers are present.
     */
    public function testHasHmacSignatureReturnsTrueWhenAllHeadersPresent(): void
    {
        $_SERVER['HTTP_X_FEDERATION_SIGNATURE'] = 'abc123';
        $_SERVER['HTTP_X_FEDERATION_TIMESTAMP'] = (string)time();
        $_SERVER['HTTP_X_FEDERATION_PLATFORM_ID'] = 'platform-001';

        $method = $this->getPrivateStaticMethod('hasHmacSignature');
        $result = $method->invoke(null);

        $this->assertTrue($result);
    }

    /**
     * Test hasHmacSignature() returns false when signature header is missing.
     */
    public function testHasHmacSignatureReturnsFalseWithoutSignature(): void
    {
        $_SERVER['HTTP_X_FEDERATION_TIMESTAMP'] = (string)time();
        $_SERVER['HTTP_X_FEDERATION_PLATFORM_ID'] = 'platform-001';
        unset($_SERVER['HTTP_X_FEDERATION_SIGNATURE']);

        $method = $this->getPrivateStaticMethod('hasHmacSignature');
        $result = $method->invoke(null);

        $this->assertFalse($result);
    }

    /**
     * Test hasHmacSignature() returns false when timestamp header is missing.
     */
    public function testHasHmacSignatureReturnsFalseWithoutTimestamp(): void
    {
        $_SERVER['HTTP_X_FEDERATION_SIGNATURE'] = 'abc123';
        $_SERVER['HTTP_X_FEDERATION_PLATFORM_ID'] = 'platform-001';
        unset($_SERVER['HTTP_X_FEDERATION_TIMESTAMP']);

        $method = $this->getPrivateStaticMethod('hasHmacSignature');
        $result = $method->invoke(null);

        $this->assertFalse($result);
    }

    /**
     * Test hasHmacSignature() returns false when platform ID header is missing.
     */
    public function testHasHmacSignatureReturnsFalseWithoutPlatformId(): void
    {
        $_SERVER['HTTP_X_FEDERATION_SIGNATURE'] = 'abc123';
        $_SERVER['HTTP_X_FEDERATION_TIMESTAMP'] = (string)time();
        unset($_SERVER['HTTP_X_FEDERATION_PLATFORM_ID']);

        $method = $this->getPrivateStaticMethod('hasHmacSignature');
        $result = $method->invoke(null);

        $this->assertFalse($result);
    }

    /**
     * Test hasHmacSignature() returns false when all headers are missing.
     */
    public function testHasHmacSignatureReturnsFalseWhenAllHeadersMissing(): void
    {
        unset($_SERVER['HTTP_X_FEDERATION_SIGNATURE']);
        unset($_SERVER['HTTP_X_FEDERATION_TIMESTAMP']);
        unset($_SERVER['HTTP_X_FEDERATION_PLATFORM_ID']);

        $method = $this->getPrivateStaticMethod('hasHmacSignature');
        $result = $method->invoke(null);

        $this->assertFalse($result);
    }

    // -----------------------------------------------------------------------
    // validateTimestamp() tests
    // -----------------------------------------------------------------------

    /**
     * Test validateTimestamp() accepts a valid ISO 8601 timestamp within tolerance.
     */
    public function testValidateTimestampWithValidIso8601(): void
    {
        $timestamp = date('c'); // Current time in ISO 8601

        $method = $this->getPrivateStaticMethod('validateTimestamp');
        $result = $method->invoke(null, $timestamp);

        $this->assertTrue($result);
    }

    /**
     * Test validateTimestamp() accepts a valid Unix timestamp within tolerance.
     */
    public function testValidateTimestampWithValidUnixTimestamp(): void
    {
        $timestamp = (string)time();

        $method = $this->getPrivateStaticMethod('validateTimestamp');
        $result = $method->invoke(null, $timestamp);

        $this->assertTrue($result);
    }

    /**
     * Test validateTimestamp() accepts a timestamp at the edge of the 5-minute tolerance.
     */
    public function testValidateTimestampAtEdgeOfTolerance(): void
    {
        // 299 seconds ago (just within 300-second tolerance)
        $timestamp = (string)(time() - 299);

        $method = $this->getPrivateStaticMethod('validateTimestamp');
        $result = $method->invoke(null, $timestamp);

        $this->assertTrue($result);
    }

    /**
     * SECURITY: Test validateTimestamp() rejects an expired timestamp (>300 seconds old).
     * This prevents replay attacks.
     */
    public function testValidateTimestampRejectsExpiredTimestamp(): void
    {
        // 600 seconds ago (well beyond 300-second tolerance)
        $timestamp = (string)(time() - 600);

        $method = $this->getPrivateStaticMethod('validateTimestamp');
        $result = $method->invoke(null, $timestamp);

        $this->assertFalse($result, 'Timestamps older than 300 seconds should be rejected (replay attack prevention)');
    }

    /**
     * SECURITY: Test validateTimestamp() rejects a future timestamp beyond tolerance.
     */
    public function testValidateTimestampRejectsFarFutureTimestamp(): void
    {
        // 600 seconds in the future
        $timestamp = (string)(time() + 600);

        $method = $this->getPrivateStaticMethod('validateTimestamp');
        $result = $method->invoke(null, $timestamp);

        $this->assertFalse($result, 'Timestamps too far in the future should be rejected');
    }

    /**
     * Test validateTimestamp() rejects garbage/non-timestamp strings.
     */
    public function testValidateTimestampRejectsGarbageInput(): void
    {
        $method = $this->getPrivateStaticMethod('validateTimestamp');

        $this->assertFalse($method->invoke(null, 'not-a-timestamp'));
        $this->assertFalse($method->invoke(null, ''));
        $this->assertFalse($method->invoke(null, 'abc123'));
    }

    /**
     * Test validateTimestamp() accepts various valid ISO 8601 formats.
     */
    public function testValidateTimestampAcceptsVariousIsoFormats(): void
    {
        $method = $this->getPrivateStaticMethod('validateTimestamp');

        // Standard ISO 8601 with timezone
        $this->assertTrue($method->invoke(null, gmdate('Y-m-d\TH:i:s\Z')));

        // RFC 2822 format (also parseable by strtotime)
        $this->assertTrue($method->invoke(null, date('r')));
    }

    // -----------------------------------------------------------------------
    // generateSigningSecret() tests
    // -----------------------------------------------------------------------

    /**
     * Test generateSigningSecret() returns a 64-character hex string.
     * (32 bytes = 64 hex chars)
     */
    public function testGenerateSigningSecretReturns64CharHexString(): void
    {
        $secret = FederationApiMiddleware::generateSigningSecret();

        $this->assertIsString($secret);
        $this->assertEquals(64, strlen($secret), 'Signing secret should be 64 hex characters (32 bytes)');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $secret, 'Secret should be lowercase hex');
    }

    /**
     * Test generateSigningSecret() produces unique values each call.
     */
    public function testGenerateSigningSecretIsUnique(): void
    {
        $secrets = [];
        for ($i = 0; $i < 10; $i++) {
            $secrets[] = FederationApiMiddleware::generateSigningSecret();
        }

        $uniqueSecrets = array_unique($secrets);
        $this->assertCount(10, $uniqueSecrets, 'Each generated secret should be unique');
    }

    // -----------------------------------------------------------------------
    // generateSignature() tests
    // -----------------------------------------------------------------------

    /**
     * Test generateSignature() produces consistent HMAC output for same inputs.
     */
    public function testGenerateSignatureIsConsistent(): void
    {
        $secret = 'test-secret-key';
        $method = 'GET';
        $path = '/api/v2/federation/directory';
        $timestamp = '2026-02-19T10:00:00Z';
        $body = '';

        $sig1 = FederationApiMiddleware::generateSignature($secret, $method, $path, $timestamp, $body);
        $sig2 = FederationApiMiddleware::generateSignature($secret, $method, $path, $timestamp, $body);

        $this->assertEquals($sig1, $sig2, 'Same inputs should produce the same signature');
    }

    /**
     * Test generateSignature() returns a hex-encoded SHA-256 hash (64 chars).
     */
    public function testGenerateSignatureReturnsHexSha256(): void
    {
        $sig = FederationApiMiddleware::generateSignature('secret', 'POST', '/api/test', (string)time(), '{"key":"value"}');

        $this->assertIsString($sig);
        $this->assertEquals(64, strlen($sig), 'HMAC-SHA256 should produce a 64-character hex string');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $sig);
    }

    /**
     * Test generateSignature() produces different output for different secrets.
     */
    public function testGenerateSignatureDiffersWithDifferentSecret(): void
    {
        $path = '/api/test';
        $timestamp = (string)time();

        $sig1 = FederationApiMiddleware::generateSignature('secret-a', 'GET', $path, $timestamp);
        $sig2 = FederationApiMiddleware::generateSignature('secret-b', 'GET', $path, $timestamp);

        $this->assertNotEquals($sig1, $sig2, 'Different secrets must produce different signatures');
    }

    /**
     * Test generateSignature() produces different output for different paths.
     */
    public function testGenerateSignatureDiffersWithDifferentPath(): void
    {
        $secret = 'test-secret';
        $timestamp = (string)time();

        $sig1 = FederationApiMiddleware::generateSignature($secret, 'GET', '/api/users', $timestamp);
        $sig2 = FederationApiMiddleware::generateSignature($secret, 'GET', '/api/listings', $timestamp);

        $this->assertNotEquals($sig1, $sig2, 'Different paths must produce different signatures');
    }

    /**
     * Test generateSignature() produces different output for different methods.
     */
    public function testGenerateSignatureDiffersWithDifferentMethod(): void
    {
        $secret = 'test-secret';
        $path = '/api/test';
        $timestamp = (string)time();

        $sig1 = FederationApiMiddleware::generateSignature($secret, 'GET', $path, $timestamp);
        $sig2 = FederationApiMiddleware::generateSignature($secret, 'POST', $path, $timestamp);

        $this->assertNotEquals($sig1, $sig2, 'Different HTTP methods must produce different signatures');
    }

    /**
     * Test generateSignature() uppercases the HTTP method.
     */
    public function testGenerateSignatureUppercasesMethod(): void
    {
        $secret = 'test-secret';
        $path = '/api/test';
        $timestamp = (string)time();

        $sig1 = FederationApiMiddleware::generateSignature($secret, 'get', $path, $timestamp);
        $sig2 = FederationApiMiddleware::generateSignature($secret, 'GET', $path, $timestamp);

        $this->assertEquals($sig1, $sig2, 'Method should be uppercased before signing');
    }

    /**
     * Test generateSignature() includes body in the signing string.
     */
    public function testGenerateSignatureIncludesBody(): void
    {
        $secret = 'test-secret';
        $path = '/api/test';
        $timestamp = (string)time();

        $sig1 = FederationApiMiddleware::generateSignature($secret, 'POST', $path, $timestamp, '{"data":"a"}');
        $sig2 = FederationApiMiddleware::generateSignature($secret, 'POST', $path, $timestamp, '{"data":"b"}');

        $this->assertNotEquals($sig1, $sig2, 'Different request bodies must produce different signatures');
    }

    // -----------------------------------------------------------------------
    // verifyHmacSignature() tests — via generateSignature matching
    // -----------------------------------------------------------------------

    /**
     * Test that verifyHmacSignature() can be verified by matching generateSignature()
     * output. Since verifyHmacSignature is private and uses $_SERVER, we test the
     * signing/verification contract through the public generateSignature method.
     */
    public function testSignatureVerificationContract(): void
    {
        $secret = FederationApiMiddleware::generateSigningSecret();
        $method = 'POST';
        $path = '/api/v2/federation/sync';
        $timestamp = date('c');
        $body = '{"users":["u1","u2"]}';

        // Generate the signature the way a client would
        $signature = FederationApiMiddleware::generateSignature($secret, $method, $path, $timestamp, $body);

        // The expected signature should match what verifyHmacSignature would compute
        $stringToSign = implode("\n", [strtoupper($method), $path, $timestamp, $body]);
        $expected = hash_hmac('sha256', $stringToSign, $secret);

        $this->assertEquals($expected, $signature, 'Generated signature should match manual HMAC computation');
        $this->assertTrue(hash_equals($expected, $signature), 'Timing-safe comparison should pass');
    }

    /**
     * SECURITY: Test that a tampered signature does NOT match.
     */
    public function testTamperedSignatureDoesNotMatch(): void
    {
        $secret = 'legitimate-secret';
        $method = 'POST';
        $path = '/api/v2/federation/transfer';
        $timestamp = date('c');
        $body = '{"amount":100}';

        $validSignature = FederationApiMiddleware::generateSignature($secret, $method, $path, $timestamp, $body);

        // Tamper with one character
        $tamperedSignature = 'a' . substr($validSignature, 1);

        $this->assertNotEquals($validSignature, $tamperedSignature);
        $this->assertFalse(hash_equals($validSignature, $tamperedSignature), 'Tampered signature should not match');
    }

    // -----------------------------------------------------------------------
    // hasPermission() / getPartner() / getAuthMethod() tests
    // -----------------------------------------------------------------------

    /**
     * Test hasPermission() returns false when no partner is authenticated.
     */
    public function testHasPermissionReturnsFalseWithNoPartner(): void
    {
        $this->assertFalse(FederationApiMiddleware::hasPermission('read'));
        $this->assertFalse(FederationApiMiddleware::hasPermission('write'));
        $this->assertFalse(FederationApiMiddleware::hasPermission('*'));
    }

    /**
     * Test hasPermission() with wildcard '*' permission grants access to any feature.
     */
    public function testHasPermissionWithWildcardPermission(): void
    {
        // Set up authenticated partner with wildcard permissions via reflection
        $partnerProp = $this->reflection->getProperty('authenticatedPartner');
        $partnerProp->setAccessible(true);
        $partnerProp->setValue(null, [
            'id' => 1,
            'tenant_id' => 2,
            'name' => 'Test Partner',
            'permissions' => json_encode(['*']),
            'status' => 'active',
        ]);

        $this->assertTrue(FederationApiMiddleware::hasPermission('read'));
        $this->assertTrue(FederationApiMiddleware::hasPermission('write'));
        $this->assertTrue(FederationApiMiddleware::hasPermission('delete'));
        $this->assertTrue(FederationApiMiddleware::hasPermission('any_permission'));
    }

    /**
     * Test hasPermission() with specific permissions only grants matching features.
     */
    public function testHasPermissionWithSpecificPermissions(): void
    {
        $partnerProp = $this->reflection->getProperty('authenticatedPartner');
        $partnerProp->setAccessible(true);
        $partnerProp->setValue(null, [
            'id' => 1,
            'tenant_id' => 2,
            'name' => 'Limited Partner',
            'permissions' => json_encode(['read', 'directory']),
            'status' => 'active',
        ]);

        $this->assertTrue(FederationApiMiddleware::hasPermission('read'));
        $this->assertTrue(FederationApiMiddleware::hasPermission('directory'));
        $this->assertFalse(FederationApiMiddleware::hasPermission('write'));
        $this->assertFalse(FederationApiMiddleware::hasPermission('admin'));
    }

    /**
     * Test hasPermission() handles null/empty permissions gracefully.
     */
    public function testHasPermissionWithEmptyPermissions(): void
    {
        $partnerProp = $this->reflection->getProperty('authenticatedPartner');
        $partnerProp->setAccessible(true);
        $partnerProp->setValue(null, [
            'id' => 1,
            'tenant_id' => 2,
            'name' => 'No Perms Partner',
            'permissions' => null,
            'status' => 'active',
        ]);

        $this->assertFalse(FederationApiMiddleware::hasPermission('read'));
    }

    /**
     * Test hasPermission() with malformed JSON permissions throws TypeError.
     *
     * BUG FOUND: json_decode('not-valid-json') returns null, and in_array()
     * does not accept null as the haystack parameter in PHP 8.x.
     * This means corrupted permissions data in the database will cause a
     * fatal error instead of graceful denial. The fix would be:
     *   $permissions = json_decode(..., true) ?? [];
     *
     * Until fixed, this test documents the current (buggy) behavior.
     */
    public function testHasPermissionWithMalformedJsonPermissionsThrowsTypeError(): void
    {
        $partnerProp = $this->reflection->getProperty('authenticatedPartner');
        $partnerProp->setAccessible(true);
        $partnerProp->setValue(null, [
            'id' => 1,
            'tenant_id' => 2,
            'name' => 'Bad JSON Partner',
            'permissions' => 'not-valid-json',
            'status' => 'active',
        ]);

        // json_decode returns null for invalid JSON
        // in_array() with null haystack throws TypeError in PHP 8.x
        $this->expectException(\TypeError::class);
        FederationApiMiddleware::hasPermission('read');
    }

    /**
     * Test getPartner() returns null before authentication.
     */
    public function testGetPartnerReturnsNullBeforeAuthentication(): void
    {
        $this->assertNull(FederationApiMiddleware::getPartner());
    }

    /**
     * Test getPartner() returns partner data after being set.
     */
    public function testGetPartnerReturnsPartnerAfterSet(): void
    {
        $partnerData = [
            'id' => 42,
            'tenant_id' => 5,
            'name' => 'Partner Alpha',
            'permissions' => json_encode(['read', 'write']),
            'status' => 'active',
        ];

        $partnerProp = $this->reflection->getProperty('authenticatedPartner');
        $partnerProp->setAccessible(true);
        $partnerProp->setValue(null, $partnerData);

        $partner = FederationApiMiddleware::getPartner();
        $this->assertNotNull($partner);
        $this->assertEquals(42, $partner['id']);
        $this->assertEquals('Partner Alpha', $partner['name']);
    }

    /**
     * Test getAuthMethod() returns null before authentication.
     */
    public function testGetAuthMethodReturnsNullBeforeAuthentication(): void
    {
        $this->assertNull(FederationApiMiddleware::getAuthMethod());
    }

    /**
     * Test getAuthMethod() returns the set auth method.
     */
    public function testGetAuthMethodReturnsSetMethod(): void
    {
        $authMethodProp = $this->reflection->getProperty('authMethod');
        $authMethodProp->setAccessible(true);

        $authMethodProp->setValue(null, 'hmac');
        $this->assertEquals('hmac', FederationApiMiddleware::getAuthMethod());

        $authMethodProp->setValue(null, 'api_key');
        $this->assertEquals('api_key', FederationApiMiddleware::getAuthMethod());

        $authMethodProp->setValue(null, 'jwt');
        $this->assertEquals('jwt', FederationApiMiddleware::getAuthMethod());
    }

    /**
     * Test getPartnerTenantId() returns null before authentication.
     */
    public function testGetPartnerTenantIdReturnsNullBeforeAuth(): void
    {
        $this->assertNull(FederationApiMiddleware::getPartnerTenantId());
    }

    /**
     * Test getPartnerTenantId() returns correct tenant ID after auth.
     */
    public function testGetPartnerTenantIdReturnsCorrectId(): void
    {
        $partnerProp = $this->reflection->getProperty('authenticatedPartner');
        $partnerProp->setAccessible(true);
        $partnerProp->setValue(null, [
            'id' => 1,
            'tenant_id' => 7,
            'name' => 'Test',
            'permissions' => '[]',
            'status' => 'active',
        ]);

        $this->assertEquals(7, FederationApiMiddleware::getPartnerTenantId());
    }

    // -----------------------------------------------------------------------
    // TIMESTAMP_TOLERANCE constant test
    // -----------------------------------------------------------------------

    /**
     * Test that the timestamp tolerance constant is 300 seconds (5 minutes).
     */
    public function testTimestampToleranceIs300Seconds(): void
    {
        $constant = $this->reflection->getConstant('TIMESTAMP_TOLERANCE');
        $this->assertEquals(300, $constant, 'Timestamp tolerance should be 300 seconds (5 minutes)');
    }

    // -----------------------------------------------------------------------
    // hasJwtToken() tests
    // -----------------------------------------------------------------------

    /**
     * Test hasJwtToken() detects a JWT-formatted Bearer token.
     */
    public function testHasJwtTokenDetectsJwtFormat(): void
    {
        // JWT format: three base64url segments separated by dots
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer eyJhbGciOiJIUzI1NiJ9.eyJ1c2VyIjoiMSJ9.abc123def456';

        $method = $this->getPrivateStaticMethod('hasJwtToken');
        $result = $method->invoke(null);

        $this->assertTrue($result);
    }

    /**
     * Test hasJwtToken() returns false for a non-JWT Bearer token (plain API key).
     */
    public function testHasJwtTokenReturnsFalseForPlainBearerKey(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer simple-api-key-no-dots';

        $method = $this->getPrivateStaticMethod('hasJwtToken');
        $result = $method->invoke(null);

        $this->assertFalse($result);
    }

    /**
     * Test hasJwtToken() returns false when no authorization header.
     */
    public function testHasJwtTokenReturnsFalseWithNoHeader(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);

        $method = $this->getPrivateStaticMethod('hasJwtToken');
        $result = $method->invoke(null);

        $this->assertFalse($result);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Get a private static method via reflection.
     */
    private function getPrivateStaticMethod(string $methodName): ReflectionMethod
    {
        $method = $this->reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method;
    }
}
