<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AiChatService — Laravel DI-based service for AI chat operations.
 *
 * Manages AI-powered chat interactions, conversation history, and streaming responses.
 */
class AiChatService
{
    /**
     * Send a chat message and get a response from the AI provider.
     */
    public function chat(int $userId, string $message, array $context = []): array
    {
        $apiKey = config('services.openai.key');
        if (empty($apiKey)) {
            return ['reply' => 'AI chat is not configured.', 'error' => true];
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'    => $context['model'] ?? 'gpt-4o-mini',
                    'messages' => array_merge(
                        [['role' => 'system', 'content' => $context['system_prompt'] ?? 'You are a helpful community assistant.']],
                        $context['history'] ?? [],
                        [['role' => 'user', 'content' => $message]]
                    ),
                    'max_tokens' => min((int) ($context['max_tokens'] ?? 1024), 4096),
                ]);

            $reply = $response->json('choices.0.message.content', 'No response.');

            $this->saveMessage($userId, $message, $reply);

            return ['reply' => $reply, 'error' => false];
        } catch (\Throwable $e) {
            Log::error('AiChatService::chat failed', ['error' => $e->getMessage()]);
            return ['reply' => 'An error occurred. Please try again.', 'error' => true];
        }
    }

    /**
     * Stream a chat response (returns generator-compatible config).
     */
    public function streamChat(int $userId, string $message, array $context = []): array
    {
        return [
            'model'    => $context['model'] ?? 'gpt-4o-mini',
            'messages' => array_merge(
                [['role' => 'system', 'content' => $context['system_prompt'] ?? 'You are a helpful community assistant.']],
                $context['history'] ?? [],
                [['role' => 'user', 'content' => $message]]
            ),
            'stream'     => true,
            'max_tokens' => min((int) ($context['max_tokens'] ?? 1024), 4096),
        ];
    }

    /**
     * Get chat history for a user.
     */
    public function getHistory(int $userId, int $limit = 50): array
    {
        return DB::table('ai_chat_messages')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit(min($limit, 200))
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * Save a message exchange to the database.
     */
    private function saveMessage(int $userId, string $userMessage, string $aiReply): void
    {
        DB::table('ai_chat_messages')->insert([
            'user_id'    => $userId,
            'user_msg'   => $userMessage,
            'ai_reply'   => $aiReply,
            'created_at' => now(),
        ]);
    }
}
