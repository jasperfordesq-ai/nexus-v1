<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\MessageService;
use Illuminate\Http\JsonResponse;

/**
 * MessagesController - Conversations and direct messaging.
 *
 * Native Eloquent implementation for core endpoints.
 * Complex endpoints (typing indicators, voice upload, reactions) delegate to legacy.
 *
 * Endpoints (v2):
 *   GET    /api/v2/messages/conversations  conversations()
 *   GET    /api/v2/messages/{id}           show()
 *   POST   /api/v2/messages                send()
 *   PUT    /api/v2/messages/{id}/read      markRead()
 *   GET    /api/v2/messages/unread-count   unreadCount()
 */
class MessagesController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly MessageService $messageService,
    ) {}

    /**
     * GET /api/v2/messages/conversations
     *
     * List conversations for the authenticated user, grouped by partner.
     * Each conversation shows the latest message, partner info, and unread count.
     *
     * Response: { data: [...], meta: { cursor, per_page, has_more } }
     */
    public function conversations(): JsonResponse
    {
        $userId = $this->requireAuth();

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];
        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = $this->messageService->getConversations($userId, $filters);

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'] ?? null,
            $filters['limit'],
            $result['has_more'] ?? false
        );
    }

    /**
     * GET /api/v2/messages/{id}
     *
     * Get messages in a conversation with user {id}.
     * Automatically marks messages as read when viewed.
     *
     * Query params: per_page (int, default 50), cursor (string),
     *               direction ('older'|'newer', default 'older').
     *
     * Response: { data: [...messages], meta: { cursor, per_page, has_more } }
     */
    public function show(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $filters = [
            'limit' => $this->queryInt('per_page', 50, 1, 100),
            'direction' => $this->query('direction', 'older'),
        ];
        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = $this->messageService->getMessages($id, $userId, $filters);

        if ($result === null) {
            return $this->respondWithError('NOT_FOUND', 'Conversation not found', null, 404);
        }

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'] ?? null,
            $filters['limit'],
            $result['has_more'] ?? false
        );
    }

    /**
     * POST /api/v2/messages
     *
     * Send a new message. Requires authentication.
     *
     * Request body:
     * {
     *   "recipient_id": int (required),
     *   "body": string (required unless voice_url provided),
     *   "voice_url": string (optional),
     *   "voice_duration": int (optional)
     * }
     *
     * Response: 201 { data: { ...message } }
     */
    public function send(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('message_send', 30, 60);

        $input = $this->getAllInput();

        if (empty($input['recipient_id'])) {
            return $this->respondWithError('VALIDATION_ERROR', 'recipient_id is required', 'recipient_id', 422);
        }

        $body = trim($input['body'] ?? '');
        $voiceUrl = $input['voice_url'] ?? null;

        if (empty($body) && empty($voiceUrl)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Message body or voice message is required', 'body', 422);
        }

        $message = $this->messageService->send($userId, $input);

        if (isset($message['error'])) {
            return $this->respondWithError('VALIDATION_ERROR', $message['error'], null, 422);
        }

        return $this->respondWithData($message, null, 201);
    }

    /**
     * PUT /api/v2/messages/{id}/read
     *
     * Mark all messages from user {id} as read.
     *
     * Response: { data: { marked_read: true } }
     */
    public function markRead(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $this->messageService->markRead($userId, $id);

        return $this->respondWithData(['marked_read' => true]);
    }

    /**
     * GET /api/v2/messages/unread-count
     *
     * Get unread message count for badge display.
     *
     * Response: { data: { count: N } }
     *
     * Note: Legacy response used "count", we match that. The old controller
     * used "unread_count" but the legacy API used "count".
     */
    public function unreadCount(): JsonResponse
    {
        $userId = $this->requireAuth();

        $count = $this->messageService->getUnreadCount($userId);

        return $this->respondWithData([
            'count' => $count,
        ]);
    }

    // ========================================================================
    // Delegated endpoints — complex logic (Pusher typing, voice upload, etc.)
    // ========================================================================

    public function restrictionStatus(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\MessagesApiController::class, 'restrictionStatus');
    }

    public function typing(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\MessagesApiController::class, 'typing');
    }

    public function uploadVoice(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\MessagesApiController::class, 'uploadVoice');
    }

    public function sendVoice(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\MessagesApiController::class, 'sendVoice');
    }

    public function archiveConversation($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\MessagesApiController::class, 'archiveConversation', [$id]);
    }

    public function toggleReaction($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\MessagesApiController::class, 'toggleReaction', [$id]);
    }

    public function update($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\MessagesApiController::class, 'update', [$id]);
    }

    public function deleteMessage($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\MessagesApiController::class, 'deleteMessage', [$id]);
    }

    public function archive($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\MessagesApiController::class, 'archive', [$id]);
    }

    public function restoreConversation($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\MessagesApiController::class, 'restoreConversation', [$id]);
    }

    public function deleteConversation(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\MessageController::class, 'deleteConversation');
    }

    public function getReactionsBatch(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\MessageController::class, 'getReactionsBatch');
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
}
