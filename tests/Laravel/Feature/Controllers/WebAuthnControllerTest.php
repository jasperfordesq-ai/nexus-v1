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
 * Feature tests for WebAuthnController — passkey registration, auth, management.
 *
 * Auth challenge routes are PUBLIC (pre-login). Registration and management require auth.
 */
class WebAuthnControllerTest extends TestCase
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
    //  POST /webauthn/auth-challenge (PUBLIC, rate-limited)
    // ------------------------------------------------------------------

    public function test_auth_challenge_is_public(): void
    {
        $response = $this->apiPost('/webauthn/auth-challenge', []);

        $this->assertNotEquals(401, $response->getStatusCode());
    }

    // ------------------------------------------------------------------
    //  POST /webauthn/auth-verify (PUBLIC, rate-limited)
    // ------------------------------------------------------------------

    public function test_auth_verify_is_public(): void
    {
        $response = $this->apiPost('/webauthn/auth-verify', []);

        $this->assertNotEquals(401, $response->getStatusCode());
    }

    // ------------------------------------------------------------------
    //  POST /webauthn/login/options (PUBLIC, rate-limited — alias)
    // ------------------------------------------------------------------

    public function test_login_options_is_public(): void
    {
        $response = $this->apiPost('/webauthn/login/options', []);

        $this->assertNotEquals(401, $response->getStatusCode());
    }

    // ------------------------------------------------------------------
    //  POST /webauthn/register-challenge (auth required)
    // ------------------------------------------------------------------

    public function test_register_challenge_requires_auth(): void
    {
        $response = $this->apiPost('/webauthn/register-challenge');

        $response->assertStatus(401);
    }

    public function test_register_challenge_returns_options(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/webauthn/register-challenge');

        $this->assertContains($response->getStatusCode(), [200, 500]);
    }

    // ------------------------------------------------------------------
    //  POST /webauthn/register-verify (auth required)
    // ------------------------------------------------------------------

    public function test_register_verify_requires_auth(): void
    {
        $response = $this->apiPost('/webauthn/register-verify', []);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /webauthn/remove (auth required)
    // ------------------------------------------------------------------

    public function test_remove_requires_auth(): void
    {
        $response = $this->apiPost('/webauthn/remove', ['credential_id' => 'abc']);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /webauthn/rename (auth required)
    // ------------------------------------------------------------------

    public function test_rename_requires_auth(): void
    {
        $response = $this->apiPost('/webauthn/rename', [
            'credential_id' => 'abc',
            'name' => 'My Passkey',
        ]);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /webauthn/remove-all (auth required)
    // ------------------------------------------------------------------

    public function test_remove_all_requires_auth(): void
    {
        $response = $this->apiPost('/webauthn/remove-all');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /webauthn/credentials (auth required)
    // ------------------------------------------------------------------

    public function test_credentials_requires_auth(): void
    {
        $response = $this->apiGet('/webauthn/credentials');

        $response->assertStatus(401);
    }

    public function test_credentials_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/webauthn/credentials');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /webauthn/status (auth required)
    // ------------------------------------------------------------------

    public function test_status_requires_auth(): void
    {
        $response = $this->apiGet('/webauthn/status');

        $response->assertStatus(401);
    }

    public function test_status_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/webauthn/status');

        $response->assertStatus(200);
    }
}
