<?php

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\BadgeCollectionService;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class BadgeCollectionServiceTest extends TestCase
{
    private static $testUserId;
    private static $testTenantId = 1;
    private static $otherTenantId = 2;
    private static $testCollectionId;

    public static function setUpBeforeClass(): void
    {
        TenantContext::setById(self::$testTenantId);

        // Create a test user
        $uniqueEmail = 'test_badge_collection_' . time() . '@test.com';
        Database::query(
            "INSERT INTO users (tenant_id, email, first_name, last_name, xp, level, is_approved, created_at)
             VALUES (?, ?, 'Test', 'BadgeUser', 0, 1, 1, NOW())",
            [self::$testTenantId, $uniqueEmail]
        );
        self::$testUserId = Database::getInstance()->lastInsertId();

        // Create a test collection for tenant 1
        Database::query(
            "INSERT INTO badge_collections (tenant_id, collection_key, name, description, icon, bonus_xp, display_order)
             VALUES (?, 'test_collection', 'Test Collection', 'Test Description', '🧪', 50, 1)",
            [self::$testTenantId]
        );
        self::$testCollectionId = Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up test data
        if (self::$testCollectionId) {
            try {
                Database::query("DELETE FROM badge_collection_items WHERE collection_id = ?", [self::$testCollectionId]);
            } catch (\Exception $e) {}
            try {
                Database::query("DELETE FROM badge_collections WHERE id = ?", [self::$testCollectionId]);
            } catch (\Exception $e) {}
        }
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM user_collection_completions WHERE user_id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
            try {
                Database::query("DELETE FROM user_badges WHERE user_id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
            try {
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
        }
        // Clean up any test collections from other tenant
        try {
            Database::query("DELETE FROM badge_collections WHERE collection_key = 'cross_tenant_test'");
        } catch (\Exception $e) {}
    }

    protected function setUp(): void
    {
        TenantContext::setById(self::$testTenantId);
    }

    public function testGetByIdReturnsTenantScopedCollection(): void
    {
        $collection = BadgeCollectionService::getById(self::$testCollectionId);

        $this->assertNotNull($collection);
        $this->assertEquals('test_collection', $collection['collection_key']);
        $this->assertEquals('Test Collection', $collection['name']);
        $this->assertEquals(self::$testTenantId, $collection['tenant_id']);
    }

    public function testGetByIdReturnsNullForOtherTenantCollection(): void
    {
        // Create a collection in tenant 2
        Database::query(
            "INSERT INTO badge_collections (tenant_id, collection_key, name, description, icon, bonus_xp, display_order)
             VALUES (?, 'cross_tenant_test', 'Other Tenant Collection', 'Test', '🔒', 10, 1)",
            [self::$otherTenantId]
        );
        $otherTenantCollectionId = Database::getInstance()->lastInsertId();

        // Try to access from tenant 1 context - should return null due to tenant scoping
        TenantContext::setById(self::$testTenantId);
        $collection = BadgeCollectionService::getById($otherTenantCollectionId);

        $this->assertNull($collection, 'Should not be able to access collection from another tenant');

        // Clean up
        Database::query("DELETE FROM badge_collections WHERE id = ?", [$otherTenantCollectionId]);
    }

    public function testGetByIdReturnsNullForNonExistentCollection(): void
    {
        $collection = BadgeCollectionService::getById(999999);

        $this->assertNull($collection);
    }

    public function testGetByIdIncludesBadges(): void
    {
        // Add a badge to the collection
        Database::query(
            "INSERT INTO badge_collection_items (collection_id, badge_key, display_order)
             VALUES (?, 'test_badge', 1)",
            [self::$testCollectionId]
        );

        $collection = BadgeCollectionService::getById(self::$testCollectionId);

        $this->assertNotNull($collection);
        $this->assertArrayHasKey('badges', $collection);
        $this->assertCount(1, $collection['badges']);
        $this->assertEquals('test_badge', $collection['badges'][0]['badge_key']);

        // Clean up
        Database::query(
            "DELETE FROM badge_collection_items WHERE collection_id = ? AND badge_key = 'test_badge'",
            [self::$testCollectionId]
        );
    }

    public function testGetCollectionsWithProgressReturnsEmptyForNoCollections(): void
    {
        // Use a different tenant with no collections
        TenantContext::setById(99999);

        $collections = BadgeCollectionService::getCollectionsWithProgress(self::$testUserId);

        $this->assertIsArray($collections);
        $this->assertEmpty($collections);

        // Reset tenant
        TenantContext::setById(self::$testTenantId);
    }

    public function testGetCollectionsWithProgressIncludesProgressData(): void
    {
        $collections = BadgeCollectionService::getCollectionsWithProgress(self::$testUserId);

        $this->assertIsArray($collections);

        if (!empty($collections)) {
            $firstCollection = $collections[0];
            $this->assertArrayHasKey('badges', $firstCollection);
            $this->assertArrayHasKey('earned_count', $firstCollection);
            $this->assertArrayHasKey('total_count', $firstCollection);
        }
    }

    public function testCreateCollectionInsertsWithCorrectTenant(): void
    {
        $collectionId = BadgeCollectionService::create([
            'collection_key' => 'new_test_collection_' . time(),
            'name' => 'New Test Collection',
            'description' => 'Test description',
            'icon' => '✨',
            'bonus_xp' => 100,
            'display_order' => 99
        ]);

        $this->assertIsInt($collectionId);
        $this->assertGreaterThan(0, $collectionId);

        // Verify tenant scoping
        $created = Database::query(
            "SELECT * FROM badge_collections WHERE id = ?",
            [$collectionId]
        )->fetch();

        $this->assertEquals(self::$testTenantId, $created['tenant_id']);

        // Clean up
        Database::query("DELETE FROM badge_collections WHERE id = ?", [$collectionId]);
    }

    public function testDeleteCollectionRemovesCollection(): void
    {
        // Create a collection to delete
        Database::query(
            "INSERT INTO badge_collections (tenant_id, collection_key, name, description, icon, bonus_xp, display_order)
             VALUES (?, 'to_delete', 'Delete Me', 'Test', '🗑️', 10, 99)",
            [self::$testTenantId]
        );
        $deleteId = Database::getInstance()->lastInsertId();

        // Delete it
        BadgeCollectionService::delete($deleteId);

        // Verify it's gone
        $deleted = Database::query(
            "SELECT * FROM badge_collections WHERE id = ?",
            [$deleteId]
        )->fetch();

        $this->assertFalse($deleted);
    }
}
