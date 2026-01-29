<?php

declare(strict_types=1);

namespace Nexus\Tests\Models;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\Listing;

/**
 * Listing Model Tests
 *
 * Tests listing creation, retrieval, updates, and various listing methods.
 */
class ListingTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testListingId = null;
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
        $timestamp = time();

        // Create test user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [
                self::$testTenantId,
                "listing_model_test_{$timestamp}@test.com",
                "listing_model_test_{$timestamp}",
                'Listing',
                'Tester',
                'Listing Tester',
                100
            ]
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Try to get or create a test category
        try {
            $category = Database::query(
                "SELECT id FROM categories WHERE tenant_id = ? LIMIT 1",
                [self::$testTenantId]
            )->fetch();

            if ($category) {
                self::$testCategoryId = (int)$category['id'];
            } else {
                // Create a test category
                Database::query(
                    "INSERT INTO categories (tenant_id, name, slug, created_at) VALUES (?, ?, ?, NOW())",
                    [self::$testTenantId, 'Test Category', 'test-category-' . $timestamp]
                );
                self::$testCategoryId = (int)Database::getInstance()->lastInsertId();
            }
        } catch (\Exception $e) {
            self::$testCategoryId = null;
        }

        // Create test listing
        Database::query(
            "INSERT INTO listings (tenant_id, user_id, title, description, type, category_id, status, location, latitude, longitude, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, NOW())",
            [
                self::$testTenantId,
                self::$testUserId,
                "Test Listing {$timestamp}",
                "This is a test listing description for model tests.",
                'offer',
                self::$testCategoryId,
                'Dublin, Ireland',
                53.3498,
                -6.2603
            ]
        );
        self::$testListingId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM listings WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM activity_log WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM notifications WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);
    }

    // ==========================================
    // Create Tests
    // ==========================================

    public function testCreateListingReturnsId(): void
    {
        $timestamp = time();
        $title = "New Test Listing {$timestamp}";

        $id = Listing::create(
            self::$testUserId,
            $title,
            'Test description',
            'offer',
            self::$testCategoryId
        );

        $this->assertIsNumeric($id);
        $this->assertGreaterThan(0, $id);

        // Clean up
        Database::query("DELETE FROM listings WHERE id = ?", [$id]);
    }

    public function testCreateListingWithAllFields(): void
    {
        $timestamp = time();

        $id = Listing::create(
            self::$testUserId,
            "Full Listing {$timestamp}",
            'Full description',
            'request',
            self::$testCategoryId,
            '/uploads/test-image.jpg',
            'Cork, Ireland',
            51.8969,
            -8.4863,
            'listed' // federated visibility
        );

        $this->assertIsNumeric($id);

        // Verify all fields were saved
        $listing = Listing::find($id);
        $this->assertEquals("Full Listing {$timestamp}", $listing['title']);
        $this->assertEquals('request', $listing['type']);
        $this->assertEquals('/uploads/test-image.jpg', $listing['image_url']);
        $this->assertEquals('Cork, Ireland', $listing['location']);

        // Clean up
        Database::query("DELETE FROM listings WHERE id = ?", [$id]);
    }

    public function testCreateListingValidatesFederatedVisibility(): void
    {
        $id = Listing::create(
            self::$testUserId,
            'Visibility Test',
            'Test description',
            'offer',
            null,
            null,
            null,
            null,
            null,
            'invalid_visibility' // Should default to 'none'
        );

        $listing = Database::query(
            "SELECT federated_visibility FROM listings WHERE id = ?",
            [$id]
        )->fetch();

        $this->assertEquals('none', $listing['federated_visibility']);

        // Clean up
        Database::query("DELETE FROM listings WHERE id = ?", [$id]);
    }

    // ==========================================
    // Find Tests
    // ==========================================

    public function testFindReturnsListing(): void
    {
        $listing = Listing::find(self::$testListingId);

        $this->assertNotFalse($listing);
        $this->assertIsArray($listing);
        $this->assertEquals(self::$testListingId, $listing['id']);
    }

    public function testFindIncludesAuthorInfo(): void
    {
        $listing = Listing::find(self::$testListingId);

        $this->assertArrayHasKey('author_name', $listing);
        $this->assertNotEmpty($listing['author_name']);
    }

    public function testFindReturnsNullForDeletedListing(): void
    {
        // Create and soft-delete a listing
        $id = Listing::create(
            self::$testUserId,
            'To Be Deleted',
            'Description',
            'offer'
        );

        Listing::delete($id);

        $listing = Listing::find($id);
        $this->assertFalse($listing);
    }

    public function testFindReturnsFalseForNonExistent(): void
    {
        $listing = Listing::find(999999999);

        $this->assertFalse($listing);
    }

    // ==========================================
    // All/List Tests
    // ==========================================

    public function testAllReturnsArray(): void
    {
        $listings = Listing::all();

        $this->assertIsArray($listings);
    }

    public function testAllFiltersByType(): void
    {
        $listings = Listing::all('offer');

        $this->assertIsArray($listings);
        foreach ($listings as $listing) {
            $this->assertEquals('offer', $listing['type']);
        }
    }

    public function testAllFiltersByMultipleTypes(): void
    {
        $listings = Listing::all(['offer', 'request']);

        $this->assertIsArray($listings);
        foreach ($listings as $listing) {
            $this->assertContains($listing['type'], ['offer', 'request']);
        }
    }

    public function testAllFiltersByCategory(): void
    {
        if (!self::$testCategoryId) {
            $this->markTestSkipped('No test category available');
        }

        $listings = Listing::all(null, self::$testCategoryId);

        $this->assertIsArray($listings);
        foreach ($listings as $listing) {
            $this->assertEquals(self::$testCategoryId, $listing['category_id']);
        }
    }

    public function testAllFiltersBySearch(): void
    {
        $listings = Listing::all(null, null, 'Test Listing');

        $this->assertIsArray($listings);
        // Should find our test listing
        $found = false;
        foreach ($listings as $listing) {
            if ($listing['id'] == self::$testListingId) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    // ==========================================
    // Search Tests
    // ==========================================

    public function testSearchReturnsMatchingListings(): void
    {
        $listings = Listing::search('Test Listing');

        $this->assertIsArray($listings);
    }

    public function testSearchIncludesLocationMatches(): void
    {
        $listings = Listing::search('Dublin');

        $this->assertIsArray($listings);
        // Should find our test listing with Dublin location
        $found = false;
        foreach ($listings as $listing) {
            if ($listing['id'] == self::$testListingId) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Search should find listings by location');
    }

    public function testSearchWithSpecialCharacters(): void
    {
        $listings = Listing::search("Test's Listing");

        $this->assertIsArray($listings);
        // Should not throw an error
    }

    // ==========================================
    // GetForUser Tests
    // ==========================================

    public function testGetForUserReturnsUserListings(): void
    {
        $listings = Listing::getForUser(self::$testUserId);

        $this->assertIsArray($listings);
        $this->assertGreaterThanOrEqual(1, count($listings));

        foreach ($listings as $listing) {
            $this->assertEquals(self::$testUserId, $listing['user_id']);
        }
    }

    public function testGetForUserExcludesDeletedListings(): void
    {
        // Create and delete a listing
        $id = Listing::create(
            self::$testUserId,
            'User Deleted Listing',
            'Description',
            'offer'
        );
        Listing::delete($id);

        $listings = Listing::getForUser(self::$testUserId);

        // Deleted listing should not appear
        $found = false;
        foreach ($listings as $listing) {
            if ($listing['id'] == $id) {
                $found = true;
                break;
            }
        }
        $this->assertFalse($found, 'Deleted listings should not appear in getForUser');
    }

    // ==========================================
    // Update Tests
    // ==========================================

    public function testUpdateChangesFields(): void
    {
        $newTitle = 'Updated Title ' . time();
        $newDescription = 'Updated description text';

        Listing::update(
            self::$testListingId,
            $newTitle,
            $newDescription,
            'request', // change type
            self::$testCategoryId,
            null, // no new image
            'Galway, Ireland',
            53.2707,
            -9.0568
        );

        $listing = Listing::find(self::$testListingId);

        $this->assertEquals($newTitle, $listing['title']);
        $this->assertEquals($newDescription, $listing['description']);
        $this->assertEquals('request', $listing['type']);
        $this->assertEquals('Galway, Ireland', $listing['location']);

        // Reset for other tests
        Listing::update(
            self::$testListingId,
            'Test Listing ' . time(),
            'This is a test listing description for model tests.',
            'offer',
            self::$testCategoryId,
            null,
            'Dublin, Ireland',
            53.3498,
            -6.2603
        );
    }

    public function testUpdateWithImageUrl(): void
    {
        $imageUrl = '/uploads/updated-image-' . time() . '.jpg';

        Listing::update(
            self::$testListingId,
            'Image Update Test',
            'Description',
            'offer',
            null,
            $imageUrl,
            'Dublin, Ireland',
            53.3498,
            -6.2603
        );

        $listing = Listing::find(self::$testListingId);

        $this->assertEquals($imageUrl, $listing['image_url']);
    }

    public function testUpdateFederatedVisibility(): void
    {
        Listing::update(
            self::$testListingId,
            'Federated Update Test',
            'Description',
            'offer',
            null,
            null,
            'Dublin, Ireland',
            53.3498,
            -6.2603,
            'bookable'
        );

        $listing = Database::query(
            "SELECT federated_visibility FROM listings WHERE id = ?",
            [self::$testListingId]
        )->fetch();

        $this->assertEquals('bookable', $listing['federated_visibility']);

        // Reset
        Listing::update(
            self::$testListingId,
            'Test Listing',
            'Description',
            'offer',
            null,
            null,
            'Dublin, Ireland',
            53.3498,
            -6.2603,
            'none'
        );
    }

    // ==========================================
    // Delete Tests
    // ==========================================

    public function testDeleteSoftDeletesListing(): void
    {
        $id = Listing::create(
            self::$testUserId,
            'To Delete',
            'Description',
            'offer'
        );

        Listing::delete($id);

        // Should not be found via find()
        $listing = Listing::find($id);
        $this->assertFalse($listing);

        // But should still exist in database with 'deleted' status
        $rawListing = Database::query(
            "SELECT status FROM listings WHERE id = ?",
            [$id]
        )->fetch();

        $this->assertNotFalse($rawListing);
        $this->assertEquals('deleted', $rawListing['status']);

        // Clean up
        Database::query("DELETE FROM listings WHERE id = ?", [$id]);
    }

    // ==========================================
    // Count Tests
    // ==========================================

    public function testCountByUserReturnsCorrectCount(): void
    {
        $count = Listing::countByUser(self::$testUserId, 'offer');

        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function testCountByUserExcludesDeletedListings(): void
    {
        $initialCount = Listing::countByUser(self::$testUserId, 'offer');

        // Create and delete a listing
        $id = Listing::create(
            self::$testUserId,
            'Count Test',
            'Description',
            'offer'
        );

        $countAfterCreate = Listing::countByUser(self::$testUserId, 'offer');
        $this->assertEquals($initialCount + 1, $countAfterCreate);

        Listing::delete($id);

        $countAfterDelete = Listing::countByUser(self::$testUserId, 'offer');
        $this->assertEquals($initialCount, $countAfterDelete);
    }

    // ==========================================
    // GetRecent Tests
    // ==========================================

    public function testGetRecentReturnsArray(): void
    {
        $listings = Listing::getRecent('offer', 5);

        $this->assertIsArray($listings);
        $this->assertLessThanOrEqual(5, count($listings));
    }

    public function testGetRecentFiltersCorrectly(): void
    {
        $listings = Listing::getRecent('offer', 10);

        foreach ($listings as $listing) {
            $this->assertEquals('offer', $listing['type']);
            $this->assertEquals('active', $listing['status']);
        }
    }

    public function testGetRecentWithSinceDate(): void
    {
        $yesterday = date('Y-m-d H:i:s', strtotime('-1 day'));

        $listings = Listing::getRecent('offer', 10, $yesterday);

        $this->assertIsArray($listings);
        foreach ($listings as $listing) {
            $this->assertGreaterThanOrEqual($yesterday, $listing['created_at']);
        }
    }

    // ==========================================
    // GetNearby Tests
    // ==========================================

    public function testGetNearbyReturnsArray(): void
    {
        $listings = Listing::getNearby(53.3498, -6.2603, 50, 10);

        $this->assertIsArray($listings);
    }

    public function testGetNearbyIncludesDistance(): void
    {
        $listings = Listing::getNearby(53.3498, -6.2603, 100, 10);

        foreach ($listings as $listing) {
            $this->assertArrayHasKey('distance_km', $listing);
            $this->assertIsNumeric($listing['distance_km']);
        }
    }

    public function testGetNearbyFiltersWithinRadius(): void
    {
        $radiusKm = 10;
        $listings = Listing::getNearby(53.3498, -6.2603, $radiusKm, 50);

        foreach ($listings as $listing) {
            $this->assertLessThanOrEqual($radiusKm, (float)$listing['distance_km']);
        }
    }

    public function testGetNearbyFiltersByType(): void
    {
        $listings = Listing::getNearby(53.3498, -6.2603, 100, 10, 'offer');

        foreach ($listings as $listing) {
            $this->assertEquals('offer', $listing['type']);
        }
    }

    public function testGetNearbyFiltersByMultipleTypes(): void
    {
        $listings = Listing::getNearby(53.3498, -6.2603, 100, 10, ['offer', 'request']);

        foreach ($listings as $listing) {
            $this->assertContains($listing['type'], ['offer', 'request']);
        }
    }

    public function testGetNearbyFiltersByCategory(): void
    {
        if (!self::$testCategoryId) {
            $this->markTestSkipped('No test category available');
        }

        $listings = Listing::getNearby(53.3498, -6.2603, 100, 10, null, self::$testCategoryId);

        foreach ($listings as $listing) {
            $this->assertEquals(self::$testCategoryId, $listing['category_id']);
        }
    }

    // ==========================================
    // Edge Cases
    // ==========================================

    public function testCreateWithNullOptionalFields(): void
    {
        $id = Listing::create(
            self::$testUserId,
            'Minimal Listing',
            'Just title and description',
            'offer'
        );

        $this->assertIsNumeric($id);

        $listing = Listing::find($id);
        $this->assertNull($listing['category_id']);
        $this->assertNull($listing['image_url']);
        $this->assertNull($listing['latitude']);
        $this->assertNull($listing['longitude']);

        // Clean up
        Database::query("DELETE FROM listings WHERE id = ?", [$id]);
    }

    public function testSearchWithEmptyQuery(): void
    {
        $listings = Listing::search('');

        $this->assertIsArray($listings);
    }

    public function testAllWithEmptyFilters(): void
    {
        $listings = Listing::all(null, null, null);

        $this->assertIsArray($listings);
    }
}
