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
 * Feature tests for OnboardingController — onboarding status, categories, completion.
 */
class OnboardingControllerTest extends TestCase
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
    //  GET /v2/onboarding/status
    // ------------------------------------------------------------------

    public function test_status_requires_auth(): void
    {
        $response = $this->apiGet('/v2/onboarding/status');

        $response->assertStatus(401);
    }

    public function test_status_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/onboarding/status');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /v2/onboarding/categories
    // ------------------------------------------------------------------

    public function test_categories_requires_auth(): void
    {
        $response = $this->apiGet('/v2/onboarding/categories');

        $response->assertStatus(401);
    }

    public function test_categories_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/onboarding/categories');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  POST /v2/onboarding/complete
    // ------------------------------------------------------------------

    public function test_complete_requires_auth(): void
    {
        $response = $this->apiPost('/v2/onboarding/complete');

        $response->assertStatus(401);
    }

    public function test_complete_marks_onboarding_done(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/onboarding/complete', [
            'categories' => [1, 2],
            'bio' => 'I love timebanking!',
        ]);

        $this->assertContains($response->getStatusCode(), [200, 201]);
    }
}
