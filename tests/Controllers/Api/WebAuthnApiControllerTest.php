<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * Tests for WebAuthnApiController endpoints
 *
 * Tests WebAuthn/passwordless authentication features including
 * registration, authentication, and credential management.
 */
class WebAuthnApiControllerTest extends ApiTestCase
{
    private const CONTROLLER = 'Nexus\Controllers\Api\WebAuthnApiController';

    /**
     * Make an unauthenticated request by temporarily clearing session/auth state,
     * then invoking the controller directly.
     *
     * @return array{status: int, body: array|string, raw: string, method: string, endpoint: string}
     */
    private function makeUnauthenticatedRequest(string $method, string $endpoint, array $data, string $action): array
    {
        $oldSession = $_SESSION ?? [];
        $oldServer = $_SERVER;

        $_SESSION = [];
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $endpoint;
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_SERVER['HTTP_X_TENANT_ID'] = (string) self::$testTenantId;
        unset($_SERVER['HTTP_AUTHORIZATION']);

        if ($method === 'GET') {
            $_GET = $data;
            $_POST = [];
        } else {
            $_POST = $data;
            $_GET = [];
        }

        $statusCode = 200;
        $rawOutput = '';

        ob_start();
        try {
            [$controllerClass, $actionMethod] = explode('@', $action);
            $controller = new $controllerClass();
            $controller->$actionMethod();
        } catch (\Throwable $e) {
            // Controller may throw on exit() or error path — capture output
        } finally {
            $rawOutput = ob_get_clean() ?: '';
        }

        $statusCode = http_response_code() ?: 200;

        $_SESSION = $oldSession;
        $_SERVER = $oldServer;

        $body = json_decode($rawOutput, true);
        if ($body === null && !empty($rawOutput)) {
            $body = $rawOutput;
        }

        return [
            'status' => $statusCode,
            'body' => $body ?? [],
            'raw' => $rawOutput,
            'method' => $method,
            'endpoint' => $endpoint,
        ];
    }

    // =========================================================================
    // REGISTER CHALLENGE
    // =========================================================================

    /**
     * Test 1: POST /api/webauthn/register-challenge without auth returns 401
     */
    public function testRegisterChallengeRequiresAuth(): void
    {
        $response = $this->makeUnauthenticatedRequest(
            'POST',
            '/api/webauthn/register-challenge',
            [],
            self::CONTROLLER . '@registerChallenge'
        );

        $this->assertEquals(401, $response['status'], 'Register challenge without auth should return 401');
        $this->assertIsArray($response['body']);
        $this->assertArrayHasKey('error', $response['body']);
    }

    /**
     * Test 2: POST /api/webauthn/register-challenge with auth returns valid creation options
     */
    public function testRegisterChallengeReturnsValidOptions(): void
    {
        $response = $this->post(
            '/api/webauthn/register-challenge',
            [],
            [],
            self::CONTROLLER . '@registerChallenge'
        );

        $this->assertIsArray($response['body']);
        $body = $response['body'];

        // Verify all required WebAuthn creation options are present
        $this->assertArrayHasKey('challenge', $body, 'Response must contain challenge');
        $this->assertArrayHasKey('challenge_id', $body, 'Response must contain challenge_id');
        $this->assertArrayHasKey('rp', $body, 'Response must contain rp (relying party)');
        $this->assertArrayHasKey('user', $body, 'Response must contain user');
        $this->assertArrayHasKey('pubKeyCredParams', $body, 'Response must contain pubKeyCredParams');
        $this->assertArrayHasKey('authenticatorSelection', $body, 'Response must contain authenticatorSelection');
        $this->assertArrayHasKey('timeout', $body, 'Response must contain timeout');
        $this->assertArrayHasKey('attestation', $body, 'Response must contain attestation');

        // Validate rp structure
        $this->assertArrayHasKey('name', $body['rp']);
        $this->assertArrayHasKey('id', $body['rp']);

        // Validate user structure
        $this->assertArrayHasKey('id', $body['user']);
        $this->assertArrayHasKey('name', $body['user']);
        $this->assertArrayHasKey('displayName', $body['user']);

        // Validate pubKeyCredParams contains at least one algorithm
        $this->assertNotEmpty($body['pubKeyCredParams']);
        $this->assertEquals('public-key', $body['pubKeyCredParams'][0]['type']);
        $this->assertContains($body['pubKeyCredParams'][0]['alg'], [-7, -257], 'Algorithm must be ES256 (-7) or RS256 (-257)');

        // Validate authenticatorSelection
        $this->assertArrayHasKey('userVerification', $body['authenticatorSelection']);

        // Validate timeout is a positive integer (milliseconds)
        $this->assertIsInt($body['timeout']);
        $this->assertGreaterThan(0, $body['timeout']);

        // Validate attestation preference
        $this->assertContains($body['attestation'], ['none', 'direct', 'indirect', 'enterprise']);

        // Challenge should be a non-empty base64url string
        $this->assertNotEmpty($body['challenge']);
        $this->assertIsString($body['challenge']);

        // Challenge ID should be a non-empty string
        $this->assertNotEmpty($body['challenge_id']);
        $this->assertIsString($body['challenge_id']);
    }

