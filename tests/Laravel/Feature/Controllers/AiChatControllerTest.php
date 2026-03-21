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
 * Feature tests for AiChatController — AI chat, conversations, content generation.
 */
class AiChatControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authenticatedUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    // ------------------------------------------------------------------
    //  POST /ai/chat
    // ------------------------------------------------------------------

    public function test_chat_requires_authentication(): void
    {
        $response = $this->apiPost('/ai/chat', ['message' => 'Hello']);

        $response->assertStatus(401);
    }

    public function test_chat_requires_message_field(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/ai/chat', []);

        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    // ------------------------------------------------------------------
    //  GET /ai/conversations
    // ------------------------------------------------------------------

    public function test_list_conversations_requires_auth(): void
    {
        $response = $this->apiGet('/ai/conversations');

        $response->assertStatus(401);
    }

    public function test_list_conversations_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/ai/conversations');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  POST /ai/conversations
    // ------------------------------------------------------------------

    public function test_create_conversation_requires_auth(): void
    {
        $response = $this->apiPost('/ai/conversations', []);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /ai/providers
    // ------------------------------------------------------------------

    public function test_get_providers_requires_auth(): void
    {
        $response = $this->apiGet('/ai/providers');

        $response->assertStatus(401);
    }

    public function test_get_providers_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/ai/providers');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /ai/limits
    // ------------------------------------------------------------------

    public function test_get_limits_requires_auth(): void
    {
        $response = $this->apiGet('/ai/limits');

        $response->assertStatus(401);
    }

    public function test_get_limits_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/ai/limits');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  POST /ai/generate/listing
    // ------------------------------------------------------------------

    public function test_generate_listing_requires_auth(): void
    {
        $response = $this->apiPost('/ai/generate/listing', ['prompt' => 'dog walking']);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /ai/generate/bio
    // ------------------------------------------------------------------

    public function test_generate_bio_requires_auth(): void
    {
        $response = $this->apiPost('/ai/generate/bio', ['prompt' => 'community helper']);

        $response->assertStatus(401);
    }
}
