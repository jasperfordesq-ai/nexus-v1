<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Nexus\Models\AiConversation;
use Nexus\Models\AiMessage;
use Nexus\Models\AiUserLimit;
use Nexus\Services\AI\AIServiceFactory;

/**
 * AiChatController -- AI chat endpoints (chat, stream, history, conversations,
 * providers, content generation).
 *
 * Core chat/history uses DB facade. Conversation CRUD and AI content generation
 * call legacy static services/models directly. The streamChat method is kept as
 * delegation because it uses SSE (Server-Sent Events) with ob_flush/flush/exit.
 */
class AiChatController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct() {}

    // =====================================================================
    // CHAT (already migrated)
    // =====================================================================

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

    /** POST /api/v2/ai/chat/stream — SSE streaming, kept as delegation */
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

    // =====================================================================
    // HISTORY (already migrated)
    // =====================================================================

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

    // =====================================================================
    // CONVERSATIONS
    // =====================================================================

    /** GET /api/v2/ai/conversations */
    public function listConversations(): JsonResponse
    {
        $userId = $this->getUserId();
        $limit = min($this->queryInt('limit', 50, 1, 100), 100);
        $offset = $this->queryInt('offset', 0, 0);

        $conversations = AiConversation::getByUserId($userId, $limit, $offset);
        $total = AiConversation::countByUserId($userId);

        return $this->respondWithData($conversations, [
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /** GET /api/v2/ai/conversations/{id} */
    public function getConversation($id): JsonResponse
    {
        $userId = $this->getUserId();
        $id = (int) $id;

        if (!AiConversation::belongsToUser($id, $userId)) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'Conversation not found', null, 404);
        }

        $conversation = AiConversation::getWithMessages($id);

        return $this->respondWithData($conversation);
    }

    /** POST /api/v2/ai/conversations */
    public function createConversation(): JsonResponse
    {
        $userId = $this->getUserId();

        $conversationId = AiConversation::create($userId, [
            'title' => $this->input('title', 'New Chat'),
            'provider' => $this->input('provider'),
            'context_type' => $this->input('context_type', 'general'),
            'context_id' => $this->input('context_id'),
        ]);

        return $this->respondWithData([
            'conversation_id' => $conversationId,
        ], null, 201);
    }

    /** DELETE /api/v2/ai/conversations/{id} */
    public function deleteConversation($id): JsonResponse
    {
        $userId = $this->getUserId();
        $id = (int) $id;

        if (!AiConversation::belongsToUser($id, $userId)) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'Conversation not found', null, 404);
        }

        AiConversation::delete($id);

        return $this->respondWithData(['success' => true]);
    }

    // =====================================================================
    // PROVIDERS
    // =====================================================================

    /** GET /api/v2/ai/providers */
    public function getProviders(): JsonResponse
    {
        $this->getUserId();

        $providers = AIServiceFactory::getAvailableProviders();
        $defaultProvider = AIServiceFactory::getDefaultProvider();

        return $this->respondWithData([
            'providers' => $providers,
            'default' => $defaultProvider,
            'enabled' => AIServiceFactory::isEnabled(),
        ]);
    }

    /** GET /api/v2/ai/limits */
    public function getLimits(): JsonResponse
    {
        $userId = $this->getUserId();
        $limits = AiUserLimit::canMakeRequest($userId);

        return $this->respondWithData(['limits' => $limits]);
    }

    /** POST /api/v2/ai/test-provider */
    public function testProvider(): JsonResponse
    {
        $this->getUserId();

        $providerId = $this->input('provider', 'gemini');

        try {
            $provider = AIServiceFactory::getProvider($providerId);
            $result = $provider->testConnection();

            return $this->respondWithData([
                'success' => $result['success'],
                'message' => $result['message'],
                'latency_ms' => $result['latency_ms'],
            ]);
        } catch (\Exception $e) {
            return $this->respondWithData([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    // =====================================================================
    // CONTENT GENERATION
    // =====================================================================

    /** POST /api/v2/ai/generate/listing */
    public function generateListing(): JsonResponse
    {
        return $this->delegateAiContent(\Nexus\Controllers\Api\Ai\AiContentController::class, 'generateListing');
    }

    /** POST /api/v2/ai/generate/event */
    public function generateEvent(): JsonResponse
    {
        return $this->delegateAiContent(\Nexus\Controllers\Api\Ai\AiContentController::class, 'generateEvent');
    }

    /** POST /api/v2/ai/generate/message */
    public function generateMessage(): JsonResponse
    {
        return $this->delegateAiContent(\Nexus\Controllers\Api\Ai\AiContentController::class, 'generateMessage');
    }

    /** POST /api/v2/ai/generate/bio */
    public function generateBio(): JsonResponse
    {
        return $this->delegateAiContent(\Nexus\Controllers\Api\Ai\AiContentController::class, 'generateBio');
    }

    /** POST /api/v2/ai/generate/newsletter */
    public function generateNewsletter(): JsonResponse
    {
        return $this->delegateAiContent(\Nexus\Controllers\Api\Ai\AiAdminContentController::class, 'generateNewsletter');
    }

    /** POST /api/v2/ai/generate/blog */
    public function generateBlog(): JsonResponse
    {
        return $this->delegateAiContent(\Nexus\Controllers\Api\Ai\AiAdminContentController::class, 'generateBlog');
    }

    /** POST /api/v2/ai/generate/page */
    public function generatePage(): JsonResponse
    {
        return $this->delegateAiContent(\Nexus\Controllers\Api\Ai\AiAdminContentController::class, 'generatePage');
    }

    // =====================================================================
    // PRIVATE — AI content delegation (these call external AI APIs)
    // =====================================================================

    /**
     * Delegate AI content generation to legacy controllers.
     *
     * These methods involve external AI API calls (OpenAI, Gemini, etc.)
     * with complex prompt building, usage tracking, and rate limiting.
     * Kept as delegation until the AI service layer is fully ported.
     */
    private function delegateAiContent(string $legacyClass, string $method): JsonResponse
    {
        $controller = new $legacyClass();
        ob_start();
        $controller->$method();
        $output = ob_get_clean();
        $status = http_response_code();
        return response()->json(json_decode($output, true) ?: $output, $status ?: 200);
    }
}
