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
 * Feature tests for KnowledgeBaseController — KB articles CRUD, search, feedback.
 */
class KnowledgeBaseControllerTest extends TestCase
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
    //  GET /v2/kb
    // ------------------------------------------------------------------

    public function test_index_requires_auth(): void
    {
        $response = $this->apiGet('/v2/kb');

        $response->assertStatus(401);
    }

    public function test_index_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/kb');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /v2/kb/search
    // ------------------------------------------------------------------

    public function test_search_requires_auth(): void
    {
        $response = $this->apiGet('/v2/kb/search?q=help');

        $response->assertStatus(401);
    }

    public function test_search_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/kb/search?q=help');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  POST /v2/kb
    // ------------------------------------------------------------------

    public function test_store_requires_auth(): void
    {
        $response = $this->apiPost('/v2/kb', [
            'title' => 'How to use timebanking',
            'content' => 'Timebanking is...',
        ]);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/kb/{id}
    // ------------------------------------------------------------------

    public function test_show_requires_auth(): void
    {
        $response = $this->apiGet('/v2/kb/1');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/kb/slug/{slug}
    // ------------------------------------------------------------------

    public function test_show_by_slug_requires_auth(): void
    {
        $response = $this->apiGet('/v2/kb/slug/test-article');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  DELETE /v2/kb/{id}
    // ------------------------------------------------------------------

    public function test_destroy_requires_auth(): void
    {
        $response = $this->apiDelete('/v2/kb/1');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /v2/kb/{id}/feedback
    // ------------------------------------------------------------------

    public function test_feedback_requires_auth(): void
    {
        $response = $this->apiPost('/v2/kb/1/feedback', [
            'helpful' => true,
        ]);

        $response->assertStatus(401);
    }
}
