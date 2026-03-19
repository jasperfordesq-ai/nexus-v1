<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * ChallengeOutcomeService — Laravel DI wrapper for legacy \Nexus\Services\ChallengeOutcomeService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class ChallengeOutcomeService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy ChallengeOutcomeService::getErrors().
     */
    public function getErrors(): array
    {
        if (!class_exists('\Nexus\Services\ChallengeOutcomeService')) { return []; }
        return \Nexus\Services\ChallengeOutcomeService::getErrors();
    }

    /**
     * Delegates to legacy ChallengeOutcomeService::getForChallenge().
     */
    public function getForChallenge(int $challengeId): ?array
    {
        if (!class_exists('\Nexus\Services\ChallengeOutcomeService')) { return null; }
        return \Nexus\Services\ChallengeOutcomeService::getForChallenge($challengeId);
    }

    /**
     * Delegates to legacy ChallengeOutcomeService::upsert().
     */
    public function upsert(int $challengeId, int $userId, array $data): ?int
    {
        if (!class_exists('\Nexus\Services\ChallengeOutcomeService')) { return null; }
        return \Nexus\Services\ChallengeOutcomeService::upsert($challengeId, $userId, $data);
    }

    /**
     * Delegates to legacy ChallengeOutcomeService::getDashboard().
     */
    public function getDashboard(): array
    {
        if (!class_exists('\Nexus\Services\ChallengeOutcomeService')) { return []; }
        return \Nexus\Services\ChallengeOutcomeService::getDashboard();
    }
}
