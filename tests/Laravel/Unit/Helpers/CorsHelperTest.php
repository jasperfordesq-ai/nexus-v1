<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Helpers;

use App\Helpers\CorsHelper;
use PHPUnit\Framework\TestCase;

class CorsHelperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $ref = new \ReflectionClass(CorsHelper::class);

        $prop = $ref->getProperty('allowedOrigins');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        $prop2 = $ref->getProperty('tenantDomainOrigins');
        $prop2->setAccessible(true);
        $prop2->setValue(null, null);

        unset($_SERVER['HTTP_ORIGIN']);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_ORIGIN']);

        $ref = new \ReflectionClass(CorsHelper::class);
        $prop = $ref->getProperty('allowedOrigins');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        $prop2 = $ref->getProperty('tenantDomainOrigins');
        $prop2->setAccessible(true);
        $prop2->setValue(null, null);

        parent::tearDown();
    }

    // -------------------------------------------------------
    // isOriginAllowed()
    // -------------------------------------------------------

    public function test_isOriginAllowed_direct_match(): void
    {
        $this->assertTrue(CorsHelper::isOriginAllowed(
            'http://localhost:5173',
            ['http://localhost:5173', 'https://example.com']
        ));
    }

    public function test_isOriginAllowed_rejects_unknown(): void
    {
        $this->assertFalse(CorsHelper::isOriginAllowed(
            'https://evil.com',
            ['https://project-nexus.ie']
        ));
    }

    public function test_isOriginAllowed_subdomain_match(): void
    {
        $this->assertTrue(CorsHelper::isOriginAllowed(
            'https://app.project-nexus.ie',
            ['https://project-nexus.ie']
        ));
    }

    public function test_isOriginAllowed_rejects_scheme_mismatch(): void
    {
        $this->assertFalse(CorsHelper::isOriginAllowed(
            'http://app.project-nexus.ie',
            ['https://project-nexus.ie']
        ));
    }

    // -------------------------------------------------------
    // addAllowedOrigin()
    // -------------------------------------------------------

    public function test_addAllowedOrigin_adds_new_origin(): void
    {
        CorsHelper::addAllowedOrigin('https://new-domain.com');
        $origins = CorsHelper::getAllowedOrigins();
        $this->assertContains('https://new-domain.com', $origins);
    }

    public function test_addAllowedOrigin_strips_trailing_slash(): void
    {
        CorsHelper::addAllowedOrigin('https://with-slash.com/');
        $origins = CorsHelper::getAllowedOrigins();
        $this->assertContains('https://with-slash.com', $origins);
    }

    public function test_addAllowedOrigin_ignores_empty(): void
    {
        $before = count(CorsHelper::getAllowedOrigins());
        CorsHelper::addAllowedOrigin('');
        $after = count(CorsHelper::getAllowedOrigins());
        $this->assertSame($before, $after);
    }

    // -------------------------------------------------------
    // getAllowedOrigins()
    // -------------------------------------------------------

    public function test_getAllowedOrigins_returns_non_empty_array(): void
    {
        $origins = CorsHelper::getAllowedOrigins();
        $this->assertIsArray($origins);
        $this->assertNotEmpty($origins);
    }

    public function test_getAllowedOrigins_includes_localhost(): void
    {
        $origins = CorsHelper::getAllowedOrigins();
        $this->assertContains('http://localhost:5173', $origins);
    }
}
