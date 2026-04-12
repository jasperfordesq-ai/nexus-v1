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
 * Feature tests for HelpController — FAQs and feedback.
 */
class HelpControllerTest extends TestCase
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
    //  GET /v2/help/faqs
    // ------------------------------------------------------------------

    public function test_get_faqs_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/help/faqs');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  POST /help/feedback
    // ------------------------------------------------------------------

    public function test_feedback_requires_auth(): void
    {
        $response = $this->apiPost('/help/feedback', [
            'message' => 'Great platform!',
            'rating' => 5,
        ]);

        $response->assertStatus(401);
    }

    public function test_feedback_accepts_submission(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/help/feedback', [
            'message' => 'Great platform!',
            'rating' => 5,
        ]);

        $this->assertContains($response->getStatusCode(), [200, 201]);
    }
}
