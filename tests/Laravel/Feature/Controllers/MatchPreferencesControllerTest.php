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
 * Feature tests for MatchPreferencesController — user match preferences.
 */
class MatchPreferencesControllerTest extends TestCase
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
    //  GET /v2/users/me/match-preferences
    // ------------------------------------------------------------------

    public function test_show_requires_auth(): void
    {
        $response = $this->apiGet('/v2/users/me/match-preferences');

        $response->assertStatus(401);
    }

    public function test_show_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/users/me/match-preferences');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  PUT /v2/users/me/match-preferences
    // ------------------------------------------------------------------

    public function test_update_requires_auth(): void
    {
        $response = $this->apiPut('/v2/users/me/match-preferences', [
            'max_distance' => 10,
        ]);

        $response->assertStatus(401);
    }

    public function test_update_saves_preferences(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPut('/v2/users/me/match-preferences', [
            'max_distance' => 10,
            'preferred_days' => ['monday', 'wednesday'],
        ]);

        $this->assertContains($response->getStatusCode(), [200, 204]);
    }
}
