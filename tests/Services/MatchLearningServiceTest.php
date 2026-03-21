<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\MatchLearningService;
use App\Core\Database;
use App\Core\TenantContext;

/**
 * MatchLearningService Tests
 *
 * Tests the learning engine that improves match quality from user interactions:
 * historical boost/penalty, interaction recording, category affinities,
 * distance preference learning, and aggregate stats.
 */
class MatchLearningServiceTest extends TestCase
{
    private static int $testTenantId = 1;
    private static ?int $testUserId = null;
    private static ?int $testOwnerUserId = null;
    private static ?int $testCategoryId = null;
    private static ?int $testListingId = null;
    private static bool $dbAvailable = false;

    private MatchLearningService $service;

    public static function setUpBeforeClass(): void
    {
        try {
            TenantContext::setById(self::$testTenantId);
        } catch (\Throwable $e) {
            return;
        }

        try {
            $timestamp = time() . rand(1000, 9999);

            // Create category
            Database::query(
                "INSERT INTO categories (tenant_id, name, slug, color, created_at)
                 VALUES (?, 'Learning Test Cat', ?, '#333333', NOW())",
                [self::$testTenantId, "learn-test-cat-{$timestamp}"]
            );
            self::$testCategoryId = (int) Database::getInstance()->lastInsertId();

            // Create test user (the one being matched)
            Database::query(
                "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, is_approved, status, created_at)
                 VALUES (?, ?, ?, 'Learn', 'User', 'Learn User', 1, 'active', NOW())",
                [self::$testTenantId, "learn_user_{$timestamp}@test.com", "learn_user_{$timestamp}"]
            );
            self::$testUserId = (int) Database::getInstance()->lastInsertId();

            // Create listing owner
            Database::query(
                "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, is_approved, status, created_at)
                 VALUES (?, ?, ?, 'Learn', 'Owner', 'Learn Owner', 1, 'active', NOW())",
                [self::$testTenantId, "learn_owner_{$timestamp}@test.com", "learn_owner_{$timestamp}"]
            );
            self::$testOwnerUserId = (int) Database::getInstance()->lastInsertId();

            // Create listing
            Database::query(
                "INSERT INTO listings (tenant_id, user_id, title, description, type, category_id, status, created_at)
                 VALUES (?, ?, 'Learning Test Listing', 'A listing for testing match learning', 'offer', ?, 'active', NOW())",
                [self::$testTenantId, self::$testOwnerUserId, self::$testCategoryId]
            );
            self::$testListingId = (int) Database::getInstance()->lastInsertId();

            self::$dbAvailable = true;
        } catch (\Throwable $e) {
            error_log("MatchLearningServiceTest setup failed: " . $e->getMessage());
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (!self::$dbAvailable) {
            return;
        }

        try {
            if (self::$testUserId) {
                Database::query("DELETE FROM match_history WHERE user_id = ? AND tenant_id = ?", [self::$testUserId, self::$testTenantId]);
            }
            if (self::$testListingId) {
                Database::query("DELETE FROM listings WHERE id = ?", [self::$testListingId]);
            }
            if (self::$testUserId) {
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            }
            if (self::$testOwnerUserId) {
                Database::query("DELETE FROM users WHERE id = ?", [self::$testOwnerUserId]);
            }
            if (self::$testCategoryId) {
                Database::query("DELETE FROM categories WHERE id = ?", [self::$testCategoryId]);
            }
        } catch (\Throwable $e) {
            // Ignore
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$dbAvailable) {
            $this->markTestSkipped('Database not available for integration test');
        }

        TenantContext::setById(self::$testTenantId);
        $this->service = new MatchLearningService();
    }

    // ==========================================
    // recordInteraction Tests
    // ==========================================

    public function testRecordInteractionViewSuccess(): void
    {
        $result = $this->service->recordInteraction(
            self::$testUserId,
            self::$testListingId,
            'view'
        );

        $this->assertTrue($result);
    }

    public function testRecordInteractionAcceptSuccess(): void
    {
        $result = $this->service->recordInteraction(
            self::$testUserId,
            self::$testListingId,
            'accept',
            ['match_score' => 85.0, 'distance_km' => 5.0]
        );

        $this->assertTrue($result);
    }

    public function testRecordInteractionDismissSuccess(): void
    {
        $result = $this->service->recordInteraction(
            self::$testUserId,
            self::$testListingId,
            'dismiss',
            ['match_score' => 40.0]
        );

        $this->assertTrue($result);
    }

    public function testRecordInteractionContactSuccess(): void
    {
        $result = $this->service->recordInteraction(
            self::$testUserId,
            self::$testListingId,
            'contact'
        );

        $this->assertTrue($result);
    }

    public function testRecordInteractionSaveSuccess(): void
    {
        $result = $this->service->recordInteraction(
            self::$testUserId,
            self::$testListingId,
            'save'
        );

        $this->assertTrue($result);
    }

    public function testRecordInteractionNormalizesAliasActions(): void
    {
        // 'dismissed' should be mapped to 'dismiss'
        $result = $this->service->recordInteraction(
            self::$testUserId,
            self::$testListingId,
            'dismissed'
        );

        $this->assertTrue($result);
    }

    public function testRecordInteractionUnknownActionDefaultsToView(): void
    {
        $result = $this->service->recordInteraction(
            self::$testUserId,
            self::$testListingId,
            'unknown_action_xyz'
        );

        $this->assertTrue($result);
    }

    public function testRecordInteractionWithMetadata(): void
    {
        $result = $this->service->recordInteraction(
            self::$testUserId,
            self::$testListingId,
            'view',
            ['score' => 72, 'source' => 'feed', 'distance_km' => 12.5]
        );

        $this->assertTrue($result);
    }

    // ==========================================
    // getHistoricalBoost Tests
    // ==========================================

    public function testGetHistoricalBoostReturnsFloat(): void
    {
        $boost = $this->service->getHistoricalBoost(self::$testUserId, [
            'user_id' => self::$testOwnerUserId,
            'category_id' => self::$testCategoryId,
        ]);

        $this->assertIsFloat($boost);
    }

    public function testGetHistoricalBoostIsBounded(): void
    {
        // Record some interactions first to ensure there's data
        $this->service->recordInteraction(self::$testUserId, self::$testListingId, 'accept');
        $this->service->recordInteraction(self::$testUserId, self::$testListingId, 'accept');

        $boost = $this->service->getHistoricalBoost(self::$testUserId, [
            'user_id' => self::$testOwnerUserId,
            'category_id' => self::$testCategoryId,
        ]);

        $this->assertGreaterThanOrEqual(-15.0, $boost);
        $this->assertLessThanOrEqual(15.0, $boost);
    }

    public function testGetHistoricalBoostWithNoOwnerReturnsZero(): void
    {
        $boost = $this->service->getHistoricalBoost(self::$testUserId, [
            'user_id' => 0,
            'category_id' => self::$testCategoryId,
        ]);

        $this->assertEquals(0.0, $boost);
    }

    public function testGetHistoricalBoostWithMissingUserIdReturnsZero(): void
    {
        $boost = $this->service->getHistoricalBoost(self::$testUserId, [
            'category_id' => self::$testCategoryId,
        ]);

        $this->assertEquals(0.0, $boost);
    }

    public function testGetHistoricalBoostAcceptsObjectInput(): void
    {
        $listing = (object) [
            'user_id' => self::$testOwnerUserId,
            'category_id' => self::$testCategoryId,
        ];

        $boost = $this->service->getHistoricalBoost(self::$testUserId, $listing);

        $this->assertIsFloat($boost);
    }

    public function testGetHistoricalBoostPositiveAfterAccepts(): void
    {
        // Record multiple accepts to ensure positive boost
        for ($i = 0; $i < 5; $i++) {
            $this->service->recordInteraction(self::$testUserId, self::$testListingId, 'accept');
        }

        $boost = $this->service->getHistoricalBoost(self::$testUserId, [
            'user_id' => self::$testOwnerUserId,
            'category_id' => self::$testCategoryId,
        ]);

        $this->assertGreaterThan(0, $boost, 'Boost should be positive after multiple accepts');
    }

    // ==========================================
    // getCategoryAffinities Tests
    // ==========================================

    public function testGetCategoryAffinitiesReturnsArray(): void
    {
        $affinities = $this->service->getCategoryAffinities(self::$testUserId);

        $this->assertIsArray($affinities);
    }

    public function testGetCategoryAffinitiesValuesAreBounded(): void
    {
        // Record interactions first
        $this->service->recordInteraction(self::$testUserId, self::$testListingId, 'accept');

        $affinities = $this->service->getCategoryAffinities(self::$testUserId);

        foreach ($affinities as $categoryId => $score) {
            $this->assertIsInt($categoryId);
            $this->assertIsFloat($score);
            $this->assertGreaterThanOrEqual(-1.0, $score);
            $this->assertLessThanOrEqual(1.0, $score);
        }
    }

    public function testGetCategoryAffinitiesForNewUserIsEmpty(): void
    {
        $affinities = $this->service->getCategoryAffinities(999999);

        $this->assertIsArray($affinities);
        $this->assertEmpty($affinities);
    }

    public function testGetCategoryAffinitiesContainsTestCategory(): void
    {
        // Record a positive interaction
        $this->service->recordInteraction(self::$testUserId, self::$testListingId, 'accept');

        $affinities = $this->service->getCategoryAffinities(self::$testUserId);

        if (!empty($affinities)) {
            $this->assertArrayHasKey(self::$testCategoryId, $affinities,
                'Test category should appear in affinities after recording interaction');
        }
    }

    // ==========================================
    // getLearnedDistancePreference Tests
    // ==========================================

    public function testGetLearnedDistancePreferenceReturnsExpectedStructure(): void
    {
        $pref = $this->service->getLearnedDistancePreference(self::$testUserId);

        $this->assertIsArray($pref);
        $this->assertArrayHasKey('preferred_km', $pref);
        $this->assertArrayHasKey('max_km', $pref);
        $this->assertArrayHasKey('confidence', $pref);
        $this->assertArrayHasKey('sample_size', $pref);
    }

    public function testGetLearnedDistancePreferenceValuesAreNumeric(): void
    {
        $pref = $this->service->getLearnedDistancePreference(self::$testUserId);

        $this->assertIsFloat($pref['preferred_km']);
        $this->assertIsFloat($pref['max_km']);
        $this->assertIsFloat($pref['confidence']);
        $this->assertIsInt($pref['sample_size']);
    }

    public function testGetLearnedDistancePreferenceDefaultsForNewUser(): void
    {
        $pref = $this->service->getLearnedDistancePreference(999999);

        $this->assertEquals(25.0, $pref['preferred_km']);
        $this->assertEquals(50.0, $pref['max_km']);
        $this->assertEquals(0.0, $pref['confidence']);
        $this->assertEquals(0, $pref['sample_size']);
    }

    public function testGetLearnedDistancePreferenceConfidenceBounded(): void
    {
        $pref = $this->service->getLearnedDistancePreference(self::$testUserId);

        $this->assertGreaterThanOrEqual(0.0, $pref['confidence']);
        $this->assertLessThanOrEqual(1.0, $pref['confidence']);
    }

    public function testGetLearnedDistancePreferenceMaxGreaterThanPreferred(): void
    {
        $pref = $this->service->getLearnedDistancePreference(self::$testUserId);

        $this->assertGreaterThanOrEqual(
            $pref['preferred_km'],
            $pref['max_km'],
            'max_km should be >= preferred_km'
        );
    }

    // ==========================================
    // getLearningStats Tests
    // ==========================================

    public function testGetLearningStatsReturnsExpectedStructure(): void
    {
        $stats = $this->service->getLearningStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_interactions', $stats);
        $this->assertArrayHasKey('unique_users', $stats);
        $this->assertArrayHasKey('action_breakdown', $stats);
        $this->assertArrayHasKey('avg_interactions_per_user', $stats);
        $this->assertArrayHasKey('category_affinity_coverage', $stats);
    }

    public function testGetLearningStatsValuesAreCorrectTypes(): void
    {
        $stats = $this->service->getLearningStats();

        $this->assertIsInt($stats['total_interactions']);
        $this->assertIsInt($stats['unique_users']);
        $this->assertIsArray($stats['action_breakdown']);
        $this->assertIsFloat($stats['avg_interactions_per_user']);
        $this->assertIsInt($stats['category_affinity_coverage']);
    }

    public function testGetLearningStatsNonNegativeValues(): void
    {
        $stats = $this->service->getLearningStats();

        $this->assertGreaterThanOrEqual(0, $stats['total_interactions']);
        $this->assertGreaterThanOrEqual(0, $stats['unique_users']);
        $this->assertGreaterThanOrEqual(0, $stats['avg_interactions_per_user']);
        $this->assertGreaterThanOrEqual(0, $stats['category_affinity_coverage']);
    }

    public function testGetLearningStatsActionBreakdownKeysAreValidActions(): void
    {
        $stats = $this->service->getLearningStats();

        $validActions = ['impression', 'view', 'save', 'contact', 'dismiss', 'accept', 'decline'];
        foreach (array_keys($stats['action_breakdown']) as $action) {
            $this->assertContains($action, $validActions,
                "Action '{$action}' should be a valid action type");
        }
    }

    public function testGetLearningStatsHasInteractionsAfterRecording(): void
    {
        // Ensure we have at least one interaction
        $this->service->recordInteraction(self::$testUserId, self::$testListingId, 'view');

        $stats = $this->service->getLearningStats();

        $this->assertGreaterThanOrEqual(1, $stats['total_interactions']);
        $this->assertGreaterThanOrEqual(1, $stats['unique_users']);
    }

    // ==========================================
    // Private Helper Tests (via reflection)
    // ==========================================

    public function testMedianCalculation(): void
    {
        $result = $this->callPrivateMethod($this->service, 'median', [[1, 3, 5, 7, 9]]);
        $this->assertEquals(5.0, $result);
    }

    public function testMedianEvenCount(): void
    {
        $result = $this->callPrivateMethod($this->service, 'median', [[1, 3, 5, 7]]);
        $this->assertEquals(4.0, $result);
    }

    public function testMedianEmptyArray(): void
    {
        $result = $this->callPrivateMethod($this->service, 'median', [[]]);
        $this->assertEquals(0.0, $result);
    }

    public function testMedianSingleElement(): void
    {
        $result = $this->callPrivateMethod($this->service, 'median', [[42.0]]);
        $this->assertEquals(42.0, $result);
    }

    public function testPercentile90th(): void
    {
        $values = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
        $result = $this->callPrivateMethod($this->service, 'percentile', [$values, 90]);

        $this->assertGreaterThanOrEqual(9.0, $result);
        $this->assertLessThanOrEqual(10.0, $result);
    }

    public function testPercentile50thEqualsMedian(): void
    {
        $values = [1, 2, 3, 4, 5];
        $p50 = $this->callPrivateMethod($this->service, 'percentile', [$values, 50]);
        $median = $this->callPrivateMethod($this->service, 'median', [$values]);

        $this->assertEquals($median, $p50);
    }

    public function testPercentileEmptyArray(): void
    {
        $result = $this->callPrivateMethod($this->service, 'percentile', [[], 50]);
        $this->assertEquals(0.0, $result);
    }
}
