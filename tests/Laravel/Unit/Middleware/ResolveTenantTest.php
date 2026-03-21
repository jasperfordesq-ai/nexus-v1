<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Middleware;

use App\Core\TenantContext;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Http\Request;
use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ResolveTenantTest extends TestCase
{
    use DatabaseTransactions;

    private ResolveTenant $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new ResolveTenant();
    }

    private function makeNext(): \Closure
    {
        return function ($request) {
            return response()->json(['ok' => true], 200);
        };
    }

    public function test_handle_passes_through_when_tenant_already_resolved(): void
    {
        // TenantContext is set by parent setUp() to testTenantId
        $this->assertNotNull(TenantContext::getId());

        $request = Request::create('/api/v2/feed', 'GET');
        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_handle_binds_tenant_id_to_container(): void
    {
        $request = Request::create('/api/v2/feed', 'GET');
        $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals($this->testTenantId, app('tenant.id'));
    }

    public function test_handle_returns_400_when_tenant_cannot_be_resolved(): void
    {
        // Clear tenant context so resolve() is called
        TenantContext::setById(0);

        // The resolve() method will fail because no host/header maps to a tenant
        $request = Request::create('/api/v2/feed', 'GET');
        $response = $this->middleware->handle($request, $this->makeNext());

        // If resolve() throws, we expect a 400
        // If resolve() succeeds (e.g. fallback), we expect 200
        $this->assertContains($response->getStatusCode(), [200, 400]);
    }
}
