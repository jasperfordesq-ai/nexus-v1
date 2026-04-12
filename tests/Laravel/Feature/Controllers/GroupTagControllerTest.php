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
 * Smoke tests for GroupTagController.
 */
class GroupTagControllerTest extends TestCase
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

    public function test_index_requires_auth(): void
    {
        $response = $this->apiGet('/v2/groups/1/tags');
        $response->assertStatus(401);
    }

    public function test_update_requires_auth(): void
    {
        $response = $this->apiPut('/v2/groups/1/tags', []);
        $response->assertStatus(401);
    }

    public function test_all_tags_requires_auth(): void
    {
        $response = $this->apiGet('/v2/group-tags');
        $response->assertStatus(401);
    }

    public function test_popular_requires_auth(): void
    {
        $response = $this->apiGet('/v2/group-tags/popular');
        $response->assertStatus(401);
    }

    public function test_suggest_requires_auth(): void
    {
        $response = $this->apiGet('/v2/group-tags/suggest');
        $response->assertStatus(401);
    }

    public function test_all_tags_authenticated_smoke(): void
    {
        $this->authenticatedUser();
        $response = $this->apiGet('/v2/group-tags');
        $this->assertLessThan(600, $response->status());
    }

    public function test_popular_authenticated_smoke(): void
    {
        $this->authenticatedUser();
        $response = $this->apiGet('/v2/group-tags/popular');
        $this->assertLessThan(600, $response->status());
    }
}
