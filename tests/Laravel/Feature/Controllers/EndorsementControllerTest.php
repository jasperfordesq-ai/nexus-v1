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
 * Feature tests for EndorsementController — skill endorsements between members.
 */
class EndorsementControllerTest extends TestCase
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
    //  POST /v2/members/{id}/endorse
    // ------------------------------------------------------------------

    public function test_endorse_requires_auth(): void
    {
        $response = $this->apiPost('/v2/members/1/endorse', ['skill_id' => 1]);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  DELETE /v2/members/{id}/endorse
    // ------------------------------------------------------------------

    public function test_remove_endorsement_requires_auth(): void
    {
        $response = $this->apiDelete('/v2/members/1/endorse');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/members/{id}/endorsements
    // ------------------------------------------------------------------

    public function test_get_endorsements_requires_auth(): void
    {
        $response = $this->apiGet('/v2/members/1/endorsements');

        $response->assertStatus(401);
    }

    public function test_get_endorsements_returns_data(): void
    {
        $this->authenticatedUser();
        $other = User::factory()->forTenant($this->testTenantId)->create();

        $response = $this->apiGet("/v2/members/{$other->id}/endorsements");

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /v2/members/top-endorsed
    // ------------------------------------------------------------------

    public function test_top_endorsed_requires_auth(): void
    {
        $response = $this->apiGet('/v2/members/top-endorsed');

        $response->assertStatus(401);
    }

    public function test_top_endorsed_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/members/top-endorsed');

        $response->assertStatus(200);
    }
}
