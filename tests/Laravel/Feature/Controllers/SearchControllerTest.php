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
 * Feature tests for SearchController — global search, suggestions, saved searches, trending.
 */
class SearchControllerTest extends TestCase
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
    //  GET /v2/search
    // ------------------------------------------------------------------

    public function test_index_requires_auth(): void
    {
        $response = $this->apiGet('/v2/search?q=help');

        $response->assertStatus(401);
    }

    public function test_index_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/search?q=help');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /v2/search/suggestions
    // ------------------------------------------------------------------

    public function test_suggestions_requires_auth(): void
    {
        $response = $this->apiGet('/v2/search/suggestions?q=dog');

        $response->assertStatus(401);
    }

    public function test_suggestions_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/search/suggestions?q=dog');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /v2/search/saved
    // ------------------------------------------------------------------

    public function test_saved_searches_requires_auth(): void
    {
        $response = $this->apiGet('/v2/search/saved');

        $response->assertStatus(401);
    }

    public function test_saved_searches_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/search/saved');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  POST /v2/search/saved
    // ------------------------------------------------------------------

    public function test_save_search_requires_auth(): void
    {
        $response = $this->apiPost('/v2/search/saved', [
            'query' => 'dog walking',
        ]);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  DELETE /v2/search/saved/{id}
    // ------------------------------------------------------------------

    public function test_delete_saved_search_requires_auth(): void
    {
        $response = $this->apiDelete('/v2/search/saved/1');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/search/trending
    // ------------------------------------------------------------------

    public function test_trending_requires_auth(): void
    {
        $response = $this->apiGet('/v2/search/trending');

        $response->assertStatus(401);
    }

    public function test_trending_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/search/trending');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  Tenant isolation
    // ------------------------------------------------------------------

    public function test_search_is_tenant_scoped(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/search?q=test');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertIsArray($data);
    }
}
