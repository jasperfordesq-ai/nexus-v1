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
 * Feature tests for LegalController — public legal documents (terms, privacy policy).
 *
 * GET routes are PUBLIC (no auth required).
 * POST routes (accept, accept-all, status) require auth.
 */
class LegalControllerTest extends TestCase
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
    //  GET /v2/legal/{type} (PUBLIC — no auth)
    // ------------------------------------------------------------------

    public function test_get_document_is_public(): void
    {
        $response = $this->apiGet('/v2/legal/terms-of-service');

        // Should NOT return 401 — this is a public route
        $this->assertNotEquals(401, $response->getStatusCode());
    }

    public function test_get_document_nonexistent_type(): void
    {
        $response = $this->apiGet('/v2/legal/nonexistent-type');

        $this->assertContains($response->getStatusCode(), [200, 404]);
    }

    // ------------------------------------------------------------------
    //  GET /v2/legal/{type}/versions (PUBLIC)
    // ------------------------------------------------------------------

    public function test_get_versions_is_public(): void
    {
        $response = $this->apiGet('/v2/legal/terms-of-service/versions');

        $this->assertNotEquals(401, $response->getStatusCode());
    }

    // ------------------------------------------------------------------
    //  GET /v2/legal/versions/compare (PUBLIC)
    // ------------------------------------------------------------------

    public function test_compare_versions_is_public(): void
    {
        $response = $this->apiGet('/v2/legal/versions/compare');

        $this->assertNotEquals(401, $response->getStatusCode());
    }

    // ------------------------------------------------------------------
    //  POST /legal/accept (auth required)
    // ------------------------------------------------------------------

    public function test_accept_requires_auth(): void
    {
        $response = $this->apiPost('/legal/accept', [
            'document_type' => 'terms-of-service',
        ]);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /legal/accept-all (auth required)
    // ------------------------------------------------------------------

    public function test_accept_all_requires_auth(): void
    {
        $response = $this->apiPost('/legal/accept-all');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /legal/status (auth required)
    // ------------------------------------------------------------------

    public function test_status_requires_auth(): void
    {
        $response = $this->apiGet('/legal/status');

        $response->assertStatus(401);
    }

    public function test_status_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/legal/status');

        $response->assertStatus(200);
    }
}
