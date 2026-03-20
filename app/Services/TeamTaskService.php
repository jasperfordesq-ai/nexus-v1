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
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy TeamTaskService::getTasks().
     */
    public function getTasks(int $groupId, array $filters = []): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy TeamTaskService::getById().
     */
    public function getById(int $taskId): ?array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy TeamTaskService::create().
     */
    public function create(int $groupId, int $userId, array $data): ?int
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy TeamTaskService::update().
     */
    public function update(int $taskId, int $userId, array $data): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy TeamTaskService::delete().
     */
    public function delete(int $taskId, int $userId): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy TeamTaskService::getStats().
     */
    public function getStats(int $groupId): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }
}