    /**
     * Test 3: Register challenge response includes excludeCredentials array
     */
    public function testRegisterChallengeExcludesExistingCredentials(): void
    {
        $response = $this->post(
            '/api/webauthn/register-challenge',
            [],
            [],
            self::CONTROLLER . '@registerChallenge'
        );

        $this->assertIsArray($response['body']);
        $this->assertArrayHasKey('excludeCredentials', $response['body'], 'Response must contain excludeCredentials');
        $this->assertIsArray($response['body']['excludeCredentials']);
    }

    // =========================================================================
    // REGISTER VERIFY
    // =========================================================================

    /**
     * Test 4: POST /api/webauthn/register-verify rejects empty/invalid credential data
     */
    public function testRegisterVerifyRejectsInvalidData(): void
    {
        // Send with empty data — should fail validation
        $response = $this->post(
            '/api/webauthn/register-verify',
            [],
            [],
            self::CONTROLLER . '@registerVerify'
        );

        $body = $response['body'];
        $this->assertIsArray($body);

        // Should return an error (400 for validation or 401 for challenge)
        $this->assertContains($response['status'], [400, 401],
            'Register verify with empty data should return 400 or 401');

        if (isset($body['success'])) {
            $this->assertFalse($body['success']);
        }
    }

    /**
     * Test 5: POST /api/webauthn/register-verify with bad challenge_id returns 401
     */
    public function testRegisterVerifyRejectsExpiredChallenge(): void
    {
        $response = $this->post(
            '/api/webauthn/register-verify',
            [
                'challenge_id' => 'nonexistent-challenge-id-12345',
                'id' => 'fake-credential-id',
                'response' => [
                    'clientDataJSON' => 'fake-data',
                    'attestationObject' => 'fake-data',
                ],
            ],
            [],
            self::CONTROLLER . '@registerVerify'
        );

        $this->assertEquals(401, $response['status'],
            'Register verify with invalid challenge_id should return 401');
        $this->assertIsArray($response['body']);
        $this->assertFalse($response['body']['success'] ?? true);
    }

    // =========================================================================
    // AUTH CHALLENGE
    // =========================================================================

