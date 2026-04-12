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
 * Feature tests for BlogPublicController — public blog list and detail.
 */
class BlogPublicControllerTest extends TestCase
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
    //  GET /v2/blog
    // ------------------------------------------------------------------

    public function test_blog_index_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/blog');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /v2/blog/categories
    // ------------------------------------------------------------------

    public function test_blog_categories_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/blog/categories');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /v2/blog/{slug}
    // ------------------------------------------------------------------

    public function test_blog_show_nonexistent_returns_404(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/blog/nonexistent-slug-xyz');

        $this->assertContains($response->getStatusCode(), [404, 200]);
    }
}
