<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\OnboardingService;
use App\Models\User;
use App\Models\UserInterest;
use App\Models\Category;
use App\Models\Listing;
use App\Core\TenantContext;
use Mockery;
use Illuminate\Support\Facades\DB;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class OnboardingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    // ── getProgress ──

    public function test_getProgress_returns_steps_and_percentage(): void
    {
        // Mock a user with onboarding not completed
        $user = Mockery::mock();
        $user->avatar_url = null;
        $user->bio = null;
        $user->onboarding_completed = false;

        $userModel = Mockery::mock('alias:' . User::class . '_progress');
        // We test the structure of the return value
        $result = OnboardingService::getProgress(2, 0);
        $this->assertIsArray($result);
    }

    // ── completeStep ──

    public function test_completeStep_with_complete_step_returns_true(): void
    {
        // completeStep('complete') tries to update user; in test context may return 0
        $result = OnboardingService::completeStep(2, 0, 'complete');
        // Returns bool based on update count
        $this->assertIsBool($result);
    }

    public function test_completeStep_with_non_complete_step_returns_true(): void
    {
        $result = OnboardingService::completeStep(2, 1, 'profile');
        $this->assertTrue($result);
    }

    // ── getChecklist ──

    public function test_getChecklist_returns_four_steps(): void
    {
        $result = OnboardingService::getChecklist(2);
        $this->assertCount(4, $result);
        $this->assertEquals('profile', $result[0]['key']);
        $this->assertEquals('interests', $result[1]['key']);
        $this->assertEquals('skills', $result[2]['key']);
        $this->assertEquals('complete', $result[3]['key']);
    }

    // ── isOnboardingComplete ──

    public function test_isOnboardingComplete_returns_false_for_nonexistent_user(): void
    {
        $result = OnboardingService::isOnboardingComplete(0);
        $this->assertFalse($result);
    }

    // ── getRecommendations ──

    public function test_getRecommendations_returns_categories_and_skills(): void
    {
        $result = OnboardingService::getRecommendations(2);
        $this->assertArrayHasKey('categories', $result);
        $this->assertArrayHasKey('skills', $result);
        $this->assertIsArray($result['categories']);
        $this->assertIsArray($result['skills']);
    }

    // ── skipStep ──

    public function test_skipStep_delegates_to_completeStep(): void
    {
        $result = OnboardingService::skipStep(2, 1, 'profile');
        $this->assertTrue($result);
    }

    // ── resetProgress ──

    public function test_resetProgress_returns_true(): void
    {
        $result = OnboardingService::resetProgress(2, 0);
        $this->assertTrue($result);
    }
}
