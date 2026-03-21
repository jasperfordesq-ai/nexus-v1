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
 * Feature tests for ResourceCategoryController — resource category CRUD.
 */
class ResourceCategoryControllerTest extends TestCase
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
    //  GET /v2/resources/categories/tree
    // ------------------------------------------------------------------

    public function test_tree_requires_auth(): void
    {
        $response = $this->apiGet('/v2/resources/categories/tree');

        $response->assertStatus(401);
    }

    public function test_tree_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/resources/categories/tree');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  POST /v2/resources/categories
    // ------------------------------------------------------------------

    public function test_store_requires_auth(): void
    {
        $response = $this->apiPost('/v2/resources/categories', [
            'name' => 'Guides',
        ]);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  PUT /v2/resources/categories/{id}
    // ------------------------------------------------------------------

    public function test_update_requires_auth(): void
    {
        $response = $this->apiPut('/v2/resources/categories/1', [
            'name' => 'Updated',
        ]);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  DELETE /v2/resources/categories/{id}
    // ------------------------------------------------------------------

    public function test_destroy_requires_auth(): void
    {
        $response = $this->apiDelete('/v2/resources/categories/1');

        $response->assertStatus(401);
    }
}
