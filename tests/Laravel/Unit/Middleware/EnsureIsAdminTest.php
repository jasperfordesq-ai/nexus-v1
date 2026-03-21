<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Middleware;

use App\Http\Middleware\EnsureIsAdmin;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class EnsureIsAdminTest extends TestCase
{
    use DatabaseTransactions;

    private EnsureIsAdmin $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new EnsureIsAdmin();
    }

    private function makeNext(): \Closure
    {
        return function ($request) {
            return response()->json(['ok' => true], 200);
        };
    }

    public function test_handle_unauthenticated_returns_401(): void
    {
        $request = Request::create('/api/v2/admin/dashboard', 'GET');

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(401, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertEquals('auth_required', $data['errors'][0]['code']);
        $this->assertEquals('2.0', $response->headers->get('API-Version'));
    }

    public function test_handle_non_admin_user_returns_403(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->testTenantId,
            'role' => 'member',
            'is_admin' => false,
            'is_super_admin' => false,
            'is_tenant_super_admin' => false,
        ]);

        $request = Request::create('/api/v2/admin/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(403, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertEquals('forbidden', $data['errors'][0]['code']);
        $this->assertStringContainsString('Admin access required', $data['errors'][0]['message']);
    }

    public function test_handle_admin_user_passes_through(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->testTenantId,
            'is_admin' => true,
        ]);

        $request = Request::create('/api/v2/admin/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_handle_super_admin_passes_through(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->testTenantId,
            'is_super_admin' => true,
        ]);

        $request = Request::create('/api/v2/admin/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_handle_tenant_super_admin_passes_through(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->testTenantId,
            'is_tenant_super_admin' => true,
        ]);

        $request = Request::create('/api/v2/admin/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_handle_user_with_admin_role_passes_through(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->testTenantId,
            'role' => 'admin',
            'is_admin' => false,
        ]);

        $request = Request::create('/api/v2/admin/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_handle_user_with_tenant_admin_role_passes_through(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->testTenantId,
            'role' => 'tenant_admin',
            'is_admin' => false,
        ]);

        $request = Request::create('/api/v2/admin/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_handle_user_with_super_admin_role_passes_through(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->testTenantId,
            'role' => 'super_admin',
            'is_admin' => false,
        ]);

        $request = Request::create('/api/v2/admin/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_handle_god_user_passes_through(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->testTenantId,
            'is_god' => true,
        ]);

        $request = Request::create('/api/v2/admin/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
    }
}
