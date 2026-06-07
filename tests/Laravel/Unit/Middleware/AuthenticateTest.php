<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Middleware;

use App\Core\TenantContext;
use App\Http\Middleware\Authenticate;
use App\Models\User;
use App\Services\TokenService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;

class AuthenticateTest extends TestCase
{
    use DatabaseTransactions;

    private Authenticate $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new Authenticate();
    }

    private function makeNext(): \Closure
    {
        return function ($request) {
            return response()->json(['ok' => true], 200);
        };
    }

    public function test_handle_unauthenticated_request_returns_401(): void
    {
        $request = Request::create('/api/v2/feed', 'GET');

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertEquals('auth_required', $data['errors'][0]['code']);
        $this->assertEquals('2.0', $response->headers->get('API-Version'));
    }

    public function test_handle_sanctum_authenticated_user_passes_through(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->testTenantId,
            'status' => 'active',
        ]);

        // Creating the user fires observers that reset TenantContext to tenant 1;
        // re-pin tenant 2 so the middleware's tenant check sees the request tenant
        // the user actually belongs to.
        TenantContext::setById($this->testTenantId);

        $request = Request::create('/api/v2/feed', 'GET');
        $this->actingAs($user, 'sanctum');

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['ok']);
    }

    public function test_handle_sanctum_user_wrong_tenant_returns_403(): void
    {
        // Create a second tenant
        \Illuminate\Support\Facades\DB::table('tenants')->insertOrIgnore([
            'id' => 99,
            'name' => 'Other Tenant',
            'slug' => 'other-tenant',
            'is_active' => true,
            'depth' => 0,
            'allows_subtenants' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'tenant_id' => 99,
            'status' => 'active',
            'is_super_admin' => false,
            'is_tenant_super_admin' => false,
        ]);

        // Re-pin tenant 2 after the create (observers reset TenantContext to 1).
        TenantContext::setById($this->testTenantId);

        $request = Request::create('/api/v2/feed', 'GET');
        $this->actingAs($user, 'sanctum');

        // TenantContext is set to tenant 2 by setUp(), user belongs to tenant 99
        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(403, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertEquals('tenant_mismatch', $data['errors'][0]['code']);
    }

    public function test_handle_super_admin_bypasses_tenant_check(): void
    {
        \Illuminate\Support\Facades\DB::table('tenants')->insertOrIgnore([
            'id' => 99,
            'name' => 'Other Tenant',
            'slug' => 'other-tenant',
            'is_active' => true,
            'depth' => 0,
            'allows_subtenants' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'tenant_id' => 99,
            'status' => 'active',
            'is_super_admin' => true,
        ]);

        // Re-pin tenant 2 after the create (observers reset TenantContext to 1).
        TenantContext::setById($this->testTenantId);

        $request = Request::create('/api/v2/feed', 'GET');
        $this->actingAs($user, 'sanctum');

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * A TENANT super-admin is scoped to its own tenant and must NOT cross into
     * another tenant. This was hardened in commit f1a6b4ab0 ("resolve platform
     * audit findings"): only platform super_admin / god / is_super_admin / is_god
     * bypass the cross-tenant check. The legacy bypass for is_tenant_super_admin
     * was deliberately removed.
     */
    public function test_handle_tenant_super_admin_does_not_bypass_tenant_check(): void
    {
        \Illuminate\Support\Facades\DB::table('tenants')->insertOrIgnore([
            'id' => 99,
            'name' => 'Other Tenant',
            'slug' => 'other-tenant',
            'is_active' => true,
            'depth' => 0,
            'allows_subtenants' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'tenant_id' => 99,
            'status' => 'active',
            'is_super_admin' => false,
            'is_god' => false,
            'is_tenant_super_admin' => true,
        ]);

        // Re-pin tenant 2 after the create (observers reset TenantContext to 1).
        TenantContext::setById($this->testTenantId);

        $request = Request::create('/api/v2/feed', 'GET');
        $this->actingAs($user, 'sanctum');

        $response = $this->middleware->handle($request, $this->makeNext());

        // Tenant super-admin in tenant 99 cannot access tenant 2 → 403.
        $this->assertEquals(403, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertEquals('tenant_mismatch', $data['errors'][0]['code']);
    }

    public function test_handle_invalid_bearer_token_returns_401(): void
    {
        $request = Request::create('/api/v2/feed', 'GET');
        $request->headers->set('Authorization', 'Bearer invalid-token-here');

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_handle_no_authorization_header_returns_401(): void
    {
        $request = Request::create('/api/v2/feed', 'GET');

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(401, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertEquals('auth_required', $data['errors'][0]['code']);
    }
}
