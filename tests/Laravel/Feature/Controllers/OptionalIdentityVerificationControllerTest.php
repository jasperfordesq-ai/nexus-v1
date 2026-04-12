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
 * Smoke tests for OptionalIdentityVerificationController.
 *
 * All routes live under auth:sanctum — unauthenticated => 401.
 */
class OptionalIdentityVerificationControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_get_status_requires_auth(): void
    {
        $this->apiGet('/v2/identity/status')->assertStatus(401);
    }

    public function test_start_verification_requires_auth(): void
    {
        $this->apiPost('/v2/identity/start', [])->assertStatus(401);
    }

    public function test_save_dob_requires_auth(): void
    {
        $this->apiPost('/v2/identity/save-dob', [])->assertStatus(401);
    }

    public function test_create_payment_requires_auth(): void
    {
        $this->apiPost('/v2/identity/create-payment', [])->assertStatus(401);
    }

    public function test_get_status_returns_authenticated_response(): void
    {
        Sanctum::actingAs(User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]));
        $response = $this->apiGet('/v2/identity/status');
        // Smoke only — route is reached & authenticated; 500 acceptable if Stripe
        // Identity SDK isn't configured in the test environment.
        $this->assertNotEquals(401, $response->status());
        $this->assertNotEquals(403, $response->status());
    }
}
