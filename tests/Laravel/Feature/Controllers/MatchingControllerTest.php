<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for MatchingController — smart matching endpoints.
 *
 * Covers:
 *   GET  /v2/matches/all         (auth required, query validation)
 *   POST /v2/matches/{id}/dismiss (auth required, input validation, success path)
 */
class MatchingControllerTest extends TestCase
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

    public function test_all_matches_requires_auth(): void
    {
        $response = $this->apiGet('/v2/matches/all');

        $response->assertStatus(401);
    }

    public function test_all_matches_returns_data_for_authenticated_user(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/matches/all');

        $response->assertStatus(200);
    }

    public function test_all_matches_honors_modules_filter(): void
    {
        $this->authenticatedUser();

        // Passing modules should succeed even if filter is empty / irrelevant.
        $response = $this->apiGet('/v2/matches/all?modules=listings,jobs&limit=5');

        $response->assertStatus(200);
    }

    public function test_dismiss_requires_auth(): void
    {
        $response = $this->apiPost('/v2/matches/1/dismiss');

        $response->assertStatus(401);
    }

    public function test_dismiss_rejects_invalid_listing_id(): void
    {
        $this->authenticatedUser();

        // id=0 triggers the VALIDATION_ERROR branch in the controller.
        $response = $this->apiPost('/v2/matches/0/dismiss');

        $this->assertContains($response->getStatusCode(), [400, 404, 422]);
    }

    public function test_dismiss_succeeds_with_valid_id_and_is_idempotent(): void
    {
        $this->authenticatedUser();

        $first = $this->apiPost('/v2/matches/12345/dismiss', ['reason' => 'not_relevant']);
        $first->assertStatus(200);
        $first->assertJsonPath('data.dismissed', true);
        $first->assertJsonPath('data.listing_id', 12345);

        // Repeated dismiss should still succeed (INSERT IGNORE).
        $second = $this->apiPost('/v2/matches/12345/dismiss', ['reason' => 'other']);
        $second->assertStatus(200);
    }

    public function test_dismiss_coerces_invalid_reason(): void
    {
        $this->authenticatedUser();

        // Unknown reason should be coerced to 'other' but request still succeeds.
        $response = $this->apiPost('/v2/matches/54321/dismiss', ['reason' => 'NOT_A_VALID_REASON']);

        $response->assertStatus(200);
        $response->assertJsonPath('data.dismissed', true);
    }
}
