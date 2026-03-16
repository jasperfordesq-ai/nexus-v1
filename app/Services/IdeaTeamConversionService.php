<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * IdeaTeamConversionService — Laravel DI wrapper for legacy \Nexus\Services\IdeaTeamConversionService.
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
        return \Nexus\Services\IdeaTeamConversionService::getErrors();
    }

    /**
     * Delegates to legacy IdeaTeamConversionService::convert().
     */
    public function convert(int $ideaId, int $userId, array $options = []): ?array
    {
        return \Nexus\Services\IdeaTeamConversionService::convert($ideaId, $userId, $options);
    }

    /**
     * Delegates to legacy IdeaTeamConversionService::getLinksForChallenge().
     */
    public function getLinksForChallenge(int $challengeId): array
    {
        return \Nexus\Services\IdeaTeamConversionService::getLinksForChallenge($challengeId);
    }

    /**
     * Delegates to legacy IdeaTeamConversionService::getLinkForIdea().
     */
    public function getLinkForIdea(int $ideaId): ?array
    {
        return \Nexus\Services\IdeaTeamConversionService::getLinkForIdea($ideaId);
    }
}
