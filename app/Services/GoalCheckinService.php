<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * GoalCheckinService — Laravel DI wrapper for legacy \Nexus\Services\GoalCheckinService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class GoalCheckinService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy GoalCheckinService::getErrors().
     */
    public function getErrors(): array
    {
        return \Nexus\Services\GoalCheckinService::getErrors();
    }

    /**
     * Delegates to legacy GoalCheckinService::create().
     */
    public function create(int $goalId, int $userId, array $data): ?int
    {
        return \Nexus\Services\GoalCheckinService::create($goalId, $userId, $data);
    }

    /**
     * Delegates to legacy GoalCheckinService::getByGoalId().
     */
    public function getByGoalId(int $goalId, array $filters = []): array
    {
        return \Nexus\Services\GoalCheckinService::getByGoalId($goalId, $filters);
    }
}
