<?php

namespace Nexus\Controllers\Api;

use Nexus\Services\MessageService;
use Nexus\Core\AudioUploader;

/**
 * MessagesApiController - RESTful API for messages and conversations
 *
 * Provides messaging endpoints with standardized response format.
 * All endpoints require authentication - messages are private.
 *
 * Endpoints:
 * - GET    /api/v2/messages              - List conversations (inbox)
 * - GET    /api/v2/messages/unread-count - Get total unread count
 * - GET    /api/v2/messages/{id}         - Get messages in a conversation
 * - POST   /api/v2/messages              - Send a message
 * - PUT    /api/v2/messages/{id}/read    - Mark conversation as read
 * - DELETE /api/v2/messages/{id}         - Archive/delete conversation
 * - POST   /api/v2/messages/typing       - Send typing indicator
 * - POST   /api/v2/messages/upload-voice - Upload voice message audio
 *
 * Note: {id} refers to the other user's ID (conversation identifier)
 *
 * Response Format:
 * Success: { "data": {...}, "meta": {...} }
 * Error:   { "errors": [{ "code": "...", "message": "...", "field": "..." }] }
 */
class MessagesApiController extends BaseApiController
{
    /**
     * GET /api/v2/messages
     *
     * List user's conversations (inbox view) with cursor pagination.
     * Each conversation shows the other participant, last message preview, and unread count.
     *
     * Query Parameters:
     * - cursor: string (pagination cursor)
     * - per_page: int (default 20, max 100)
     *
     * Response: 200 OK with conversations array and pagination meta
     */
    public function conversations(): void
    {
        $userId = $this->getUserId();
        $this->rateLimit('messages_list', 60, 60);

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = MessageService::getConversations($userId, $filters);

        $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /**
     * GET /api/v2/messages/unread-count
     *
     * Get total unread message count for badge display.
     *
     * Response: 200 OK with count
     */
    public function unreadCount(): void
    {
        $userId = $this->getUserId();
        $this->rateLimit('messages_unread', 120, 60);

        $count = MessageService::getUnreadCount($userId);

        $this->respondWithData(['count' => $count]);
    }

    /**
     * GET /api/v2/messages/{id}
     *
     * Get messages within a specific conversation.
     * The {id} is the other user's ID (conversation identifier).
     * Automatically marks messages as read when retrieved.
     *
     * Query Parameters:
     * - cursor: string (pagination cursor)
     * - per_page: int (default 50, max 100)
     * - direction: 'older' (default) or 'newer'
     *
     * Response: 200 OK with messages array and pagination meta
     */
    public function show(int $id): void
    {
        $userId = $this->getUserId();
        $this->rateLimit('messages_thread', 120, 60);

        $otherUserId = $id;

        // Get conversation details first to verify it exists
        $conversation = MessageService::getConversation($otherUserId, $userId);

        if (!$conversation) {
            $errors = MessageService::getErrors();
            if (!empty($errors)) {
                $this->respondWithErrors($errors, 404);
            }
            $this->respondWithError('NOT_FOUND', 'Conversation not found', null, 404);
        }

        // Get messages
        $filters = [
            'limit' => $this->queryInt('per_page', 50, 1, 100),
            'direction' => $this->query('direction', 'older'),
        ];

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = MessageService::getMessages($otherUserId, $userId, $filters);

        // Mark as read when viewing (unless explicitly fetching newer messages for polling)
        if ($filters['direction'] !== 'newer' || !$this->query('cursor')) {
            MessageService::markAsRead($otherUserId, $userId);
        }

        $this->respondWithData($result['items'], [
            'conversation' => $conversation,
            'cursor' => $result['cursor'],
            'per_page' => $filters['limit'],
            'has_more' => $result['has_more'],
        ]);
    }

    /**
     * POST /api/v2/messages
     *
     * Send a message to a user.
     * Creates a new conversation if one doesn't exist.
     *
     * Request Body (JSON):
     * {
     *   "recipient_id": "int (required) - user ID to send to",
     *   "body": "string (required unless voice_url provided)",
     *   "voice_url": "string (optional) - URL of uploaded voice message",
     *   "voice_duration": "int (optional) - duration in seconds"
     * }
     *
     * Response: 201 Created with sent message data
     */
    public function send(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('messages_send', 30, 60);

        $data = $this->getAllInput();

        $message = MessageService::send($userId, $data);

        if (!$message) {
            $errors = MessageService::getErrors();
            $this->respondWithErrors($errors, 422);
        }

        $this->respondWithData($message, null, 201);
    }

    /**
     * PUT /api/v2/messages/{id}/read
     *
     * Mark all messages in a conversation as read.
     *
     * Response: 200 OK with count of messages marked as read
     */
    public function markRead(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('messages_read', 60, 60);

        $otherUserId = $id;

        $count = MessageService::markAsRead($otherUserId, $userId);

        $this->respondWithData(['marked_read' => $count]);
    }

    /**
     * DELETE /api/v2/messages/{id}
     *
     * Archive (delete) a conversation.
     * Currently performs hard delete - removes all messages between the users.
     *
     * Response: 204 No Content on success
     */
    public function archive(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('messages_delete', 10, 60);

        $otherUserId = $id;

        // Verify conversation exists (user is participant)
        $conversation = MessageService::getConversation($otherUserId, $userId);
        if (!$conversation) {
            $this->respondWithError('NOT_FOUND', 'Conversation not found', null, 404);
        }

        MessageService::archiveConversation($otherUserId, $userId);

        $this->noContent();
    }

    /**
     * POST /api/v2/messages/typing
     *
     * Send a typing indicator to another user.
     * Broadcasts via Pusher for real-time updates.
     *
     * Request Body (JSON):
     * {
     *   "recipient_id": "int (required)",
     *   "is_typing": "bool (default true)"
     * }
     *
     * Response: 200 OK
     */
    public function typing(): void
    {
        $userId = $this->getUserId();
        $this->rateLimit('messages_typing', 60, 60);

        $recipientId = $this->inputInt('recipient_id', 0, 1);
        $isTyping = $this->inputBool('is_typing', true);

        if (!$recipientId) {
            $this->respondWithError('VALIDATION_ERROR', 'Recipient ID is required', 'recipient_id', 400);
        }

        MessageService::setTypingIndicator($recipientId, $userId, $isTyping);

        $this->respondWithData(['sent' => true]);
    }

    /**
     * POST /api/v2/messages/upload-voice
     *
     * Upload a voice message audio file.
     * Returns a URL that can be used when sending the message.
     *
     * Request: multipart/form-data with 'audio' file or 'audio_data' base64
     * - audio: file upload
     * - audio_data: base64 encoded audio
     * - mime_type: string (default 'audio/webm')
     * - duration: int (seconds)
     *
     * Response: 200 OK with audio URL and duration
     */
    public function uploadVoice(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('messages_voice_upload', 10, 60);

        $duration = $this->inputInt('duration', 0, 0);

        try {
            $audioResult = null;

            // Handle file upload
            if (!empty($_FILES['audio']) && $_FILES['audio']['error'] === UPLOAD_ERR_OK) {
                $audioResult = AudioUploader::upload($_FILES['audio'], $duration);
            }
            // Handle base64 encoded audio
            elseif (!empty($_POST['audio_data'])) {
                $mimeType = $_POST['mime_type'] ?? 'audio/webm';
                $audioResult = AudioUploader::uploadFromBase64($_POST['audio_data'], $mimeType, $duration);
            }
            else {
                $this->respondWithError('VALIDATION_ERROR', 'No audio data provided', 'audio', 400);
            }

            $this->respondWithData([
                'voice_url' => $audioResult['url'],
                'duration' => $audioResult['duration'],
            ]);
        } catch (\Exception $e) {
            $this->respondWithError('UPLOAD_FAILED', 'Failed to upload audio: ' . $e->getMessage(), 'audio', 400);
        }
    }
}
