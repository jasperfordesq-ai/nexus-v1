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
 * Feature tests for TwoFactorController — 2FA setup, verify, disable.
 */
class TwoFactorControllerTest extends TestCase
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
    //  GET /v2/auth/2fa/status
    // ------------------------------------------------------------------

    public function test_status_requires_auth(): void
    {
        $response = $this->apiGet('/v2/auth/2fa/status');

        $response->assertStatus(401);
    }

    public function test_status_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/auth/2fa/status');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  POST /v2/auth/2fa/setup
    // ------------------------------------------------------------------

    public function test_setup_requires_auth(): void
    {
        $response = $this->apiPost('/v2/auth/2fa/setup');

        $response->assertStatus(401);
    }

    public function test_setup_returns_qr_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/auth/2fa/setup');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  POST /v2/auth/2fa/verify
    // ------------------------------------------------------------------

    public function test_verify_requires_auth(): void
    {
        $response = $this->apiPost('/v2/auth/2fa/verify', ['code' => '123456']);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /v2/auth/2fa/disable
    // ------------------------------------------------------------------

    public function test_disable_requires_auth(): void
    {
        $response = $this->apiPost('/v2/auth/2fa/disable');

        $response->assertStatus(401);
    }
}
