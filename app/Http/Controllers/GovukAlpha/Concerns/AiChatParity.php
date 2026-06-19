<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\GovukAlpha\Concerns;

use App\Core\TenantContext;
use App\Services\AI\AIServiceFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * AI Chat — accessible (GOV.UK, no-JS) equivalent of the React AiChatPage.
 *
 * The React page streams tokens and renders tool-result cards; the accessible
 * version is HTML-first single-turn-per-reload: you type a message, the server
 * calls the SAME AIServiceFactory the API uses (synchronously, no tool-calling
 * orchestration), stores both messages in ai_conversations/ai_messages, and
 * re-renders the full thread. Past conversations are listed as plain links.
 * Degraded vs React (no streaming, no live tool cards) but fully functional and
 * keyboard/screen-reader friendly.
 */
trait AiChatParity
{
    /** GET /{tenantSlug}/alpha/chat[?c=<conversationId>] */
    public function aiChat(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('ai_chat'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tenantId = TenantContext::getId();

        $conversations = DB::table('ai_conversations')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'title', 'updated_at'])
            ->map(static fn ($r) => (array) $r)
            ->all();

        $selectedId = (int) self::asStr($request->query('c'));
        $messages = [];
        if ($selectedId > 0) {
            // Only load the thread if the conversation belongs to this user+tenant.
            $owns = DB::table('ai_conversations')
                ->where('id', $selectedId)
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->exists();
            if (!$owns) {
                $selectedId = 0;
            } else {
                $messages = DB::table('ai_messages')
                    ->where('conversation_id', $selectedId)
                    ->whereIn('role', ['user', 'assistant'])
                    ->orderBy('id')
                    ->limit(200)
                    ->get(['id', 'role', 'content', 'created_at'])
                    ->map(static fn ($r) => (array) $r)
                    ->all();
            }
        }

        return $this->view('accessible-frontend::ai-chat', [
            'title' => __('govuk_alpha_aichat.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'explore',
            'conversations' => $conversations,
            'selectedId' => $selectedId,
            'messages' => $messages,
            'aiEnabled' => AIServiceFactory::isEnabled(),
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /** POST /{tenantSlug}/alpha/chat — send a message, get a reply (PRG redirect). */
    public function aiChatSend(Request $request, string $tenantSlug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('ai_chat'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tenantId = TenantContext::getId();
        $message = trim(self::asStr($request->input('message')));
        if ($message === '') {
            return redirect()->route('govuk-alpha.chat.index', ['tenantSlug' => $tenantSlug, 'status' => 'empty']);
        }
        $message = mb_substr($message, 0, 4000);

        // Resolve / create the conversation (validated to this user+tenant).
        $conversationId = (int) self::asStr($request->input('conversation_id'));
        if ($conversationId > 0) {
            $owns = DB::table('ai_conversations')
                ->where('id', $conversationId)
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->exists();
            if (!$owns) {
                $conversationId = 0;
            }
        }
        if ($conversationId === 0) {
            $conversationId = (int) DB::table('ai_conversations')->insertGetId([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'title' => mb_substr($message, 0, 100),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('ai_messages')->insert([
            'conversation_id' => $conversationId,
            'role' => 'user',
            'content' => $message,
            'created_at' => now(),
        ]);

        // Build a bounded history (last ~12 turns) so follow-ups have context.
        $history = DB::table('ai_messages')
            ->where('conversation_id', $conversationId)
            ->whereIn('role', ['user', 'assistant'])
            ->orderByDesc('id')
            ->limit(12)
            ->get(['role', 'content'])
            ->reverse()
            ->values();

        $chatMessages = [[
            'role' => 'system',
            'content' => AIServiceFactory::getSystemPrompt() ?: 'You are a helpful community assistant for a timebanking platform. Answer concisely.',
        ]];
        foreach ($history as $h) {
            $chatMessages[] = ['role' => (string) $h->role, 'content' => (string) $h->content];
        }

        $reply = '';
        // Only hit the provider when one is configured — avoids a pointless network
        // attempt (and test-env hang) when AI is disabled; the fallback covers it.
        if (AIServiceFactory::isEnabled()) {
            try {
                $response = AIServiceFactory::chatWithFallback($chatMessages, [
                    'temperature' => 0.4,
                    'max_tokens' => 1000,
                ]);
                $reply = trim((string) ($response['content'] ?? ''));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($reply === '') {
            // Provider down/unconfigured — record a graceful fallback so the thread
            // still reads sensibly rather than showing a blank assistant turn.
            $reply = __('govuk_alpha_aichat.unavailable_reply');
        }

        DB::table('ai_messages')->insert([
            'conversation_id' => $conversationId,
            'role' => 'assistant',
            'content' => $reply,
            'created_at' => now(),
        ]);
        DB::table('ai_conversations')->where('id', $conversationId)->update(['updated_at' => now()]);

        return redirect()->route('govuk-alpha.chat.index', [
            'tenantSlug' => $tenantSlug,
            'c' => $conversationId,
            'status' => 'sent',
        ]);
    }
}
