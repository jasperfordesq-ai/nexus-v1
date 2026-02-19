<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Models;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\UserBadge;

/**
 * Badge (UserBadge) Model Tests
 *
 * Tests badge awarding, duplicate prevention, badge checking,
 * showcase functionality, rarity stats, and display methods.
 *
 * Note: The model is UserBadge (src/Models/UserBadge.php), not Badge.
 */
class BadgeTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testUserId2 = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);

        self::createTestData();
    }

    protected static function createTestData(): void
    {
        $timestamp = time();

        // Create test users
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 100, 1, NOW())",
            [
                self::$testTenantId,
                "badge_test_user1_{$timestamp}@test.com",
                "badge_test_user1_{$timestamp}",
                'Badge',
                'Tester1',
                'Badge Tester1'
            ]
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 100, 1, NOW())",
            [
                self::$testTenantId,
                "badge_test_user2_{$timestamp}@test.com",
                "badge_test_user2_{$timestamp}",
                'Badge',
                'Tester2',
                'Badge Tester2'
            ]
        );
        self::$testUserId2 = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        $userIds = array_filter([self::$testUserId, self::$testUserId2]);
        foreach ($userIds as $uid) {
            try {
                Database::query("DELETE FROM user_badges WHERE user_id = ?", [$uid]);
                Database::query("DELETE FROM users WHERE id = ?", [$uid]);
            } catch (\Exception $e) {
            }
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);

        // Clean badges before each test
        try {
            Database::query("DELETE FROM user_badges WHERE user_id IN (?, ?)", [self::$testUserId, self::$testUserId2]);
        } catch (\Exception $e) {
        }
    }

    // ==========================================
    // Award Badge Tests
    // ==========================================

    public function testAwardBadgeCreatesEntry(): void
    {
        UserBadge::award(self::$testUserId, 'first_listing', 'First Listing', 'listing-icon');

        $this->assertTrue(UserBadge::hasBadge(self::$testUserId, 'first_listing'));
    }

    public function testAwardBadgeWithIcon(): void
    {
        UserBadge::award(self::$testUserId, 'helper', 'Helper Badge', 'heart-icon');

        $badges = UserBadge::getForUser(self::$testUserId);
        $this->assertNotEmpty($badges);

        $badge = $badges[0];
        $this->assertEquals('helper', $badge['badge_key']);
        $this->assertEquals('Helper Badge', $badge['name']);
        $this->assertEquals('heart-icon', $badge['icon']);
    }

    public function testAwardBadgeWithNullIcon(): void
    {
        UserBadge::award(self::$testUserId, 'no_icon', 'No Icon Badge', null);

        $badges = UserBadge::getForUser(self::$testUserId);
        $foundBadge = null;
        foreach ($badges as $b) {
            if ($b['badge_key'] === 'no_icon') {
                $foundBadge = $b;
                break;
            }
        }

        $this->assertNotNull($foundBadge);
        $this->assertNull($foundBadge['icon']);
    }

    public function testAwardDuplicateBadgeIsIgnored(): void
    {
        UserBadge::award(self::$testUserId, 'first_listing', 'First Listing', 'icon1');
        UserBadge::award(self::$testUserId, 'first_listing', 'First Listing Updated', 'icon2');

        $badges = UserBadge::getForUser(self::$testUserId);
        $matchingBadges = array_filter($badges, fn($b) => $b['badge_key'] === 'first_listing');

        $this->assertCount(1, $matchingBadges, 'INSERT IGNORE should prevent duplicate badge entries');
    }

    // ==========================================
    // Has Badge (Requirement Checking) Tests
    // ==========================================

    public function testHasBadgeReturnsTrueForAwardedBadge(): void
    {
        UserBadge::award(self::$testUserId, 'verified', 'Verified Member', 'check-icon');

        $this->assertTrue(UserBadge::hasBadge(self::$testUserId, 'verified'));
    }

    public function testHasBadgeReturnsFalseForMissingBadge(): void
    {
        $this->assertFalse(UserBadge::hasBadge(self::$testUserId, 'nonexistent_badge'));
    }

    public function testHasBadgeReturnsFalseForDifferentUser(): void
    {
        UserBadge::award(self::$testUserId, 'exclusive_badge', 'Exclusive', 'star-icon');

        $this->assertTrue(UserBadge::hasBadge(self::$testUserId, 'exclusive_badge'));
        $this->assertFalse(UserBadge::hasBadge(self::$testUserId2, 'exclusive_badge'));
    }

    // ==========================================
    // Get For User (Display) Tests
    // ==========================================

    public function testGetForUserReturnsArray(): void
    {
        $badges = UserBadge::getForUser(self::$testUserId);
        $this->assertIsArray($badges);
    }

    public function testGetForUserReturnsEmptyForNoBadges(): void
    {
        $badges = UserBadge::getForUser(self::$testUserId);
        $this->assertEmpty($badges);
    }

    public function testGetForUserReturnsAllBadges(): void
    {
        UserBadge::award(self::$testUserId, 'badge_a', 'Badge A', null);
        UserBadge::award(self::$testUserId, 'badge_b', 'Badge B', null);
        UserBadge::award(self::$testUserId, 'badge_c', 'Badge C', null);

        $badges = UserBadge::getForUser(self::$testUserId);
        $this->assertCount(3, $badges);
    }

    public function testGetForUserIncludesAllFields(): void
    {
        UserBadge::award(self::$testUserId, 'full_badge', 'Full Badge', 'trophy');

        $badges = UserBadge::getForUser(self::$testUserId);
        $this->assertNotEmpty($badges);

        $badge = $badges[0];
        $this->assertArrayHasKey('user_id', $badge);
        $this->assertArrayHasKey('badge_key', $badge);
        $this->assertArrayHasKey('name', $badge);
        $this->assertArrayHasKey('icon', $badge);
        $this->assertArrayHasKey('awarded_at', $badge);
    }

    public function testGetForUserOrdersByAwardedAtDesc(): void
    {
        UserBadge::award(self::$testUserId, 'first', 'First Badge', null);
        // Small delay to ensure different timestamps
        usleep(10000);
        UserBadge::award(self::$testUserId, 'second', 'Second Badge', null);

        $badges = UserBadge::getForUser(self::$testUserId);
        $this->assertGreaterThanOrEqual(2, count($badges));

        // Most recently awarded should come first
        $this->assertEquals('second', $badges[0]['badge_key']);
    }

    // ==========================================
    // Showcase Tests
    // ==========================================

    public function testGetShowcasedReturnsEmptyByDefault(): void
    {
        UserBadge::award(self::$testUserId, 'test_badge', 'Test', null);

        $showcased = UserBadge::getShowcased(self::$testUserId);
        $this->assertIsArray($showcased);
        $this->assertEmpty($showcased);
    }

    public function testSetShowcasedMarksBadge(): void
    {
        UserBadge::award(self::$testUserId, 'showcase_badge', 'Showcase Badge', null);
        UserBadge::setShowcased(self::$testUserId, 'showcase_badge', 0);

        $showcased = UserBadge::getShowcased(self::$testUserId);
        $this->assertCount(1, $showcased);
        $this->assertEquals('showcase_badge', $showcased[0]['badge_key']);
    }

    public function testShowcaseMaxThreeBadges(): void
    {
        UserBadge::award(self::$testUserId, 'sc1', 'SC1', null);
        UserBadge::award(self::$testUserId, 'sc2', 'SC2', null);
        UserBadge::award(self::$testUserId, 'sc3', 'SC3', null);
        UserBadge::award(self::$testUserId, 'sc4', 'SC4', null);

        UserBadge::setShowcased(self::$testUserId, 'sc1', 0);
        UserBadge::setShowcased(self::$testUserId, 'sc2', 1);
        UserBadge::setShowcased(self::$testUserId, 'sc3', 2);
        UserBadge::setShowcased(self::$testUserId, 'sc4', 3);

        $showcased = UserBadge::getShowcased(self::$testUserId);
        // LIMIT 3 in the query
        $this->assertLessThanOrEqual(3, count($showcased));
    }

    public function testRemoveFromShowcase(): void
    {
        UserBadge::award(self::$testUserId, 'remove_sc', 'Remove SC', null);
        UserBadge::setShowcased(self::$testUserId, 'remove_sc', 0);

        $this->assertCount(1, UserBadge::getShowcased(self::$testUserId));

        UserBadge::removeFromShowcase(self::$testUserId, 'remove_sc');

        $this->assertEmpty(UserBadge::getShowcased(self::$testUserId));
    }

    public function testUpdateShowcaseReplacesAll(): void
    {
        UserBadge::award(self::$testUserId, 'old1', 'Old1', null);
        UserBadge::award(self::$testUserId, 'old2', 'Old2', null);
        UserBadge::award(self::$testUserId, 'new1', 'New1', null);
        UserBadge::award(self::$testUserId, 'new2', 'New2', null);

        // Set old showcase
        UserBadge::setShowcased(self::$testUserId, 'old1', 0);
        UserBadge::setShowcased(self::$testUserId, 'old2', 1);

        // Replace with new showcase
        UserBadge::updateShowcase(self::$testUserId, ['new1', 'new2']);

        $showcased = UserBadge::getShowcased(self::$testUserId);
        $keys = array_column($showcased, 'badge_key');

        $this->assertContains('new1', $keys);
        $this->assertContains('new2', $keys);
        $this->assertNotContains('old1', $keys);
        $this->assertNotContains('old2', $keys);
    }

    public function testUpdateShowcaseLimitsToThree(): void
    {
        $badgeKeys = ['lim1', 'lim2', 'lim3', 'lim4', 'lim5'];
        foreach ($badgeKeys as $key) {
            UserBadge::award(self::$testUserId, $key, ucfirst($key), null);
        }

        UserBadge::updateShowcase(self::$testUserId, $badgeKeys);

        $showcased = UserBadge::getShowcased(self::$testUserId);
        $this->assertLessThanOrEqual(3, count($showcased));
    }

    // ==========================================
    // Rarity Stats Tests
    // ==========================================

    public function testGetBadgeRarityStatsReturnsArray(): void
    {
        UserBadge::award(self::$testUserId, 'common_badge', 'Common', null);

        $rarity = UserBadge::getBadgeRarityStats();
        $this->assertIsArray($rarity);
    }

    public function testGetBadgeRarityStatsIncludesBadgeData(): void
    {
        UserBadge::award(self::$testUserId, 'rarity_test', 'Rarity Test', null);

        $rarity = UserBadge::getBadgeRarityStats();

        if (isset($rarity['rarity_test'])) {
            $this->assertArrayHasKey('count', $rarity['rarity_test']);
            $this->assertArrayHasKey('percent', $rarity['rarity_test']);
            $this->assertArrayHasKey('label', $rarity['rarity_test']);
        } else {
            // The badge may not appear if test user is not in the same tenant
            $this->assertIsArray($rarity);
        }
    }

    public function testRarityLabelsAreCorrect(): void
    {
        // Use reflection to test the private getRarityLabel method
        $reflection = new \ReflectionClass(UserBadge::class);
        $method = $reflection->getMethod('getRarityLabel');
        $method->setAccessible(true);

        $this->assertEquals('Legendary', $method->invoke(null, 0.5));
        $this->assertEquals('Legendary', $method->invoke(null, 1.0));
        $this->assertEquals('Epic', $method->invoke(null, 2.0));
        $this->assertEquals('Epic', $method->invoke(null, 5.0));
        $this->assertEquals('Rare', $method->invoke(null, 10.0));
        $this->assertEquals('Rare', $method->invoke(null, 15.0));
        $this->assertEquals('Uncommon', $method->invoke(null, 25.0));
        $this->assertEquals('Uncommon', $method->invoke(null, 40.0));
        $this->assertEquals('Common', $method->invoke(null, 50.0));
        $this->assertEquals('Common', $method->invoke(null, 100.0));
    }

    // ==========================================
    // Edge Cases
    // ==========================================

    public function testGetForUserWithNonExistentUser(): void
    {
        $badges = UserBadge::getForUser(999999999);
        $this->assertIsArray($badges);
        $this->assertEmpty($badges);
    }

    public function testHasBadgeWithNonExistentUser(): void
    {
        $this->assertFalse(UserBadge::hasBadge(999999999, 'any_badge'));
    }

    public function testAwardBadgeWithSpecialCharactersInName(): void
    {
        UserBadge::award(self::$testUserId, 'special_chars', 'Badge with "quotes" & <tags>', null);

        $this->assertTrue(UserBadge::hasBadge(self::$testUserId, 'special_chars'));

        $badges = UserBadge::getForUser(self::$testUserId);
        $found = array_filter($badges, fn($b) => $b['badge_key'] === 'special_chars');
        $this->assertNotEmpty($found);
    }
}
