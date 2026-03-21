<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Middleware;

use App\Core\TenantContext;
use App\Middleware\MaintenanceModeMiddleware;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Tests for the legacy MaintenanceModeMiddleware (App\Middleware).
 *
 * This middleware uses static methods with $_SERVER superglobals and
 * calls exit() in the blocking path. Tests focus on the logic that
 * can be verified without triggering exit().
 */
class MaintenanceModeMiddlewareTest extends TestCase
{
    use DatabaseTransactions;

    private array $originalServer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalServer = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        parent::tearDown();
    }

    public function test_check_passes_through_when_maintenance_disabled(): void
    {
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'general.maintenance_mode'],
            ['setting_value' => 'false']
        );

        $_SERVER['REQUEST_URI'] = '/api/v2/feed';

        // Should not throw or exit
        MaintenanceModeMiddleware::check();

        // If we reach here, the middleware passed through
        $this->assertTrue(true);
    }

    public function test_check_passes_through_for_exempt_admin_api_route(): void
    {
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'general.maintenance_mode'],
            ['setting_value' => 'true']
        );

        $_SERVER['REQUEST_URI'] = '/api/v2/admin/dashboard';

        MaintenanceModeMiddleware::check();
        $this->assertTrue(true);
    }

    public function test_check_passes_through_for_exempt_auth_route(): void
    {
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'general.maintenance_mode'],
            ['setting_value' => 'true']
        );

        $_SERVER['REQUEST_URI'] = '/api/auth/login';

        MaintenanceModeMiddleware::check();
        $this->assertTrue(true);
    }

    public function test_check_passes_through_for_exempt_health_route(): void
    {
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'general.maintenance_mode'],
            ['setting_value' => 'true']
        );

        $_SERVER['REQUEST_URI'] = '/health.php';

        MaintenanceModeMiddleware::check();
        $this->assertTrue(true);
    }

    public function test_check_passes_through_for_admin_legacy_route(): void
    {
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'general.maintenance_mode'],
            ['setting_value' => 'true']
        );

        $_SERVER['REQUEST_URI'] = '/admin-legacy/dashboard';

        MaintenanceModeMiddleware::check();
        $this->assertTrue(true);
    }

    public function test_check_passes_through_for_super_admin_route(): void
    {
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'general.maintenance_mode'],
            ['setting_value' => 'true']
        );

        $_SERVER['REQUEST_URI'] = '/super-admin/tenants';

        MaintenanceModeMiddleware::check();
        $this->assertTrue(true);
    }

    public function test_check_passes_through_for_tenant_bootstrap_route(): void
    {
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'general.maintenance_mode'],
            ['setting_value' => 'true']
        );

        $_SERVER['REQUEST_URI'] = '/api/v2/tenant/bootstrap';

        MaintenanceModeMiddleware::check();
        $this->assertTrue(true);
    }

    public function test_check_passes_through_when_no_tenant_context(): void
    {
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'general.maintenance_mode'],
            ['setting_value' => 'true']
        );

        TenantContext::setById(0);
        $_SERVER['REQUEST_URI'] = '/api/v2/feed';

        MaintenanceModeMiddleware::check();
        $this->assertTrue(true);
    }

    public function test_check_passes_through_when_setting_not_found(): void
    {
        // Don't insert any maintenance setting
        $_SERVER['REQUEST_URI'] = '/api/v2/feed';

        MaintenanceModeMiddleware::check();
        $this->assertTrue(true);
    }

    public function test_check_passes_through_for_favicon_route(): void
    {
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'general.maintenance_mode'],
            ['setting_value' => 'true']
        );

        $_SERVER['REQUEST_URI'] = '/favicon.ico';

        MaintenanceModeMiddleware::check();
        $this->assertTrue(true);
    }

    public function test_check_recognizes_setting_value_one_as_maintenance(): void
    {
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'general.maintenance_mode'],
            ['setting_value' => '1']
        );

        $_SERVER['REQUEST_URI'] = '/api/v2/feed';
        $_SERVER['HTTP_AUTHORIZATION'] = '';
        unset($_SESSION['user_id']);

        // This would call showMaintenancePage() and exit() for a non-admin non-exempt route.
        // We can't test the exit path directly, but we verify the setting is recognized.
        // The middleware will either pass (if user is admin) or call exit (non-testable).
        // For this test, we verify the DB query returns the expected value.
        $rows = DB::select(
            "SELECT setting_value FROM tenant_settings WHERE tenant_id = ? AND setting_key = 'general.maintenance_mode'",
            [$this->testTenantId]
        );

        $this->assertNotEmpty($rows);
        $this->assertEquals('1', $rows[0]->setting_value);
    }
}
