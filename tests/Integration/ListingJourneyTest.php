<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Integration;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\ListingService;

/**
 * Listing Journey Integration Test
 *
 * Tests complete marketplace workflows:
 * - Create listing → browse → search → view detail
 * - Edit own listing → update status → delete
 * - Category filtering → type filtering (offer/request)
 */
class ListingJourneyTest extends DatabaseTestCase
{
    private static int $testTenantId = 2;
    private int $testUserId;
    private array $createdListingIds = [];
    private int $testCategoryId;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);

        // Create test user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, password_hash, is_approved, created_at, balance)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), 100)",
            [
                self::$testTenantId,
                'listing_test_' . time() . '@example.com',
                'listing_user_' . time(),
                'Listing',
                'Tester',
                'Listing Tester',
                password_hash('password', PASSWORD_DEFAULT)
            ]
        );
        $this->testUserId = (int)Database::lastInsertId();

        // Get or create test category
        $stmt = Database::query(
            "SELECT id FROM categories WHERE tenant_id = ? AND type = 'listing' LIMIT 1",
            [self::$testTenantId]
        );
        $category = $stmt->fetch();

        if (!$category) {
            Database::query(
                "INSERT INTO categories (tenant_id, name, slug, type, created_at) VALUES (?, 'Test Category', 'test-category', 'listing', NOW())",
                [self::$testTenantId]
            );
            $this->testCategoryId = (int)Database::lastInsertId();
        } else {
            $this->testCategoryId = (int)$category['id'];
        }
    }

    protected function tearDown(): void
    {
        // Clean up listings
        foreach ($this->createdListingIds as $listingId) {
            try {
                Database::query("DELETE FROM listings WHERE id = ?", [$listingId]);
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }

        // Clean up user
        try {
            Database::query("DELETE FROM users WHERE id = ?", [$this->testUserId]);
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }

        parent::tearDown();
    }

    /**
     * Test: Complete listing creation and retrieval flow
     */
    public function testCreateAndRetrieveListingFlow(): void
    {
        // Step 1: Create an offer listing
        $listingData = [
            'tenant_id' => self::$testTenantId,
            'user_id' => $this->testUserId,
            'title' => 'Garden Help Available',
            'description' => 'I can help with gardening tasks including weeding, planting, and maintenance.',
            'type' => 'offer',
            'category_id' => $this->testCategoryId,
            'time_credits' => 2,
            'status' => 'active',
        ];

        Database::query(
            "INSERT INTO listings (tenant_id, user_id, title, description, type, category_id, time_credits, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $listingData['tenant_id'],
                $listingData['user_id'],
                $listingData['title'],
                $listingData['description'],
                $listingData['type'],
                $listingData['category_id'],
                $listingData['time_credits'],
                $listingData['status']
            ]
        );

        $listingId = (int)Database::lastInsertId();
        $this->createdListingIds[] = $listingId;

        $this->assertGreaterThan(0, $listingId, 'Listing should be created with valid ID');

        // Step 2: Retrieve the listing
        $stmt = Database::query(
            "SELECT * FROM listings WHERE id = ? AND tenant_id = ?",
            [$listingId, self::$testTenantId]
        );
        $listing = $stmt->fetch();

        $this->assertNotFalse($listing, 'Listing should exist');
        $this->assertEquals($listingData['title'], $listing['title']);
        $this->assertEquals($listingData['type'], $listing['type']);
        $this->assertEquals($this->testUserId, $listing['user_id']);
    }

    /**
     * Test: Browse listings with filtering
     */
    public function testBrowseListingsWithFiltering(): void
    {
        // Step 1: Create multiple listings of different types
        $offerListing = $this->createTestListing('Offer: Plumbing Services', 'offer');
        $requestListing = $this->createTestListing('Request: Computer Help', 'request');
        $anotherOfferListing = $this->createTestListing('Offer: Cooking Classes', 'offer');

        // Step 2: Get all listings using ListingService
        $result = ListingService::getAll(['limit' => 20]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertGreaterThanOrEqual(3, count($result['items']), 'Should have at least 3 listings');

        // Step 3: Filter by type (offers only)
        $offersResult = ListingService::getAll(['type' => 'offer', 'limit' => 20]);
        $offerIds = array_column($offersResult['items'], 'id');

        $this->assertContains($offerListing, $offerIds, 'Should include offer listing');
        $this->assertContains($anotherOfferListing, $offerIds, 'Should include another offer listing');

        // Verify request listing is not in offers-only results
        $requestCount = count(array_filter($offersResult['items'], function ($item) use ($requestListing) {
            return $item['id'] === $requestListing;
        }));
        $this->assertEquals(0, $requestCount, 'Should not include request listing in offers filter');

        // Step 4: Filter by category
        $categoryResult = ListingService::getAll(['category_id' => $this->testCategoryId, 'limit' => 20]);
        $this->assertGreaterThanOrEqual(3, count($categoryResult['items']), 'Should filter by category');
    }

    /**
     * Test: Search listings by keyword
     */
    public function testSearchListingsByKeyword(): void
    {
        // Step 1: Create listings with distinctive keywords
        $this->createTestListing('Bicycle Repair Services', 'offer');
        $this->createTestListing('Guitar Lessons Available', 'offer');
        $this->createTestListing('Need Help with Bicycle Maintenance', 'request');

        // Step 2: Search for "bicycle"
        $searchResult = ListingService::getAll(['search' => 'bicycle', 'limit' => 20]);

        $this->assertGreaterThanOrEqual(2, count($searchResult['items']), 'Should find bicycle-related listings');

        // Verify search results contain the keyword
        foreach ($searchResult['items'] as $item) {
            $titleMatch = stripos($item['title'], 'bicycle') !== false;
            $descMatch = stripos($item['description'] ?? '', 'bicycle') !== false;
            $this->assertTrue(
                $titleMatch || $descMatch,
                'Search results should contain keyword in title or description'
            );
        }

        // Step 3: Search for "guitar"
        $guitarResult = ListingService::getAll(['search' => 'guitar', 'limit' => 20]);
        $this->assertGreaterThanOrEqual(1, count($guitarResult['items']), 'Should find guitar listing');
    }

    /**
     * Test: Edit and update listing
     */
    public function testEditAndUpdateListingFlow(): void
    {
        // Step 1: Create initial listing
        $listingId = $this->createTestListing('Original Title', 'offer');

        // Step 2: Read current listing
        $stmt = Database::query("SELECT * FROM listings WHERE id = ?", [$listingId]);
        $listing = $stmt->fetch();
        $this->assertEquals('Original Title', $listing['title']);

        // Step 3: Update listing
        $newTitle = 'Updated Title - Professional Services';
        $newDescription = 'Updated description with more details about my services.';

        Database::query(
            "UPDATE listings SET title = ?, description = ?, updated_at = NOW()
             WHERE id = ? AND tenant_id = ? AND user_id = ?",
            [$newTitle, $newDescription, $listingId, self::$testTenantId, $this->testUserId]
        );

        // Step 4: Verify updates persisted
        $stmt = Database::query("SELECT * FROM listings WHERE id = ?", [$listingId]);
        $updatedListing = $stmt->fetch();

        $this->assertEquals($newTitle, $updatedListing['title'], 'Title should be updated');
        $this->assertEquals($newDescription, $updatedListing['description'], 'Description should be updated');
        $this->assertNotNull($updatedListing['updated_at'], 'Updated timestamp should be set');
    }

    /**
     * Test: Deactivate and delete listing
     */
    public function testDeactivateAndDeleteListingFlow(): void
    {
        // Step 1: Create active listing
        $listingId = $this->createTestListing('Listing to Delete', 'offer');

        $stmt = Database::query("SELECT status FROM listings WHERE id = ?", [$listingId]);
        $listing = $stmt->fetch();
        $this->assertEquals('active', $listing['status']);

        // Step 2: Deactivate listing (soft delete)
        Database::query(
            "UPDATE listings SET status = 'inactive', updated_at = NOW()
             WHERE id = ? AND tenant_id = ? AND user_id = ?",
            [$listingId, self::$testTenantId, $this->testUserId]
        );

        // Verify listing is inactive
        $stmt = Database::query("SELECT status FROM listings WHERE id = ?", [$listingId]);
        $deactivated = $stmt->fetch();
        $this->assertEquals('inactive', $deactivated['status']);

        // Step 3: Hard delete listing
        Database::query(
            "DELETE FROM listings WHERE id = ? AND tenant_id = ? AND user_id = ?",
            [$listingId, self::$testTenantId, $this->testUserId]
        );

        // Verify listing no longer exists
        $stmt = Database::query("SELECT * FROM listings WHERE id = ?", [$listingId]);
        $this->assertFalse($stmt->fetch(), 'Listing should be deleted');

        // Remove from cleanup array since already deleted
        $this->createdListingIds = array_filter($this->createdListingIds, fn($id) => $id !== $listingId);
    }

    /**
     * Helper: Create a test listing
     */
    private function createTestListing(string $title, string $type): int
    {
        Database::query(
            "INSERT INTO listings (tenant_id, user_id, title, description, type, category_id, time_credits, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 1, 'active', NOW())",
            [
                self::$testTenantId,
                $this->testUserId,
                $title,
                "Test description for: {$title}",
                $type,
                $this->testCategoryId
            ]
        );

        $listingId = (int)Database::lastInsertId();
        $this->createdListingIds[] = $listingId;

        return $listingId;
    }
}
