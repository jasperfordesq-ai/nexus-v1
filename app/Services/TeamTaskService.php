<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * TeamTaskService — Laravel DI wrapper for legacy \Nexus\Services\TeamTaskService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class TeamTaskService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy TeamTaskService::getErrors().
     */
    public function getErrors(): array
    {
        return \Nexus\Services\TeamTaskService::getErrors();
    }

    /**
     * Delegates to legacy TeamTaskService::getTasks().
     */
    public function getTasks(int $groupId, array $filters = []): array
    {
        return \Nexus\Services\TeamTaskService::getTasks($groupId, $filters);
    }

    /**
     * Delegates to legacy TeamTaskService::getById().
     */
    public function getById(int $taskId): ?array
    {
        return \Nexus\Services\TeamTaskService::getById($taskId);
    }

    /**
     * Delegates to legacy TeamTaskService::create().
     */
    public function create(int $groupId, int $userId, array $data): ?int
    {
        return \Nexus\Services\TeamTaskService::create($groupId, $userId, $data);
    }

    /**
     * Delegates to legacy TeamTaskService::update().
     */
    public function update(int $taskId, int $userId, array $data): bool
    {
        return \Nexus\Services\TeamTaskService::update($taskId, $userId, $data);
    }

    /**
     * Delegates to legacy TeamTaskService::delete().
     */
    public function delete(int $taskId, int $userId): bool
    {
        return \Nexus\Services\TeamTaskService::delete($taskId, $userId);
    }

    /**
     * Delegates to legacy TeamTaskService::getStats().
     */
    public function getStats(int $groupId): array
    {
        return \Nexus\Services\TeamTaskService::getStats($groupId);
    }
}
