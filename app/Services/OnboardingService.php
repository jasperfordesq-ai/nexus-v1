<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use App\Models\UserInterest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * OnboardingService — Laravel DI-based service for post-registration onboarding wizard.
 *
 * Handles onboarding completion tracking, interest/skill saving, and auto-listing creation.
 * All queries are tenant-scoped via HasTenantScope trait on models.
 */
class OnboardingService
{
    public function __construct() {}

    /**
     * Get onboarding progress for a user.
     */
    public static function getProgress(int $tenantId, int $userId): array
    {
        $user = User::find($userId);
        if (!$user) {
            return [];
        }

        $interests = UserInterest::where('user_id', $userId)->count();
        $hasAvatar = !empty($user->avatar_url);
        $hasBio = !empty($user->bio);

        $steps = [
            'profile' => $hasAvatar || $hasBio,
            'interests' => $interests > 0,
            'skills' => UserInterest::where('user_id', $userId)
                ->whereIn('interest_type', ['skill_offer', 'skill_need'])
                ->exists(),
            'complete' => (bool) $user->onboarding_completed,
        ];

        $completedCount = count(array_filter($steps));
        $totalSteps = count($steps);

        return [
            'steps' => $steps,
            'completed' => $completedCount,
            'total' => $totalSteps,
            'percentage' => $totalSteps > 0 ? (int) round(($completedCount / $totalSteps) * 100) : 0,
            'is_complete' => (bool) $user->onboarding_completed,
        ];
    }

    /**
     * Complete a specific onboarding step.
     */
    public static function completeStep(int $tenantId, int $userId, string $step): bool
    {
        // Steps are tracked implicitly via data presence (profile, interests, skills)
        // For explicit step tracking, we use the onboarding_completed flag
        if ($step === 'complete') {
            return User::where('id', $userId)
                ->update(['onboarding_completed' => true]) > 0;
        }

        return true;
    }

    /**
     * Get onboarding checklist (available steps).
     */
    public static function getChecklist(int $tenantId): array
    {
        return [
            ['key' => 'profile', 'label' => 'Complete your profile', 'description' => 'Add a photo and bio'],
            ['key' => 'interests', 'label' => 'Select your interests', 'description' => 'Choose categories you care about'],
            ['key' => 'skills', 'label' => 'Share your skills', 'description' => 'Tell us what you can offer and what you need'],
            ['key' => 'complete', 'label' => 'All done!', 'description' => 'Start exploring your community'],
        ];
    }

    /**
     * Reset onboarding progress for a user.
     */
    public static function resetProgress(int $tenantId, int $userId): bool
    {
        User::where('id', $userId)
            ->update(['onboarding_completed' => false]);

        UserInterest::where('user_id', $userId)->delete();

        return true;
    }

    /**
     * Check if onboarding is complete.
     */
    public static function isOnboardingComplete(int $userId): bool
    {
        $user = User::find($userId, ['id', 'onboarding_completed']);
        return $user && (bool) $user->onboarding_completed;
    }

    /**
     * Get user's selected interests.
     */
    public static function getUserInterests(int $userId): array
    {
        return UserInterest::where('user_id', $userId)
            ->join('categories', 'categories.id', '=', 'user_interests.category_id')
            ->select('user_interests.*', 'categories.name as category_name')
            ->orderBy('user_interests.interest_type')
            ->orderBy('categories.name')
            ->get()
            ->map(fn ($row) => $row->toArray())
            ->all();
    }

    /**
     * Save user's category interests (from onboarding Step 2).
     * Replaces all existing 'interest' type entries.
     */
    public static function saveInterests(int $userId, array $categoryIds): void
    {
        $tenantId = TenantContext::getId();

        UserInterest::where('user_id', $userId)
            ->where('interest_type', 'interest')
            ->delete();

        foreach ($categoryIds as $catId) {
            UserInterest::firstOrCreate([
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'category_id' => (int) $catId,
                'interest_type' => 'interest',
            ]);
        }
    }

    /**
     * Save user's skill offers and needs (from onboarding Step 3).
     * Replaces all existing skill_offer and skill_need entries.
     */
    public static function saveSkills(int $userId, array $offers, array $needs): void
    {
        $tenantId = TenantContext::getId();

        UserInterest::where('user_id', $userId)
            ->whereIn('interest_type', ['skill_offer', 'skill_need'])
            ->delete();

        foreach ($offers as $catId) {
            UserInterest::firstOrCreate([
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'category_id' => (int) $catId,
                'interest_type' => 'skill_offer',
            ]);
        }

        foreach ($needs as $catId) {
            UserInterest::firstOrCreate([
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'category_id' => (int) $catId,
                'interest_type' => 'skill_need',
            ]);
        }
    }

    /**
     * Auto-create listings from selected skills.
     *
     * @return array List of created listing IDs
     */
    public static function autoCreateListings(int $userId, array $offers, array $needs): array
    {
        $tenantId = TenantContext::getId();
        $createdIds = [];

        // Get category names for listing titles
        $allCatIds = array_unique(array_merge(
            array_map('intval', $offers),
            array_map('intval', $needs)
        ));

        $categories = [];
        if (!empty($allCatIds)) {
            $categories = Category::whereIn('id', $allCatIds)
                ->pluck('name', 'id')
                ->all();
        }

        // Create offer listings
        foreach ($offers as $catId) {
            $catId = (int) $catId;
            $catName = $categories[$catId] ?? 'Service';
            $title = "I can help with {$catName}";
            $description = "I'm available to help with {$catName}. Get in touch to arrange!";

            $listing = Listing::create([
                'tenant_id' => $tenantId,
                'title' => $title,
                'description' => $description,
                'type' => 'offer',
                'category_id' => $catId,
                'user_id' => $userId,
                'status' => 'active',
            ]);

            $createdIds[] = $listing->id;
        }

        // Create request listings
        foreach ($needs as $catId) {
            $catId = (int) $catId;
            $catName = $categories[$catId] ?? 'Service';
            $title = "Looking for help with {$catName}";
            $description = "I'm looking for someone who can help me with {$catName}.";

            $listing = Listing::create([
                'tenant_id' => $tenantId,
                'title' => $title,
                'description' => $description,
                'type' => 'request',
                'category_id' => $catId,
                'user_id' => $userId,
                'status' => 'active',
            ]);

            $createdIds[] = $listing->id;
        }

        return $createdIds;
    }

    /**
     * Mark onboarding as complete.
     */
    public static function completeOnboarding(int $userId): void
    {
        User::where('id', $userId)
            ->update(['onboarding_completed' => true]);
    }

    /**
     * Skip an onboarding step — convenience method that marks a step complete.
     */
    public static function skipStep(int $tenantId, int $userId, string $step): bool
    {
        return self::completeStep($tenantId, $userId, $step);
    }

    /**
     * Get onboarding recommendations (categories, skills) for the user.
     */
    public static function getRecommendations(int $tenantId): array
    {
        $categories = Category::orderBy('name')
            ->select(['id', 'name', 'slug', 'color'])
            ->get()
            ->map(fn ($c) => $c->toArray())
            ->all();

        $skills = DB::table('skills')
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
     * Alias for completeOnboarding.
     */
    public static function markComplete(int $userId): void
    {
        self::completeOnboarding($userId);
    }
}
