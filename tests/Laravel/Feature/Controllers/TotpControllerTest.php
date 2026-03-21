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
 * Feature tests for TotpController — TOTP verification and status.
 */
class TotpControllerTest extends TestCase
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
    //  POST /totp/verify (PUBLIC, rate-limited)
    // ------------------------------------------------------------------

    public function test_verify_is_public(): void
    {
        $response = $this->apiPost('/totp/verify', [
            'code' => '123456',
            'user_id' => 1,
        ]);

        // Should not return 401 — this is a public pre-login endpoint
        $this->assertNotEquals(401, $response->getStatusCode());
    }

    public function test_verify_requires_code(): void
    {
        $response = $this->apiPost('/totp/verify', []);

        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    // ------------------------------------------------------------------
    //  GET /totp/status (auth required)
    // ------------------------------------------------------------------

    public function test_status_requires_auth(): void
    {
        $response = $this->apiGet('/totp/status');

        $response->assertStatus(401);
    }

    public function test_status_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/totp/status');

        $response->assertStatus(200);
    }
}
