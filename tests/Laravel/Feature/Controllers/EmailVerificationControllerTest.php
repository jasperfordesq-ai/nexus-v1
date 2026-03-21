<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Feature tests for EmailVerificationController — email verification and resend.
 *
 * These are public endpoints (rate-limited) — no auth required.
 */
class EmailVerificationControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ------------------------------------------------------------------
    //  POST /auth/verify-email (public, rate-limited)
    // ------------------------------------------------------------------

    public function test_verify_email_requires_token(): void
    {
        $response = $this->apiPost('/auth/verify-email', []);

        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    public function test_verify_email_rejects_invalid_token(): void
    {
        $response = $this->apiPost('/auth/verify-email', [
            'token' => 'invalid-token-abc123',
        ]);

        $this->assertContains($response->getStatusCode(), [400, 404, 422]);
    }

    // ------------------------------------------------------------------
    //  POST /auth/resend-verification (public, rate-limited)
    // ------------------------------------------------------------------

    public function test_resend_verification_requires_email(): void
    {
        $response = $this->apiPost('/auth/resend-verification', []);

        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    // ------------------------------------------------------------------
    //  POST /auth/resend-verification-by-email (public, rate-limited)
    // ------------------------------------------------------------------

    public function test_resend_verification_by_email_requires_email(): void
    {
        $response = $this->apiPost('/auth/resend-verification-by-email', []);

        $this->assertContains($response->getStatusCode(), [400, 422]);
    }
}
