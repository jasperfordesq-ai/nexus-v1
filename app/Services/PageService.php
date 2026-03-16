<?php
// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\Page;

/**
 * PageService — Laravel DI-based service for CMS page operations.
 *
 * All queries are tenant-scoped automatically via the HasTenantScope trait.
 */
class PageService
{
    public function __construct(
        private readonly Page $page,
    ) {}

    /**
     * Get a published page by its slug.
     */
    public function getBySlug(string $slug): ?Page
    {
        return $this->page->newQuery()
            ->published()
            ->where('slug', $slug)
            ->first();
    }
}
