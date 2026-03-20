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
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy IdeaMediaService::getMediaForIdea().
     */
    public function getMediaForIdea(int $ideaId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy IdeaMediaService::addMedia().
     */
    public function addMedia(int $ideaId, int $userId, array $data): ?int
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy IdeaMediaService::deleteMedia().
     */
    public function deleteMedia(int $mediaId, int $userId): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }
}
