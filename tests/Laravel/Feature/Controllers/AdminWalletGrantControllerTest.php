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
 * Feature tests for AdminWalletGrantController.
 *
 * Covers index (grant history) and store (grant credits).
 */
class AdminWalletGrantControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ================================================================
    // INDEX — GET /v2/admin/wallet/grants
    // ================================================================

    public function test_index_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/wallet/grants');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['grants', 'total', 'page', 'per_page'],
        ]);
    }

    public function test_index_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/wallet/grants');

        $response->assertStatus(403);
    }

    public function test_index_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/wallet/grants');

        $response->assertStatus(401);
    }

    // ================================================================
    // STORE — POST /v2/admin/wallet/grant
    // ================================================================

    public function test_store_returns_200_for_valid_grant(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $user = User::factory()->forTenant($this->testTenantId)->create(['balance' => 0]);
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/wallet/grant', [
            'user_id' => $user->id,
            'amount' => 5.0,
            'reason' => 'Welcome bonus',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'grant' => ['id', 'user_id', 'user_name', 'amount', 'reason', 'admin_id', 'status'],
                'message',
            ],
        ]);

        // Verify balance was updated
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'balance' => 5.0,
        ]);
    }

    public function test_store_requires_user_id(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/wallet/grant', [
            'amount' => 5.0,
            'reason' => 'Test',
        ]);

        $response->assertStatus(422);
    }

    public function test_store_requires_positive_amount(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/wallet/grant', [
            'user_id' => 1,
            'amount' => 0,
        ]);

        $response->assertStatus(422);
    }

    public function test_store_rejects_negative_amount(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/wallet/grant', [
            'user_id' => 1,
            'amount' => -5.0,
        ]);

        $response->assertStatus(422);
    }

    public function test_store_returns_404_for_nonexistent_user(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/wallet/grant', [
            'user_id' => 99999,
            'amount' => 5.0,
        ]);

        $response->assertStatus(404);
    }

    public function test_store_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/wallet/grant', [
            'user_id' => 1,
            'amount' => 5.0,
        ]);

        $response->assertStatus(403);
    }

    public function test_store_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiPost('/v2/admin/wallet/grant', [
            'user_id' => 1,
            'amount' => 5.0,
        ]);

        $response->assertStatus(401);
    }
}
