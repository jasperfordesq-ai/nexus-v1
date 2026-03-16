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
}
