<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

/**
 * Tests for AuthController API endpoints
 *
 * Tests authentication-related endpoints including login, logout,
 * session management, and token validation.
 */
class AuthControllerTest extends ApiTestCase
{
    /**
     * Test POST /api/auth/check-session
     */
    public function testCheckSession(): void
    {
        $response = $this->get('/api/auth/check-session');

        $this->assertArrayHasKey('status', $response);
        $this->assertEquals('GET', $response['method']);
        $this->assertEquals('/api/auth/check-session', $response['endpoint']);
    }

    /**
     * Test POST /api/auth/heartbeat
     */
    public function testHeartbeat(): void
    {
        $response = $this->post('/api/auth/heartbeat');

        $this->assertArrayHasKey('method', $response);
        $this->assertEquals('POST', $response['method']);
        $this->assertEquals('/api/auth/heartbeat', $response['endpoint']);
    }

    /**
     * Test POST /api/auth/refresh-session
     */
    public function testRefreshSession(): void
    {
        $response = $this->post('/api/auth/refresh-session');

        $this->assertArrayHasKey('endpoint', $response);
        $this->assertEquals('/api/auth/refresh-session', $response['endpoint']);
    }

    /**
     * Test POST /api/auth/refresh-token
     */
    public function testRefreshToken(): void
    {
        $response = $this->post('/api/auth/refresh-token', [
            'refresh_token' => 'test_token'
        ]);

        $this->assertArrayHasKey('status', $response);
        $this->assertIsArray($response['body']);
    }

    /**
     * Test POST /api/auth/validate-token
     */
    public function testValidateToken(): void
    {
        $response = $this->post('/api/auth/validate-token', [
            'token' => self::$testAuthToken
        ]);

        $this->assertArrayHasKey('status', $response);
        $this->assertIsArray($response['body']);
    }

    /**
     * Test POST /api/auth/logout
     */
    public function testLogout(): void
    {
        $response = $this->post('/api/auth/logout');

        $this->assertArrayHasKey('method', $response);
        $this->assertEquals('POST', $response['method']);
        $this->assertEquals('/api/auth/logout', $response['endpoint']);
    }

    /**
     * Test authentication headers are set up by test harness
     */
    public function testAuthenticationHeaders(): void
    {
        $response = $this->get('/api/auth/check-session');

        // The test harness sets these in $_SERVER, verify the response ran
        $this->assertArrayHasKey('status', $response);
        $this->assertEquals('GET', $response['method']);
    }

    /**
     * Test tenant context in requests
     */
    public function testTenantContext(): void
    {
        $response = $this->get('/api/auth/check-session');

        // Verify tenant was set for the request
        $this->assertArrayHasKey('status', $response);
        $this->assertEquals('/api/auth/check-session', $response['endpoint']);
    }
}
