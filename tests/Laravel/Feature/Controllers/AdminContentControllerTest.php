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
 * Feature tests for AdminContentController.
 *
 * Covers pages, menus, menu items, plans, and subscriptions.
 */
class AdminContentControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ================================================================
    // PAGES — GET /v2/admin/pages
    // ================================================================

    public function test_get_pages_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/pages');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_get_pages_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/pages');

        $response->assertStatus(403);
    }

    public function test_get_pages_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/pages');

        $response->assertStatus(401);
    }

    // ================================================================
    // CREATE PAGE — POST /v2/admin/pages
    // ================================================================

    public function test_create_page_returns_201_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/pages', [
            'title' => 'Test Page',
            'slug' => 'test-page',
            'content' => '<p>Test content</p>',
            'status' => 'published',
        ]);

        // Accept 200 or 201 depending on implementation
        $this->assertContains($response->getStatusCode(), [200, 201]);
        $response->assertJsonStructure(['data']);
    }

    public function test_create_page_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/pages', [
            'title' => 'Should Fail',
        ]);

        $response->assertStatus(403);
    }

    // ================================================================
    // MENUS — GET /v2/admin/menus
    // ================================================================

    public function test_get_menus_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/menus');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_get_menus_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/menus');

        $response->assertStatus(403);
    }

    // ================================================================
    // CREATE MENU — POST /v2/admin/menus
    // ================================================================

    public function test_create_menu_returns_success_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/menus', [
            'name' => 'Test Menu',
            'location' => 'header',
        ]);

        $this->assertContains($response->getStatusCode(), [200, 201]);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // PLANS — GET /v2/admin/plans
    // ================================================================

    public function test_get_plans_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/plans');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_get_plans_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/plans');

        $response->assertStatus(403);
    }

    // ================================================================
    // SUBSCRIPTIONS — GET /v2/admin/subscriptions
    // ================================================================

    public function test_get_subscriptions_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/subscriptions');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_get_subscriptions_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/subscriptions');

        $response->assertStatus(401);
    }
}
