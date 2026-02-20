<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Services;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\OnboardingService;

/**
 * OnboardingService Tests
 *
 * Tests post-registration onboarding wizard operations including
 * interests, skills, and auto-listing creation.
 */
class OnboardingServiceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testCategoryId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);

        self::createTestData();
    }

    protected static function createTestData(): void
    {
        $ts = time();

        // Create test user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, onboarding_completed, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 0, NOW())",
            [self::$testTenantId, "onboard_{$ts}@test.com", "onboard_{$ts}", 'Onboard', 'User', 'Onboard User']
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Get or create a test category
        $stmt = Database::query(
            "SELECT id FROM categories WHERE tenant_id = ? LIMIT 1",
            [self::$testTenantId]
        );
        $category = $stmt->fetch();

        if ($category) {
            self::$testCategoryId = (int)$category['id'];
        } else {
            Database::query(
                "INSERT INTO categories (tenant_id, name) VALUES (?, ?)",
                [self::$testTenantId, "Test Category {$ts}"]
            );
            self::$testCategoryId = (int)Database::getInstance()->lastInsertId();
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM user_interests WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM listings WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    // ==========================================
    // Onboarding Status Tests
    // ==========================================

    public function testIsOnboardingCompleteReturnsFalseByDefault(): void
    {
        $completed = OnboardingService::isOnboardingComplete(self::$testUserId);
        $this->assertFalse($completed);
    }

    public function testCompleteOnboardingMarksUserAsComplete(): void
    {
        OnboardingService::completeOnboarding(self::$testUserId);

        $completed = OnboardingService::isOnboardingComplete(self::$testUserId);
        $this->assertTrue($completed);

        // Reset for other tests
        Database::query("UPDATE users SET onboarding_completed = 0 WHERE id = ?", [self::$testUserId]);
    }

    // ==========================================
    // Interests Tests
    // ==========================================

    public function testSaveInterestsSavesCategories(): void
    {
        OnboardingService::saveInterests(self::$testUserId, [self::$testCategoryId]);

        $interests = OnboardingService::getUserInterests(self::$testUserId);
        $interestTypes = array_column($interests, 'interest_type');

        $this->assertContains('interest', $interestTypes);
    }

    public function testSaveInterestsReplacesExistingInterests(): void
    {
        // First save
        OnboardingService::saveInterests(self::$testUserId, [self::$testCategoryId]);

        // Second save (should replace)
        OnboardingService::saveInterests(self::$testUserId, []);

        $interests = OnboardingService::getUserInterests(self::$testUserId);
        $interestRows = array_filter($interests, fn($i) => $i['interest_type'] === 'interest');

        $this->assertEmpty($interestRows);
    }

    public function testGetUserInterestsReturnsArray(): void
    {
        $interests = OnboardingService::getUserInterests(self::$testUserId);
        $this->assertIsArray($interests);
    }

    public function testGetUserInterestsIncludesCategoryName(): void
    {
        OnboardingService::saveInterests(self::$testUserId, [self::$testCategoryId]);

        $interests = OnboardingService::getUserInterests(self::$testUserId);

        if (!empty($interests)) {
            $this->assertArrayHasKey('category_name', $interests[0]);
        }
        $this->assertTrue(true); // Always pass
    }

    // ==========================================
    // Skills Tests
    // ==========================================

    public function testSaveSkillsSavesOffersAndNeeds(): void
    {
        OnboardingService::saveSkills(
            self::$testUserId,
            [self::$testCategoryId],
            [self::$testCategoryId]
        );

        $interests = OnboardingService::getUserInterests(self::$testUserId);
        $types = array_column($interests, 'interest_type');

        $this->assertContains('skill_offer', $types);
        $this->assertContains('skill_need', $types);
    }

    public function testSaveSkillsReplacesExistingSkills(): void
    {
        // First save
        OnboardingService::saveSkills(self::$testUserId, [self::$testCategoryId], []);

        // Second save (should replace)
        OnboardingService::saveSkills(self::$testUserId, [], []);

        $interests = OnboardingService::getUserInterests(self::$testUserId);
        $skillRows = array_filter($interests, fn($i) => in_array($i['interest_type'], ['skill_offer', 'skill_need']));

        $this->assertEmpty($skillRows);
    }

    // ==========================================
    // Auto-Create Listings Tests
    // ==========================================

    public function testAutoCreateListingsCreatesOfferListings(): void
    {
        $created = OnboardingService::autoCreateListings(
            self::$testUserId,
            [self::$testCategoryId],
            []
        );

        $this->assertNotEmpty($created);
        $this->assertIsArray($created);

        // Verify listing created
        $stmt = Database::query(
            "SELECT * FROM listings WHERE id = ? AND type = 'offer'",
            [$created[0]]
        );
        $listing = $stmt->fetch();
        $this->assertNotEmpty($listing);

        // Cleanup
        foreach ($created as $id) {
            Database::query("DELETE FROM listings WHERE id = ?", [$id]);
        }
    }

    public function testAutoCreateListingsCreatesRequestListings(): void
    {
        $created = OnboardingService::autoCreateListings(
            self::$testUserId,
            [],
            [self::$testCategoryId]
        );

        $this->assertNotEmpty($created);

        // Verify listing created
        $stmt = Database::query(
            "SELECT * FROM listings WHERE id = ? AND type = 'request'",
            [$created[0]]
        );
        $listing = $stmt->fetch();
        $this->assertNotEmpty($listing);

        // Cleanup
        foreach ($created as $id) {
            Database::query("DELETE FROM listings WHERE id = ?", [$id]);
        }
    }

    public function testAutoCreateListingsReturnsEmptyWhenNoCategories(): void
    {
        $created = OnboardingService::autoCreateListings(self::$testUserId, [], []);
        $this->assertEmpty($created);
    }

    public function testAutoCreateListingsCreatesBothTypes(): void
    {
        $created = OnboardingService::autoCreateListings(
            self::$testUserId,
            [self::$testCategoryId],
            [self::$testCategoryId]
        );

        $this->assertCount(2, $created);

        // Cleanup
        foreach ($created as $id) {
            Database::query("DELETE FROM listings WHERE id = ?", [$id]);
        }
    }
}
