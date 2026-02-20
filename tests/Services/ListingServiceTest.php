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
use Nexus\Services\ListingService;

/**
 * ListingService Tests
 *
 * Tests listing CRUD, search, filtering, validation, and marketplace functionality.
 */
class ListingServiceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testUser2Id = null;
    protected static ?int $testCategoryId = null;
    protected static ?int $testListingOfferId = null;
    protected static ?int $testListingRequestId = null;

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

        // Create test users
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 100, 1, NOW())",
            [self::$testTenantId, "lstsvc_user1_{$ts}@test.com", "lstsvc_user1_{$ts}", 'Listing', 'Owner', 'Listing Owner']
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 50, 1, NOW())",
            [self::$testTenantId, "lstsvc_user2_{$ts}@test.com", "lstsvc_user2_{$ts}", 'Second', 'User', 'Second User']
        );
        self::$testUser2Id = (int)Database::getInstance()->lastInsertId();

        // Create test category
        try {
            Database::query(
                "INSERT INTO categories (tenant_id, name, slug, type, created_at)
                 VALUES (?, ?, ?, 'listing', NOW())",
                [self::$testTenantId, "Test Category {$ts}", "test-cat-{$ts}"]
            );
            self::$testCategoryId = (int)Database::getInstance()->lastInsertId();
        } catch (\Exception $e) {
            // Category may not exist in all schemas
        }

        // Create test offer listing
        Database::query(
            "INSERT INTO listings (tenant_id, user_id, category_id, title, description, type, status, location, created_at)
             VALUES (?, ?, ?, ?, ?, 'offer', 'active', 'Dublin', NOW())",
            [
                self::$testTenantId,
                self::$testUserId,
                self::$testCategoryId,
                "Test Offer Listing {$ts}",
                "Test offer description"
            ]
        );
        self::$testListingOfferId = (int)Database::getInstance()->lastInsertId();

        // Create test request listing
        Database::query(
            "INSERT INTO listings (tenant_id, user_id, category_id, title, description, type, status, location, created_at)
             VALUES (?, ?, ?, ?, ?, 'request', 'active', 'Cork', NOW())",
            [
                self::$testTenantId,
                self::$testUser2Id,
                self::$testCategoryId,
                "Test Request Listing {$ts}",
                "Test request description"
            ]
        );
        self::$testListingRequestId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        $listingIds = array_filter([self::$testListingOfferId, self::$testListingRequestId]);
        foreach ($listingIds as $lid) {
            try {
                Database::query("DELETE FROM listings WHERE id = ? AND tenant_id = ?", [$lid, self::$testTenantId]);
            } catch (\Exception $e) {}
        }

        if (self::$testCategoryId) {
            try {
                Database::query("DELETE FROM categories WHERE id = ? AND tenant_id = ?", [self::$testCategoryId, self::$testTenantId]);
            } catch (\Exception $e) {}
        }

        $userIds = array_filter([self::$testUserId, self::$testUser2Id]);
        foreach ($userIds as $uid) {
            try {
                Database::query("DELETE FROM users WHERE id = ? AND tenant_id = ?", [$uid, self::$testTenantId]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    // ==========================================
    // getAll Tests
    // ==========================================

    public function testGetAllReturnsValidStructure(): void
    {
        $result = ListingService::getAll();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertIsArray($result['items']);
    }

    public function testGetAllFiltersByType(): void
    {
        $result = ListingService::getAll(['type' => 'offer']);

        foreach ($result['items'] as $item) {
            $this->assertEquals('offer', $item['type']);
        }
    }

    public function testGetAllFiltersByMultipleTypes(): void
    {
        $result = ListingService::getAll(['type' => ['offer', 'request']]);

        $this->assertNotEmpty($result['items']);
        foreach ($result['items'] as $item) {
            $this->assertContains($item['type'], ['offer', 'request']);
        }
    }

    public function testGetAllFiltersByCategory(): void
    {
        if (!self::$testCategoryId) {
            $this->markTestSkipped('Category not available');
        }

        $result = ListingService::getAll(['category_id' => self::$testCategoryId]);

        foreach ($result['items'] as $item) {
            $this->assertEquals(self::$testCategoryId, $item['category_id']);
        }
    }

    public function testGetAllFiltersByUserId(): void
    {
        $result = ListingService::getAll(['user_id' => self::$testUserId]);

        $this->assertNotEmpty($result['items']);
        foreach ($result['items'] as $item) {
            $this->assertEquals(self::$testUserId, $item['user_id']);
        }
    }

    public function testGetAllRespectsLimit(): void
    {
        $result = ListingService::getAll(['limit' => 5]);

        $this->assertLessThanOrEqual(5, count($result['items']));
    }

    public function testGetAllEnforcesMaxLimit(): void
    {
        $result = ListingService::getAll(['limit' => 500]);

        $this->assertLessThanOrEqual(100, count($result['items']));
    }

    public function testGetAllExcludesDeletedByDefault(): void
    {
        $result = ListingService::getAll();

        foreach ($result['items'] as $item) {
            $this->assertContains($item['status'] ?? 'active', ['active', null]);
        }
    }

    // ==========================================
    // getById Tests
    // ==========================================

    public function testGetByIdReturnsValidListing(): void
    {
        $listing = ListingService::getById(self::$testListingOfferId);

        $this->assertNotNull($listing);
        $this->assertIsArray($listing);
        $this->assertEquals(self::$testListingOfferId, $listing['id']);
        $this->assertArrayHasKey('title', $listing);
        $this->assertArrayHasKey('description', $listing);
        $this->assertArrayHasKey('type', $listing);
    }

    public function testGetByIdReturnsNullForNonExistent(): void
    {
        $listing = ListingService::getById(999999);

        $this->assertNull($listing);
    }

    public function testGetByIdIncludesAuthorInfo(): void
    {
        $listing = ListingService::getById(self::$testListingOfferId);

        $this->assertNotNull($listing);
        $this->assertArrayHasKey('author_name', $listing);
        $this->assertArrayHasKey('user_id', $listing);
    }

    // ==========================================
    // validateListing Tests
    // ==========================================

    public function testValidateListingAcceptsValidData(): void
    {
        $valid = ListingService::validateListing([
            'title' => 'Valid Listing Title',
            'description' => 'Valid description',
            'type' => 'offer',
        ]);

        $this->assertTrue($valid);
        $this->assertEmpty(ListingService::getErrors());
    }

    public function testValidateListingRejectsMissingTitle(): void
    {
        $valid = ListingService::validateListing([
            'description' => 'Description',
            'type' => 'offer',
        ]);

        $this->assertFalse($valid);
        $errors = ListingService::getErrors();
        $this->assertNotEmpty($errors);
    }

    public function testValidateListingRejectsEmptyTitle(): void
    {
        $valid = ListingService::validateListing([
            'title' => '',
            'description' => 'Description',
            'type' => 'offer',
        ]);

        $this->assertFalse($valid);
    }

    public function testValidateListingRejectsTooLongTitle(): void
    {
        $valid = ListingService::validateListing([
            'title' => str_repeat('A', 256),
            'description' => 'Description',
            'type' => 'offer',
        ]);

        $this->assertFalse($valid);
    }

    public function testValidateListingRejectsInvalidType(): void
    {
        $valid = ListingService::validateListing([
            'title' => 'Valid Title',
            'description' => 'Description',
            'type' => 'invalid_type',
        ]);

        $this->assertFalse($valid);
    }

    public function testValidateListingAcceptsOfferType(): void
    {
        $valid = ListingService::validateListing([
            'title' => 'Valid Title',
            'description' => 'Description',
            'type' => 'offer',
        ]);

        $this->assertTrue($valid);
    }

    public function testValidateListingAcceptsRequestType(): void
    {
        $valid = ListingService::validateListing([
            'title' => 'Valid Title',
            'description' => 'Description',
            'type' => 'request',
        ]);

        $this->assertTrue($valid);
    }

    // ==========================================
    // createListing Tests
    // ==========================================

    public function testCreateListingReturnsFalseForInvalidData(): void
    {
        $result = ListingService::createListing([
            'title' => '', // Invalid: empty
            'type' => 'offer',
        ], self::$testUserId);

        $this->assertFalse($result);
        $this->assertNotEmpty(ListingService::getErrors());
    }

    public function testCreateListingUsesCurrentTenant(): void
    {
        $listingId = ListingService::createListing([
            'title' => 'Tenant Test Listing',
            'description' => 'Test',
            'type' => 'offer',
        ], self::$testUserId);

        if ($listingId) {
            $stmt = Database::query("SELECT tenant_id FROM listings WHERE id = ?", [$listingId]);
            $row = $stmt->fetch();
            $this->assertEquals(self::$testTenantId, $row['tenant_id']);

            // Cleanup
            Database::query("DELETE FROM listings WHERE id = ? AND tenant_id = ?", [$listingId, self::$testTenantId]);
        }
    }

    // ==========================================
    // updateListing Tests
    // ==========================================

    public function testUpdateListingReturnsFalseForInvalidData(): void
    {
        $result = ListingService::updateListing(self::$testListingOfferId, [
            'title' => str_repeat('X', 300),
        ]);

        $this->assertFalse($result);
    }

    public function testUpdateListingReturnsTrueForEmptyData(): void
    {
        $result = ListingService::updateListing(self::$testListingOfferId, []);

        $this->assertTrue($result);
    }

    // ==========================================
    // deleteListing Tests
    // ==========================================

    public function testDeleteListingReturnsTrueForExistingListing(): void
    {
        // Create a temporary listing to delete
        Database::query(
            "INSERT INTO listings (tenant_id, user_id, title, description, type, status, created_at)
             VALUES (?, ?, 'To Delete', 'Test', 'offer', 'active', NOW())",
            [self::$testTenantId, self::$testUserId]
        );
        $tempId = (int)Database::getInstance()->lastInsertId();

        $result = ListingService::deleteListing($tempId);

        $this->assertTrue($result);

        // Verify it was marked as deleted
        $stmt = Database::query("SELECT status FROM listings WHERE id = ? AND tenant_id = ?", [$tempId, self::$testTenantId]);
        $row = $stmt->fetch();
        $this->assertEquals('deleted', $row['status'] ?? null);

        // Cleanup
        Database::query("DELETE FROM listings WHERE id = ? AND tenant_id = ?", [$tempId, self::$testTenantId]);
    }

    public function testDeleteListingReturnsFalseForNonExistent(): void
    {
        $result = ListingService::deleteListing(999999);

        $this->assertFalse($result);
    }
}
