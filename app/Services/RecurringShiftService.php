<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * RecurringShiftService — Laravel DI wrapper for legacy \Nexus\Services\RecurringShiftService.
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
        return \Nexus\Services\RecurringShiftService::getErrors();
    }

    /**
     * Delegates to legacy RecurringShiftService::createPattern().
     */
    public function createPattern(int $opportunityId, int $createdBy, array $data): ?int
    {
        return \Nexus\Services\RecurringShiftService::createPattern($opportunityId, $createdBy, $data);
    }

    /**
     * Delegates to legacy RecurringShiftService::generateOccurrences().
     */
    public function generateOccurrences(int $patternId, int $daysAhead = 14): int
    {
        return \Nexus\Services\RecurringShiftService::generateOccurrences($patternId, $daysAhead);
    }

    /**
     * Delegates to legacy RecurringShiftService::processAllPatterns().
     */
    public function processAllPatterns(int $daysAhead = 14): array
    {
        return \Nexus\Services\RecurringShiftService::processAllPatterns($daysAhead);
    }

    /**
     * Delegates to legacy RecurringShiftService::getPatternsForOpportunity().
     */
    public function getPatternsForOpportunity(int $opportunityId, ?int $userId = null): array
    {
        return \Nexus\Services\RecurringShiftService::getPatternsForOpportunity($opportunityId, $userId);
    }

    /**
     * Delegates to legacy RecurringShiftService::getPattern().
     */
    public function getPattern(int $patternId): ?array
    {
        return \Nexus\Services\RecurringShiftService::getPattern($patternId);
    }

    /**
     * Delegates to legacy RecurringShiftService::updatePattern().
     */
    public function updatePattern(int $patternId, array $data, int $userId): bool
    {
        return \Nexus\Services\RecurringShiftService::updatePattern($patternId, $data, $userId);
    }

    /**
     * Delegates to legacy RecurringShiftService::deactivatePattern().
     */
    public function deactivatePattern(int $patternId, int $userId): bool
    {
        return \Nexus\Services\RecurringShiftService::deactivatePattern($patternId, $userId);
    }

    /**
     * Delegates to legacy RecurringShiftService::deleteFutureShifts().
     */
    public function deleteFutureShifts(int $patternId, int $userId): int
    {
        return \Nexus\Services\RecurringShiftService::deleteFutureShifts($patternId, $userId);
    }
}
