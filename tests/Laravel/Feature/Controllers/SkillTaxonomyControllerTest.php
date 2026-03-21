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
 * Feature tests for SkillTaxonomyController — skill categories, user skills CRUD.
 */
class SkillTaxonomyControllerTest extends TestCase
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
    //  GET /v2/skills/categories
    // ------------------------------------------------------------------

    public function test_categories_requires_auth(): void
    {
        $response = $this->apiGet('/v2/skills/categories');

        $response->assertStatus(401);
    }

    public function test_categories_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/skills/categories');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /v2/skills/search
    // ------------------------------------------------------------------

    public function test_search_requires_auth(): void
    {
        $response = $this->apiGet('/v2/skills/search?q=cooking');

        $response->assertStatus(401);
    }

    public function test_search_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/skills/search?q=cooking');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /v2/skills/members
    // ------------------------------------------------------------------

    public function test_members_with_skill_requires_auth(): void
    {
        $response = $this->apiGet('/v2/skills/members?skill_id=1');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/users/me/skills
    // ------------------------------------------------------------------

    public function test_my_skills_requires_auth(): void
    {
        $response = $this->apiGet('/v2/users/me/skills');

        $response->assertStatus(401);
    }

    public function test_my_skills_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/users/me/skills');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  POST /v2/users/me/skills
    // ------------------------------------------------------------------

    public function test_add_skill_requires_auth(): void
    {
        $response = $this->apiPost('/v2/users/me/skills', [
            'name' => 'Cooking',
            'level' => 'intermediate',
        ]);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  DELETE /v2/users/me/skills/{id}
    // ------------------------------------------------------------------

    public function test_remove_skill_requires_auth(): void
    {
        $response = $this->apiDelete('/v2/users/me/skills/1');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/users/{id}/skills
    // ------------------------------------------------------------------

    public function test_user_skills_requires_auth(): void
    {
        $response = $this->apiGet('/v2/users/1/skills');

        $response->assertStatus(401);
    }

    public function test_user_skills_returns_data(): void
    {
        $user = $this->authenticatedUser();
        $other = User::factory()->forTenant($this->testTenantId)->create();

        $response = $this->apiGet("/v2/users/{$other->id}/skills");

        $response->assertStatus(200);
    }
}
