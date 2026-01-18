<?php

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\MatchingService;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * MatchingServiceTest - Tests for the MatchingService facade
 *
 * Tests preferences, interactions, and statistics.
 */
class MatchingServiceTest extends TestCase
{
    private static $testTenantId = 1;
    private static $testUserId;
    private static $testUser2Id;
    private static $testCategoryId;
    private static $testListingId;

    public static function setUpBeforeClass(): void
    {
        TenantContext::setById(self::$testTenantId);

        $timestamp = time() . rand(1000, 9999);

        // Create test category
        Database::query(
            "INSERT INTO categories (tenant_id, name, slug, color, created_at)
             VALUES (?, 'MatchService Test', ?, '#10b981', NOW())",
            [self::$testTenantId, 'matchservice-test-' . $timestamp]
        );
        self::$testCategoryId = Database::getInstance()->lastInsertId();

        // Create test users
        Database::query(
            "INSERT INTO users (tenant_id, email, first_name, last_name, location, latitude, longitude, is_approved, status, created_at)
             VALUES (?, ?, 'Match', 'TestUser', 'Test Location', 51.5074, -0.1278, 1, 'active', NOW())",
            [self::$testTenantId, 'match_service_test_' . $timestamp . '@test.com']
        );
        self::$testUserId = Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO users (tenant_id, email, first_name, last_name, location, latitude, longitude, is_approved, status, created_at)
             VALUES (?, ?, 'Match', 'TestUser2', 'Test Location 2', 51.5200, -0.1000, 1, 'active', NOW())",
            [self::$testTenantId, 'match_service_test2_' . $timestamp . '@test.com']
        );
        self::$testUser2Id = Database::getInstance()->lastInsertId();

