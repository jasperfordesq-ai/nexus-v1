<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * IdeaMediaService — Laravel DI wrapper for legacy \Nexus\Services\IdeaMediaService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class IdeaMediaService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy IdeaMediaService::getErrors().
     */
    public function getErrors(): array
    {
        return \Nexus\Services\IdeaMediaService::getErrors();
    }

    /**
     * Delegates to legacy IdeaMediaService::getMediaForIdea().
     */
    public function getMediaForIdea(int $ideaId): array
    {
        return \Nexus\Services\IdeaMediaService::getMediaForIdea($ideaId);
    }

    /**
     * Delegates to legacy IdeaMediaService::addMedia().
     */
    public function addMedia(int $ideaId, int $userId, array $data): ?int
    {
        return \Nexus\Services\IdeaMediaService::addMedia($ideaId, $userId, $data);
    }

    /**
     * Delegates to legacy IdeaMediaService::deleteMedia().
     */
    public function deleteMedia(int $mediaId, int $userId): bool
    {
        return \Nexus\Services\IdeaMediaService::deleteMedia($mediaId, $userId);
    }
}
