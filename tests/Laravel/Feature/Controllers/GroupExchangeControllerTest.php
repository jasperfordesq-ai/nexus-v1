<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use App\Models\User;

/**
 * Feature tests for GroupExchangeController — group time exchanges.
 */
class GroupExchangeControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authenticatedUser(): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    // ------------------------------------------------------------------
    //  GET /v2/group-exchanges
    // ------------------------------------------------------------------

    public function test_index_requires_auth(): void
    {
        $response = $this->apiGet('/v2/group-exchanges');

        $response->assertStatus(401);
    }

    public function test_index_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/group-exchanges');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  POST /v2/group-exchanges
    // ------------------------------------------------------------------

    public function test_store_requires_auth(): void
    {
        $response = $this->apiPost('/v2/group-exchanges', ['title' => 'Group session']);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/group-exchanges/{id}
    // ------------------------------------------------------------------

    public function test_show_requires_auth(): void
    {
        $response = $this->apiGet('/v2/group-exchanges/1');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  PUT /v2/group-exchanges/{id}
    // ------------------------------------------------------------------

    public function test_update_requires_auth(): void
    {
        $response = $this->apiPut('/v2/group-exchanges/1', ['title' => 'Updated']);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  DELETE /v2/group-exchanges/{id}
    // ------------------------------------------------------------------

    public function test_destroy_requires_auth(): void
    {
        $response = $this->apiDelete('/v2/group-exchanges/1');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /v2/group-exchanges/{id}/confirm
    // ------------------------------------------------------------------

    public function test_confirm_requires_auth(): void
    {
        $response = $this->apiPost('/v2/group-exchanges/1/confirm');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /v2/group-exchanges/{id}/complete
    // ------------------------------------------------------------------

    public function test_complete_requires_auth(): void
    {
        $response = $this->apiPost('/v2/group-exchanges/1/complete');

        $response->assertStatus(401);
    }
}
