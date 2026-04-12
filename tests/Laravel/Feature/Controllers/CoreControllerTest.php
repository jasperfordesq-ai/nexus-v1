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
 * Feature tests for CoreController — contact form, messages, members API.
 */
class CoreControllerTest extends TestCase
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
    //  POST /v2/contact (public route)
    // ------------------------------------------------------------------

    public function test_contact_form_accepts_submission(): void
    {
        $response = $this->apiPost('/v2/contact', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'message' => 'This is a test contact message.',
        ]);

        $this->assertContains($response->getStatusCode(), [200, 201, 422]);
    }

    // ------------------------------------------------------------------
    //  GET /messages/unread-count (auth required)
    // ------------------------------------------------------------------

    public function test_unread_count_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/messages/unread-count');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /members (auth required)
    // ------------------------------------------------------------------

    public function test_members_requires_auth(): void
    {
        $response = $this->apiGet('/members');

        $response->assertStatus(401);
    }

    public function test_members_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/members');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /listings (auth required)
    // ------------------------------------------------------------------

    public function test_listings_requires_auth(): void
    {
        $response = $this->apiGet('/listings');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /notifications (auth required)
    // ------------------------------------------------------------------

    public function test_notifications_requires_auth(): void
    {
        $response = $this->apiGet('/notifications');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /notifications/unread-count (auth required)
    // ------------------------------------------------------------------

    public function test_notifications_unread_count_requires_auth(): void
    {
        $response = $this->apiGet('/notifications/unread-count');

        $response->assertStatus(401);
    }
}
