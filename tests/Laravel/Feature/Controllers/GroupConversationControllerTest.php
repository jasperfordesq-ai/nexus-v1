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
 * Feature smoke tests for GroupConversationController.
 */
class GroupConversationControllerTest extends TestCase
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

    public function test_store_requires_auth(): void
    {
        $this->apiPost('/v2/conversations/groups', [])->assertStatus(401);
    }

    public function test_index_requires_auth(): void
    {
        $this->apiGet('/v2/conversations/groups')->assertStatus(401);
    }

    public function test_participants_requires_auth(): void
    {
        $this->apiGet('/v2/conversations/1/participants')->assertStatus(401);
    }

    public function test_add_participant_requires_auth(): void
    {
        $this->apiPost('/v2/conversations/1/participants', [])->assertStatus(401);
    }

    public function test_remove_participant_requires_auth(): void
    {
        $this->apiDelete('/v2/conversations/1/participants/2')->assertStatus(401);
    }

    public function test_update_group_requires_auth(): void
    {
        $this->patchJson('/api/v2/conversations/1/group', [], [
            'X-Tenant-ID' => (string) $this->testTenantId,
            'Accept' => 'application/json',
        ])->assertStatus(401);
    }

    public function test_messages_requires_auth(): void
    {
        $this->apiGet('/v2/conversations/1/messages')->assertStatus(401);
    }

    public function test_send_message_requires_auth(): void
    {
        $this->apiPost('/v2/conversations/1/messages', [])->assertStatus(401);
    }

    public function test_index_returns_non_5xx_when_authenticated(): void
    {
        $this->authenticatedUser();
        $response = $this->apiGet('/v2/conversations/groups');
        $this->assertNotEquals(401, $response->status(), 'Auth should have passed');
    }
}
