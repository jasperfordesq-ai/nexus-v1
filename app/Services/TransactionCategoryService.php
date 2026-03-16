<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * TransactionCategoryService — Laravel DI wrapper for legacy \Nexus\Services\TransactionCategoryService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class TransactionCategoryService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy TransactionCategoryService::getAll().
     */
    public function getAll(int $tenantId): array
    {
        return \Nexus\Services\TransactionCategoryService::getAll($tenantId);
    }

    /**
     * Delegates to legacy TransactionCategoryService::create().
     */
    public function create(int $tenantId, string $name, ?string $description = null): ?int
    {
        return \Nexus\Services\TransactionCategoryService::create($tenantId, $name, $description);
    }

    /**
     * Delegates to legacy TransactionCategoryService::update().
     */
    public function update(int $tenantId, int $categoryId, array $data): bool
    {
        return \Nexus\Services\TransactionCategoryService::update($tenantId, $categoryId, $data);
    }

    /**
     * Delegates to legacy TransactionCategoryService::delete().
     */
    public function delete(int $tenantId, int $categoryId): bool
    {
        return \Nexus\Services\TransactionCategoryService::delete($tenantId, $categoryId);
    }
}
