<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Integration tests for admin endpoint access control.
 *
 * Verifies that the `admin` middleware correctly blocks non-admin users
 * from accessing sensitive admin API endpoints via real HTTP requests.
 *
 * While EnsureIsAdminTest covers the middleware in isolation, these tests
 * confirm the middleware is wired correctly on actual routes.
 */
class AdminAccessControlTest extends TestCase
{
    use DatabaseTransactions;

    private function authenticatedMember(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
            'role' => 'member',
        ], $overrides));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function authenticatedAdmin(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->admin()->create($overrides);

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    // ================================================================
    // ADMIN DASHBOARD
    // ================================================================

    public function test_admin_dashboard_stats_returns_403_for_member(): void
    {
        $this->authenticatedMember();

        $response = $this->apiGet('/v2/admin/dashboard/stats');

        $response->assertStatus(403);
    }

    public function test_admin_dashboard_stats_returns_200_for_admin(): void
    {
        $this->authenticatedAdmin();

        $response = $this->apiGet('/v2/admin/dashboard/stats');

        $response->assertStatus(200);
    }

    // ================================================================
    // ADMIN USERS
    // ================================================================

    public function test_admin_users_list_returns_403_for_member(): void
    {
        $this->authenticatedMember();

        $response = $this->apiGet('/v2/admin/users');

        $response->assertStatus(403);
    }

    public function test_admin_users_list_returns_200_for_admin(): void
    {
        $this->authenticatedAdmin();

        $response = $this->apiGet('/v2/admin/users');

        $response->assertStatus(200);
    }

    // ================================================================
    // ADMIN LISTINGS
    // ================================================================

    public function test_admin_listings_returns_403_for_member(): void
    {
        $this->authenticatedMember();

        $response = $this->apiGet('/v2/admin/listings');

        $response->assertStatus(403);
    }

    public function test_admin_listings_returns_200_for_admin(): void
    {
        $this->authenticatedAdmin();

        $response = $this->apiGet('/v2/admin/listings');

        $response->assertStatus(200);
    }

    // ================================================================
    // UNAUTHENTICATED — all admin routes return 401
    // ================================================================

    public function test_admin_dashboard_returns_401_without_auth(): void
    {
        $response = $this->apiGet('/v2/admin/dashboard/stats');

        $response->assertStatus(401);
    }

    public function test_admin_users_returns_401_without_auth(): void
    {
        $response = $this->apiGet('/v2/admin/users');

        $response->assertStatus(401);
    }

    public function test_admin_listings_returns_401_without_auth(): void
    {
        $response = $this->apiGet('/v2/admin/listings');

        $response->assertStatus(401);
    }

    // ================================================================
    // ROLE-BASED ADMIN ACCESS
    // ================================================================

    public function test_user_with_admin_role_can_access_admin_endpoints(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
            'role' => 'admin',
        ]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->apiGet('/v2/admin/dashboard/stats');

        $response->assertStatus(200);
    }

    public function test_user_with_tenant_admin_role_can_access_admin_endpoints(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
            'role' => 'tenant_admin',
        ]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->apiGet('/v2/admin/dashboard/stats');

        $response->assertStatus(200);
    }
}
