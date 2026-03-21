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
 * Feature tests for RegistrationPolicyController — verification status, invite codes.
 */
class RegistrationPolicyControllerTest extends TestCase
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
    //  GET /v2/auth/verification-status (auth required)
    // ------------------------------------------------------------------

    public function test_verification_status_requires_auth(): void
    {
        $response = $this->apiGet('/v2/auth/verification-status');

        $response->assertStatus(401);
    }

    public function test_verification_status_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/auth/verification-status');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  POST /v2/auth/start-verification (auth required)
    // ------------------------------------------------------------------

    public function test_start_verification_requires_auth(): void
    {
        $response = $this->apiPost('/v2/auth/start-verification', [
            'provider' => 'email',
        ]);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /v2/auth/validate-invite (auth required)
    // ------------------------------------------------------------------

    public function test_validate_invite_requires_auth(): void
    {
        $response = $this->apiPost('/v2/auth/validate-invite', [
            'code' => 'INVITE123',
        ]);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/auth/registration-info (auth required)
    // ------------------------------------------------------------------

    public function test_registration_info_requires_auth(): void
    {
        $response = $this->apiGet('/v2/auth/registration-info');

        $response->assertStatus(401);
    }

    public function test_registration_info_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/auth/registration-info');

        $response->assertStatus(200);
    }
}
