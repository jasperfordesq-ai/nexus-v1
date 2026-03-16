<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * SavedSearchService — Laravel DI wrapper for legacy \Nexus\Services\SavedSearchService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class SavedSearchService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy SavedSearchService::getErrors().
     */
    public function getErrors(): array
    {
        return \Nexus\Services\SavedSearchService::getErrors();
    }

    /**
     * Delegates to legacy SavedSearchService::save().
     */
    public function save(int $userId, string $name, array $queryParams, bool $notifyOnNew = false): ?int
    {
        return \Nexus\Services\SavedSearchService::save($userId, $name, $queryParams, $notifyOnNew);
    }

    /**
     * Delegates to legacy SavedSearchService::getAll().
     */
    public function getAll(int $userId): array
    {
        return \Nexus\Services\SavedSearchService::getAll($userId);
    }

    /**
     * Delegates to legacy SavedSearchService::getById().
     */
    public function getById(int $id, int $userId): ?array
    {
        return \Nexus\Services\SavedSearchService::getById($id, $userId);
    }

    /**
     * Delegates to legacy SavedSearchService::delete().
     */
    public function delete(int $id, int $userId): bool
    {
        return \Nexus\Services\SavedSearchService::delete($id, $userId);
    }
}
