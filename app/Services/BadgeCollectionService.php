<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * BadgeCollectionService — Laravel DI wrapper for legacy \Nexus\Services\BadgeCollectionService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class BadgeCollectionService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy BadgeCollectionService::getCollectionsWithProgress().
     */
    public function getCollectionsWithProgress($userId)
    {
        return \Nexus\Services\BadgeCollectionService::getCollectionsWithProgress($userId);
    }

    /**
     * Delegates to legacy BadgeCollectionService::checkCollectionCompletion().
     */
    public function checkCollectionCompletion($userId)
    {
        return \Nexus\Services\BadgeCollectionService::checkCollectionCompletion($userId);
    }

    /**
     * Delegates to legacy BadgeCollectionService::create().
     */
    public function create($data)
    {
        return \Nexus\Services\BadgeCollectionService::create($data);
    }

    /**
     * Delegates to legacy BadgeCollectionService::addBadgeToCollection().
     */
    public function addBadgeToCollection($collectionId, $badgeKey, $order = 0)
    {
        return \Nexus\Services\BadgeCollectionService::addBadgeToCollection($collectionId, $badgeKey, $order);
    }

    /**
     * Delegates to legacy BadgeCollectionService::removeBadgeFromCollection().
     */
    public function removeBadgeFromCollection($collectionId, $badgeKey)
    {
        return \Nexus\Services\BadgeCollectionService::removeBadgeFromCollection($collectionId, $badgeKey);
    }
}
