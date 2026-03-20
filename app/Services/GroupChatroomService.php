<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
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
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy GroupChatroomService::getChatrooms().
     */
    public function getChatrooms(int $groupId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy GroupChatroomService::getById().
     */
    public function getById(int $chatroomId): ?array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy GroupChatroomService::create().
     */
    public function create(int $groupId, int $userId, array $data): ?int
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy GroupChatroomService::ensureDefaultChatroom().
     */
    public function ensureDefaultChatroom(int $groupId, int $userId): ?int
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy GroupChatroomService::delete().
     */
    public function delete(int $chatroomId, int $userId): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy GroupChatroomService::getMessages().
     */
    public function getMessages(int $chatroomId, array $filters = []): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy GroupChatroomService::postMessage().
     */
    public function postMessage(int $chatroomId, int $userId, string $body): ?int
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy GroupChatroomService::deleteMessage().
     */
    public function deleteMessage(int $messageId, int $userId): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }
}
