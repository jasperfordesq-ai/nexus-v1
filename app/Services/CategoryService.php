<?php
// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

/**
 * CategoryService — Laravel DI-based service for category operations.
 *
 * All queries are tenant-scoped automatically via the HasTenantScope trait.
 */
class CategoryService
{
    public function __construct(
        private readonly Category $category,
    ) {}

    /**
     * Get categories filtered by type (listing, event, blog, resource, etc.).
     */
    public function getByType(string $type): Collection
    {
        return $this->category->newQuery()
            ->ofType($type)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get all active categories regardless of type.
     */
    public function getAll(): Collection
    {
        return $this->category->newQuery()
            ->active()
            ->orderBy('type')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
