<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\GroupConversationService;
use Illuminate\Http\JsonResponse;

/**
 * GroupConversationController — group DM (multi-participant conversation) endpoints.
 *
 * Endpoints:
 *   POST   /api/v2/conversations/groups                     — create group
 *   GET    /api/v2/conversations/groups                     — list user's groups
 *   GET    /api/v2/conversations/{id}/participants          — list participants
 *   POST   /api/v2/conversations/{id}/participants          — add member
 *   DELETE /api/v2/conversations/{id}/participants/{userId} — remove/leave
 *   PATCH  /api/v2/conversations/{id}/group                 — update group name/avatar
 *   GET    /api/v2/conversations/{id}/messages              — get group messages
 *   POST   /api/v2/conversations/{id}/messages              — send group message
 */
class GroupConversationController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * POST /api/v2/conversations/groups
     *
     * Create a new group conversation.
     * Body: { "name": string, "member_ids": int[] }
     */
    public function store(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('create_group_conversation', 10, 60);

        $name = trim($this->input('name', ''));
        $memberIds = $this->input('member_ids', []);

        if (!is_array($memberIds)) {
            return $this->respondWithError('VALIDATION_ERROR', __('errors.validation.member_ids_must_be_array'), 'member_ids', 422);
        }

        $result = GroupConversationService::createGroup($userId, $memberIds, $name);

        if ($result === null) {
            $errors = GroupConversationService::getErrors();
            return $this->respondWithErrors($errors, 422);
        }

        return $this->respondWithData($result, null, 201);
    }

    /**
     * GET /api/v2/conversations/groups
     *
     * List group conversations the current user is part of.
     */
    public function index(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('list_group_conversations', 30, 60);

        $groups = GroupConversationService::getUserGroups($userId);

        return $this->respondWithData($groups);
    }

    /**
     * GET /api/v2/conversations/{id}/participants
     *
     * List participants of a group conversation.
     */
    public function participants(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $participants = GroupConversationService::getParticipants($id, $userId);

        if ($participants === null) {
            $errors = GroupConversationService::getErrors();
            return $this->respondWithErrors($errors, 403);
        }

        return $this->respondWithData($participants->all());
    }

    /**
     * POST /api/v2/conversations/{id}/participants
     *
     * Add a member to a group conversation.
     * Body: { "user_id": int }
     */
    public function addParticipant(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('add_participant', 20, 60);

        $targetUserId = $this->inputInt('user_id');

        if (!$targetUserId) {
            return $this->respondWithError('VALIDATION_ERROR', 'user_id is required', 'user_id', 422);
        }

        $result = GroupConversationService::addMember($id, $targetUserId, $userId);

        if ($result === null) {
            $errors = GroupConversationService::getErrors();
            $status = 422;
            if (!empty($errors[0]['code']) && $errors[0]['code'] === 'FORBIDDEN') {
                $status = 403;
            }
            return $this->respondWithErrors($errors, $status);
        }

        return $this->respondWithData($result);
    }

    /**
     * DELETE /api/v2/conversations/{id}/participants/{userId}
     *
     * Remove a member or leave the group.
     */
    public function removeParticipant(int $id, int $targetUserId): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('remove_participant', 20, 60);

        $success = GroupConversationService::removeMember($id, $targetUserId, $userId);

        if (!$success) {
            $errors = GroupConversationService::getErrors();
            $status = 422;
            if (!empty($errors[0]['code']) && $errors[0]['code'] === 'FORBIDDEN') {
                $status = 403;
            }
            return $this->respondWithErrors($errors, $status);
        }

        return $this->respondWithData(['success' => true]);
    }

    /**
     * PATCH /api/v2/conversations/{id}/group
     *
     * Update group name or avatar. Admin only.
     * Body: { "name"?: string, "avatar_url"?: string }
     */
    public function updateGroup(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('update_group', 10, 60);

        $data = $this->getAllInput();

        $result = GroupConversationService::updateGroup($id, $userId, $data);

        if ($result === null) {
            $errors = GroupConversationService::getErrors();
            return $this->respondWithErrors($errors, 403);
        }

        return $this->respondWithData($result);
    }

    /**
     * GET /api/v2/conversations/{id}/messages
     *
     * Get messages in a group conversation.
     */
    public function messages(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $filters = [
            'limit' => $this->queryInt('per_page', 50, 1, 100),
            'direction' => $this->query('direction', 'older'),
        ];
        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = GroupConversationService::getGroupMessages($id, $userId, $filters);

        if ($result === null) {
            $errors = GroupConversationService::getErrors();
            return $this->respondWithErrors($errors, 403);
        }

        return $this->respondWithData($result['items'], [
            'conversation' => $result['conversation'],
            'cursor' => $result['cursor'],
            'per_page' => $filters['limit'],
            'has_more' => $result['has_more'],
        ]);
    }

    /**
     * POST /api/v2/conversations/{id}/messages
     *
     * Send a message to a group conversation.
     * Body: { "body": string }
     */
    public function sendMessage(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('send_group_message', 30, 60);

        $body = trim($this->input('body', ''));

        if (empty($body)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Message body is required', 'body', 422);
        }

        if (mb_strlen($body) > 10000) {
            return $this->respondWithError('VALIDATION_ERROR', 'Message is too long', 'body', 400);
        }

        $result = GroupConversationService::sendGroupMessage($id, $userId, $body);

        if ($result === null) {
            $errors = GroupConversationService::getErrors();
            return $this->respondWithErrors($errors, 403);
        }

        return $this->respondWithData($result, null, 201);
    }
}
