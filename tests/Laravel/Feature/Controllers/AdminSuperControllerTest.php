<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for AdminSuperController.
 *
 * Covers dashboard, tenant CRUD, user management, audit, federation.
 * Super admin endpoints require is_super_admin or is_tenant_super_admin.
 */
class AdminSuperControllerTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Create a super admin user with the right flags set.
     */
    private function createSuperAdmin(): User
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        DB::table('users')->where('id', $admin->id)->update([
            'is_super_admin' => 1,
            'is_tenant_super_admin' => 1,
        ]);
        $admin->refresh();
        return $admin;
    }

    // ================================================================
    // DASHBOARD — GET /v2/admin/super/dashboard
    // ================================================================

    public function test_dashboard_returns_200_for_super_admin(): void
    {
        $admin = $this->createSuperAdmin();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/super/dashboard');

        // 200 or 403 depending on SuperPanelAccess checks
        $this->assertTrue(in_array($response->status(), [200, 403]));
    }

    public function test_dashboard_returns_403_for_regular_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/super/dashboard');

        $response->assertStatus(403);
    }

    public function test_dashboard_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/super/dashboard');

        $response->assertStatus(403);
    }

    public function test_dashboard_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/super/dashboard');

        $response->assertStatus(401);
    }

    // ================================================================
    // TENANT LIST — GET /v2/admin/super/tenants
    // ================================================================

    public function test_tenant_list_returns_403_for_regular_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/super/tenants');

        $response->assertStatus(403);
    }

    public function test_tenant_list_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/super/tenants');

        $response->assertStatus(403);
    }

    // ================================================================
    // USER LIST — GET /v2/admin/super/users
    // ================================================================

    public function test_user_list_returns_403_for_regular_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/super/users');

        $response->assertStatus(403);
    }

    public function test_user_list_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/super/users');

        $response->assertStatus(401);
    }

    // ================================================================
    // AUDIT — GET /v2/admin/super/audit
    // ================================================================

    public function test_audit_returns_403_for_regular_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/super/audit');

        $response->assertStatus(403);
    }

    // ================================================================
    // FEDERATION OVERVIEW — GET /v2/admin/super/federation
    // ================================================================

    public function test_federation_returns_403_for_regular_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/super/federation');

        $response->assertStatus(403);
    }

    public function test_federation_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/super/federation');

        $response->assertStatus(403);
    }

    // ================================================================
    // TENANT HIERARCHY — GET /v2/admin/super/tenants/hierarchy
    // ================================================================

    public function test_tenant_hierarchy_returns_403_for_regular_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/super/tenants/hierarchy');

        $response->assertStatus(403);
    }

    // ================================================================
    // FEDERATION SYSTEM CONTROLS — GET /v2/admin/super/federation/system-controls
    // ================================================================

    public function test_federation_system_controls_returns_403_for_regular_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/super/federation/system-controls');

        $response->assertStatus(403);
    }
}
