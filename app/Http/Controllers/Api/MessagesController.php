<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\MessageService;
use App\Services\BrokerMessageVisibilityService;
use App\Core\AudioUploader;
use App\Models\Message;
use Illuminate\Support\Facades\Log;

/**
 * MessagesController - Conversations, direct messaging, reactions.
 *
 * Converted from delegation to direct static service calls.
 * uploadVoice() and sendVoice() now use native Laravel request()->file().
 * typing() remains delegated (uses Pusher real-time events).
 */
class MessagesController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly MessageService $messageService,
        private readonly BrokerMessageVisibilityService $brokerMessageVisibilityService,
    ) {}

    // ================================================================
    // CONVERSATIONS
    // ================================================================

    /**
     * GET /api/v2/messages/conversations
     */
    public function conversations(): JsonResponse
    {
        $userId = $this->requireAuth();

        $filters = [
            'limit'    => $this->queryInt('per_page', 20, 1, 100),
            'archived' => $this->queryBool('archived', false),
        ];
        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = $this->messageService->getConversations($userId, $filters);

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /**
     * GET /api/v2/messages/{id}
     */
    public function show(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $otherUserId = $id;

        // Verify conversation exists
        $conversation = $this->messageService->getConversation($otherUserId, $userId);

        if (!$conversation) {
            $errors = $this->messageService->getErrors();
            if (!empty($errors)) {
                return $this->respondWithErrors($errors, 404);
            }
            return $this->respondWithError('NOT_FOUND', 'Conversation not found', null, 404);
        }

        $filters = [
            'limit'     => $this->queryInt('per_page', 50, 1, 100),
            'direction' => $this->query('direction', 'older'),
        ];
        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = $this->messageService->getMessages($otherUserId, $userId, $filters);

        // Mark as read when viewing (unless explicitly fetching newer messages for polling)
        if ($filters['direction'] !== 'newer' || !$this->query('cursor')) {
            $this->messageService->markAsRead($otherUserId, $userId);
        }

        return $this->respondWithData($result['items'], [
            'conversation' => $conversation,
            'cursor'       => $result['cursor'],
            'per_page'     => $filters['limit'],
            'has_more'     => $result['has_more'],
        ]);
    }

    /**
     * POST /api/v2/messages
     */
    public function send(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('messages_send', 30, 60);

        $data = $this->getAllInput();

        if (empty($data['recipient_id'])) {
            return $this->respondWithError('VALIDATION_ERROR', 'recipient_id is required', 'recipient_id', 422);
        }

        $body = trim($data['body'] ?? '');
        $voiceUrl = $data['voice_url'] ?? null;

        if (empty($body) && empty($voiceUrl)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Message body or voice message is required', 'body', 422);
        }

        if (strlen($body) > 10000) {
            return $this->respondWithError('VALIDATION_ERROR', 'Message is too long (max 10000 characters)', 'body', 400);
        }

        $message = $this->messageService->send($userId, $data);

        if (!$message) {
            $errors = $this->messageService->getErrors();
            return $this->respondWithErrors($errors, 422);
        }

        // Award XP for sending a message
        try {
            \App\Services\GamificationService::awardXP($userId, \App\Services\GamificationService::XP_VALUES['send_message'], 'send_message', 'Sent a message');
        } catch (\Throwable $e) {
            \Log::warning('Gamification XP award failed', ['action' => 'send_message', 'user' => $userId, 'error' => $e->getMessage()]);
        }

        return $this->respondWithData($message, null, 201);
    }

    /**
     * PUT /api/v2/messages/{id}/read
     */
    public function markRead(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('messages_mark_read', 60, 60);
        $otherUserId = $id;

        $count = $this->messageService->markAsRead($otherUserId, $userId);

        return $this->respondWithData(['marked_read' => $count]);
    }

    /**
     * GET /api/v2/messages/unread-count
     */
    public function unreadCount(): JsonResponse
    {
        $userId = $this->requireAuth();

        $count = $this->messageService->getUnreadCount($userId);

        return $this->respondWithData(['count' => $count]);
    }

    // ================================================================
    // RESTRICTION STATUS
    // ================================================================

    /**
     * GET /api/v2/messages/restriction-status
     */
    public function restrictionStatus(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('messages_restriction_status', 30, 60);

        $status = $this->brokerMessageVisibilityService->getUserRestrictionStatus($userId);

        return $this->respondWithData($status);
    }

    // ================================================================
    // ARCHIVE / RESTORE
    // ================================================================

    /**
     * DELETE /api/v2/messages/conversations/{id}
     *
     * Accepts optional body: { "scope": "self" | "everyone" }
     * "self" (default) — hides from current user's inbox only (restorable).
     * "everyone"       — hides from both users' inboxes.
     */
    public function archiveConversation($id): JsonResponse
    {
        $id = (int) $id;
        $userId = $this->requireAuth();
        $this->rateLimit('messages_delete', 10, 60);

        $conversation = $this->messageService->getConversation($id, $userId);
        if (!$conversation) {
            return $this->respondWithError('NOT_FOUND', 'Conversation not found', null, 404);
        }

        $scope = in_array($this->input('scope'), ['self', 'everyone'], true)
            ? $this->input('scope')
            : 'self';

        // Returns int (rows updated) — 0 is valid when already archived (idempotent)
        $this->messageService->archiveConversation($id, $userId, $scope);

        return $this->respondWithData(['success' => true, 'message' => 'Conversation deleted']);
    }

    /**
     * DELETE /api/v2/messages/{id} — Archive/delete conversation
     */
    public function archive($id): JsonResponse
    {
        $id = (int) $id;
        $userId = $this->requireAuth();
        $this->rateLimit('messages_delete', 10, 60);

        $conversation = $this->messageService->getConversation($id, $userId);
        if (!$conversation) {
            return $this->respondWithError('NOT_FOUND', 'Conversation not found', null, 404);
        }

        $this->messageService->archiveConversation($id, $userId);

        return $this->noContent();
    }

    /**
     * POST /api/v2/messages/conversations/{id}/restore
     */
    public function restoreConversation($id): JsonResponse
    {
        $id = (int) $id;
        $userId = $this->requireAuth();
        $this->rateLimit('messages_restore', 20, 60);

        $count = $this->messageService->unarchiveConversation($id, $userId);

        if ($count === 0) {
            return $this->respondWithError('NOT_FOUND', 'No archived conversation found', null, 404);
        }

        return $this->respondWithData(['success' => true, 'message' => 'Conversation restored', 'restored_count' => $count]);
    }

    // ================================================================
    // EDIT / DELETE MESSAGE
    // ================================================================

    /**
     * PUT /api/v2/messages/{id}
     */
    public function update($id): JsonResponse
    {
        $id = (int) $id;
        $userId = $this->requireAuth();
        $this->rateLimit('messages_edit', 30, 60);

        $body = trim($this->input('body', ''));
        if (empty($body)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Message body is required', 'body', 400);
        }

        if (strlen($body) > 10000) {
            return $this->respondWithError('VALIDATION_ERROR', 'Message is too long (max 10000 characters)', 'body', 400);
        }

        $result = $this->messageService->editMessage($id, $userId, $body);

        if ($result === null) {
            $errors = $this->messageService->getErrors();
            if (!empty($errors)) {
                return $this->respondWithErrors($errors, 403);
            }
            return $this->respondWithError('NOT_FOUND', 'Message not found', null, 404);
        }

        return $this->respondWithData($result);
    }

    /**
     * DELETE /api/v2/messages/{id}
     *
     * Accepts optional body: { "scope": "self" | "everyone" }
     * "everyone" (default) — blanks message for both parties.
     * "self"               — hides message from current user's view only.
     */
    public function deleteMessage($id): JsonResponse
    {
        $id = (int) $id;
        $userId = $this->requireAuth();
        $this->rateLimit('messages_delete', 20, 60);

        $scope = in_array($this->input('scope'), ['self', 'everyone'], true)
            ? $this->input('scope')
            : 'everyone';

        $success = $this->messageService->deleteMessage($id, $userId, $scope);

        if (!$success) {
            $errors = $this->messageService->getErrors();
            if (!empty($errors)) {
                return $this->respondWithErrors($errors, 403);
            }
            return $this->respondWithError('NOT_FOUND', 'Message not found', null, 404);
        }

        return $this->respondWithData(['success' => true, 'message' => 'Message deleted']);
    }

    // ================================================================
    // REACTIONS
    // ================================================================

    /**
     * POST /api/v2/messages/{id}/reactions
     */
    public function toggleReaction($id): JsonResponse
    {
        $id = (int) $id;
        $userId = $this->requireAuth();
        $this->rateLimit('messages_reactions', 60, 60);

        $emoji = $this->input('emoji', '');
        if (empty($emoji)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Emoji is required', 'emoji', 400);
        }

        $allowedEmojis = ['👍', '❤️', '😂', '😮', '😢', '🙏'];
        if (!in_array($emoji, $allowedEmojis, true)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Invalid emoji', 'emoji', 400);
        }

        $result = $this->messageService->toggleReaction($id, $userId, $emoji);

        if ($result === null) {
            $errors = $this->messageService->getErrors();
            if (!empty($errors)) {
                return $this->respondWithErrors($errors, 404);
            }
            return $this->respondWithError('NOT_FOUND', 'Message not found', null, 404);
        }

        return $this->respondWithData([
            'action'     => $result ? 'added' : 'removed',
            'emoji'      => $emoji,
            'message_id' => $id,
        ]);
    }

    /**
     * DELETE /api/v2/messages/conversations (v1 legacy — delete conversation)
     */
    public function deleteConversation(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('messages_delete', 10, 60);

        $otherUserId = $this->inputInt('other_user_id');
        if (!$otherUserId) {
            return $this->respondWithError('VALIDATION_ERROR', 'Other user ID required', 'other_user_id', 400);
        }

        try {
            $deleted = Message::deleteConversation($userId, $otherUserId);
            return $this->success(['deleted' => $deleted]);
        } catch (\Throwable $e) {
            Log::error('Failed to delete conversation', ['error' => $e->getMessage(), 'user' => $userId, 'other_user' => $otherUserId]);
            return $this->error('Failed to delete conversation', 500);
        }
    }

    /**
     * GET /api/v2/messages/reactions/batch?ids=1,2,3
     */
    public function getReactionsBatch(): JsonResponse
    {
        $this->requireAuth();
        $this->rateLimit('messages_reactions_batch', 30, 60);

        $idsParam = $this->query('ids', '');
        if (empty($idsParam)) {
            return $this->success(['reactions' => []]);
        }

        $ids = array_filter(array_map('intval', explode(',', $idsParam)));
        if (empty($ids)) {
            return $this->success(['reactions' => []]);
        }

        // Limit to 100 messages at a time
        $ids = array_slice($ids, 0, 100);

        try {
            $reactions = Message::getReactionsBatch($ids);
            return $this->success(['reactions' => $reactions]);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch reactions batch', ['error' => $e->getMessage(), 'ids' => $ids]);
            return $this->error('Failed to fetch reactions', 500);
        }
    }

    // ================================================================
    // TYPING INDICATOR (Pusher real-time)
    // ================================================================

    /**
     * POST /api/v2/messages/typing
     */
    public function typing(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('messages_typing', 60, 60);

        $recipientId = $this->inputInt('recipient_id', 0, 1);
        $isTyping = $this->inputBool('is_typing', true);

        if (!$recipientId) {
            return $this->respondWithError('VALIDATION_ERROR', 'Recipient ID is required', 'recipient_id', 400);
        }

        $this->messageService->setTypingIndicator($recipientId, $userId, $isTyping);

        return $this->respondWithData(['sent' => true]);
    }

    // ================================================================
    // VOICE UPLOADS (native Laravel)
    // ================================================================

    /**
     * POST /api/v2/messages/upload-voice
     *
     * Upload an audio file for voice messaging without sending.
     * Accepts file upload (field: 'audio') or base64 (field: 'audio_data' + 'mime_type').
     * Returns the voice URL and duration for later use with send().
     */
    public function uploadVoice(): JsonResponse
    {
        $this->requireAuth();
        $this->rateLimit('messages_voice_upload', 10, 60);

        $duration = $this->inputInt('duration', 0, 0);

        try {
            $audioResult = null;

            $file = request()->file('audio');
            if ($file && $file->isValid()) {
                // Standard file upload
                $fileArray = [
                    'name'     => $file->getClientOriginalName(),
                    'type'     => $file->getMimeType(),
                    'tmp_name' => $file->getRealPath(),
                    'error'    => UPLOAD_ERR_OK,
                    'size'     => $file->getSize(),
                ];
                $audioResult = AudioUploader::upload($fileArray, $duration);
            } elseif (request()->input('audio_data')) {
                // Base64 encoded audio (from MediaRecorder blob)
                $mimeType = request()->input('mime_type', 'audio/webm');
                $audioResult = AudioUploader::uploadFromBase64(request()->input('audio_data'), $mimeType, $duration);
            } else {
                return $this->respondWithError('VALIDATION_ERROR', 'No audio data provided', 'audio', 400);
            }

            return $this->respondWithData([
                'voice_url' => $audioResult['url'],
                'duration'  => $audioResult['duration'],
            ]);
        } catch (\Exception $e) {
            Log::error('Voice upload failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->respondWithError('UPLOAD_FAILED', 'Failed to upload audio file', 'audio', 400);
        }
    }

    /**
     * POST /api/v2/messages/voice
     *
     * Upload and send a voice message in one step. Uses request()->file() (Laravel native).
     * Field name: 'voice_message'. Form field: 'recipient_id' (required).
     */
    public function sendVoice(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('messages_voice_upload', 10, 60);

        $recipientId = (int) request()->input('recipient_id', 0);
        if (!$recipientId) {
            return $this->respondWithError('VALIDATION_ERROR', 'Recipient ID is required', 'recipient_id', 400);
        }

        $file = request()->file('voice_message');
        if (!$file || !$file->isValid()) {
            return $this->respondWithError('VALIDATION_ERROR', 'Voice message file is required', 'voice_message', 400);
        }

        try {
            // Build a $_FILES-compatible array for AudioUploader::upload()
            $fileArray = [
                'name'     => $file->getClientOriginalName(),
                'type'     => $file->getMimeType(),
                'tmp_name' => $file->getRealPath(),
                'error'    => UPLOAD_ERR_OK,
                'size'     => $file->getSize(),
            ];

            $audioResult = AudioUploader::upload($fileArray, 0);

            // Send the message with voice attachment
            $message = $this->messageService->send($userId, [
                'recipient_id'   => $recipientId,
                'body'           => '', // Voice messages have no text body
                'is_voice'       => true,
                'audio_url'      => $audioResult['url'],
                'audio_duration' => $audioResult['duration'],
            ]);

            if (!$message) {
                $errors = $this->messageService->getErrors();
                return $this->respondWithErrors($errors, 422);
            }

            return $this->respondWithData($message, null, 201);
        } catch (\Exception $e) {
            Log::error('Voice message send failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->respondWithError('UPLOAD_FAILED', 'Failed to send voice message', 'voice_message', 400);
        }
    }

}
