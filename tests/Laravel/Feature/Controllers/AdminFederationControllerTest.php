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
 * Feature tests for AdminFederationController.
 *
 * Covers settings, partnerships, directory, profile, analytics, API keys, and data management.
 */
class AdminFederationControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ================================================================
    // SETTINGS — GET /v2/admin/federation/settings
    // ================================================================

    public function test_settings_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/federation/settings');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_settings_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/federation/settings');

        $response->assertStatus(403);
    }

    public function test_settings_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/federation/settings');

        $response->assertStatus(401);
    }

    // ================================================================
    // PARTNERSHIPS — GET /v2/admin/federation/partnerships
    // ================================================================

    public function test_partnerships_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/federation/partnerships');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_partnerships_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/federation/partnerships');

        $response->assertStatus(403);
    }

    // ================================================================
    // DIRECTORY — GET /v2/admin/federation/directory
    // ================================================================

    public function test_directory_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/federation/directory');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_directory_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/federation/directory');

        $response->assertStatus(403);
    }

    // ================================================================
    // PROFILE — GET /v2/admin/federation/directory/profile
    // ================================================================

    public function test_profile_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/federation/directory/profile');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // ANALYTICS — GET /v2/admin/federation/analytics
    // ================================================================

    public function test_analytics_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/federation/analytics');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_analytics_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/federation/analytics');

        $response->assertStatus(403);
    }

    // ================================================================
    // API KEYS — GET /v2/admin/federation/api-keys
    // ================================================================

    public function test_api_keys_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/federation/api-keys');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_api_keys_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/federation/api-keys');

        $response->assertStatus(403);
    }

    // ================================================================
    // DATA MANAGEMENT — GET /v2/admin/federation/data
    // ================================================================

    public function test_data_management_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/federation/data');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_data_management_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/federation/data');

        $response->assertStatus(401);
    }
}
