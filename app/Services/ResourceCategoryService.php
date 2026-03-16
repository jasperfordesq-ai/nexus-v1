<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * ResourceCategoryService — Laravel DI wrapper for legacy \Nexus\Services\ResourceCategoryService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class ResourceCategoryService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy ResourceCategoryService::getErrors().
     */
    public function getErrors(): array
    {
        return \Nexus\Services\ResourceCategoryService::getErrors();
    }

    /**
     * Delegates to legacy ResourceCategoryService::getAll().
     */
    public function getAll(bool $flat = false): array
    {
        return \Nexus\Services\ResourceCategoryService::getAll($flat);
    }

    /**
     * Delegates to legacy ResourceCategoryService::getById().
     */
    public function getById(int $id): ?array
    {
        return \Nexus\Services\ResourceCategoryService::getById($id);
    }

    /**
     * Delegates to legacy ResourceCategoryService::create().
     */
    public function create(array $data): ?int
    {
        return \Nexus\Services\ResourceCategoryService::create($data);
    }

    /**
     * Delegates to legacy ResourceCategoryService::update().
     */
    public function update(int $id, array $data): bool
    {
        return \Nexus\Services\ResourceCategoryService::update($id, $data);
    }
}
