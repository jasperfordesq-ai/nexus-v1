<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Accessible (GOV.UK) frontend — AI chat parity (no-JS single-turn-per-reload).
 * Auth/feature gates + the send flow (conversation + user/assistant messages).
 * The provider is not configured in tests, so aiChatSend uses its fallback reply
 * (AIServiceFactory::isEnabled() is false) — no network call, no hang.
 */
class AiChatParityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['auth']->forgetGuards();
        foreach (['HTTP_X_TENANT_ID', 'HTTP_X_TENANT_SLUG', 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $k) {
            unset($_SERVER[$k]);
        }
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    public function test_ai_chat_requires_authentication(): void
    {
        $this->setAiChat(true);
        $this->get("/{$this->testTenantSlug}/alpha/chat")
            ->assertRedirect("/{$this->testTenantSlug}/alpha/login?status=auth-required");
    }

    public function test_ai_chat_gated_off_without_feature(): void
    {
        $this->authedUser();
        $this->setAiChat(false);
        $this->get("/{$this->testTenantSlug}/alpha/chat")->assertStatus(403);
    }

    public function test_ai_chat_renders_for_member(): void
    {
        $this->authedUser();
        $this->setAiChat(true);
        $res = $this->get("/{$this->testTenantSlug}/alpha/chat");
        $res->assertOk();
        $res->assertSee(__('govuk_alpha_aichat.title'));
        $res->assertSee('name="message"', false);
    }

    public function test_ai_chat_send_empty_message_redirects(): void
    {
        $this->authedUser();
        $this->setAiChat(true);
        $this->post("/{$this->testTenantSlug}/alpha/chat", ['message' => '   '])
            ->assertRedirect("/{$this->testTenantSlug}/alpha/chat?status=empty");
    }

    public function test_ai_chat_send_creates_conversation_and_messages(): void
    {
        $user = $this->authedUser();
        $this->setAiChat(true);

        $this->post("/{$this->testTenantSlug}/alpha/chat", ['message' => 'How do I find a gardener?'])
            ->assertRedirect();

        $conv = DB::table('ai_conversations')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($conv, 'Conversation not created');

        $msgs = DB::table('ai_messages')->where('conversation_id', $conv->id)->orderBy('id')->get();
        $this->assertCount(2, $msgs, 'Expected a user message + an assistant reply');
        $this->assertSame('user', $msgs[0]->role);
        $this->assertSame('How do I find a gardener?', $msgs[0]->content);
        $this->assertSame('assistant', $msgs[1]->role);
        $this->assertNotSame('', trim((string) $msgs[1]->content));
    }

    private function authedUser(): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        Sanctum::actingAs($user, ['*']);
        return $user;
    }

    private function setAiChat(bool $on): void
    {
        $row = DB::table('tenants')->where('id', $this->testTenantId)->value('features');
        $current = $row ? (json_decode($row, true) ?: []) : [];
        $current['ai_chat'] = $on;
        DB::table('tenants')->where('id', $this->testTenantId)->update(['features' => json_encode($current)]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $this->app->instance('tenant.id', $this->testTenantId);
    }
}
