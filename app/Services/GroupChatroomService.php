<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * GroupChatroomService — Laravel DI wrapper for legacy \Nexus\Services\GroupChatroomService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class GroupChatroomService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy GroupChatroomService::getErrors().
     */
    public function getErrors(): array
    {
        return \Nexus\Services\GroupChatroomService::getErrors();
    }

    /**
     * Delegates to legacy GroupChatroomService::getChatrooms().
     */
    public function getChatrooms(int $groupId): array
    {
        return \Nexus\Services\GroupChatroomService::getChatrooms($groupId);
    }

    /**
     * Delegates to legacy GroupChatroomService::getById().
     */
    public function getById(int $chatroomId): ?array
    {
        return \Nexus\Services\GroupChatroomService::getById($chatroomId);
    }

    /**
     * Delegates to legacy GroupChatroomService::create().
     */
    public function create(int $groupId, int $userId, array $data): ?int
    {
        return \Nexus\Services\GroupChatroomService::create($groupId, $userId, $data);
    }

    /**
     * Delegates to legacy GroupChatroomService::ensureDefaultChatroom().
     */
    public function ensureDefaultChatroom(int $groupId, int $userId): ?int
    {
        return \Nexus\Services\GroupChatroomService::ensureDefaultChatroom($groupId, $userId);
    }

    /**
     * Delegates to legacy GroupChatroomService::delete().
     */
    public function delete(int $chatroomId, int $userId): bool
    {
        return \Nexus\Services\GroupChatroomService::delete($chatroomId, $userId);
    }

    /**
     * Delegates to legacy GroupChatroomService::getMessages().
     */
    public function getMessages(int $chatroomId, array $filters = []): array
    {
        return \Nexus\Services\GroupChatroomService::getMessages($chatroomId, $filters);
    }

    /**
     * Delegates to legacy GroupChatroomService::postMessage().
     */
    public function postMessage(int $chatroomId, int $userId, string $body): ?int
    {
        return \Nexus\Services\GroupChatroomService::postMessage($chatroomId, $userId, $body);
    }

    /**
     * Delegates to legacy GroupChatroomService::deleteMessage().
     */
    public function deleteMessage(int $messageId, int $userId): bool
    {
        return \Nexus\Services\GroupChatroomService::deleteMessage($messageId, $userId);
    }
}
