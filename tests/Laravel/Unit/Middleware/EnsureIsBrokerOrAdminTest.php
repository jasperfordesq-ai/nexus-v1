<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Middleware;

use App\Http\Middleware\EnsureIsBrokerOrAdmin;
use App\Models\User;
use Illuminate\Http\Request;
use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class EnsureIsBrokerOrAdminTest extends TestCase
{
    use DatabaseTransactions;

    private EnsureIsBrokerOrAdmin $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new EnsureIsBrokerOrAdmin();
    }

    private function makeNext(): \Closure
    {
        return function ($request) {
            return response()->json(['ok' => true], 200);
        };
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $request = Request::create('/v2/admin/broker/users', 'GET');

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(401, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertEquals('auth_required', $data['errors'][0]['code']);
        $this->assertEquals('2.0', $response->headers->get('API-Version'));
    }

    public function test_plain_member_is_forbidden(): void
    {
        $user = User::factory()->create([
            'tenant_id'           => $this->testTenantId,
            'role'                => 'member',
            'is_admin'            => false,
            'is_super_admin'      => false,
            'is_tenant_super_admin' => false,
            'is_god'              => false,
        ]);

        $request = Request::create('/v2/admin/broker/users', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(403, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertEquals('forbidden', $data['errors'][0]['code']);
        $this->assertEquals('2.0', $response->headers->get('API-Version'));
    }

    public function test_broker_role_is_allowed(): void
    {
        $user = User::factory()->create([
            'tenant_id'  => $this->testTenantId,
            'role'       => 'broker',
            'is_admin'   => false,
        ]);

        $request = Request::create('/v2/admin/broker/users', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_coordinator_role_is_allowed(): void
    {
        $user = User::factory()->create([
            'tenant_id'  => $this->testTenantId,
            'role'       => 'coordinator',
            'is_admin'   => false,
        ]);

        $request = Request::create('/v2/admin/broker/users', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_is_admin_flag_is_allowed(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->testTenantId,
            'role'      => 'member',
            'is_admin'  => true,
        ]);

        $request = Request::create('/v2/admin/broker/users', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_is_super_admin_flag_is_allowed(): void
    {
        $user = User::factory()->create([
            'tenant_id'      => $this->testTenantId,
            'is_super_admin' => true,
        ]);

        $request = Request::create('/v2/admin/broker/users', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_is_tenant_super_admin_flag_is_allowed(): void
    {
        $user = User::factory()->create([
            'tenant_id'              => $this->testTenantId,
            'is_tenant_super_admin'  => true,
        ]);

        $request = Request::create('/v2/admin/broker/users', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_is_god_flag_is_allowed(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->testTenantId,
            'is_god'    => true,
        ]);

        $request = Request::create('/v2/admin/broker/users', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_admin_role_is_allowed(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->testTenantId,
            'role'      => 'admin',
            'is_admin'  => false,
        ]);

        $request = Request::create('/v2/admin/broker/users', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_tenant_admin_role_is_allowed(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->testTenantId,
            'role'      => 'tenant_admin',
            'is_admin'  => false,
        ]);

        $request = Request::create('/v2/admin/broker/users', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_super_admin_role_is_allowed(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->testTenantId,
            'role'      => 'super_admin',
            'is_admin'  => false,
        ]);

        $request = Request::create('/v2/admin/broker/users', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_god_role_is_allowed(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->testTenantId,
            'role'      => 'god',
            'is_admin'  => false,
        ]);

        $request = Request::create('/v2/admin/broker/users', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
    }
}
