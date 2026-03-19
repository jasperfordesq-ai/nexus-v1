<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * SocialNotificationService — Laravel DI wrapper for legacy \Nexus\Services\SocialNotificationService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class SocialNotificationService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy SocialNotificationService::notifyLike().
     */
    public function notifyLike($contentOwnerId, $likerId, $contentType, $contentId, $contentPreview = null)
    {
        return \Nexus\Services\SocialNotificationService::notifyLike($contentOwnerId, $likerId, $contentType, $contentId, $contentPreview);
    }

    /**
     * Delegates to legacy SocialNotificationService::notifyComment().
     */
    public function notifyComment($contentOwnerId, $commenterId, $contentType, $contentId, $commentText)
    {
        return \Nexus\Services\SocialNotificationService::notifyComment($contentOwnerId, $commenterId, $contentType, $contentId, $commentText);
    }

    /**
     * Delegates to legacy SocialNotificationService::notifyShare().
     */
    public function notifyShare($contentOwnerId, $sharerId, $contentType, $contentId)
    {
        return \Nexus\Services\SocialNotificationService::notifyShare($contentOwnerId, $sharerId, $contentType, $contentId);
    }

    /**
     * Delegates to legacy SocialNotificationService::getContentOwnerId().
     */
    public function getContentOwnerId($contentType, $contentId)
    {
        return \Nexus\Services\SocialNotificationService::getContentOwnerId($contentType, $contentId);
    }

    /**
     * Delegates to legacy SocialNotificationService::getContentPreview().
     */
    public function getContentPreview($contentType, $contentId, $maxLength = 100)
    {
        return \Nexus\Services\SocialNotificationService::getContentPreview($contentType, $contentId, $maxLength);
    }
}
