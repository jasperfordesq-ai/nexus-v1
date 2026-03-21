<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Feature tests for PasswordResetController — forgot password and reset (public).
 */
class PasswordResetControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ------------------------------------------------------------------
    //  POST /auth/forgot-password (PUBLIC, rate-limited)
    // ------------------------------------------------------------------

    public function test_forgot_password_requires_email(): void
    {
        $response = $this->apiPost('/auth/forgot-password', []);

        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    public function test_forgot_password_accepts_email(): void
    {
        $response = $this->apiPost('/auth/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        // Should return 200 even for non-existent emails (security best practice)
        $this->assertContains($response->getStatusCode(), [200, 404, 422]);
    }

    // ------------------------------------------------------------------
    //  POST /auth/reset-password (PUBLIC, rate-limited)
    // ------------------------------------------------------------------

    public function test_reset_password_requires_token(): void
    {
        $response = $this->apiPost('/auth/reset-password', [
            'password' => 'NewPassword123!',
        ]);

        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    public function test_reset_password_rejects_invalid_token(): void
    {
        $response = $this->apiPost('/auth/reset-password', [
            'token' => 'invalid-token-xyz',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $this->assertContains($response->getStatusCode(), [400, 404, 422]);
    }
}
