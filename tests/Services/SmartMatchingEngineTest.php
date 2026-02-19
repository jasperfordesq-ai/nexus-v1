<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\SmartMatchingEngine;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * SmartMatchingEngineTest - Comprehensive tests for the matching algorithm
 *
 * Tests all 6 scoring components and the overall matching workflow.
 */
class SmartMatchingEngineTest extends TestCase
{
    private static $testTenantId = 1;
    private static $testUser1Id;
    private static $testUser2Id;
    private static $testUser3Id;
    private static $testCategoryId;
    private static $testListing1Id;
    private static $testListing2Id;
    private static $testListing3Id;

    public static function setUpBeforeClass(): void
    {
        TenantContext::setById(self::$testTenantId);

        $timestamp = time() . rand(1000, 9999);

        // Create test category
        Database::query(
            "INSERT INTO categories (tenant_id, name, slug, color, created_at)
             VALUES (?, 'Test Category', ?, '#6366f1', NOW())",
            [self::$testTenantId, 'test-category-' . $timestamp]
        );
        self::$testCategoryId = Database::getInstance()->lastInsertId();

        // Create test users with coordinates
        // User 1: Has offers, located in city center
        Database::query(
            "INSERT INTO users (tenant_id, email, first_name, last_name, name, location, latitude, longitude, is_approved, status, created_at)
             VALUES (?, ?, 'Test', 'User1', 'Test User1', 'City Center', 51.5074, -0.1278, 1, 'active', NOW())",
            [self::$testTenantId, 'test_match_user1_' . $timestamp . '@test.com']
        );
        self::$testUser1Id = Database::getInstance()->lastInsertId();

        // User 2: Has requests, located nearby (5km away)
        Database::query(
            "INSERT INTO users (tenant_id, email, first_name, last_name, name, location, latitude, longitude, is_approved, status, created_at)
             VALUES (?, ?, 'Test', 'User2', 'Test User2', 'Nearby Town', 51.5200, -0.1000, 1, 'active', NOW())",
            [self::$testTenantId, 'test_match_user2_' . $timestamp . '@test.com']
        );
        self::$testUser2Id = Database::getInstance()->lastInsertId();

        // User 3: Has both offers and requests (for mutual matching), located far (50km)
        Database::query(
            "INSERT INTO users (tenant_id, email, first_name, last_name, name, location, latitude, longitude, is_approved, status, created_at)
             VALUES (?, ?, 'Test', 'User3', 'Test User3', 'Far City', 52.0000, 0.0000, 1, 'active', NOW())",
            [self::$testTenantId, 'test_match_user3_' . $timestamp . '@test.com']
        );
        self::$testUser3Id = Database::getInstance()->lastInsertId();

        // Create test listings
        // User 1's offer
        Database::query(
            "INSERT INTO listings (tenant_id, user_id, title, description, type, category_id, status, latitude, longitude, created_at)
             VALUES (?, ?, 'Gardening Help Offered', 'I can help with gardening and landscaping tasks', 'offer', ?, 'active', 51.5074, -0.1278, NOW())",
            [self::$testTenantId, self::$testUser1Id, self::$testCategoryId]
        );
        self::$testListing1Id = Database::getInstance()->lastInsertId();

        // User 2's request (matches User 1's offer)
        Database::query(
            "INSERT INTO listings (tenant_id, user_id, title, description, type, category_id, status, latitude, longitude, created_at)
             VALUES (?, ?, 'Need Gardening Help', 'Looking for help with my garden', 'request', ?, 'active', 51.5200, -0.1000, NOW())",
            [self::$testTenantId, self::$testUser2Id, self::$testCategoryId]
        );
        self::$testListing2Id = Database::getInstance()->lastInsertId();

        // User 3's offer (for mutual matching)
        Database::query(
            "INSERT INTO listings (tenant_id, user_id, title, description, type, category_id, status, latitude, longitude, created_at)
             VALUES (?, ?, 'Tutoring Services', 'Math and Science tutoring available', 'offer', ?, 'active', 52.0000, 0.0000, NOW())",
            [self::$testTenantId, self::$testUser3Id, self::$testCategoryId]
        );
        self::$testListing3Id = Database::getInstance()->lastInsertId();

        // User 3's request (creates mutual opportunity)
        Database::query(
            "INSERT INTO listings (tenant_id, user_id, title, description, type, category_id, status, created_at)
             VALUES (?, ?, 'Need Garden Work', 'Looking for gardening assistance', 'request', ?, 'active', NOW())",
            [self::$testTenantId, self::$testUser3Id, self::$testCategoryId]
        );
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up in proper order
        $userIds = [self::$testUser1Id, self::$testUser2Id, self::$testUser3Id];

        foreach ($userIds as $userId) {
            if ($userId) {
                try {
                    Database::query("DELETE FROM match_history WHERE user_id = ?", [$userId]);
                } catch (\Exception $e) {}
                try {
                    Database::query("DELETE FROM match_cache WHERE user_id = ?", [$userId]);
                } catch (\Exception $e) {}
                try {
                    Database::query("DELETE FROM match_preferences WHERE user_id = ?", [$userId]);
                } catch (\Exception $e) {}
                try {
                    Database::query("DELETE FROM listings WHERE user_id = ?", [$userId]);
                } catch (\Exception $e) {}
                try {
                    Database::query("DELETE FROM users WHERE id = ?", [$userId]);
                } catch (\Exception $e) {}
            }
        }

        if (self::$testCategoryId) {
            try {
                Database::query("DELETE FROM categories WHERE id = ?", [self::$testCategoryId]);
            } catch (\Exception $e) {}
        }
    }