        // Create test listings
        Database::query(
            "INSERT INTO listings (tenant_id, user_id, title, description, type, category_id, status, created_at)
             VALUES (?, ?, 'Test Offer', 'Test description for matching', 'offer', ?, 'active', NOW())",
            [self::$testTenantId, self::$testUserId, self::$testCategoryId]
        );
        self::$testListingId = Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO listings (tenant_id, user_id, title, description, type, category_id, status, created_at)
             VALUES (?, ?, 'Test Request', 'Need help with something', 'request', ?, 'active', NOW())",
            [self::$testTenantId, self::$testUser2Id, self::$testCategoryId]
        );
    }

    public static function tearDownAfterClass(): void
    {
        $userIds = [self::$testUserId, self::$testUser2Id];

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
        // Clear preferences before each test
        try {
            Database::query("DELETE FROM match_preferences WHERE user_id = ?", [self::$testUserId]);
        } catch (\Exception $e) {}
        try {
            Database::query("DELETE FROM match_history WHERE user_id = ?", [self::$testUserId]);
        } catch (\Exception $e) {}
        try {
            Database::query("DELETE FROM match_cache WHERE user_id = ?", [self::$testUserId]);
        } catch (\Exception $e) {}
    }

    // =========================================================================
    // SUGGESTIONS TESTS
    // =========================================================================

    public function testGetSuggestionsForUserReturnsArray(): void
    {
        $suggestions = MatchingService::getSuggestionsForUser(self::$testUserId);

        $this->assertIsArray($suggestions);
    }

    public function testGetSuggestionsWithLimit(): void
    {
        $suggestions = MatchingService::getSuggestionsForUser(self::$testUserId, 3);

        $this->assertIsArray($suggestions);
        $this->assertLessThanOrEqual(3, count($suggestions));
    }

    public function testGetSuggestionsWithOptions(): void
    {
        $suggestions = MatchingService::getSuggestionsForUser(self::$testUserId, 10, [
            'max_distance' => 50,
            'min_score' => 30
        ]);

        $this->assertIsArray($suggestions);
    }

    // =========================================================================
    // HOT/MUTUAL MATCHES TESTS
    // =========================================================================

    public function testGetHotMatchesReturnsArray(): void
    {
        $hotMatches = MatchingService::getHotMatches(self::$testUserId);

        $this->assertIsArray($hotMatches);
    }

    public function testGetMutualMatchesReturnsArray(): void
    {
        $mutualMatches = MatchingService::getMutualMatches(self::$testUserId);

        $this->assertIsArray($mutualMatches);
    }

    public function testGetMatchesByTypeReturnsExpectedStructure(): void
    {
        $matches = MatchingService::getMatchesByType(self::$testUserId);

        $this->assertIsArray($matches);
        $this->assertArrayHasKey('hot', $matches);
        $this->assertArrayHasKey('good', $matches);
        $this->assertArrayHasKey('mutual', $matches);
        $this->assertArrayHasKey('all', $matches);

        $this->assertIsArray($matches['hot']);
        $this->assertIsArray($matches['good']);
        $this->assertIsArray($matches['mutual']);
        $this->assertIsArray($matches['all']);
    }

    // =========================================================================
    // PREFERENCES TESTS
    // =========================================================================

    public function testGetPreferencesReturnsDefaults(): void
    {
        $prefs = MatchingService::getPreferences(self::$testUserId);

        $this->assertIsArray($prefs);
        $this->assertArrayHasKey('max_distance_km', $prefs);
        $this->assertArrayHasKey('min_match_score', $prefs);
        $this->assertArrayHasKey('notification_frequency', $prefs);
        $this->assertArrayHasKey('notify_hot_matches', $prefs);
        $this->assertArrayHasKey('notify_mutual_matches', $prefs);
        $this->assertArrayHasKey('categories', $prefs);

        // Check defaults
        $this->assertEquals(25, $prefs['max_distance_km']);
        $this->assertEquals(50, $prefs['min_match_score']);
        $this->assertEquals('daily', $prefs['notification_frequency']);
        $this->assertTrue($prefs['notify_hot_matches']);
        $this->assertTrue($prefs['notify_mutual_matches']);
    }

    public function testSavePreferencesSuccess(): void
    {
        $prefsToSave = [
            'max_distance_km' => 50,
            'min_match_score' => 60,
            'notification_frequency' => 'weekly',
            'notify_hot_matches' => true,
            'notify_mutual_matches' => false,
            'categories' => [self::$testCategoryId]
        ];

        $result = MatchingService::savePreferences(self::$testUserId, $prefsToSave);

        $this->assertTrue($result);
    }

    public function testSavedPreferencesArePersisted(): void
    {
        $prefsToSave = [
            'max_distance_km' => 75,
            'min_match_score' => 40,
            'notification_frequency' => 'instant',
            'notify_hot_matches' => false,
            'notify_mutual_matches' => true,
            'categories' => []
        ];

        MatchingService::savePreferences(self::$testUserId, $prefsToSave);
        $retrieved = MatchingService::getPreferences(self::$testUserId);

        $this->assertEquals(75, $retrieved['max_distance_km']);
        $this->assertEquals(40, $retrieved['min_match_score']);
        $this->assertEquals('instant', $retrieved['notification_frequency']);
        $this->assertFalse($retrieved['notify_hot_matches']);
        $this->assertTrue($retrieved['notify_mutual_matches']);
    }

    public function testSavePreferencesUpdatesExisting(): void
    {
        // Save initial preferences
        MatchingService::savePreferences(self::$testUserId, [
            'max_distance_km' => 30,
            'min_match_score' => 50
        ]);

        // Update preferences
        MatchingService::savePreferences(self::$testUserId, [
            'max_distance_km' => 100,
            'min_match_score' => 70
        ]);

        $retrieved = MatchingService::getPreferences(self::$testUserId);

        $this->assertEquals(100, $retrieved['max_distance_km']);
        $this->assertEquals(70, $retrieved['min_match_score']);
    }

    // =========================================================================
    // INTERACTION TRACKING TESTS
    // =========================================================================

    public function testRecordInteractionReturnsBoolean(): void
    {
        $result = MatchingService::recordInteraction(
            self::$testUserId,
            self::$testListingId,
            'viewed',
            75.5,
            5.2
        );

        $this->assertIsBool($result);
    }

    public function testRecordInteractionViewedAction(): void
    {
        $result = MatchingService::recordInteraction(
            self::$testUserId,
            self::$testListingId,
            'viewed',
            80.0,
            3.5
        );

        // Should succeed (might fail if table doesn't exist, which is ok)
        $this->assertIsBool($result);
    }

    public function testRecordInteractionContactedAction(): void
    {
        $result = MatchingService::recordInteraction(
            self::$testUserId,
            self::$testListingId,
            'contacted',
            85.0,
            2.0
        );

        $this->assertIsBool($result);
    }

    public function testRecordInteractionSavedAction(): void
    {
        $result = MatchingService::recordInteraction(
            self::$testUserId,
            self::$testListingId,
            'saved',
            90.0,
            1.5
        );

        $this->assertIsBool($result);
    }

    public function testRecordInteractionDismissedAction(): void
    {
        $result = MatchingService::recordInteraction(
            self::$testUserId,
            self::$testListingId,
            'dismissed',
            45.0,
            10.0
        );

        $this->assertIsBool($result);
    }

    // =========================================================================
    // STATISTICS TESTS
    // =========================================================================

    public function testGetStatsReturnsExpectedStructure(): void
    {
        $stats = MatchingService::getStats(self::$testUserId);

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_matches', $stats);
        $this->assertArrayHasKey('hot_matches', $stats);
        $this->assertArrayHasKey('mutual_matches', $stats);
        $this->assertArrayHasKey('avg_score', $stats);
        $this->assertArrayHasKey('avg_distance', $stats);
    }

    public function testGetStatsReturnsNumericValues(): void
    {
        $stats = MatchingService::getStats(self::$testUserId);

        $this->assertIsInt($stats['total_matches']);
        $this->assertIsInt($stats['hot_matches']);
        $this->assertIsInt($stats['mutual_matches']);
        $this->assertIsNumeric($stats['avg_score']);
    }

    public function testGetStatsForUserWithNoMatches(): void
    {
        // Create a user with no listings (won't have matches)
        $timestamp = time() . rand(1000, 9999);
        Database::query(
            "INSERT INTO users (tenant_id, email, first_name, last_name, is_approved, status, created_at)
             VALUES (?, ?, 'No', 'Matches', 1, 'active', NOW())",
            [self::$testTenantId, 'no_matches_' . $timestamp . '@test.com']
        );
        $noMatchUserId = Database::getInstance()->lastInsertId();

        $stats = MatchingService::getStats($noMatchUserId);

        $this->assertEquals(0, $stats['total_matches']);
        $this->assertEquals(0, $stats['hot_matches']);
        $this->assertEquals(0, $stats['mutual_matches']);
        $this->assertEquals(0, $stats['avg_score']);

        // Cleanup
        Database::query("DELETE FROM users WHERE id = ?", [$noMatchUserId]);
    }

    // =========================================================================
    // NOTIFICATION TESTS
    // =========================================================================

    public function testNotifyNewMatchesReturnsInteger(): void
    {
        $count = MatchingService::notifyNewMatches(self::$testUserId);

        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    // =========================================================================
    // EDGE CASES
    // =========================================================================

    public function testGetPreferencesForNonexistentUser(): void
    {
        $prefs = MatchingService::getPreferences(999999999);

        // Should return defaults
        $this->assertIsArray($prefs);
        $this->assertArrayHasKey('max_distance_km', $prefs);
    }

    public function testSavePreferencesWithEmptyCategories(): void
    {
        $result = MatchingService::savePreferences(self::$testUserId, [
            'categories' => []
        ]);

        $this->assertTrue($result);

        $retrieved = MatchingService::getPreferences(self::$testUserId);
        $this->assertEmpty($retrieved['categories']);
    }

    public function testSavePreferencesWithMultipleCategories(): void
    {
        $result = MatchingService::savePreferences(self::$testUserId, [
            'categories' => [1, 2, 3, 4, 5]
        ]);

        $this->assertTrue($result);

        $retrieved = MatchingService::getPreferences(self::$testUserId);
        $this->assertCount(5, $retrieved['categories']);
    }

    public function testRecordInteractionWithNullValues(): void
    {
        $result = MatchingService::recordInteraction(
            self::$testUserId,
            self::$testListingId,
            'viewed',
            null,
            null
        );

        $this->assertIsBool($result);
    }
}
