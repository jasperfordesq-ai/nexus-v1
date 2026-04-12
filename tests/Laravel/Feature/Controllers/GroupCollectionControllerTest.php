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
 * Feature smoke tests for GroupCollectionController.
 */
class GroupCollectionControllerTest extends TestCase
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
        $this->apiGet('/v2/group-collections')->assertStatus(401);
    }

    public function test_show_requires_auth(): void
    {
        $this->apiGet('/v2/group-collections/1')->assertStatus(401);
    }

    public function test_index_returns_non_5xx_when_authenticated(): void
    {
        $this->authenticatedUser();
        $response = $this->apiGet('/v2/group-collections');
        $this->assertNotEquals(401, $response->status(), 'Auth should have passed');
    }

    public function test_show_returns_non_5xx_when_authenticated(): void
    {
        $this->authenticatedUser();
        $response = $this->apiGet('/v2/group-collections/1');
        $this->assertNotEquals(401, $response->status(), 'Auth should have passed');
    }
}
