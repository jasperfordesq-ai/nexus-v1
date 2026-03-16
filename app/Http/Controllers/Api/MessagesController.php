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
     * List conversations for the authenticated user.
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
     * Get messages in a conversation.
     */
    public function show(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $filters = [
            'limit' => $this->queryInt('per_page', 50, 1, 100),
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
     * Send a new message. Requires authentication.
     */
    public function send(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('message_send', 30, 60);

        $message = $this->messageService->send($userId, $this->getAllInput());

        return $this->respondWithData($message, null, 201);
    }

    /**
     * Mark a conversation as read.
     */
    public function markRead(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $this->messageService->markRead($id, $userId);

        return $this->respondWithData(['marked_read' => true]);
    }

    /**
     * Get unread message count for the authenticated user.
     */
    public function unreadCount(): JsonResponse
    {
        $userId = $this->requireAuth();

        $count = $this->messageService->getUnreadCount($userId);

        return $this->respondWithData(['unread_count' => $count]);
    }
}
