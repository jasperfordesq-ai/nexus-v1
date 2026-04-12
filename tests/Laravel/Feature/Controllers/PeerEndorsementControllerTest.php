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
 * Feature tests for PeerEndorsementController.
 *
 * Covers:
 *  - POST /v2/members/{id}/peer-endorse — endorse a peer (auth required)
 *  - Self-endorsement prevention
 *  - Target user not found (cross-tenant or missing)
 *  - Idempotent insert (duplicate endorsements allowed, count unchanged)
 */
class PeerEndorsementControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_endorse_requires_auth(): void
    {
        $response = $this->apiPost('/v2/members/1/peer-endorse');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    public function test_user_can_endorse_peer(): void
    {
        $endorser = User::factory()->forTenant($this->testTenantId)->create();
        $target = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($endorser);

        $response = $this->apiPost("/v2/members/{$target->id}/peer-endorse");

        $response->assertStatus(200);
        $response->assertJsonPath('data.endorsed_id', $target->id);
        $response->assertJsonPath('data.threshold', 3);
    }

    public function test_cannot_endorse_self(): void
    {
        $me = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($me);

        $response = $this->apiPost("/v2/members/{$me->id}/peer-endorse");

        $response->assertStatus(422);
    }

    public function test_cannot_endorse_user_in_different_tenant(): void
    {
        $endorser = User::factory()->forTenant($this->testTenantId)->create();
        $otherTenantUser = User::factory()->forTenant(999)->create();
        Sanctum::actingAs($endorser);

        $response = $this->apiPost("/v2/members/{$otherTenantUser->id}/peer-endorse");

        $response->assertStatus(404);
    }

    public function test_duplicate_endorsement_is_idempotent(): void
    {
        $endorser = User::factory()->forTenant($this->testTenantId)->create();
        $target = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($endorser);

        $first = $this->apiPost("/v2/members/{$target->id}/peer-endorse");
        $first->assertStatus(200);
        $count1 = (int) $first->json('data.endorsement_count');

        $second = $this->apiPost("/v2/members/{$target->id}/peer-endorse");
        $second->assertStatus(200);
        $count2 = (int) $second->json('data.endorsement_count');

        // INSERT IGNORE — count does not double on repeat
        $this->assertSame($count1, $count2);
    }
}
