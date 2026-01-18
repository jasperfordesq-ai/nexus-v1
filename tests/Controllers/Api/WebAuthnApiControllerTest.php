<?php

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

/**
 * Tests for WebAuthnApiController endpoints
 *
 * Tests WebAuthn/passwordless authentication features including
 * registration, authentication, and credential management.
 */
class WebAuthnApiControllerTest extends ApiTestCase
{
    /**
     * Test POST /api/webauthn/register-challenge
     */
    public function testGetRegisterChallenge(): void
    {
        $response = $this->post('/api/webauthn/register-challenge');

        $this->assertEquals('POST', $response['method']);
        $this->assertEquals('/api/webauthn/register-challenge', $response['endpoint']);
    }

    /**
     * Test POST /api/webauthn/register-verify
     */
    public function testVerifyRegistration(): void
    {
        $response = $this->post('/api/webauthn/register-verify', [
            'id' => 'credential_id',
            'rawId' => 'raw_credential_id',
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => 'base64_encoded_data',
                'attestationObject' => 'base64_encoded_data'
            ]
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('id', $response['data']);
        $this->assertArrayHasKey('response', $response['data']);
    }

    /**
     * Test POST /api/webauthn/auth-challenge
     */
    public function testGetAuthChallenge(): void
    {
        $response = $this->post('/api/webauthn/auth-challenge', [
            'username' => 'testuser'
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('username', $response['data']);
    }

    /**
     * Test POST /api/webauthn/auth-verify
     */
    public function testVerifyAuthentication(): void
    {
        $response = $this->post('/api/webauthn/auth-verify', [
            'id' => 'credential_id',
            'rawId' => 'raw_credential_id',
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => 'base64_encoded_data',
                'authenticatorData' => 'base64_encoded_data',
                'signature' => 'base64_encoded_signature',
                'userHandle' => 'base64_encoded_handle'
            ]
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('id', $response['data']);
    }

    /**
     * Test POST /api/webauthn/remove
     */
    public function testRemoveCredential(): void
    {
        $response = $this->post('/api/webauthn/remove', [
            'credential_id' => 'test_credential_id'
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('credential_id', $response['data']);
    }

    /**
     * Test GET /api/webauthn/remove (remove all)
     */
    public function testRemoveAllCredentials(): void
    {
        $response = $this->get('/api/webauthn/remove');

        $this->assertEquals('GET', $response['method']);
        $this->assertEquals('/api/webauthn/remove', $response['endpoint']);
    }

    /**
     * Test GET /api/webauthn/credentials
     */
    public function testGetCredentials(): void
    {
        $response = $this->get('/api/webauthn/credentials');

        $this->assertEquals('GET', $response['method']);
        $this->assertEquals('/api/webauthn/credentials', $response['endpoint']);
    }

    /**
     * Test GET /api/webauthn/status
     */
    public function testGetWebAuthnStatus(): void
    {
        $response = $this->get('/api/webauthn/status');

        $this->assertEquals('/api/webauthn/status', $response['endpoint']);
    }
}
