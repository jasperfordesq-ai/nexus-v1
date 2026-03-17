<?php
// Copyright � 2024�2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * OnboardingService � Laravel DI wrapper for legacy \Nexus\Services\OnboardingService.
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

    /**
     * Check if onboarding is complete — delegates to legacy OnboardingService.
     */
    public function isOnboardingComplete(int $userId): bool
    {
        return \Nexus\Services\OnboardingService::isOnboardingComplete($userId);
    }

    /**
     * Get user's selected interests — delegates to legacy OnboardingService.
     */
    public function getUserInterests(int $userId): array
    {
        return \Nexus\Services\OnboardingService::getUserInterests($userId);
    }

    /**
     * Save user's category interests — delegates to legacy OnboardingService.
     */
    public function saveInterests(int $userId, array $categoryIds): void
    {
        \Nexus\Services\OnboardingService::saveInterests($userId, $categoryIds);
    }

    /**
     * Save user's skill offers and needs — delegates to legacy OnboardingService.
     */
    public function saveSkills(int $userId, array $offers, array $needs): void
    {
        \Nexus\Services\OnboardingService::saveSkills($userId, $offers, $needs);
    }

    /**
     * Auto-create listings from selected skills — delegates to legacy OnboardingService.
     *
     * @return array List of created listing IDs
     */
    public function autoCreateListings(int $userId, array $offers, array $needs): array
    {
        return \Nexus\Services\OnboardingService::autoCreateListings($userId, $offers, $needs);
    }

    /**
     * Mark onboarding as complete — delegates to legacy OnboardingService.
     */
    public function completeOnboarding(int $userId): void
    {
        \Nexus\Services\OnboardingService::completeOnboarding($userId);
    }

    /**
     * Skip an onboarding step — convenience method that marks a step complete.
     */
    public function skipStep(int $tenantId, int $userId, string $step): bool
    {
        return $this->completeStep($tenantId, $userId, $step);
    }

    /**
     * Get onboarding recommendations (categories, skills) for the user.
     * Returns popular categories and skills for the tenant.
     */
    public function getRecommendations(int $tenantId): array
    {
        $categories = \Illuminate\Support\Facades\DB::table('categories')
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->select(['id', 'name', 'slug', 'color'])
            ->get()
            ->map(fn ($c) => (array) $c)
            ->all();

        $skills = \Illuminate\Support\Facades\DB::table('skills')
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->select(['id', 'name'])
            ->get()
            ->map(fn ($s) => (array) $s)
            ->all();

        return [
            'categories' => $categories,
            'skills' => $skills,
        ];
    }

    /**
     * Alias for completeOnboarding — marks the full onboarding as complete.
     */
    public function markComplete(int $userId): void
    {
        $this->completeOnboarding($userId);
    }
}
