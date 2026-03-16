<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * BrokerMessageVisibilityService — Laravel DI wrapper for legacy \Nexus\Services\BrokerMessageVisibilityService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class BrokerMessageVisibilityService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy BrokerMessageVisibilityService::shouldCopyMessage().
     */
    public function shouldCopyMessage(int $senderId, int $receiverId, ?int $listingId = null): ?string
    {
        return \Nexus\Services\BrokerMessageVisibilityService::shouldCopyMessage($senderId, $receiverId, $listingId);
    }

    /**
     * Delegates to legacy BrokerMessageVisibilityService::copyMessageForBroker().
     */
    public function copyMessageForBroker(int $messageId, string $reason): ?int
    {
        return \Nexus\Services\BrokerMessageVisibilityService::copyMessageForBroker($messageId, $reason);
    }

    /**
     * Delegates to legacy BrokerMessageVisibilityService::getUnreviewedMessages().
     */
    public function getUnreviewedMessages(int $limit = 50, int $offset = 0): array
    {
        return \Nexus\Services\BrokerMessageVisibilityService::getUnreviewedMessages($limit, $offset);
    }

    /**
     * Delegates to legacy BrokerMessageVisibilityService::getMessages().
     */
    public function getMessages(string $filter = 'unreviewed', int $page = 1, int $perPage = 50): array
    {
        return \Nexus\Services\BrokerMessageVisibilityService::getMessages($filter, $page, $perPage);
    }

    /**
     * Delegates to legacy BrokerMessageVisibilityService::markAsReviewed().
     */
    public function markAsReviewed(int $copyId, int $brokerId): bool
    {
        return \Nexus\Services\BrokerMessageVisibilityService::markAsReviewed($copyId, $brokerId);
    }
}
