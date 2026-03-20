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
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy TransactionCategoryService::create().
     */
    public function create(int $tenantId, string $name, ?string $description = null): ?int
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy TransactionCategoryService::update().
     */
    public function update(int $tenantId, int $categoryId, array $data): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy TransactionCategoryService::delete().
     */
    public function delete(int $tenantId, int $categoryId): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }
}
