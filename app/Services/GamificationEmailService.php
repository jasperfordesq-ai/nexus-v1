<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * GamificationEmailService — Laravel DI wrapper for legacy \Nexus\Services\GamificationEmailService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class GamificationEmailService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy GamificationEmailService::sendWeeklyDigests().
     */
    public function sendWeeklyDigests(): array
    {
        return \Nexus\Services\GamificationEmailService::sendWeeklyDigests();
    }

    /**
     * Delegates to legacy GamificationEmailService::generateUserDigest().
     */
    public function generateUserDigest(int $userId): array
    {
        return \Nexus\Services\GamificationEmailService::generateUserDigest($userId);
    }

    /**
     * Delegates to legacy GamificationEmailService::sendMilestoneEmail().
     */
    public function sendMilestoneEmail(int $userId, string $type, array $data): bool
    {
        return \Nexus\Services\GamificationEmailService::sendMilestoneEmail($userId, $type, $data);
    }
}
