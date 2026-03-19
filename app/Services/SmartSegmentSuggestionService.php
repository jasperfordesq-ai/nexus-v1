<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * SmartSegmentSuggestionService — Laravel DI wrapper for legacy \Nexus\Services\SmartSegmentSuggestionService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class SmartSegmentSuggestionService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy SmartSegmentSuggestionService::getSuggestions().
     */
    public function getSuggestions(): array
    {
        return \Nexus\Services\SmartSegmentSuggestionService::getSuggestions();
    }

    /**
     * Delegates to legacy SmartSegmentSuggestionService::clearCache().
     */
    public function clearCache(): void
    {
        \Nexus\Services\SmartSegmentSuggestionService::clearCache();
    }

    /**
     * Delegates to legacy SmartSegmentSuggestionService::getSuggestionById().
     */
    public function getSuggestionById(string $id): ?array
    {
        return \Nexus\Services\SmartSegmentSuggestionService::getSuggestionById($id);
    }
}
