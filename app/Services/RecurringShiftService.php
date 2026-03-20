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
class RecurringShiftService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy RecurringShiftService::getErrors().
     */
    public function getErrors(): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy RecurringShiftService::createPattern().
     */
    public function createPattern(int $opportunityId, int $createdBy, array $data): ?int
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy RecurringShiftService::generateOccurrences().
     */
    public function generateOccurrences(int $patternId, int $daysAhead = 14): int
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return 0;
    }

    /**
     * Delegates to legacy RecurringShiftService::processAllPatterns().
     */
    public function processAllPatterns(int $daysAhead = 14): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy RecurringShiftService::getPatternsForOpportunity().
     */
    public function getPatternsForOpportunity(int $opportunityId, ?int $userId = null): array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return [];
    }

    /**
     * Delegates to legacy RecurringShiftService::getPattern().
     */
    public function getPattern(int $patternId): ?array
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return null;
    }

    /**
     * Delegates to legacy RecurringShiftService::updatePattern().
     */
    public function updatePattern(int $patternId, array $data, int $userId): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy RecurringShiftService::deactivatePattern().
     */
    public function deactivatePattern(int $patternId, int $userId): bool
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return false;
    }

    /**
     * Delegates to legacy RecurringShiftService::deleteFutureShifts().
     */
    public function deleteFutureShifts(int $patternId, int $userId): int
    {
        \Illuminate\Support\Facades\Log::warning('Legacy delegation removed: ' . __METHOD__);
        return 0;
    }
}
