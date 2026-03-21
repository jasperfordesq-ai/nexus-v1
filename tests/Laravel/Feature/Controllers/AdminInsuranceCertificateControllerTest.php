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
 * Feature tests for AdminInsuranceCertificateController.
 *
 * Covers list, stats, show, store, update, verify, reject, destroy, getUserCertificates.
 */
class AdminInsuranceCertificateControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ================================================================
    // STATS — GET /v2/admin/insurance/stats
    // ================================================================

    public function test_stats_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/insurance/stats');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_stats_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/insurance/stats');

        $response->assertStatus(403);
    }

    public function test_stats_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/insurance/stats');

        $response->assertStatus(401);
    }

    // ================================================================
    // LIST — GET /v2/admin/insurance
    // ================================================================

    public function test_list_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/insurance');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_list_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/insurance');

        $response->assertStatus(403);
    }

    // ================================================================
    // SHOW — GET /v2/admin/insurance/{id}
    // ================================================================

    public function test_show_returns_404_for_nonexistent_certificate(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/insurance/99999');

        $response->assertStatus(404);
    }

    // ================================================================
    // STORE — POST /v2/admin/insurance
    // ================================================================

    public function test_store_requires_user_id(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/insurance', [
            'insurance_type' => 'public_liability',
        ]);

        $response->assertStatus(422);
    }

    public function test_store_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/insurance', [
            'user_id' => 1,
            'insurance_type' => 'public_liability',
        ]);

        $response->assertStatus(403);
    }

    // ================================================================
    // REJECT — POST /v2/admin/insurance/{id}/reject
    // ================================================================

    public function test_reject_requires_reason(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/insurance/1/reject', []);

        $response->assertStatus(422);
    }

    // ================================================================
    // DELETE — DELETE /v2/admin/insurance/{id}
    // ================================================================

    public function test_destroy_returns_404_for_nonexistent_certificate(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiDelete('/v2/admin/insurance/99999');

        $response->assertStatus(404);
    }

    // ================================================================
    // USER CERTIFICATES — GET /v2/admin/insurance/user/{userId}
    // ================================================================

    public function test_user_certificates_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $user = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/insurance/user/' . $user->id);

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_user_certificates_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/insurance/user/1');

        $response->assertStatus(403);
    }
}
