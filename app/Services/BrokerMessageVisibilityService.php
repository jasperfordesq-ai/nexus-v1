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
        if (!class_exists('\Nexus\Services\BrokerMessageVisibilityService')) { return null; }
        return \Nexus\Services\BrokerMessageVisibilityService::shouldCopyMessage($senderId, $receiverId, $listingId);
    }

    /**
     * Delegates to legacy BrokerMessageVisibilityService::copyMessageForBroker().
     */
    public function copyMessageForBroker(int $messageId, string $reason): ?int
    {
        if (!class_exists('\Nexus\Services\BrokerMessageVisibilityService')) { return null; }
        return \Nexus\Services\BrokerMessageVisibilityService::copyMessageForBroker($messageId, $reason);
    }

    /**
     * Delegates to legacy BrokerMessageVisibilityService::getUnreviewedMessages().
     */
    public function getUnreviewedMessages(int $limit = 50, int $offset = 0): array
    {
        if (!class_exists('\Nexus\Services\BrokerMessageVisibilityService')) { return []; }
        return \Nexus\Services\BrokerMessageVisibilityService::getUnreviewedMessages($limit, $offset);
    }

    /**
     * Delegates to legacy BrokerMessageVisibilityService::getMessages().
     */
    public function getMessages(string $filter = 'unreviewed', int $page = 1, int $perPage = 50): array
    {
        if (!class_exists('\Nexus\Services\BrokerMessageVisibilityService')) { return []; }
        return \Nexus\Services\BrokerMessageVisibilityService::getMessages($filter, $page, $perPage);
    }

    /**
     * Delegates to legacy BrokerMessageVisibilityService::markAsReviewed().
     */
    public function markAsReviewed(int $copyId, int $brokerId): bool
    {
        if (!class_exists('\Nexus\Services\BrokerMessageVisibilityService')) { return false; }
        return \Nexus\Services\BrokerMessageVisibilityService::markAsReviewed($copyId, $brokerId);
    }

    /**
     * Delegates to legacy BrokerMessageVisibilityService::isMessagingDisabledForUser().
     */
    public function isMessagingDisabledForUser(int $userId): bool
    {
        if (!class_exists('\Nexus\Services\BrokerMessageVisibilityService')) { return false; }
        return \Nexus\Services\BrokerMessageVisibilityService::isMessagingDisabledForUser($userId);
    }

    /**
     * Get the messaging restriction status for a user.
     *
     * Delegates to legacy BrokerMessageVisibilityService::getUserRestrictionStatus().
     */
    public function getUserRestrictionStatus(int $userId): array
    {
        if (!class_exists('\Nexus\Services\BrokerMessageVisibilityService')) { return []; }
        return \Nexus\Services\BrokerMessageVisibilityService::getUserRestrictionStatus($userId);
    }

    /**
     * Count unreviewed broker message copies.
     *
     * Delegates to legacy BrokerMessageVisibilityService::countUnreviewed().
     */
    public function countUnreviewed(): int
    {
        if (!class_exists('\Nexus\Services\BrokerMessageVisibilityService')) { return 0; }
        return \Nexus\Services\BrokerMessageVisibilityService::countUnreviewed();
    }
}