    /**
     * Test 6: POST /api/webauthn/auth-challenge with email returns valid options
     */
    public function testAuthChallengeReturnsOptions(): void
    {
        $response = $this->post(
            '/api/webauthn/auth-challenge',
            ['email' => self::$testUserEmail],
            [],
            self::CONTROLLER . '@authChallenge'
        );

        $this->assertIsArray($response['body']);
        $body = $response['body'];

        $this->assertArrayHasKey('challenge', $body, 'Auth challenge must contain challenge');
        $this->assertArrayHasKey('challenge_id', $body, 'Auth challenge must contain challenge_id');
        $this->assertArrayHasKey('rpId', $body, 'Auth challenge must contain rpId');
        $this->assertArrayHasKey('timeout', $body, 'Auth challenge must contain timeout');
        $this->assertArrayHasKey('userVerification', $body, 'Auth challenge must contain userVerification');

        // Validate types
        $this->assertIsString($body['challenge']);
        $this->assertNotEmpty($body['challenge']);
        $this->assertIsString($body['challenge_id']);
        $this->assertNotEmpty($body['challenge_id']);
        $this->assertIsString($body['rpId']);
        $this->assertIsInt($body['timeout']);
        $this->assertGreaterThan(0, $body['timeout']);
    }

    /**
     * Test 7: POST /api/webauthn/auth-challenge without email (discoverable credential flow)
     * Should NOT include allowCredentials when no user context is available
     */
    public function testAuthChallengeDiscoverableFlow(): void
    {
        // Make unauthenticated request with empty body for discoverable credential flow
        $response = $this->makeUnauthenticatedRequest(
            'POST',
            '/api/webauthn/auth-challenge',
            [],
            self::CONTROLLER . '@authChallenge'
        );

        $this->assertIsArray($response['body']);
        $body = $response['body'];

        // Basic options must still be present
        $this->assertArrayHasKey('challenge', $body, 'Discoverable flow must still return challenge');
        $this->assertArrayHasKey('challenge_id', $body, 'Discoverable flow must still return challenge_id');
        $this->assertArrayHasKey('rpId', $body);

        // In discoverable credential flow, allowCredentials should be absent
        // (empty allowCredentials = browser prompts for any available passkey)
        $this->assertArrayNotHasKey('allowCredentials', $body,
            'Discoverable flow should not include allowCredentials');
    }

    // =========================================================================
    // AUTH VERIFY
    // =========================================================================

    /**
     * Test 8: POST /api/webauthn/auth-verify without required fields returns 400
     */
    public function testAuthVerifyRejectsInvalidData(): void
    {
        $response = $this->post(
            '/api/webauthn/auth-verify',
            [],
            [],
            self::CONTROLLER . '@authVerify'
        );

        $body = $response['body'];
        $this->assertIsArray($body);

        // Should return 400 (missing fields) or 401 (no challenge)
        $this->assertContains($response['status'], [400, 401],
            'Auth verify with empty data should return 400 or 401');

        if (isset($body['success'])) {
            $this->assertFalse($body['success']);
        }
    }

    /**
     * Test 9: POST /api/webauthn/auth-verify with non-existent credential returns 401
     */
    public function testAuthVerifyRejectsUnknownCredential(): void
    {
        // First get a valid challenge so we pass challenge validation
        $challengeResponse = $this->post(
            '/api/webauthn/auth-challenge',
            ['email' => self::$testUserEmail],
            [],
            self::CONTROLLER . '@authChallenge'
        );

        $challengeId = $challengeResponse['body']['challenge_id'] ?? 'fake';

        $response = $this->post(
            '/api/webauthn/auth-verify',
            [
                'challenge_id' => $challengeId,
                'id' => 'totally-nonexistent-credential-id',
                'response' => [
                    'clientDataJSON' => 'ZmFrZQ',
                    'authenticatorData' => 'ZmFrZQ',
                    'signature' => 'ZmFrZQ',
                    'userHandle' => 'ZmFrZQ',
                ],
            ],
            [],
            self::CONTROLLER . '@authVerify'
        );

        $this->assertEquals(401, $response['status'],
            'Auth verify with unknown credential should return 401');
        $this->assertIsArray($response['body']);
        $this->assertFalse($response['body']['success'] ?? true);
    }

    // =========================================================================
    // REMOVE
    // =========================================================================

    /**
     * Test 10: POST /api/webauthn/remove without auth returns 401
     */
    public function testRemoveRequiresAuth(): void
    {
        $response = $this->makeUnauthenticatedRequest(
            'POST',
            '/api/webauthn/remove',
            [],
            self::CONTROLLER . '@remove'
        );

        $this->assertEquals(401, $response['status'], 'Remove without auth should return 401');
    }