    protected function setUp(): void
    {
        SmartMatchingEngine::clearCache();
    }

    // =========================================================================
    // CONFIGURATION TESTS
    // =========================================================================

    public function testGetConfigReturnsArray(): void
    {
        $config = SmartMatchingEngine::getConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('enabled', $config);
        $this->assertArrayHasKey('max_distance_km', $config);
        $this->assertArrayHasKey('min_match_score', $config);
        $this->assertArrayHasKey('hot_match_threshold', $config);
        $this->assertArrayHasKey('weights', $config);
        $this->assertArrayHasKey('proximity', $config);
    }

    public function testWeightsSumToOne(): void
    {
        $config = SmartMatchingEngine::getConfig();
        $weights = $config['weights'];

        $total = array_sum($weights);

        $this->assertEqualsWithDelta(1.0, $total, 0.001, 'Weights should sum to 1.0');
    }

    public function testProximityTiersAreOrdered(): void
    {
        $config = SmartMatchingEngine::getConfig();
        $prox = $config['proximity'];

        $this->assertLessThan($prox['local_km'], $prox['walking_km']);
        $this->assertLessThan($prox['city_km'], $prox['local_km']);
        $this->assertLessThan($prox['regional_km'], $prox['city_km']);
        $this->assertLessThan($prox['max_km'], $prox['regional_km']);
    }

    // =========================================================================
    // SCORING COMPONENT TESTS
    // =========================================================================

