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
use Nexus\Services\CollaborativeFilteringService;
use ReflectionMethod;

/**
 * CollaborativeFilteringService Tests
 *
 * Tests item-based collaborative filtering: tenant scoping, cosine similarity
 * algorithm correctness, cold-start fallbacks, and cross-tenant isolation.
 *
 * Fixture design:
 *   - tenantA (2): 3 users, 3 listings, seeded favorites + transactions
 *   - tenantB (1): 1 listing (used only to verify cross-tenant isolation)
 *
 * Similarity setup: userA1 and userA2 both saved listingA1 + listingA2 + listingA3,
 * giving every listing-pair at least MIN_COMMON_USERS=2 shared users, so item-based
 * recs will actually return results without falling back to cold-start.
 */
class CollaborativeFilteringServiceTest extends DatabaseTestCase
{
    protected static int $tenantA = 2;
    protected static int $tenantB = 1;
    protected static ?int $userA1 = null;
    protected static ?int $userA2 = null;
    protected static ?int $userA3 = null;
    protected static ?int $listingA1 = null;
    protected static ?int $listingA2 = null;
    protected static ?int $listingA3 = null;
    protected static ?int $listingB1 = null;
    /** @var int[] IDs inserted into listing_favorites for cleanup */
    protected static array $createdFavoriteIds = [];
    /** @var int[] IDs inserted into transactions for cleanup */
    protected static array $createdTransactionIds = [];

