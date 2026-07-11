<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use App\Models\User;

/**
 * Feature tests for IdeationChallengesController — ideation challenges, ideas, voting.
 */
class IdeationChallengesControllerTest extends TestCase
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
    //  GET /v2/ideation-challenges
    // ------------------------------------------------------------------

    public function test_index_requires_authentication(): void
    {
        $this->apiGet('/v2/ideation-challenges')->assertStatus(401);
    }

    public function test_index_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/ideation-challenges');

        $response->assertStatus(200);
    }

    public function test_member_cannot_enumerate_draft_challenges_by_filter_or_id(): void
    {
        $viewer = $this->authenticatedUser();
        $creator = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $draftId = DB::table('ideation_challenges')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $creator->id,
            'title' => 'Private draft challenge',
            'description' => 'This draft must remain hidden.',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ideation_challenges')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => $creator->id,
            'title' => 'Published open challenge',
            'description' => 'This challenge is member-visible.',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $list = $this->apiGet('/v2/ideation-challenges?status=draft');
        $list->assertOk();
        $this->assertNotContains($draftId, array_map('intval', array_column($list->json('data'), 'id')));
        $this->apiGet("/v2/ideation-challenges/{$draftId}")->assertNotFound();
        $this->assertNotSame($viewer->id, $creator->id);
    }

    // ------------------------------------------------------------------
    //  POST /v2/ideation-challenges
    // ------------------------------------------------------------------

    public function test_store_requires_auth(): void
    {
        $response = $this->apiPost('/v2/ideation-challenges', [
            'title' => 'New Challenge',
            'description' => 'A test challenge',
        ]);

        $response->assertStatus(401);
    }

    public function test_store_creates_challenge_against_current_schema(): void
    {
        $user = $this->authenticatedUser();

        $response = $this->apiPost('/v2/ideation-challenges', [
            'title' => 'Community welcome challenge',
            'description' => 'Gather practical ideas for helping new members feel welcome.',
            'status' => 'open',
            'submission_deadline' => '2026-06-15 09:00:00',
            'voting_deadline' => '2026-06-20 09:00:00',
        ]);

        $response->assertStatus(201);
        $id = $response->json('data.id');

        $this->assertDatabaseHas('ideation_challenges', [
            'id' => $id,
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'title' => 'Community welcome challenge',
            'description' => 'Gather practical ideas for helping new members feel welcome.',
            'status' => 'open',
            'submission_deadline' => '2026-06-15 09:00:00',
            'voting_deadline' => '2026-06-20 09:00:00',
        ]);
    }

    // ------------------------------------------------------------------
    //  GET /v2/ideation-challenges/{id}
    // ------------------------------------------------------------------

    public function test_show_requires_authentication(): void
    {
        $this->apiGet('/v2/ideation-challenges/1')->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/ideation-challenges/{id}/ideas
    // ------------------------------------------------------------------

public function test_ideas_requires_auth(): void
    {
        $response = $this->apiGet('/v2/ideation-challenges/1/ideas');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /v2/ideation-challenges/{id}/ideas
    // ------------------------------------------------------------------

    public function test_submit_idea_requires_auth(): void
    {
        $response = $this->apiPost('/v2/ideation-challenges/1/ideas', [
            'title' => 'My Idea',
            'description' => 'A great idea',
        ]);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/ideation-ideas/{id}
    // ------------------------------------------------------------------

    public function test_show_idea_requires_auth(): void
    {
        $response = $this->apiGet('/v2/ideation-ideas/1');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /v2/ideation-ideas/{id}/vote
    // ------------------------------------------------------------------

    public function test_vote_idea_requires_auth(): void
    {
        $response = $this->apiPost('/v2/ideation-ideas/1/vote');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/ideation-categories
    // ------------------------------------------------------------------

    public function test_categories_requires_auth(): void
    {
        $response = $this->apiGet('/v2/ideation-categories');

        $response->assertStatus(401);
    }

    public function test_categories_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/ideation-categories');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /v2/ideation-tags/popular
    // ------------------------------------------------------------------

    public function test_popular_tags_requires_auth(): void
    {
        $response = $this->apiGet('/v2/ideation-tags/popular');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/ideation-templates
    // ------------------------------------------------------------------

    public function test_templates_requires_auth(): void
    {
        $response = $this->apiGet('/v2/ideation-templates');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/ideation-campaigns
    // ------------------------------------------------------------------

    public function test_campaigns_requires_auth(): void
    {
        $response = $this->apiGet('/v2/ideation-campaigns');

        $response->assertStatus(401);
    }
}
