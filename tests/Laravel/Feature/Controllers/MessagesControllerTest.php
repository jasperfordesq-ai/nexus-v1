<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for MessagesController.
 *
 * Covers conversations, send, mark read, unread count, archive, reactions, typing.
 */
class MessagesControllerTest extends TestCase
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

    // ================================================================
    // CONVERSATIONS — Happy path
    // ================================================================

    public function test_conversations_returns_collection(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/messages');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'meta' => ['per_page', 'has_more'],
        ]);
    }

    // ================================================================
    // CONVERSATIONS — Authentication required
    // ================================================================

    public function test_conversations_returns_401_without_auth(): void
    {
        $response = $this->apiGet('/v2/messages');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // SEND — Happy path
    // ================================================================

    public function test_send_message_creates_message(): void
    {
        $sender = $this->authenticatedUser();
        $recipient = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
        ]);

        $response = $this->apiPost('/v2/messages', [
            'recipient_id' => $recipient->id,
            'body' => 'Hello, how are you?',
        ]);

        $this->assertContains($response->getStatusCode(), [200, 201]);
    }

    // ================================================================
    // SEND — Authentication required
    // ================================================================

    public function test_send_returns_401_without_auth(): void
    {
        $response = $this->apiPost('/v2/messages', [
            'recipient_id' => 1,
            'body' => 'Hello',
        ]);

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // SEND — Validation errors
    // ================================================================

    public function test_send_returns_422_without_recipient(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/messages', [
            'body' => 'No recipient specified.',
        ]);

        $response->assertStatus(422);
    }

    public function test_send_returns_422_without_body(): void
    {
        $this->authenticatedUser();
        $recipient = User::factory()->forTenant($this->testTenantId)->create();

        $response = $this->apiPost('/v2/messages', [
            'recipient_id' => $recipient->id,
        ]);

        $response->assertStatus(422);
    }

    // ================================================================
    // SHOW CONVERSATION — Authentication required
    // ================================================================

    public function test_show_conversation_returns_401_without_auth(): void
    {
        $response = $this->apiGet('/v2/messages/1');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // SHOW CONVERSATION — Not found
    // ================================================================

    public function test_show_conversation_returns_404_for_nonexistent_user(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/messages/999999');

        $response->assertStatus(404);
    }

    // ================================================================
    // MARK READ — Authentication required
    // ================================================================

    public function test_mark_read_returns_401_without_auth(): void
    {
        $response = $this->apiPut('/v2/messages/1/read');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // UNREAD COUNT — Happy path
    // ================================================================

    public function test_unread_count_returns_count(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/messages/unread-count');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['count']]);
    }

    // ================================================================
    // UNREAD COUNT — Authentication required
    // ================================================================

    public function test_unread_count_returns_401_without_auth(): void
    {
        $response = $this->apiGet('/v2/messages/unread-count');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // ARCHIVE CONVERSATION — Authentication required
    // ================================================================

    public function test_archive_conversation_returns_401_without_auth(): void
    {
        $response = $this->apiDelete('/v2/messages/conversations/1');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // ARCHIVE CONVERSATION — Not found
    // ================================================================

    public function test_archive_conversation_returns_404_for_nonexistent(): void
    {
        $this->authenticatedUser();

        $response = $this->apiDelete('/v2/messages/conversations/999999');

        $response->assertStatus(404);
    }

    // ================================================================
    // EDIT MESSAGE — Validation
    // ================================================================

    public function test_edit_message_returns_400_without_body(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPut('/v2/messages/1', [
            'body' => '',
        ]);

        $response->assertStatus(400);
    }

    public function test_edit_message_returns_401_without_auth(): void
    {
        $response = $this->apiPut('/v2/messages/1', [
            'body' => 'Updated',
        ]);

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // DELETE MESSAGE — Authentication required
    // ================================================================

    public function test_delete_message_returns_401_without_auth(): void
    {
        $response = $this->apiDelete('/v2/messages/1');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // REACTIONS — Validation
    // ================================================================

    public function test_toggle_reaction_returns_400_without_emoji(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/messages/1/reactions', []);

        $response->assertStatus(400);
    }

    public function test_toggle_reaction_returns_400_for_invalid_emoji(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/messages/1/reactions', [
            'emoji' => 'invalid',
        ]);

        $response->assertStatus(400);
    }

    public function test_toggle_reaction_returns_401_without_auth(): void
    {
        $response = $this->apiPost('/v2/messages/1/reactions', [
            'emoji' => "\xF0\x9F\x91\x8D", // thumbs up
        ]);

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // TYPING — Validation
    // ================================================================

    public function test_typing_returns_400_without_recipient(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/messages/typing', []);

        $response->assertStatus(400);
    }

    public function test_typing_returns_401_without_auth(): void
    {
        $response = $this->apiPost('/v2/messages/typing', [
            'recipient_id' => 1,
        ]);

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // RESTRICTION STATUS — Happy path
    // ================================================================

    public function test_restriction_status_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/messages/restriction-status');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // RESTRICTION STATUS — Authentication required
    // ================================================================

    public function test_restriction_status_returns_401_without_auth(): void
    {
        $response = $this->apiGet('/v2/messages/restriction-status');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // RESTORE CONVERSATION — Not found
    // ================================================================

    public function test_restore_conversation_returns_404_for_nonexistent(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/messages/conversations/999999/restore');

        $response->assertStatus(404);
    }
}