    // =========================================================================
    // Fixtures
    // =========================================================================

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        TenantContext::setById(self::$tenantA);
        self::createFixtures();
    }

    protected static function createFixtures(): void
    {
        $ts = time();

        // --- Users in tenantA ---
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 100, 1, ?, NOW())",
            [self::$tenantA, "cf_usera1_{$ts}@test.com", "cf_usera1_{$ts}", 'CF', 'UserA1', 'CF UserA1', 'active']
        );
        self::$userA1 = (int) Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 100, 1, ?, NOW())",
            [self::$tenantA, "cf_usera2_{$ts}@test.com", "cf_usera2_{$ts}", 'CF', 'UserA2', 'CF UserA2', 'active']
        );
        self::$userA2 = (int) Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 100, 1, ?, NOW())",
            [self::$tenantA, "cf_usera3_{$ts}@test.com", "cf_usera3_{$ts}", 'CF', 'UserA3', 'CF UserA3', 'active']
        );
        self::$userA3 = (int) Database::getInstance()->lastInsertId();

        // --- Listings in tenantA ---
        Database::query(
            "INSERT INTO listings (tenant_id, user_id, title, description, type, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [self::$tenantA, self::$userA1, 'CF Listing A1', 'test', 'offer', 'active']
        );
        self::$listingA1 = (int) Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO listings (tenant_id, user_id, title, description, type, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [self::$tenantA, self::$userA2, 'CF Listing A2', 'test', 'offer', 'active']
        );
        self::$listingA2 = (int) Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO listings (tenant_id, user_id, title, description, type, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [self::$tenantA, self::$userA3, 'CF Listing A3', 'test', 'offer', 'active']
        );
        self::$listingA3 = (int) Database::getInstance()->lastInsertId();

        // --- Listing in tenantB (for cross-tenant isolation tests) ---
        try {
            Database::query(
                "INSERT INTO listings (tenant_id, user_id, title, description, type, status, created_at)
                 VALUES (?, 1, ?, ?, ?, ?, NOW())",
                [self::$tenantB, 'CF Listing B1', 'test', 'offer', 'active']
            );
            self::$listingB1 = (int) Database::getInstance()->lastInsertId();
        } catch (\Throwable $e) {
            // tenantB listing optional -- isolation tests will skip if null
        }

        // --- Seed listing_favorites ---
        // userA1 + userA2 both saved all 3 listings => MIN_COMMON_USERS=2 satisfied for all pairs
        // userA3 saved only listingA1 (extra signal, does not affect pair eligibility)
        $favPairs = [
            [self::$userA1, self::$listingA1],
            [self::$userA1, self::$listingA2],
            [self::$userA1, self::$listingA3],
            [self::$userA2, self::$listingA1],
            [self::$userA2, self::$listingA2],
            [self::$userA2, self::$listingA3],
            [self::$userA3, self::$listingA1],
        ];
        foreach ($favPairs as [$uid, $lid]) {
            try {
                Database::query(
                    "INSERT INTO listing_favorites (user_id, listing_id, created_at) VALUES (?, ?, NOW())",
                    [$uid, $lid]
                );
                self::$createdFavoriteIds[] = (int) Database::getInstance()->lastInsertId();
            } catch (\Throwable $e) {
                // unique constraint -- ignore duplicates
            }
        }

        // --- Seed transactions for member interaction graph ---
        // A1<->A2 bidirectional (2 rows), A1->A3 (1 row)
        $txPairs = [
            [self::$userA1, self::$userA2],
            [self::$userA2, self::$userA1],
            [self::$userA1, self::$userA3],
        ];
        foreach ($txPairs as [$sender, $receiver]) {
            try {
                Database::query(
                    "INSERT INTO transactions (tenant_id, sender_id, receiver_id, amount, status, created_at)
                     VALUES (?, ?, ?, 1, ?, NOW())",
                    [self::$tenantA, $sender, $receiver, 'completed']
                );
                self::$createdTransactionIds[] = (int) Database::getInstance()->lastInsertId();
            } catch (\Throwable $e) {
                // transactions table structure varies -- non-fatal
            }
        }
    }

    public static function tearDownAfterClass(): void
    {
        foreach (self::$createdTransactionIds as $txId) {
            try {
                Database::query("DELETE FROM transactions WHERE id = ?", [$txId]);
            } catch (\Throwable $e) {}
        }

        foreach (self::$createdFavoriteIds as $favId) {
            try {
                Database::query("DELETE FROM listing_favorites WHERE id = ?", [$favId]);
            } catch (\Throwable $e) {}
        }

        $userIds = array_filter([self::$userA1, self::$userA2, self::$userA3]);
        foreach ($userIds as $uid) {
            try {
                Database::query("DELETE FROM listing_favorites WHERE user_id = ?", [$uid]);
            } catch (\Throwable $e) {}
        }

        $listingIds = array_filter([self::$listingA1, self::$listingA2, self::$listingA3, self::$listingB1]);
        foreach ($listingIds as $lid) {
            try {
                Database::query("DELETE FROM listings WHERE id = ?", [$lid]);
            } catch (\Throwable $e) {}
        }

        foreach ($userIds as $uid) {
            try {
                Database::query("DELETE FROM users WHERE id = ? AND tenant_id = ?", [$uid, self::$tenantA]);
            } catch (\Throwable $e) {}
        }

        parent::tearDownAfterClass();
    }

    // =========================================================================
    // getSimilarListings Tests
    // =========================================================================

    public function testGetSimilarListingsReturnsArray(): void
    {
        if (!self::$listingA1) { $this->markTestSkipped('Fixtures not created'); }
        $result = CollaborativeFilteringService::getSimilarListings(self::$listingA1, self::$tenantA, 5);
        $this->assertIsArray($result);
    }

    public function testGetSimilarListingsExcludesSourceListing(): void
    {
        if (!self::$listingA1) { $this->markTestSkipped('Fixtures not created'); }
        $result = CollaborativeFilteringService::getSimilarListings(self::$listingA1, self::$tenantA, 5);
        $this->assertNotContains(self::$listingA1, $result, 'Source listing must not appear in similar listings');
    }

    public function testGetSimilarListingsRespectsLimit(): void
    {
        if (!self::$listingA1) { $this->markTestSkipped('Fixtures not created'); }
        $result = CollaborativeFilteringService::getSimilarListings(self::$listingA1, self::$tenantA, 1);
        $this->assertLessThanOrEqual(1, count($result));
    }

    public function testGetSimilarListingsReturnsIntegerIds(): void
    {
        if (!self::$listingA1) { $this->markTestSkipped('Fixtures not created'); }
        $result = CollaborativeFilteringService::getSimilarListings(self::$listingA1, self::$tenantA, 5);
        foreach ($result as $id) {
            $this->assertIsInt($id, 'All returned IDs must be integers');
        }
    }

    public function testGetSimilarListingsReturnsArrayForUnknownListing(): void
    {
        // Unknown listing has no interactions: cold-start or empty -- either is valid
        $result = CollaborativeFilteringService::getSimilarListings(999999999, self::$tenantA, 5);
        $this->assertIsArray($result);
    }

    public function testGetSimilarListingsDoesNotReturnTenantBListings(): void
    {
        if (!self::$listingA1 || !self::$listingB1) { $this->markTestSkipped('Fixtures not created for both tenants'); }
        $result = CollaborativeFilteringService::getSimilarListings(self::$listingA1, self::$tenantA, 20);
        $this->assertNotContains(
            self::$listingB1,
            $result,
            'Cross-tenant listings must never appear in recommendations'
        );
    }

    // =========================================================================
    // getSuggestedMembers Tests
    // =========================================================================

    public function testGetSuggestedMembersReturnsArray(): void
    {
        if (!self::$userA1) { $this->markTestSkipped('Fixtures not created'); }
        $result = CollaborativeFilteringService::getSuggestedMembers(self::$userA1, self::$tenantA, 5);
        $this->assertIsArray($result);
    }

    public function testGetSuggestedMembersExcludesSourceUser(): void
    {
        if (!self::$userA1) { $this->markTestSkipped('Fixtures not created'); }
        $result = CollaborativeFilteringService::getSuggestedMembers(self::$userA1, self::$tenantA, 5);
        $this->assertNotContains(self::$userA1, $result, 'Source user must not appear in their own suggestions');
    }

    public function testGetSuggestedMembersRespectsLimit(): void
    {
        if (!self::$userA1) { $this->markTestSkipped('Fixtures not created'); }
        $result = CollaborativeFilteringService::getSuggestedMembers(self::$userA1, self::$tenantA, 1);
        $this->assertLessThanOrEqual(1, count($result));
    }

    public function testGetSuggestedMembersReturnsIntegerIds(): void
    {
        if (!self::$userA1) { $this->markTestSkipped('Fixtures not created'); }
        $result = CollaborativeFilteringService::getSuggestedMembers(self::$userA1, self::$tenantA, 5);
        foreach ($result as $id) {
            $this->assertIsInt($id, 'All returned IDs must be integers');
        }
    }

    public function testGetSuggestedMembersFallsBackForUnknownUser(): void
    {
        $result = CollaborativeFilteringService::getSuggestedMembers(999999999, self::$tenantA, 5);
        $this->assertIsArray($result);
    }

    public function testGetSuggestedMembersDoesNotReturnTenantBUsers(): void
    {
        if (!self::$userA1) { $this->markTestSkipped('Fixtures not created'); }
        try {
            $stmt = Database::query(
                "SELECT id FROM users WHERE tenant_id = ? AND status = ? LIMIT 1",
                [self::$tenantB, 'active']
            );
            $tenantBUser = $stmt->fetchColumn();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Could not query tenantB users');
            return;
        }
        if (!$tenantBUser) { $this->markTestSkipped('No active users in tenantB'); }
        $result = CollaborativeFilteringService::getSuggestedMembers(self::$userA1, self::$tenantA, 50);
        $this->assertNotContains(
            (int) $tenantBUser,
            $result,
            'Cross-tenant users must never appear in member suggestions'
        );
    }

    // =========================================================================
    // getSuggestedListingsForUser Tests
    // =========================================================================

    public function testGetSuggestedListingsForUserReturnsArray(): void
    {
        if (!self::$userA1) { $this->markTestSkipped('Fixtures not created'); }
        $result = CollaborativeFilteringService::getSuggestedListingsForUser(self::$userA1, self::$tenantA, 10);
        $this->assertIsArray($result);
    }

    public function testGetSuggestedListingsForUserRespectsLimit(): void
    {
        if (!self::$userA1) { $this->markTestSkipped('Fixtures not created'); }
        $result = CollaborativeFilteringService::getSuggestedListingsForUser(self::$userA1, self::$tenantA, 2);
        $this->assertLessThanOrEqual(2, count($result));
    }

    public function testGetSuggestedListingsForUserExcludesAlreadySaved(): void
    {
        if (!self::$userA1 || !self::$listingA1) { $this->markTestSkipped('Fixtures not created'); }
        // userA1 has saved listingA1, listingA2, listingA3 in fixtures
        $result = CollaborativeFilteringService::getSuggestedListingsForUser(self::$userA1, self::$tenantA, 20);
        $this->assertNotContains(self::$listingA1, $result, 'Already-saved listing must be excluded');
        $this->assertNotContains(self::$listingA2, $result, 'Already-saved listing must be excluded');
        $this->assertNotContains(self::$listingA3, $result, 'Already-saved listing must be excluded');
    }

    public function testGetSuggestedListingsForUserFallsBackForUnknownUser(): void
    {
        $result = CollaborativeFilteringService::getSuggestedListingsForUser(999999999, self::$tenantA, 10);
        $this->assertIsArray($result);
    }

    // =========================================================================
    // cosineSimilarity Tests (via Reflection -- private static method)
    // =========================================================================

    private function callCosineSimilarity(array $a, array $b): float
    {
        $method = new ReflectionMethod(CollaborativeFilteringService::class, 'cosineSimilarity');
        $method->setAccessible(true);
        return (float) $method->invoke(null, $a, $b);
    }

    public function testCosineSimilarityIdenticalVectorsReturnOne(): void
    {
        $vec = [1 => 1.0, 2 => 2.0, 3 => 1.5];
        $sim = $this->callCosineSimilarity($vec, $vec);
        $this->assertEqualsWithDelta(1.0, $sim, 1e-9, 'Identical vectors must have cosine similarity = 1.0');
    }

    public function testCosineSimilarityOrthogonalVectorsReturnZero(): void
    {
        $a = [1 => 1.0, 2 => 0.0];
        $b = [1 => 0.0, 2 => 1.0];
        $sim = $this->callCosineSimilarity($a, $b);
        $this->assertEqualsWithDelta(0.0, $sim, 1e-9, 'Orthogonal vectors must have cosine similarity = 0.0');
    }

    public function testCosineSimilarityEmptyVectorReturnsZero(): void
    {
        $sim = $this->callCosineSimilarity([], [1 => 1.0]);
        $this->assertEqualsWithDelta(0.0, $sim, 1e-9, 'Empty vector must return 0.0 to avoid division by zero');
    }

    public function testCosineSimilarityIsBoundedBetweenZeroAndOne(): void
    {
        $a = [1 => 3.0, 2 => 1.0, 5 => 2.0];
        $b = [1 => 1.0, 3 => 4.0, 5 => 1.0];
        $sim = $this->callCosineSimilarity($a, $b);
        $this->assertGreaterThanOrEqual(0.0, $sim);
        $this->assertLessThanOrEqual(1.0, $sim);
    }

    public function testCosineSimilarityIsSymmetric(): void
    {
        $a = [1 => 2.0, 2 => 1.0];
        $b = [1 => 1.0, 2 => 3.0];
        $simAB = $this->callCosineSimilarity($a, $b);
        $simBA = $this->callCosineSimilarity($b, $a);
        $this->assertEqualsWithDelta($simAB, $simBA, 1e-9, 'Cosine similarity must be symmetric: sim(A,B) == sim(B,A)');
    }

    // =========================================================================
    // itemBasedRecommendations Tests (via Reflection -- private static method)
    // =========================================================================

    private function callItemBasedRecommendations(int $sourceItemId, array $interactions, int $limit): array
    {
        $method = new ReflectionMethod(CollaborativeFilteringService::class, 'itemBasedRecommendations');
        $method->setAccessible(true);
        return (array) $method->invoke(null, $sourceItemId, $interactions, $limit);
    }

    public function testItemBasedRecommendationsReturnsSimilarItems(): void
    {
        // Minimal matrix: users 1 and 2 both interacted with items 10 and 20
        $interactions = [
            1 => [10 => 1.0, 20 => 1.0],
            2 => [10 => 1.0, 20 => 1.0],
        ];
        $result = $this->callItemBasedRecommendations(10, $interactions, 5);
        $this->assertIsArray($result);
        $this->assertContains(20, $result, 'Item 20 should be recommended as similar to item 10');
    }

    public function testItemBasedRecommendationsReturnsEmptyForUnknownSource(): void
    {
        $interactions = [1 => [10 => 1.0, 20 => 1.0]];
        $result = $this->callItemBasedRecommendations(999, $interactions, 5);
        $this->assertEmpty($result, 'Unknown source item with no interactions must return empty array');
    }

    public function testItemBasedRecommendationsRespectsLimit(): void
    {
        $interactions = [
            1 => [10 => 1.0, 20 => 1.0, 30 => 1.0],
            2 => [10 => 1.0, 20 => 1.0, 30 => 1.0],
        ];
        $result = $this->callItemBasedRecommendations(10, $interactions, 1);
        $this->assertLessThanOrEqual(1, count($result));
    }

    public function testItemBasedRecommendationsRequiresMinCommonUsers(): void
    {
        // Only one user shared items 10 and 20 -- below MIN_COMMON_USERS=2
        $interactions = [1 => [10 => 1.0, 20 => 1.0]];
        $result = $this->callItemBasedRecommendations(10, $interactions, 5);
        $this->assertEmpty($result, 'Must not recommend items with fewer than MIN_COMMON_USERS shared interactions');
    }

    // =========================================================================
    // Cold-start Fallback Tenant Scoping Tests
    // =========================================================================

    public function testPopularListingsFallbackScopedToTenantA(): void
    {
        if (!self::$listingB1) { $this->markTestSkipped('tenantB listing not created -- skipping isolation check'); }
        // Force cold-start via non-existent listing ID
        $result = CollaborativeFilteringService::getSimilarListings(999999998, self::$tenantA, 50);
        $this->assertNotContains(
            self::$listingB1,
            $result,
            'Cold-start fallback must never return listings from a different tenant'
        );
    }

    public function testPopularMembersFallbackScopedToEmptyTenant(): void
    {
        // Empty scratch tenant has no users => result must be empty array
        $result = CollaborativeFilteringService::getSuggestedMembers(999999998, 99999, 50);
        $this->assertIsArray($result);
        $this->assertEmpty($result, 'Cold-start fallback for an empty tenant must return an empty array');
    }
}
