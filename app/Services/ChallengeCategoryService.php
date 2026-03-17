<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * ChallengeCategoryService — Laravel DI wrapper for legacy \Nexus\Services\ChallengeCategoryService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class ChallengeCategoryService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy ChallengeCategoryService::getErrors().
     */
    public function getErrors(): array
    {
        return \Nexus\Services\ChallengeCategoryService::getErrors();
    }

    /**
     * Delegates to legacy ChallengeCategoryService::getAll().
     */
    public function getAll(): array
    {
        return \Nexus\Services\ChallengeCategoryService::getAll();
    }

    /**
     * Delegates to legacy ChallengeCategoryService::getById().
     */
    public function getById(int $id): ?array
    {
        return \Nexus\Services\ChallengeCategoryService::getById($id);
    }

    /**
     * Delegates to legacy ChallengeCategoryService::create().
     */
    public function create(int $userId, array $data): ?int
    {
        return \Nexus\Services\ChallengeCategoryService::create($userId, $data);
    }

    /**
     * Delegates to legacy ChallengeCategoryService::update().
     */
    public function update(int $id, int $userId, array $data): bool
    {
        return \Nexus\Services\ChallengeCategoryService::update($id, $userId, $data);
    }

    /**
     * Delegates to legacy ChallengeCategoryService::delete().
     */
    public function delete(int $id, int $userId): bool
    {
        return \Nexus\Services\ChallengeCategoryService::delete($id, $userId);
    }
}
