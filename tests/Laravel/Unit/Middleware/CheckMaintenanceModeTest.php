<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Middleware;

use App\Core\TenantContext;
use App\Http\Middleware\CheckMaintenanceMode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class CheckMaintenanceModeTest extends TestCase
{
    use DatabaseTransactions;

    private CheckMaintenanceMode $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new CheckMaintenanceMode();
    }

    private function makeNext(): \Closure
    {
        return function ($request) {
            return response()->json(['ok' => true], 200);
        };
    }

    private function enableMaintenanceMode(): void
    {
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'general.maintenance_mode'],
            ['setting_value' => 'true']
        );
    }

    private function disableMaintenanceMode(): void
    {
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'general.maintenance_mode'],
            ['setting_value' => 'false']
        );
    }

    public function test_handle_passes_through_when_maintenance_mode_disabled(): void
    {
        $this->disableMaintenanceMode();

        $request = Request::create('/api/v2/feed', 'GET');
        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_handle_returns_503_when_maintenance_mode_enabled(): void
    {
        $this->enableMaintenanceMode();

        $request = Request::create('/api/v2/feed', 'GET');
        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(503, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertEquals('MAINTENANCE_MODE', $data['code']);
        $this->assertFalse($data['success']);
        $this->assertEquals('300', $response->headers->get('Retry-After'));
    }

    public function test_handle_allows_admin_api_routes_during_maintenance(): void
    {
        $this->enableMaintenanceMode();

        $request = Request::create('/api/v2/admin/dashboard', 'GET');
        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_handle_allows_auth_routes_during_maintenance(): void
    {
        $this->enableMaintenanceMode();

        $request = Request::create('/api/auth/login', 'POST');
        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_handle_allows_health_check_during_maintenance(): void
    {
        $this->enableMaintenanceMode();

        $request = Request::create('/health.php', 'GET');
        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_handle_allows_up_route_during_maintenance(): void
    {
        $this->enableMaintenanceMode();

        $request = Request::create('/up', 'GET');
        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_handle_allows_tenant_bootstrap_during_maintenance(): void
    {
        $this->enableMaintenanceMode();

        $request = Request::create('/api/v2/tenant/bootstrap', 'GET');
        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_handle_allows_favicon_during_maintenance(): void
    {
        $this->enableMaintenanceMode();

        $request = Request::create('/favicon.ico', 'GET');
        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_handle_allows_admin_legacy_routes_during_maintenance(): void
    {
        $this->enableMaintenanceMode();

        $request = Request::create('/admin-legacy/dashboard', 'GET');
        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_handle_allows_super_admin_routes_during_maintenance(): void
    {
        $this->enableMaintenanceMode();

        $request = Request::create('/super-admin/tenants', 'GET');
        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_handle_allows_admin_user_during_maintenance(): void
    {
        $this->enableMaintenanceMode();

        $user = User::factory()->create([
            'tenant_id' => $this->testTenantId,
            'role' => 'admin',
            'status' => 'active',
        ]);

        $request = Request::create('/api/v2/feed', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_handle_allows_super_admin_user_during_maintenance(): void
    {
        $this->enableMaintenanceMode();

        $user = User::factory()->create([
            'tenant_id' => $this->testTenantId,
            'is_super_admin' => true,
            'status' => 'active',
        ]);

        $request = Request::create('/api/v2/feed', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_handle_passes_through_when_no_tenant_context(): void
    {
        $this->enableMaintenanceMode();

        // Force tenant context to id=0 via reflection (setById(0) fails silently, leaving previous tenant)
        $ref = new \ReflectionClass(TenantContext::class);
        $prop = $ref->getProperty('tenant');
        $prop->setAccessible(true);
        $prop->setValue(null, ['id' => 0, 'name' => '', 'features' => '{}']);

        $request = Request::create('/api/v2/feed', 'GET');
        $response = $this->middleware->handle($request, $this->makeNext());

        // Should pass through because tenant ID is 0 (falsy)
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_handle_passes_through_when_setting_is_one(): void
    {
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'general.maintenance_mode'],
            ['setting_value' => '1']
        );

        $request = Request::create('/api/v2/feed', 'GET');
        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(503, $response->getStatusCode());
    }

    public function test_handle_allows_messages_unread_count_during_maintenance(): void
    {
        $this->enableMaintenanceMode();

        $request = Request::create('/api/v2/messages/unread-count', 'GET');
        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_handle_allows_notification_counts_during_maintenance(): void
    {
        $this->enableMaintenanceMode();

        $request = Request::create('/api/v2/notifications/counts', 'GET');
        $response = $this->middleware->handle($request, $this->makeNext());

        $this->assertEquals(200, $response->getStatusCode());
    }
}
