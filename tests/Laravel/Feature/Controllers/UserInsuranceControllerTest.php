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
 * Feature tests for UserInsuranceController — user insurance certificates.
 */
class UserInsuranceControllerTest extends TestCase
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
    //  GET /v2/users/me/insurance
    // ------------------------------------------------------------------

    public function test_list_requires_auth(): void
    {
        $response = $this->apiGet('/v2/users/me/insurance');

        $response->assertStatus(401);
    }

    public function test_list_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/users/me/insurance');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  POST /v2/users/me/insurance
    // ------------------------------------------------------------------

    public function test_upload_requires_auth(): void
    {
        $response = $this->apiPost('/v2/users/me/insurance', []);

        $response->assertStatus(401);
    }
}
