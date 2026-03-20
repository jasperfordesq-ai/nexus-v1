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
class IdeaTeamConversionService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy IdeaTeamConversionService::getErrors().
     */
    public function getErrors(): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy IdeaTeamConversionService::convert().
     */
    public function convert(int $ideaId, int $userId, array $options = []): ?array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy IdeaTeamConversionService::getLinksForChallenge().
     */
    public function getLinksForChallenge(int $challengeId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy IdeaTeamConversionService::getLinkForIdea().
     */
    public function getLinkForIdea(int $ideaId): ?array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }
}
