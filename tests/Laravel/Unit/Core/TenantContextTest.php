<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Core;

use App\Core\TenantContext;
use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class TenantContextTest extends TestCase
{
    use DatabaseTransactions;

    // -------------------------------------------------------
    // get() / getId()
    // -------------------------------------------------------

    public function test_get_returns_tenant_array(): void
    {
        $tenant = TenantContext::get();
        $this->assertIsArray($tenant);
        $this->assertArrayHasKey('id', $tenant);
    }

    public function test_getId_returns_integer(): void
    {
        $id = TenantContext::getId();
        $this->assertIsInt($id);
        $this->assertSame($this->testTenantId, $id);
    }

    // -------------------------------------------------------
    // setById()
    // -------------------------------------------------------

    public function test_setById_with_valid_tenant_returns_true(): void
    {
        $this->assertTrue(TenantContext::setById($this->testTenantId));
        $this->assertSame($this->testTenantId, TenantContext::getId());
    }

    public function test_setById_with_invalid_tenant_returns_false(): void
    {
        $this->assertFalse(TenantContext::setById(99999));
    }

    // -------------------------------------------------------
    // getBasePath()
    // -------------------------------------------------------

    public function test_getBasePath_returns_string(): void
    {
        $basePath = TenantContext::getBasePath();
        $this->assertIsString($basePath);
    }

    // -------------------------------------------------------
    // getSlugPrefix()
    // -------------------------------------------------------

    public function test_getSlugPrefix_returns_slug_with_slash_for_non_master(): void
    {
        TenantContext::setById($this->testTenantId);
        $prefix = TenantContext::getSlugPrefix();
        $this->assertSame('/hour-timebank', $prefix);
    }

    // -------------------------------------------------------
    // getSetting()
    // -------------------------------------------------------

    public function test_getSetting_site_name_returns_tenant_name(): void
    {
        $name = TenantContext::getSetting('site_name');
        $this->assertSame('Hour Timebank', $name);
    }

    public function test_getSetting_returns_default_for_unknown_key(): void
    {
        $result = TenantContext::getSetting('totally_unknown_key', 'default_val');
        $this->assertSame('default_val', $result);
    }

    // -------------------------------------------------------
    // hasFeature()
    // -------------------------------------------------------

    public function test_hasFeature_returns_bool(): void
    {
        // With default features, most should be true
        $result = TenantContext::hasFeature('listings');
        $this->assertIsBool($result);
    }

    // -------------------------------------------------------
    // getFrontendUrl()
    // -------------------------------------------------------

    public function test_getFrontendUrl_returns_string(): void
    {
        $url = TenantContext::getFrontendUrl();
        $this->assertIsString($url);
        $this->assertNotEmpty($url);
    }

    // -------------------------------------------------------
    // getDomain()
    // -------------------------------------------------------

    public function test_getDomain_returns_string(): void
    {
        $domain = TenantContext::getDomain();
        $this->assertIsString($domain);
    }

    // -------------------------------------------------------
    // getHeaderTenantId() / getTokenTenantId()
    // -------------------------------------------------------

    public function test_getHeaderTenantId_returns_null_by_default(): void
    {
        // After setById, header tenant ID is not set
        $this->assertNull(TenantContext::getHeaderTenantId());
    }

    public function test_getTokenTenantId_returns_null_by_default(): void
    {
        $this->assertNull(TenantContext::getTokenTenantId());
    }
}
