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
 * Feature tests for PagesPublicController — public CMS pages.
 */
class PagesPublicControllerTest extends TestCase
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
    //  GET /v2/pages/{slug}
    // ------------------------------------------------------------------

    public function test_show_is_public_no_auth_required(): void
    {
        // /v2/pages/{slug} is a public endpoint — unauthenticated requests
        // should NOT get 401. A missing page returns 404, not 401.
        $response = $this->apiGet('/v2/pages/nonexistent-slug-xyz');

        $response->assertStatus(404);
    }

    public function test_show_nonexistent_page(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/pages/nonexistent-page-xyz');

        $this->assertContains($response->getStatusCode(), [200, 404]);
    }
}
