<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * GoalProgressService — Laravel DI wrapper for legacy \Nexus\Services\GoalProgressService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class GoalProgressService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy GoalProgressService::logEvent().
     */
    public function logEvent(int $goalId, int $tenantId, string $eventType, ?string $oldValue = null, ?string $newValue = null, ?int $createdBy = null, ?array $metadata = null): void
    {
        \Nexus\Services\GoalProgressService::logEvent($goalId, $tenantId, $eventType, $oldValue, $newValue, $createdBy, $metadata);
    }

    /**
     * Delegates to legacy GoalProgressService::getProgressHistory().
     */
    public function getProgressHistory(int $goalId, array $filters = []): array
    {
        return \Nexus\Services\GoalProgressService::getProgressHistory($goalId, $filters);
    }

    /**
     * Delegates to legacy GoalProgressService::getSummary().
     */
    public function getSummary(int $goalId): array
    {
        return \Nexus\Services\GoalProgressService::getSummary($goalId);
    }
}
