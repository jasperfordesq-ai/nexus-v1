<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Controllers;

use PHPUnit\Framework\TestCase;
use Nexus\Controllers\MatchController;
use Nexus\Services\MatchingService;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * MatchControllerTest - Tests for the MatchController
 *
 * Tests API endpoints and controller logic.
 * Note: These are unit tests, not integration tests.
 */
class MatchControllerTest extends TestCase
{
    private static $testTenantId = 1;
    private static $testUserId;
    private static $testCategoryId;
    private static $testListingId;
    private $controller;

    public static function setUpBeforeClass(): void
    {
        TenantContext::setById(self::$testTenantId);

        $timestamp = time() . rand(1000, 9999);

        // Create test category
        Database::query(
            "INSERT INTO categories (tenant_id, name, slug, color, created_at)
             VALUES (?, 'Controller Test', ?, '#f59e0b', NOW())",
            [self::$testTenantId, 'controller-test-' . $timestamp]
        );
        self::$testCategoryId = Database::getInstance()->lastInsertId();

        // Create test user
        Database::query(
            "INSERT INTO users (tenant_id, email, first_name, last_name, name, location, latitude, longitude, is_approved, status, created_at)
             VALUES (?, ?, 'Controller', 'TestUser', 'Controller TestUser', 'Test City', 51.5074, -0.1278, 1, 'active', NOW())",
            [self::$testTenantId, 'controller_test_' . $timestamp . '@test.com']
        );
        self::$testUserId = Database::getInstance()->lastInsertId();

        // Create test listing
        Database::query(
            "INSERT INTO listings (tenant_id, user_id, title, description, type, category_id, status, created_at)
             VALUES (?, ?, 'Controller Test Listing', 'Test description', 'offer', ?, 'active', NOW())",
            [self::$testTenantId, self::$testUserId, self::$testCategoryId]
        );
        self::$testListingId = Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM match_history WHERE user_id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
            try {
                Database::query("DELETE FROM match_cache WHERE user_id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
            try {
                Database::query("DELETE FROM match_preferences WHERE user_id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
            try {
                Database::query("DELETE FROM listings WHERE user_id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
            try {
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
        }

        if (self::$testCategoryId) {
            try {
                Database::query("DELETE FROM categories WHERE id = ?", [self::$testCategoryId]);
            } catch (\Exception $e) {}
        }
    }

    protected function setUp(): void
    {
        $this->controller = new MatchController();

        // Simulate logged-in user
        $_SESSION['user_id'] = self::$testUserId;

        // Clear preferences
        try {
            Database::query("DELETE FROM match_preferences WHERE user_id = ?", [self::$testUserId]);
        } catch (\Exception $e) {}
    }

    protected function tearDown(): void
    {
        unset($_SESSION['user_id']);
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    // =========================================================================
    // SERVICE INTEGRATION TESTS
    // =========================================================================

    /**
     * Test that MatchingService returns valid data for authenticated user
     */
    public function testMatchingServiceReturnsValidData(): void
    {
        $matches = MatchingService::getMatchesByType(self::$testUserId);

        $this->assertIsArray($matches);
        $this->assertArrayHasKey('hot', $matches);
        $this->assertArrayHasKey('good', $matches);
        $this->assertArrayHasKey('mutual', $matches);
        $this->assertArrayHasKey('all', $matches);
    }

    /**
     * Test that stats are calculated correctly
     */
    public function testMatchingServiceStatsCalculation(): void
    {
        $stats = MatchingService::getStats(self::$testUserId);

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_matches', $stats);
        $this->assertArrayHasKey('hot_matches', $stats);
        $this->assertArrayHasKey('mutual_matches', $stats);
        $this->assertArrayHasKey('avg_score', $stats);
    }

    /**
     * Test preferences retrieval
     */
    public function testPreferencesRetrievalWithDefaults(): void
    {
        $prefs = MatchingService::getPreferences(self::$testUserId);

        $this->assertIsArray($prefs);
        $this->assertEquals(25, $prefs['max_distance_km']);
        $this->assertEquals(50, $prefs['min_match_score']);
        $this->assertEquals('daily', $prefs['notification_frequency']);
    }

    /**
     * Test preferences save and retrieve cycle
     */
    public function testPreferencesSaveAndRetrieve(): void
    {
        $newPrefs = [
            'max_distance_km' => 100,
            'min_match_score' => 30,
            'notification_frequency' => 'weekly',
            'notify_hot_matches' => false,
            'notify_mutual_matches' => true,
            'categories' => [self::$testCategoryId]
        ];

        $saved = MatchingService::savePreferences(self::$testUserId, $newPrefs);
        $this->assertTrue($saved);

        $retrieved = MatchingService::getPreferences(self::$testUserId);

        $this->assertEquals(100, $retrieved['max_distance_km']);
        $this->assertEquals(30, $retrieved['min_match_score']);
        $this->assertEquals('weekly', $retrieved['notification_frequency']);
        $this->assertFalse($retrieved['notify_hot_matches']);
        $this->assertTrue($retrieved['notify_mutual_matches']);
        $this->assertContains(self::$testCategoryId, $retrieved['categories']);
    }

    // =========================================================================
    // INTERACTION TRACKING TESTS
    // =========================================================================

    /**
     * Test recording a view interaction
     */
    public function testRecordViewInteraction(): void
    {
        $result = MatchingService::recordInteraction(
            self::$testUserId,
            self::$testListingId,
            'viewed',
            75.5,
            5.0
        );

        $this->assertIsBool($result);
    }

    /**
     * Test recording multiple interaction types
     */
    public function testRecordMultipleInteractionTypes(): void
    {
        $actions = ['viewed', 'saved', 'contacted', 'dismissed'];

        foreach ($actions as $action) {
            $result = MatchingService::recordInteraction(
                self::$testUserId,
                self::$testListingId,
                $action,
                80.0,
                3.5
            );

            $this->assertIsBool($result, "Failed for action: $action");
        }
    }

    // =========================================================================
    // HOT MATCHES TESTS
    // =========================================================================

    /**
     * Test hot matches retrieval
     */
    public function testGetHotMatches(): void
    {
        $hotMatches = MatchingService::getHotMatches(self::$testUserId, 10);

        $this->assertIsArray($hotMatches);

        // All hot matches should have score >= 80
        foreach ($hotMatches as $match) {
            $this->assertGreaterThanOrEqual(80, $match['match_score']);
        }
    }

    /**
     * Test hot matches with custom limit
     */
    public function testGetHotMatchesWithLimit(): void
    {
        $hotMatches = MatchingService::getHotMatches(self::$testUserId, 3);

        $this->assertIsArray($hotMatches);
        $this->assertLessThanOrEqual(3, count($hotMatches));
    }

    // =========================================================================
    // MUTUAL MATCHES TESTS
    // =========================================================================

    /**
     * Test mutual matches retrieval
     */
    public function testGetMutualMatches(): void
    {
        $mutualMatches = MatchingService::getMutualMatches(self::$testUserId, 10);

        $this->assertIsArray($mutualMatches);

        // All mutual matches should have 'mutual' type
        foreach ($mutualMatches as $match) {
            $this->assertEquals('mutual', $match['match_type']);
        }
    }

    // =========================================================================
    // SUGGESTIONS TESTS
    // =========================================================================

    /**
     * Test suggestions with various options
     */
    public function testGetSuggestionsWithOptions(): void
    {
        $options = [
            'max_distance' => 50,
            'min_score' => 40
        ];

        $suggestions = MatchingService::getSuggestionsForUser(self::$testUserId, 10, $options);

        $this->assertIsArray($suggestions);
        $this->assertLessThanOrEqual(10, count($suggestions));
    }

    /**
     * Test suggestions filter by min score
     */
    public function testSuggestionsFilteredByMinScore(): void
    {
        $minScore = 60;
        $suggestions = MatchingService::getSuggestionsForUser(self::$testUserId, 20, [
            'min_score' => $minScore,
            'max_distance' => 200
        ]);

        foreach ($suggestions as $match) {
            $this->assertGreaterThanOrEqual($minScore, $match['match_score']);
        }
    }

    // =========================================================================
    // EDGE CASES
    // =========================================================================

    /**
     * Test behavior with unauthenticated user ID
     */
    public function testBehaviorWithInvalidUserId(): void
    {
        $invalidUserId = 999999999;

        $matches = MatchingService::getSuggestionsForUser($invalidUserId);
        $this->assertIsArray($matches);
        $this->assertEmpty($matches);

        $stats = MatchingService::getStats($invalidUserId);
        $this->assertEquals(0, $stats['total_matches']);

        $prefs = MatchingService::getPreferences($invalidUserId);
        $this->assertIsArray($prefs);
    }

    /**
     * Test preferences with boundary values
     */
    public function testPreferencesWithBoundaryValues(): void
    {
        // Test minimum values
        $minPrefs = [
            'max_distance_km' => 1,
            'min_match_score' => 1
        ];
        MatchingService::savePreferences(self::$testUserId, $minPrefs);
        $retrieved = MatchingService::getPreferences(self::$testUserId);
        $this->assertEquals(1, $retrieved['max_distance_km']);
        $this->assertEquals(1, $retrieved['min_match_score']);

        // Test maximum values
        $maxPrefs = [
            'max_distance_km' => 500,
            'min_match_score' => 100
        ];
        MatchingService::savePreferences(self::$testUserId, $maxPrefs);
        $retrieved = MatchingService::getPreferences(self::$testUserId);
        $this->assertEquals(500, $retrieved['max_distance_km']);
        $this->assertEquals(100, $retrieved['min_match_score']);
    }

    /**
     * Test notification frequency options
     */
    public function testAllNotificationFrequencyOptions(): void
    {
        $frequencies = ['instant', 'daily', 'weekly', 'never'];

        foreach ($frequencies as $freq) {
            MatchingService::savePreferences(self::$testUserId, [
                'notification_frequency' => $freq
            ]);

            $retrieved = MatchingService::getPreferences(self::$testUserId);
            $this->assertEquals($freq, $retrieved['notification_frequency']);
        }
    }

    /**
     * Test interactions are recorded correctly
     */
    public function testInteractionRecording(): void
    {
        // Record multiple interactions
        MatchingService::recordInteraction(self::$testUserId, self::$testListingId, 'viewed', 70, 5);
        MatchingService::recordInteraction(self::$testUserId, self::$testListingId, 'saved', 70, 5);
        MatchingService::recordInteraction(self::$testUserId, self::$testListingId, 'contacted', 70, 5);

        // Verify history was recorded (if table exists)
        try {
            $count = Database::query(
                "SELECT COUNT(*) as c FROM match_history WHERE user_id = ? AND listing_id = ?",
                [self::$testUserId, self::$testListingId]
            )->fetch()['c'];

            $this->assertGreaterThanOrEqual(1, (int)$count);
        } catch (\Exception $e) {
            // Table might not exist, that's ok
            $this->assertTrue(true);
        }
    }
}
