<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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

    // ================================================================
    // TRANSLATE — Authentication required
    // ================================================================

    public function test_translate_returns_401_without_auth(): void
    {
        $this->disableMaintenanceMode();

        $response = $this->apiPost('/v2/messages/1/translate', [
            'target_language' => 'en',
        ]);

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // TRANSLATE — Validation errors
    // ================================================================

    public function test_translate_returns_400_without_target_language(): void
    {
        $this->disableMaintenanceMode();
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/messages/1/translate', []);

        $response->assertStatus(400);
    }

    public function test_translate_returns_404_for_nonexistent_message(): void
    {
        $this->disableMaintenanceMode();
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/messages/999999/translate', [
            'target_language' => 'fr',
        ]);

        $response->assertStatus(404);
    }

    // ================================================================
    // TRANSLATE — Authorization (only sender/receiver can translate)
    // ================================================================

    public function test_translate_returns_404_for_other_users_message(): void
    {
        $this->disableMaintenanceMode();
        $sender = $this->authenticatedUser();
        $recipient = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $outsider = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);

        // Create a message between sender and recipient
        $messageId = DB::table('messages')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'sender_id' => $sender->id,
            'receiver_id' => $recipient->id,
            'body' => 'Hello, this is a test message.',
            'created_at' => now(),
        ]);

        // Authenticate as the outsider (not sender or receiver)
        Sanctum::actingAs($outsider, ['*']);

        $response = $this->apiPost("/v2/messages/{$messageId}/translate", [
            'target_language' => 'fr',
        ]);

        // Should return 404 (message not found for this user)
        $response->assertStatus(404);
    }

    // ================================================================
    // TRANSLATE — helper: ensure maintenance mode is off
    // ================================================================

    private function disableMaintenanceMode(): void
    {
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'general.maintenance_mode'],
            ['setting_value' => 'false', 'updated_at' => now()]
        );
    }

    // ================================================================
    // TRANSLATE — Text message body (INT1 feature)
    // ================================================================

    public function test_translate_text_message_returns_translated_text(): void
    {
        $this->disableMaintenanceMode();
        $sender = $this->authenticatedUser();
        $recipient = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);

        $messageId = DB::table('messages')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'sender_id' => $sender->id,
            'receiver_id' => $recipient->id,
            'body' => 'Bonjour, comment allez-vous?',
            'created_at' => now(),
        ]);

        // Mock the OpenAI API response
        config(['services.openai.key' => 'test-key']);
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'Hello, how are you?']]],
            ]),
        ]);

        $response = $this->apiPost("/v2/messages/{$messageId}/translate", [
            'target_language' => 'en',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.translated_text', 'Hello, how are you?');
        $response->assertJsonPath('data.source_type', 'body');
    }

    // ================================================================
    // TRANSLATE — Voice transcript (existing INT2 feature, now generalized)
    // ================================================================

    public function test_translate_voice_transcript_returns_translated_text(): void
    {
        $this->disableMaintenanceMode();

        // Ensure transcript columns exist (migration may not have run on test DB)
        if (!DB::getSchemaBuilder()->hasColumn('messages', 'transcript')) {
            DB::statement('ALTER TABLE messages ADD COLUMN transcript TEXT NULL AFTER body');
            DB::statement('ALTER TABLE messages ADD COLUMN transcript_language VARCHAR(10) NULL AFTER transcript');
        }

        $sender = $this->authenticatedUser();
        $recipient = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);

        $messageId = DB::table('messages')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'sender_id' => $sender->id,
            'receiver_id' => $recipient->id,
            'body' => '',
            'audio_url' => '/uploads/audio/test.webm',
            'transcript' => 'Hola, como estas?',
            'transcript_language' => 'es',
            'created_at' => now(),
        ]);

        config(['services.openai.key' => 'test-key']);
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'Hello, how are you?']]],
            ]),
        ]);

        $response = $this->apiPost("/v2/messages/{$messageId}/translate", [
            'target_language' => 'en',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.translated_text', 'Hello, how are you?');
        $response->assertJsonPath('data.source_type', 'transcript');

        // Verify the correct source text was sent to OpenAI
        Http::assertSent(function ($request) {
            $body = $request->data();
            $systemPrompt = $body['messages'][0]['content'] ?? '';
            $userContent = $body['messages'][1]['content'] ?? '';
            // Should translate transcript (not body), with known source language 'es'
            return str_contains($systemPrompt, 'from es to en')
                && $userContent === 'Hola, como estas?';
        });
    }

    // ================================================================
    // TRANSLATE — Transcript takes priority over body
    // ================================================================

    public function test_translate_prefers_transcript_over_body(): void
    {
        $this->disableMaintenanceMode();

        if (!DB::getSchemaBuilder()->hasColumn('messages', 'transcript')) {
            DB::statement('ALTER TABLE messages ADD COLUMN transcript TEXT NULL AFTER body');
            DB::statement('ALTER TABLE messages ADD COLUMN transcript_language VARCHAR(10) NULL AFTER transcript');
        }

        $sender = $this->authenticatedUser();
        $recipient = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);

        $messageId = DB::table('messages')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'sender_id' => $sender->id,
            'receiver_id' => $recipient->id,
            'body' => 'This is the body text',
            'transcript' => 'This is the transcript text',
            'transcript_language' => 'en',
            'created_at' => now(),
        ]);

        config(['services.openai.key' => 'test-key']);
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'Translated transcript']]],
            ]),
        ]);

        $response = $this->apiPost("/v2/messages/{$messageId}/translate", [
            'target_language' => 'fr',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.source_type', 'transcript');

        // Verify transcript was sent, not body
        Http::assertSent(function ($request) {
            $userContent = $request->data()['messages'][1]['content'] ?? '';
            return $userContent === 'This is the transcript text';
        });
    }

    // ================================================================
    // TRANSLATE — Auto language detection for text messages
    // ================================================================

    public function test_translate_text_uses_auto_language_detection(): void
    {
        $this->disableMaintenanceMode();
        $sender = $this->authenticatedUser();
        $recipient = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);

        $messageId = DB::table('messages')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'sender_id' => $sender->id,
            'receiver_id' => $recipient->id,
            'body' => 'Guten Morgen!',
            'created_at' => now(),
        ]);

        config(['services.openai.key' => 'test-key']);
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'Good morning!']]],
            ]),
        ]);

        $response = $this->apiPost("/v2/messages/{$messageId}/translate", [
            'target_language' => 'en',
        ]);

        $response->assertStatus(200);

        // Text messages use 'auto' detection — prompt should say "Detect the language"
        Http::assertSent(function ($request) {
            $systemPrompt = $request->data()['messages'][0]['content'] ?? '';
            return str_contains($systemPrompt, 'Detect the language');
        });
    }

    // ================================================================
    // TRANSLATE — Empty message returns 422
    // ================================================================

    public function test_translate_returns_422_for_empty_message(): void
    {
        $this->disableMaintenanceMode();
        $sender = $this->authenticatedUser();
        $recipient = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);

        $messageId = DB::table('messages')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'sender_id' => $sender->id,
            'receiver_id' => $recipient->id,
            'body' => '',
            'created_at' => now(),
        ]);

        $response = $this->apiPost("/v2/messages/{$messageId}/translate", [
            'target_language' => 'en',
        ]);

        $response->assertStatus(422);
    }

    // ================================================================
    // TRANSLATE — Receiver can also translate
    // ================================================================

    public function test_translate_works_for_receiver(): void
    {
        $this->disableMaintenanceMode();
        $sender = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $recipient = $this->authenticatedUser();

        $messageId = DB::table('messages')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'sender_id' => $sender->id,
            'receiver_id' => $recipient->id,
            'body' => 'Guten Morgen!',
            'created_at' => now(),
        ]);

        config(['services.openai.key' => 'test-key']);
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'Good morning!']]],
            ]),
        ]);

        $response = $this->apiPost("/v2/messages/{$messageId}/translate", [
            'target_language' => 'en',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.translated_text', 'Good morning!');
    }
}
