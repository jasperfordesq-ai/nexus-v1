<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * UnifiedSearchService — Laravel DI wrapper for legacy \Nexus\Services\UnifiedSearchService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class UnifiedSearchService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy UnifiedSearchService::getErrors().
     */
    public function getErrors(): array
    {
        return \Nexus\Services\UnifiedSearchService::getErrors();
    }

    /**
     * Delegates to legacy UnifiedSearchService::search().
     */
    public function search(string $query, ?int $userId, array $filters = []): array
    {
        return \Nexus\Services\UnifiedSearchService::search($query, $userId, $filters);
    }

    /**
     * Delegates to legacy UnifiedSearchService::getSuggestions().
     */
    public function getSuggestions(string $query, int $tenantId, int $limit = 5): array
    {
        return \Nexus\Services\UnifiedSearchService::getSuggestions($query, $tenantId, $limit);
    }
}
