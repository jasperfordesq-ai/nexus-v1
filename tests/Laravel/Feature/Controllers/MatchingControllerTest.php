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
 * Feature tests for MatchingController — smart matching between members.
 */
class MatchingControllerTest extends TestCase
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
    //  GET /v2/matches/all
    // ------------------------------------------------------------------

    public function test_all_matches_requires_auth(): void
    {
        $response = $this->apiGet('/v2/matches/all');

        $response->assertStatus(401);
    }

    public function test_all_matches_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/matches/all');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  POST /v2/matches/{id}/dismiss
    // ------------------------------------------------------------------

    public function test_dismiss_requires_auth(): void
    {
        $response = $this->apiPost('/v2/matches/1/dismiss');

        $response->assertStatus(401);
    }
}
