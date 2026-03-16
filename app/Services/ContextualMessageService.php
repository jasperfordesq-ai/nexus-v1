<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * ContextualMessageService — Laravel DI wrapper for legacy \Nexus\Services\ContextualMessageService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class ContextualMessageService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy ContextualMessageService::sendWithContext().
     */
    public function sendWithContext(int $senderId, int $receiverId, string $body, ?string $contextType = null, ?int $contextId = null, string $subject = ''): ?int
    {
        return \Nexus\Services\ContextualMessageService::sendWithContext($senderId, $receiverId, $body, $contextType, $contextId, $subject);
    }

    /**
     * Delegates to legacy ContextualMessageService::getContextInfo().
     */
    public function getContextInfo(string $contextType, int $contextId): ?array
    {
        return \Nexus\Services\ContextualMessageService::getContextInfo($contextType, $contextId);
    }

    /**
     * Delegates to legacy ContextualMessageService::getContextInfoBatch().
     */
    public function getContextInfoBatch(array $contextPairs): array
    {
        return \Nexus\Services\ContextualMessageService::getContextInfoBatch($contextPairs);
    }

    /**
     * Delegates to legacy ContextualMessageService::enrichMessagesWithContext().
     */
    public function enrichMessagesWithContext(array $messages): array
    {
        return \Nexus\Services\ContextualMessageService::enrichMessagesWithContext($messages);
    }
}
