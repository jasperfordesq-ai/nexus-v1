<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * NexusScoreService — Laravel DI wrapper for legacy \Nexus\Services\NexusScoreService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class NexusScoreService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy NexusScoreService::calculate().
     */
    public function calculate(int $tenantId, int $userId): float
    {
        return \Nexus\Services\NexusScoreService::calculate($tenantId, $userId);
    }

    /**
     * Delegates to legacy NexusScoreService::getScore().
     */
    public function getScore(int $tenantId, int $userId): ?float
    {
        return \Nexus\Services\NexusScoreService::getScore($tenantId, $userId);
    }

    /**
     * Delegates to legacy NexusScoreService::getBreakdown().
     */
    public function getBreakdown(int $tenantId, int $userId): array
    {
        return \Nexus\Services\NexusScoreService::getBreakdown($tenantId, $userId);
    }

    /**
     * Delegates to legacy NexusScoreService::recalculateAll().
     */
    public function recalculateAll(int $tenantId): int
    {
        return \Nexus\Services\NexusScoreService::recalculateAll($tenantId);
    }
}
