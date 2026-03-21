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
 * Feature tests for IdentityProviderHealthController — identity provider health (admin).
 */
class IdentityProviderHealthControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authenticatedAdmin(): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->admin()->create();

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    // ------------------------------------------------------------------
    //  GET /v2/admin/identity/provider-health (admin route)
    // ------------------------------------------------------------------

    public function test_provider_health_requires_auth(): void
    {
        $response = $this->apiGet('/v2/admin/identity/provider-health');

        $response->assertStatus(401);
    }

    public function test_provider_health_requires_admin(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->apiGet('/v2/admin/identity/provider-health');

        $response->assertStatus(403);
    }

    public function test_provider_health_returns_data_for_admin(): void
    {
        $this->authenticatedAdmin();

        $response = $this->apiGet('/v2/admin/identity/provider-health');

        $response->assertStatus(200);
    }
}
