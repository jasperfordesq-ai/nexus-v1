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
 * Feature tests for CommentsController — CRUD and reactions on comments.
 */
class CommentsControllerTest extends TestCase
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
    //  GET /v2/comments
    // ------------------------------------------------------------------

    public function test_index_requires_auth(): void
    {
        $response = $this->apiGet('/v2/comments');

        $response->assertStatus(401);
    }

    public function test_index_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/comments?commentable_type=feed_post&commentable_id=1');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  POST /v2/comments
    // ------------------------------------------------------------------

    public function test_store_requires_auth(): void
    {
        $response = $this->apiPost('/v2/comments', [
            'commentable_type' => 'feed_post',
            'commentable_id' => 1,
            'body' => 'Great post!',
        ]);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  PUT /v2/comments/{id}
    // ------------------------------------------------------------------

    public function test_update_requires_auth(): void
    {
        $response = $this->apiPut('/v2/comments/1', ['body' => 'Updated']);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  DELETE /v2/comments/{id}
    // ------------------------------------------------------------------

    public function test_destroy_requires_auth(): void
    {
        $response = $this->apiDelete('/v2/comments/1');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /v2/comments/{id}/reactions
    // ------------------------------------------------------------------

    public function test_reactions_requires_auth(): void
    {
        $response = $this->apiPost('/v2/comments/1/reactions', ['type' => 'like']);

        $response->assertStatus(401);
    }
}