    /**
     * Test 11: GET /api/webauthn/remove-all returns 405 (POST required)
     */
    public function testRemoveAllRequiresPost(): void
    {
        $response = $this->get(
            '/api/webauthn/remove-all',
            [],
            [],
            self::CONTROLLER . '@removeAll'
        );

        $this->assertEquals(405, $response['status'], 'GET to remove-all should return 405');
        $this->assertIsArray($response['body']);
        $this->assertFalse($response['body']['success'] ?? true);
    }

    // =========================================================================
    // CREDENTIALS LIST
    // =========================================================================

    /**
     * Test 12: GET /api/webauthn/credentials without auth returns 401
     */
    public function testCredentialsListRequiresAuth(): void
    {
        $response = $this->makeUnauthenticatedRequest(
            'GET',
            '/api/webauthn/credentials',
            [],
            self::CONTROLLER . '@credentials'
        );

        $this->assertEquals(401, $response['status'], 'Credentials list without auth should return 401');
    }

    /**
     * Test 13: GET /api/webauthn/credentials with auth returns credentials array and count
     */
    public function testCredentialsListReturnsArray(): void
    {
        $response = $this->get(
            '/api/webauthn/credentials',
            [],
            [],
            self::CONTROLLER . '@credentials'
        );

        $this->assertIsArray($response['body']);
        $body = $response['body'];

        $this->assertArrayHasKey('credentials', $body, 'Response must contain credentials key');
        $this->assertArrayHasKey('count', $body, 'Response must contain count key');
        $this->assertIsArray($body['credentials'], 'credentials must be an array');
        $this->assertIsInt($body['count'], 'count must be an integer');
        $this->assertEquals(count($body['credentials']), $body['count'],
            'count must match the number of credentials returned');
    }

    // =========================================================================
    // STATUS
    // =========================================================================

    /**
     * Test 14: GET /api/webauthn/status returns registered:false, count:0 for user with no passkeys
     */
    public function testStatusReturnsRegisteredFalseForNewUser(): void
    {
        $response = $this->get(
            '/api/webauthn/status',
            [],
            [],
            self::CONTROLLER . '@status'
        );

        $this->assertIsArray($response['body']);
        $body = $response['body'];

        $this->assertArrayHasKey('registered', $body, 'Status must contain registered key');
        $this->assertArrayHasKey('count', $body, 'Status must contain count key');
        $this->assertFalse($body['registered'], 'New user should not have registered passkeys');
        $this->assertEquals(0, $body['count'], 'New user should have 0 passkeys');
    }

    // =========================================================================
    // RATE LIMITING
    // =========================================================================

    /**
     * Test 15: POST /api/webauthn/register-challenge rate limited after >10 requests
     *
     * The controller applies rateLimit('webauthn:register-challenge', 10, 60).
     * After 10 successful calls, the 11th should return 429.
     */
    public function testRateLimitOnRegisterChallenge(): void
    {
        // Make 10 requests to consume the rate limit budget
        for ($i = 0; $i < 10; $i++) {
            $response = $this->post(
                '/api/webauthn/register-challenge',
                [],
                [],
                self::CONTROLLER . '@registerChallenge'
            );

            // First 10 should succeed (status 200)
            if ($response['status'] === 429) {
                // Rate limiter may carry state from earlier tests — if we already
                // hit the limit, the test premise is validated
                $this->assertEquals(429, $response['status']);
                return;
            }
        }

        // 11th request should be rate-limited
        $response = $this->post(
            '/api/webauthn/register-challenge',
            [],
            [],
            self::CONTROLLER . '@registerChallenge'
        );

        $this->assertEquals(429, $response['status'],
            'Register challenge should return 429 after exceeding rate limit');
        $this->assertIsArray($response['body']);
        $this->assertFalse($response['body']['success'] ?? true);
    }
}
