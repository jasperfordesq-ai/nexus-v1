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
 * Feature tests for VolunteerCommunityController — swaps, waitlists, donations, community projects.
 */
class VolunteerCommunityControllerTest extends TestCase
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

    public function test_get_swap_requests_requires_auth(): void
    {
        $response = $this->apiGet('/v2/volunteering/swaps');

        $response->assertStatus(401);
    }

    public function test_my_waitlists_requires_auth(): void
    {
        $response = $this->apiGet('/v2/volunteering/my-waitlists');

        $response->assertStatus(401);
    }

    public function test_get_community_projects_requires_auth(): void
    {
        $response = $this->apiGet('/v2/volunteering/community-projects');

        $response->assertStatus(401);
    }

    public function test_get_swap_requests_authenticated_smoke(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/volunteering/swaps');

        $this->assertLessThan(500, $response->status());
    }

    public function test_get_community_projects_authenticated_smoke(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/volunteering/community-projects');

        $this->assertLessThan(500, $response->status());
    }
}
