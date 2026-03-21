<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Core;

use App\Core\FederationApiMiddleware;
use PHPUnit\Framework\TestCase;

class FederationApiMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset static state via reflection
        $ref = new \ReflectionClass(FederationApiMiddleware::class);

        $partnerProp = $ref->getProperty('authenticatedPartner');
        $partnerProp->setAccessible(true);
        $partnerProp->setValue(null, null);

        $methodProp = $ref->getProperty('authMethod');
        $methodProp->setAccessible(true);
        $methodProp->setValue(null, null);
    }

    // -------------------------------------------------------
    // getAuthMethod()
    // -------------------------------------------------------

    public function test_getAuthMethod_returns_null_before_authentication(): void
    {
        $this->assertNull(FederationApiMiddleware::getAuthMethod());
    }

    // -------------------------------------------------------
    // getPartner()
    // -------------------------------------------------------

    public function test_getPartner_returns_null_before_authentication(): void
    {
        $this->assertNull(FederationApiMiddleware::getPartner());
    }

    // -------------------------------------------------------
    // getPartnerTenantId()
    // -------------------------------------------------------

    public function test_getPartnerTenantId_returns_null_before_authentication(): void
    {
        $this->assertNull(FederationApiMiddleware::getPartnerTenantId());
    }

    // -------------------------------------------------------
    // hasPermission()
    // -------------------------------------------------------

    public function test_hasPermission_returns_false_when_not_authenticated(): void
    {
        $this->assertFalse(FederationApiMiddleware::hasPermission('listings.read'));
    }

    public function test_hasPermission_returns_true_with_wildcard(): void
    {
        // Set up authenticated partner via reflection
        $ref = new \ReflectionClass(FederationApiMiddleware::class);
        $prop = $ref->getProperty('authenticatedPartner');
        $prop->setAccessible(true);
        $prop->setValue(null, [
            'id' => 1,
            'tenant_id' => 2,
            'permissions' => json_encode(['*']),
        ]);

        $this->assertTrue(FederationApiMiddleware::hasPermission('any.feature'));
    }

    public function test_hasPermission_returns_true_with_specific_permission(): void
    {
        $ref = new \ReflectionClass(FederationApiMiddleware::class);
        $prop = $ref->getProperty('authenticatedPartner');
        $prop->setAccessible(true);
        $prop->setValue(null, [
            'id' => 1,
            'tenant_id' => 2,
            'permissions' => json_encode(['listings.read', 'members.read']),
        ]);

        $this->assertTrue(FederationApiMiddleware::hasPermission('listings.read'));
        $this->assertFalse(FederationApiMiddleware::hasPermission('events.write'));
    }

    // -------------------------------------------------------
    // generateSigningSecret()
    // -------------------------------------------------------

    public function test_generateSigningSecret_returns_64_char_hex(): void
    {
        $secret = FederationApiMiddleware::generateSigningSecret();
        $this->assertSame(64, strlen($secret));
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $secret);
    }

    public function test_generateSigningSecret_produces_unique_values(): void
    {
        $s1 = FederationApiMiddleware::generateSigningSecret();
        $s2 = FederationApiMiddleware::generateSigningSecret();
        $this->assertNotSame($s1, $s2);
    }

    // -------------------------------------------------------
    // generateSignature()
    // -------------------------------------------------------

    public function test_generateSignature_produces_hmac_sha256(): void
    {
        $secret = 'test-secret';
        $sig = FederationApiMiddleware::generateSignature($secret, 'GET', '/api/v1/test', '1234567890');
        $this->assertSame(64, strlen($sig));
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $sig);
    }

    public function test_generateSignature_is_deterministic(): void
    {
        $secret = 'test-secret';
        $sig1 = FederationApiMiddleware::generateSignature($secret, 'POST', '/api/test', '123', '{"key":"value"}');
        $sig2 = FederationApiMiddleware::generateSignature($secret, 'POST', '/api/test', '123', '{"key":"value"}');
        $this->assertSame($sig1, $sig2);
    }

    public function test_generateSignature_differs_with_different_body(): void
    {
        $secret = 'test-secret';
        $sig1 = FederationApiMiddleware::generateSignature($secret, 'POST', '/api/test', '123', 'body-a');
        $sig2 = FederationApiMiddleware::generateSignature($secret, 'POST', '/api/test', '123', 'body-b');
        $this->assertNotSame($sig1, $sig2);
    }

    public function test_generateSignature_differs_with_different_method(): void
    {
        $secret = 'test-secret';
        $sig1 = FederationApiMiddleware::generateSignature($secret, 'GET', '/api/test', '123');
        $sig2 = FederationApiMiddleware::generateSignature($secret, 'POST', '/api/test', '123');
        $this->assertNotSame($sig1, $sig2);
    }
}
