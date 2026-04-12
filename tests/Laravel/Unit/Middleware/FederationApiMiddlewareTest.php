<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Middleware;

use App\Core\FederationApiMiddleware;
use Tests\Laravel\TestCase;

/**
 * Tests for FederationApiMiddleware.
 *
 * Note: This middleware uses static methods and raw $_SERVER superglobals
 * rather than Laravel's Request object. Tests are structured accordingly,
 * focusing on the utility/helper methods that can be tested without
 * triggering exit() calls in the authentication flow.
 */
class FederationApiMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Reset the static state between tests
        $ref = new \ReflectionClass(FederationApiMiddleware::class);

        $partnerProp = $ref->getProperty('authenticatedPartner');
        $partnerProp->setAccessible(true);
        $partnerProp->setValue(null, null);

        $methodProp = $ref->getProperty('authMethod');
        $methodProp->setAccessible(true);
        $methodProp->setValue(null, null);
    }

    public function test_getPartner_returns_null_when_not_authenticated(): void
    {
        $this->assertNull(FederationApiMiddleware::getPartner());
    }

    public function test_getAuthMethod_returns_null_when_not_authenticated(): void
    {
        $this->assertNull(FederationApiMiddleware::getAuthMethod());
    }

    public function test_getPartnerTenantId_returns_null_when_not_authenticated(): void
    {
        $this->assertNull(FederationApiMiddleware::getPartnerTenantId());
    }

    public function test_hasPermission_returns_false_when_not_authenticated(): void
    {
        $this->assertFalse(FederationApiMiddleware::hasPermission('members'));
    }

    public function test_hasPermission_returns_true_for_matching_permission(): void
    {
        // Set authenticated partner via reflection
        $ref = new \ReflectionClass(FederationApiMiddleware::class);
        $prop = $ref->getProperty('authenticatedPartner');
        $prop->setAccessible(true);
        $prop->setValue(null, [
            'id' => 1,
            'tenant_id' => 2,
            'name' => 'Test Partner',
            'permissions' => json_encode(['members', 'listings']),
            'status' => 'active',
        ]);

        $this->assertTrue(FederationApiMiddleware::hasPermission('members'));
        $this->assertTrue(FederationApiMiddleware::hasPermission('listings'));
        $this->assertFalse(FederationApiMiddleware::hasPermission('events'));
    }

    public function test_hasPermission_wildcard_grants_all(): void
    {
        $ref = new \ReflectionClass(FederationApiMiddleware::class);
        $prop = $ref->getProperty('authenticatedPartner');
        $prop->setAccessible(true);
        $prop->setValue(null, [
            'id' => 1,
            'tenant_id' => 2,
            'name' => 'Test Partner',
            'permissions' => json_encode(['*']),
            'status' => 'active',
        ]);

        $this->assertTrue(FederationApiMiddleware::hasPermission('anything'));
        $this->assertTrue(FederationApiMiddleware::hasPermission('members'));
    }

    public function test_generateSigningSecret_returns_64_char_hex(): void
    {
        $secret = FederationApiMiddleware::generateSigningSecret();

        $this->assertEquals(64, strlen($secret));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $secret);
    }

    public function test_generateSignature_produces_consistent_hmac(): void
    {
        $secret = 'test-secret-key';
        $method = 'GET';
        $path = '/api/federation/members';
        $timestamp = '2026-03-21T12:00:00Z';
        $body = '';

        $sig1 = FederationApiMiddleware::generateSignature($secret, $method, $path, $timestamp, $body);
        $sig2 = FederationApiMiddleware::generateSignature($secret, $method, $path, $timestamp, $body);

        $this->assertEquals($sig1, $sig2);
        $this->assertEquals(64, strlen($sig1)); // SHA-256 hex = 64 chars
    }

    public function test_generateSignature_differs_with_different_inputs(): void
    {
        $secret = 'test-secret-key';

        $sig1 = FederationApiMiddleware::generateSignature($secret, 'GET', '/path1', '12345', '');
        $sig2 = FederationApiMiddleware::generateSignature($secret, 'GET', '/path2', '12345', '');

        $this->assertNotEquals($sig1, $sig2);
    }

    public function test_generateSignature_differs_with_different_secrets(): void
    {
        $sig1 = FederationApiMiddleware::generateSignature('secret1', 'GET', '/path', '12345', '');
        $sig2 = FederationApiMiddleware::generateSignature('secret2', 'GET', '/path', '12345', '');

        $this->assertNotEquals($sig1, $sig2);
    }

    public function test_getPartnerTenantId_returns_correct_id_when_authenticated(): void
    {
        $ref = new \ReflectionClass(FederationApiMiddleware::class);
        $prop = $ref->getProperty('authenticatedPartner');
        $prop->setAccessible(true);
        $prop->setValue(null, [
            'id' => 1,
            'tenant_id' => 42,
            'name' => 'Test Partner',
            'permissions' => '[]',
            'status' => 'active',
        ]);

        $this->assertEquals(42, FederationApiMiddleware::getPartnerTenantId());
    }

    public function test_hasPermission_handles_empty_permissions_gracefully(): void
    {
        $ref = new \ReflectionClass(FederationApiMiddleware::class);
        $prop = $ref->getProperty('authenticatedPartner');
        $prop->setAccessible(true);
        $prop->setValue(null, [
            'id' => 1,
            'tenant_id' => 2,
            'name' => 'Test Partner',
            'permissions' => '[]',
            'status' => 'active',
        ]);

        $this->assertFalse(FederationApiMiddleware::hasPermission('members'));
    }

    public function test_hasPermission_handles_null_permissions_gracefully(): void
    {
        $ref = new \ReflectionClass(FederationApiMiddleware::class);
        $prop = $ref->getProperty('authenticatedPartner');
        $prop->setAccessible(true);
        $prop->setValue(null, [
            'id' => 1,
            'tenant_id' => 2,
            'name' => 'Test Partner',
            'permissions' => null,
            'status' => 'active',
        ]);

        $this->assertFalse(FederationApiMiddleware::hasPermission('members'));
    }
}
