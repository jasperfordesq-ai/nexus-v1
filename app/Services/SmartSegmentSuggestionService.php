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
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy SmartSegmentSuggestionService::clearCache().
     */
    public function clearCache(): void
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
    }

    /**
     * Delegates to legacy SmartSegmentSuggestionService::getSuggestionById().
     */
    public function getSuggestionById(string $id): ?array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }
}
