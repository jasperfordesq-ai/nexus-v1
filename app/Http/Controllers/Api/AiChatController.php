<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * AiChatController -- AI chat endpoints (chat, stream, history).
 *
 * All methods require authentication.
 */
class AiChatController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct() {}

    /** POST /api/v2/ai/chat */
    public function chat(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();
        $this->rateLimit('ai_chat', 30, 60);

        $message = $this->requireInput('message');
        $conversationId = $this->input('conversation_id');

        DB::insert(
            'INSERT INTO ai_chat_messages (tenant_id, user_id, conversation_id, role, content, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            [$tenantId, $userId, $conversationId, 'user', $message]
        );

        $response = [
            'role' => 'assistant',
            'content' => 'AI chat is not yet configured for this tenant.',
            'conversation_id' => $conversationId,
        ];

        return $this->respondWithData($response);
    }

    /** POST /api/v2/ai/chat/stream */
    public function streamChat(): JsonResponse
    {
        $this->requireAuth();
        $this->rateLimit('ai_chat_stream', 20, 60);

        return $this->respondWithError(
            'NOT_IMPLEMENTED',
            'Streaming chat is not yet available. Use the standard chat endpoint.',
            null,
            501
        );
    }

    /** GET /api/v2/ai/chat/history */
    public function history(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 50);
        $offset = ($page - 1) * $perPage;

        $items = DB::select(
            'SELECT * FROM ai_chat_messages WHERE tenant_id = ? AND user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?',
            [$tenantId, $userId, $perPage, $offset]
        );
        $total = DB::selectOne(
            'SELECT COUNT(*) as cnt FROM ai_chat_messages WHERE tenant_id = ? AND user_id = ?',
            [$tenantId, $userId]
        )->cnt;

        return $this->respondWithPaginatedCollection($items, (int) $total, $page, $perPage);
    }

    /**
     * Delegate to legacy controller via output buffering.
     */
    private function delegate(string $legacyClass, string $method, array $params = []): JsonResponse
    {
        $controller = new $legacyClass();
        ob_start();
        $controller->$method(...$params);
        $output = ob_get_clean();
        $status = http_response_code();
        return response()->json(json_decode($output, true) ?: $output, $status ?: 200);
    }


    public function listConversations(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\Ai\AiChatController::class, 'listConversations');
    }


    public function getConversation($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\Ai\AiChatController::class, 'getConversation', [$id]);
    }


    public function createConversation(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\Ai\AiChatController::class, 'createConversation');
    }


    public function deleteConversation($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\Ai\AiChatController::class, 'deleteConversation', [$id]);
    }


    public function getProviders(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\Ai\AiProviderController::class, 'getProviders');
    }


    public function getLimits(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\Ai\AiProviderController::class, 'getLimits');
    }


    public function testProvider(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\Ai\AiProviderController::class, 'testProvider');
    }


    public function generateListing(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\Ai\AiContentController::class, 'generateListing');
    }


    public function generateEvent(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\Ai\AiContentController::class, 'generateEvent');
    }


    public function generateMessage(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\Ai\AiContentController::class, 'generateMessage');
    }


    public function generateBio(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\Ai\AiContentController::class, 'generateBio');
    }


    public function generateNewsletter(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\Ai\AiAdminContentController::class, 'generateNewsletter');
    }


    public function generateBlog(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\Ai\AiAdminContentController::class, 'generateBlog');
    }


    public function generatePage(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\Ai\AiAdminContentController::class, 'generatePage');
    }

}
