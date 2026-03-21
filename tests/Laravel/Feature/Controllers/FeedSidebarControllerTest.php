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
 * Feature tests for FeedSidebarController — community stats, suggested members, sidebar.
 */
class FeedSidebarControllerTest extends TestCase
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
    //  GET /v2/community/stats
    // ------------------------------------------------------------------

    public function test_community_stats_requires_auth(): void
    {
        $response = $this->apiGet('/v2/community/stats');

        $response->assertStatus(401);
    }

    public function test_community_stats_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/community/stats');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /v2/members/suggested
    // ------------------------------------------------------------------

    public function test_suggested_members_requires_auth(): void
    {
        $response = $this->apiGet('/v2/members/suggested');

        $response->assertStatus(401);
    }

    public function test_suggested_members_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/members/suggested');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /v2/feed/sidebar
    // ------------------------------------------------------------------

    public function test_sidebar_requires_auth(): void
    {
        $response = $this->apiGet('/v2/feed/sidebar');

        $response->assertStatus(401);
    }

    public function test_sidebar_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/feed/sidebar');

        $response->assertStatus(200);
    }
}
