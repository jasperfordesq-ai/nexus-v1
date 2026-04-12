<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for BlockUserController.
 *
 * Covers:
 *  - POST   /v2/users/{id}/block        block a user (auth required)
 *  - DELETE /v2/users/{id}/block        unblock a user
 *  - GET    /v2/users/blocked           list blocked users
 *  - GET    /v2/users/{id}/block-status check block status
 */
class BlockUserControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_block_requires_auth(): void
    {
        $response = $this->apiPost('/v2/users/1/block');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    public function test_blocked_list_requires_auth(): void
    {
        $response = $this->apiGet('/v2/users/blocked');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    public function test_cannot_block_self(): void
    {
        $me = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($me);

        $response = $this->apiPost("/v2/users/{$me->id}/block");

        $response->assertStatus(400);
    }

    public function test_unblock_returns_404_when_not_blocked(): void
    {
        $me = User::factory()->forTenant($this->testTenantId)->create();
        $target = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($me);

        $response = $this->apiDelete("/v2/users/{$target->id}/block");

        $response->assertStatus(404);
    }

    public function test_block_status_requires_auth(): void
    {
        $response = $this->apiGet('/v2/users/1/block-status');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    public function test_block_status_returns_flags(): void
    {
        $me = User::factory()->forTenant($this->testTenantId)->create();
        $target = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($me);

        $response = $this->apiGet("/v2/users/{$target->id}/block-status");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['is_blocked', 'is_blocked_by'],
        ]);
    }
}