    public function testCalculateMatchScoreReturnsExpectedStructure(): void
    {
        $userData = [
            'id' => self::$testUser1Id,
            'latitude' => 51.5074,
            'longitude' => -0.1278,
            'skills' => 'gardening, landscaping'
        ];

        $userListings = [
            [
                'id' => self::$testListing1Id,
                'type' => 'offer',
                'category_id' => self::$testCategoryId,
                'title' => 'Gardening Help',
                'description' => 'I can help with gardening'
            ]
        ];

        $myListing = $userListings[0];

        $candidateListing = [
            'id' => self::$testListing2Id,
            'user_id' => self::$testUser2Id,
            'type' => 'request',
            'category_id' => self::$testCategoryId,
            'title' => 'Need Gardening Help',
            'description' => 'Looking for help with my garden',
            'latitude' => 51.5200,
            'longitude' => -0.1000,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $result = SmartMatchingEngine::calculateMatchScore(
            $userData,
            $userListings,
            $myListing,
            $candidateListing
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('reasons', $result);
        $this->assertArrayHasKey('breakdown', $result);
        $this->assertArrayHasKey('distance', $result);
        $this->assertArrayHasKey('type', $result);

        $this->assertIsNumeric($result['score']);
        $this->assertGreaterThanOrEqual(0, $result['score']);
        $this->assertLessThanOrEqual(100, $result['score']);

        $this->assertIsArray($result['reasons']);
        $this->assertIsArray($result['breakdown']);
    }

    public function testProximityScoreWalkingDistance(): void
    {
        // Test walking distance (< 5km) should return 1.0
        $userData = ['latitude' => 51.5074, 'longitude' => -0.1278, 'skills' => ''];
        $userListings = [['type' => 'offer', 'category_id' => 1, 'title' => 'Test', 'description' => '']];
        $myListing = $userListings[0];

        // Candidate very close (same location)
        $candidate = [
            'id' => 999,
            'user_id' => 999,
            'type' => 'request',
            'category_id' => 1,
            'title' => 'Test',
            'description' => '',
            'latitude' => 51.5080, // ~0.5km away
            'longitude' => -0.1280,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $result = SmartMatchingEngine::calculateMatchScore($userData, $userListings, $myListing, $candidate);

        $this->assertEqualsWithDelta(1.0, $result['breakdown']['proximity'], 0.1, 'Walking distance should have ~1.0 proximity score');
    }

    public function testProximityScoreDecaysWithDistance(): void
    {
        $userData = ['latitude' => 51.5074, 'longitude' => -0.1278, 'skills' => ''];
        $userListings = [['type' => 'offer', 'category_id' => 1, 'title' => 'Test', 'description' => '']];
        $myListing = $userListings[0];

        // Test at various distances
        $distances = [
            ['lat' => 51.5080, 'lon' => -0.1280, 'expected_min' => 0.9],  // ~0.5km
            ['lat' => 51.5500, 'lon' => -0.1000, 'expected_min' => 0.7],  // ~5km
            ['lat' => 51.6000, 'lon' => 0.0000, 'expected_min' => 0.5],   // ~15km
            ['lat' => 52.0000, 'lon' => 0.0000, 'expected_min' => 0.1],   // ~60km
        ];

        $prevScore = 1.0;
        foreach ($distances as $d) {
            $candidate = [
                'id' => 999, 'user_id' => 999, 'type' => 'request', 'category_id' => 1,
                'title' => 'Test', 'description' => '', 'latitude' => $d['lat'],
                'longitude' => $d['lon'], 'created_at' => date('Y-m-d H:i:s')
            ];

            $result = SmartMatchingEngine::calculateMatchScore($userData, $userListings, $myListing, $candidate);
            $proxScore = $result['breakdown']['proximity'];

            $this->assertLessThanOrEqual($prevScore, $proxScore, 'Proximity score should decrease with distance');
            $prevScore = $proxScore;
        }
    }

    public function testFreshnessScoreFullForNewListings(): void
    {
        $userData = ['latitude' => 51.5074, 'longitude' => -0.1278, 'skills' => ''];
        $userListings = [['type' => 'offer', 'category_id' => 1, 'title' => 'Test', 'description' => '']];
        $myListing = $userListings[0];

        // Listing created just now
        $candidate = [
            'id' => 999, 'user_id' => 999, 'type' => 'request', 'category_id' => 1,
            'title' => 'Test', 'description' => '', 'latitude' => 51.5080,
            'longitude' => -0.1280, 'created_at' => date('Y-m-d H:i:s')
        ];

        $result = SmartMatchingEngine::calculateMatchScore($userData, $userListings, $myListing, $candidate);

        $this->assertEquals(1.0, $result['breakdown']['freshness'], 'New listing should have 1.0 freshness score');
    }

    public function testFreshnessScoreDecaysOverTime(): void
    {
        $userData = ['latitude' => 51.5074, 'longitude' => -0.1278, 'skills' => ''];
        $userListings = [['type' => 'offer', 'category_id' => 1, 'title' => 'Test', 'description' => '']];
        $myListing = $userListings[0];

        // Listing created 30 days ago
        $oldDate = date('Y-m-d H:i:s', strtotime('-30 days'));
        $candidate = [
            'id' => 999, 'user_id' => 999, 'type' => 'request', 'category_id' => 1,
            'title' => 'Test', 'description' => '', 'latitude' => 51.5080,
            'longitude' => -0.1280, 'created_at' => $oldDate
        ];

        $result = SmartMatchingEngine::calculateMatchScore($userData, $userListings, $myListing, $candidate);

        $this->assertLessThan(1.0, $result['breakdown']['freshness'], 'Old listing should have lower freshness score');
        $this->assertGreaterThanOrEqual(0.3, $result['breakdown']['freshness'], 'Freshness should not go below minimum');
    }

    public function testCategoryScoreExactMatch(): void
    {
        $userData = ['latitude' => 51.5074, 'longitude' => -0.1278, 'skills' => ''];
        $userListings = [['type' => 'offer', 'category_id' => self::$testCategoryId, 'title' => 'Test', 'description' => '']];
        $myListing = $userListings[0];

        $candidate = [
            'id' => 999, 'user_id' => 999, 'type' => 'request',
            'category_id' => self::$testCategoryId, // Same category
            'title' => 'Test', 'description' => '', 'latitude' => 51.5080,
            'longitude' => -0.1280, 'created_at' => date('Y-m-d H:i:s')
        ];

        $result = SmartMatchingEngine::calculateMatchScore($userData, $userListings, $myListing, $candidate);

        $this->assertEquals(1.0, $result['breakdown']['category'], 'Same category should have 1.0 category score');
    }

    public function testCategoryScoreDifferentCategory(): void
    {
        $userData = ['latitude' => 51.5074, 'longitude' => -0.1278, 'skills' => ''];
        $userListings = [['type' => 'offer', 'category_id' => self::$testCategoryId, 'title' => 'Test', 'description' => '']];
        $myListing = $userListings[0];

        $candidate = [
            'id' => 999, 'user_id' => 999, 'type' => 'request',
            'category_id' => 99999, // Different category
            'title' => 'Test', 'description' => '', 'latitude' => 51.5080,
            'longitude' => -0.1280, 'created_at' => date('Y-m-d H:i:s')
        ];

        $result = SmartMatchingEngine::calculateMatchScore($userData, $userListings, $myListing, $candidate);

        $this->assertLessThan(1.0, $result['breakdown']['category'], 'Different category should have lower score');
    }

    public function testQualityScoreWithGoodListing(): void
    {
        $userData = ['latitude' => 51.5074, 'longitude' => -0.1278, 'skills' => ''];
        $userListings = [['type' => 'offer', 'category_id' => 1, 'title' => 'Test', 'description' => '']];
        $myListing = $userListings[0];

        // High quality listing with long description, image, verified owner
        $candidate = [
            'id' => 999, 'user_id' => 999, 'type' => 'request', 'category_id' => 1,
            'title' => 'Test Listing',
            'description' => str_repeat('This is a detailed description. ', 10), // Long description
            'image_url' => 'https://example.com/image.jpg',
            'author_verified' => true,
            'author_rating' => 4.5,
            'latitude' => 51.5080, 'longitude' => -0.1280, 'created_at' => date('Y-m-d H:i:s')
        ];

        $result = SmartMatchingEngine::calculateMatchScore($userData, $userListings, $myListing, $candidate);

        $this->assertGreaterThan(0.7, $result['breakdown']['quality'], 'High quality listing should have high quality score');
    }

    // =========================================================================
    // MATCH FINDING TESTS
    // =========================================================================

    public function testFindMatchesForUserReturnsArray(): void
    {
        $matches = SmartMatchingEngine::findMatchesForUser(self::$testUser1Id);

        $this->assertIsArray($matches);
    }

    public function testFindMatchesForUserWithOptions(): void
    {
        $matches = SmartMatchingEngine::findMatchesForUser(self::$testUser1Id, [
            'max_distance' => 100,
            'min_score' => 20,
            'limit' => 5
        ]);

        $this->assertIsArray($matches);
        $this->assertLessThanOrEqual(5, count($matches));
    }

    public function testMatchesAreSortedByScoreDescending(): void
    {
        $matches = SmartMatchingEngine::findMatchesForUser(self::$testUser1Id, [
            'max_distance' => 200,
            'min_score' => 10,
            'limit' => 20
        ]);

        if (count($matches) > 1) {
            $prevScore = PHP_FLOAT_MAX;
            foreach ($matches as $match) {
                $this->assertLessThanOrEqual($prevScore, $match['match_score'], 'Matches should be sorted by score descending');
                $prevScore = $match['match_score'];
            }
        }

        $this->assertTrue(true); // Pass if no matches to compare
    }

    public function testMatchesExcludeOwnListings(): void
    {
        $matches = SmartMatchingEngine::findMatchesForUser(self::$testUser1Id, [
            'max_distance' => 200,
            'min_score' => 0
        ]);

        foreach ($matches as $match) {
            $this->assertNotEquals(self::$testUser1Id, $match['user_id'], 'Should not match own listings');
        }
    }

    // =========================================================================
    // HOT MATCHES TESTS
    // =========================================================================

    public function testGetHotMatchesReturnsArray(): void
    {
        $hotMatches = SmartMatchingEngine::getHotMatches(self::$testUser1Id, 5);

        $this->assertIsArray($hotMatches);
    }

    public function testHotMatchesHaveHighScores(): void
    {
        $hotMatches = SmartMatchingEngine::getHotMatches(self::$testUser1Id, 10);

        foreach ($hotMatches as $match) {
            $this->assertGreaterThanOrEqual(80, $match['match_score'], 'Hot matches should have score >= 80');
        }
    }

    // =========================================================================
    // MUTUAL MATCHES TESTS
    // =========================================================================

    public function testGetMutualMatchesReturnsArray(): void
    {
        $mutualMatches = SmartMatchingEngine::getMutualMatches(self::$testUser1Id, 10);

        $this->assertIsArray($mutualMatches);
    }

    public function testMutualMatchesHaveMutualType(): void
    {
        $mutualMatches = SmartMatchingEngine::getMutualMatches(self::$testUser1Id, 10);

        foreach ($mutualMatches as $match) {
            $this->assertEquals('mutual', $match['match_type'], 'Mutual matches should have mutual type');
        }
    }

    // =========================================================================
    // CACHE TESTS
    // =========================================================================

    public function testClearCacheDoesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        SmartMatchingEngine::clearCache();
    }

    public function testInvalidateCacheForCategoryDoesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        SmartMatchingEngine::invalidateCacheForCategory(self::$testCategoryId);
    }

    public function testInvalidateCacheForUserDoesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        SmartMatchingEngine::invalidateCacheForUser(self::$testUser1Id);
    }

