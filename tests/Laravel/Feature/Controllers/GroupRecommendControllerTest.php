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
 * Feature tests for GroupRecommendController — group recommendations.
 */
class GroupRecommendControllerTest extends TestCase
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
    //  GET /v2/groups/recommendations
    // ------------------------------------------------------------------

    public function test_index_requires_auth(): void
    {
        $response = $this->apiGet('/v2/groups/recommendations');

        $response->assertStatus(401);
    }

    public function test_index_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/groups/recommendations');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  POST /v2/groups/recommendations/track
    // ------------------------------------------------------------------

    public function test_track_requires_auth(): void
    {
        $response = $this->apiPost('/v2/groups/recommendations/track', [
            'group_id' => 1,
            'action' => 'view',
        ]);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/groups/recommendations/metrics
    // ------------------------------------------------------------------

    public function test_metrics_requires_auth(): void
    {
        $response = $this->apiGet('/v2/groups/recommendations/metrics');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/groups/{id}/similar
    // ------------------------------------------------------------------

    public function test_similar_requires_auth(): void
    {
        $response = $this->apiGet('/v2/groups/1/similar');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /recommendations/groups (legacy)
    // ------------------------------------------------------------------

    public function test_legacy_recommendations_requires_auth(): void
    {
        $response = $this->apiGet('/recommendations/groups');

        $response->assertStatus(401);
    }
}
