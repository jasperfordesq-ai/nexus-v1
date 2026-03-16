<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * OnboardingService — Laravel DI wrapper for legacy \Nexus\Services\OnboardingService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class OnboardingService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy OnboardingService::getProgress().
     */
    public function getProgress(int $tenantId, int $userId): array
    {
        return \Nexus\Services\OnboardingService::getProgress($tenantId, $userId);
    }

    /**
     * Delegates to legacy OnboardingService::completeStep().
     */
    public function completeStep(int $tenantId, int $userId, string $step): bool
    {
        return \Nexus\Services\OnboardingService::completeStep($tenantId, $userId, $step);
    }

    /**
     * Delegates to legacy OnboardingService::getChecklist().
     */
    public function getChecklist(int $tenantId): array
    {
        return \Nexus\Services\OnboardingService::getChecklist($tenantId);
    }

    /**
     * Delegates to legacy OnboardingService::resetProgress().
     */
    public function resetProgress(int $tenantId, int $userId): bool
    {
        return \Nexus\Services\OnboardingService::resetProgress($tenantId, $userId);
    }
}