    public function testClearExpiredCacheReturnsInteger(): void
    {
        $count = SmartMatchingEngine::clearExpiredCache();

        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function testWarmUpCacheReturnsExpectedStructure(): void
    {
        $result = SmartMatchingEngine::warmUpCache(5);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('processed', $result);
        $this->assertArrayHasKey('cached', $result);
    }

    // =========================================================================
    // EDGE CASES
    // =========================================================================

    public function testFindMatchesForNonexistentUserReturnsEmpty(): void
    {
        $matches = SmartMatchingEngine::findMatchesForUser(999999999);

        $this->assertIsArray($matches);
        $this->assertEmpty($matches);
    }

    public function testCalculateMatchScoreWithMissingCoordinates(): void
    {
        $userData = ['latitude' => null, 'longitude' => null, 'skills' => ''];
        $userListings = [['type' => 'offer', 'category_id' => 1, 'title' => 'Test', 'description' => '']];
        $myListing = $userListings[0];

        $candidate = [
            'id' => 999, 'user_id' => 999, 'type' => 'request', 'category_id' => 1,
            'title' => 'Test', 'description' => '', 'latitude' => null,
            'longitude' => null, 'created_at' => date('Y-m-d H:i:s')
        ];

        $result = SmartMatchingEngine::calculateMatchScore($userData, $userListings, $myListing, $candidate);

        // Should still return a valid result, just with very low proximity score
        $this->assertIsArray($result);
        $this->assertArrayHasKey('score', $result);
    }

    public function testCalculateMatchScoreWithEmptySkills(): void
    {
        $userData = ['latitude' => 51.5074, 'longitude' => -0.1278, 'skills' => ''];
        $userListings = [['type' => 'offer', 'category_id' => 1, 'title' => '', 'description' => '']];
        $myListing = $userListings[0];

        $candidate = [
            'id' => 999, 'user_id' => 999, 'type' => 'request', 'category_id' => 1,
            'title' => '', 'description' => '', 'latitude' => 51.5080,
            'longitude' => -0.1280, 'created_at' => date('Y-m-d H:i:s')
        ];

        $result = SmartMatchingEngine::calculateMatchScore($userData, $userListings, $myListing, $candidate);

        // Should handle empty skills gracefully
        $this->assertIsArray($result);
        $this->assertEquals(0.5, $result['breakdown']['skill'], 'Empty skills should return neutral score');
    }
}
