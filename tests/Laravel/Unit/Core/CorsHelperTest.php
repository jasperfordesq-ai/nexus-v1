<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Core;

use App\Core\CorsHelper;
use PHPUnit\Framework\TestCase;

class CorsHelperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset static caches via reflection
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

        // Reset static caches
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

    public function test_isOriginAllowed_with_default_origin_returns_true(): void
    {
        $this->assertTrue(CorsHelper::isOriginAllowed(
            'http://localhost:5173',
            ['http://localhost:5173']
        ));
    }

    public function test_isOriginAllowed_with_unknown_origin_returns_false(): void
    {
        $this->assertFalse(CorsHelper::isOriginAllowed(
            'https://evil.example.com',
            ['https://project-nexus.ie']
        ));
    }

    public function test_isOriginAllowed_with_subdomain_match_returns_true(): void
    {
        $this->assertTrue(CorsHelper::isOriginAllowed(
            'https://app.project-nexus.ie',
            ['https://project-nexus.ie']
        ));
    }

    public function test_isOriginAllowed_rejects_scheme_mismatch(): void
    {
        // http subdomain of https allowed origin
        $this->assertFalse(CorsHelper::isOriginAllowed(
            'http://app.project-nexus.ie',
            ['https://project-nexus.ie']
        ));
    }

    public function test_isOriginAllowed_rejects_null_host(): void
    {
        $this->assertFalse(CorsHelper::isOriginAllowed(
            'not-a-valid-origin',
            ['https://project-nexus.ie']
        ));
    }

    // -------------------------------------------------------
    // addAllowedOrigin()
    // -------------------------------------------------------

    public function test_addAllowedOrigin_adds_to_list(): void
    {
        CorsHelper::addAllowedOrigin('https://custom-domain.com');
        $origins = CorsHelper::getAllowedOrigins();
        $this->assertContains('https://custom-domain.com', $origins);
    }

    public function test_addAllowedOrigin_strips_trailing_slash(): void
    {
        CorsHelper::addAllowedOrigin('https://trailing-slash.com/');
        $origins = CorsHelper::getAllowedOrigins();
        $this->assertContains('https://trailing-slash.com', $origins);
    }

    public function test_addAllowedOrigin_does_not_add_empty(): void
    {
        $before = count(CorsHelper::getAllowedOrigins());
        CorsHelper::addAllowedOrigin('');
        $after = count(CorsHelper::getAllowedOrigins());
        $this->assertSame($before, $after);
    }

    public function test_addAllowedOrigin_does_not_duplicate(): void
    {
        CorsHelper::addAllowedOrigin('http://localhost:5173');
        $origins = CorsHelper::getAllowedOrigins();
        $count = array_count_values($origins)['http://localhost:5173'] ?? 0;
        $this->assertSame(1, $count);
    }

    // -------------------------------------------------------
    // getAllowedOrigins()
    // -------------------------------------------------------

    public function test_getAllowedOrigins_returns_array(): void
    {
        $origins = CorsHelper::getAllowedOrigins();
        $this->assertIsArray($origins);
        $this->assertNotEmpty($origins);
    }
}
