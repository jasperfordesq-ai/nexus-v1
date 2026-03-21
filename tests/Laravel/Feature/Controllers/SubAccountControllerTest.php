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
 * Feature tests for SubAccountController — parent/child sub-account management.
 */
class SubAccountControllerTest extends TestCase
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
    //  GET /v2/users/me/sub-accounts
    // ------------------------------------------------------------------

    public function test_get_children_requires_auth(): void
    {
        $response = $this->apiGet('/v2/users/me/sub-accounts');

        $response->assertStatus(401);
    }

    public function test_get_children_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/users/me/sub-accounts');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /v2/users/me/parent-accounts
    // ------------------------------------------------------------------

    public function test_get_parents_requires_auth(): void
    {
        $response = $this->apiGet('/v2/users/me/parent-accounts');

        $response->assertStatus(401);
    }

    public function test_get_parents_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/users/me/parent-accounts');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  POST /v2/users/me/sub-accounts
    // ------------------------------------------------------------------

    public function test_request_relationship_requires_auth(): void
    {
        $response = $this->apiPost('/v2/users/me/sub-accounts', [
            'child_email' => 'child@example.com',
        ]);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  PUT /v2/users/me/sub-accounts/{id}/approve
    // ------------------------------------------------------------------

    public function test_approve_requires_auth(): void
    {
        $response = $this->apiPut('/v2/users/me/sub-accounts/1/approve');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  DELETE /v2/users/me/sub-accounts/{id}
    // ------------------------------------------------------------------

    public function test_revoke_requires_auth(): void
    {
        $response = $this->apiDelete('/v2/users/me/sub-accounts/1');

        $response->assertStatus(401);
    }
}
