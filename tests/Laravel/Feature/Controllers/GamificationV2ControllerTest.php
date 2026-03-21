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
 * Feature tests for GamificationV2Controller — badges, leaderboard, challenges, shop, seasons.
 */
class GamificationV2ControllerTest extends TestCase
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
    //  GET /v2/gamification/profile
    // ------------------------------------------------------------------

    public function test_profile_requires_auth(): void
    {
        $response = $this->apiGet('/v2/gamification/profile');

        $response->assertStatus(401);
    }

    public function test_profile_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/gamification/profile');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /v2/gamification/badges
    // ------------------------------------------------------------------

    public function test_badges_requires_auth(): void
    {
        $response = $this->apiGet('/v2/gamification/badges');

        $response->assertStatus(401);
    }

    public function test_badges_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/gamification/badges');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /v2/gamification/leaderboard
    // ------------------------------------------------------------------

    public function test_leaderboard_requires_auth(): void
    {
        $response = $this->apiGet('/v2/gamification/leaderboard');

        $response->assertStatus(401);
    }

    public function test_leaderboard_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/gamification/leaderboard');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /v2/gamification/challenges
    // ------------------------------------------------------------------

    public function test_challenges_requires_auth(): void
    {
        $response = $this->apiGet('/v2/gamification/challenges');

        $response->assertStatus(401);
    }

    public function test_challenges_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/gamification/challenges');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /v2/gamification/collections
    // ------------------------------------------------------------------

    public function test_collections_requires_auth(): void
    {
        $response = $this->apiGet('/v2/gamification/collections');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/gamification/daily-reward
    // ------------------------------------------------------------------

    public function test_daily_reward_status_requires_auth(): void
    {
        $response = $this->apiGet('/v2/gamification/daily-reward');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /v2/gamification/daily-reward
    // ------------------------------------------------------------------

    public function test_claim_daily_reward_requires_auth(): void
    {
        $response = $this->apiPost('/v2/gamification/daily-reward');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/gamification/shop
    // ------------------------------------------------------------------

    public function test_shop_requires_auth(): void
    {
        $response = $this->apiGet('/v2/gamification/shop');

        $response->assertStatus(401);
    }

    public function test_shop_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/gamification/shop');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /v2/gamification/seasons
    // ------------------------------------------------------------------

    public function test_seasons_requires_auth(): void
    {
        $response = $this->apiGet('/v2/gamification/seasons');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/gamification/seasons/current
    // ------------------------------------------------------------------

    public function test_current_season_requires_auth(): void
    {
        $response = $this->apiGet('/v2/gamification/seasons/current');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/gamification/nexus-score
    // ------------------------------------------------------------------

    public function test_nexus_score_requires_auth(): void
    {
        $response = $this->apiGet('/v2/gamification/nexus-score');

        $response->assertStatus(401);
    }

    public function test_nexus_score_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/gamification/nexus-score');

        $response->assertStatus(200);
    }
}
