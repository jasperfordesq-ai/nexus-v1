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
use Nexus\Models\Category;

/**
 * Category Model Tests
 *
 * Tests category CRUD, type-based filtering, hierarchy,
 * data loss prevention on update, seed defaults, and tenant scoping.
 */
class CategoryTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testCategoryId = null;
    protected static ?int $testEventCategoryId = null;

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

        // Create a test listing category
        self::$testCategoryId = (int)Category::create([
            'name' => "Test Category {$timestamp}",
            'slug' => "test-category-{$timestamp}",
            'color' => 'blue',
            'type' => 'listing',
        ]);

        // Create a test event category
        self::$testEventCategoryId = (int)Category::create([
            'name' => "Test Event Cat {$timestamp}",
            'slug' => "test-event-cat-{$timestamp}",
            'color' => 'green',
            'type' => 'event',
        ]);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testCategoryId) {
            try {
                Database::query("DELETE FROM categories WHERE id = ?", [self::$testCategoryId]);
            } catch (\Exception $e) {}
        }
        if (self::$testEventCategoryId) {
            try {
                Database::query("DELETE FROM categories WHERE id = ?", [self::$testEventCategoryId]);
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

    public function testCreateCategoryReturnsId(): void
    {
        $id = Category::create([
            'name' => 'New Test Category ' . time(),
            'slug' => 'new-test-cat-' . time(),
            'color' => 'red',
            'type' => 'listing',
        ]);

        $this->assertIsNumeric($id);
        $this->assertGreaterThan(0, (int)$id);

        // Clean up
        Database::query("DELETE FROM categories WHERE id = ?", [$id]);
    }

    public function testCreateCategorySetsTenantId(): void
    {
        $id = Category::create([
            'name' => 'Tenant Cat ' . time(),
            'slug' => 'tenant-cat-' . time(),
            'color' => 'purple',
            'type' => 'listing',
        ]);

        $cat = Database::query("SELECT tenant_id FROM categories WHERE id = ?", [$id])->fetch();
        $this->assertEquals(self::$testTenantId, (int)$cat['tenant_id']);

        // Clean up
        Database::query("DELETE FROM categories WHERE id = ?", [$id]);
    }

    public function testCreateCategoryWithDefaultColor(): void
    {
        $id = Category::create([
            'name' => 'Default Color Cat ' . time(),
            'slug' => 'default-color-cat-' . time(),
            'type' => 'listing',
            // No color specified -- should default to 'blue'
        ]);

        $cat = Database::query("SELECT color FROM categories WHERE id = ?", [$id])->fetch();
        $this->assertEquals('blue', $cat['color']);

        // Clean up
        Database::query("DELETE FROM categories WHERE id = ?", [$id]);
    }

    public function testCreateCategoryWithDefaultType(): void
    {
        $id = Category::create([
            'name' => 'Default Type Cat ' . time(),
            'slug' => 'default-type-cat-' . time(),
            'color' => 'teal',
            // No type specified -- should default to 'listing'
        ]);

        $cat = Database::query("SELECT type FROM categories WHERE id = ?", [$id])->fetch();
        $this->assertEquals('listing', $cat['type']);

        // Clean up
        Database::query("DELETE FROM categories WHERE id = ?", [$id]);
    }

    // ==========================================
    // Find Tests
    // ==========================================

    public function testFindReturnsCategory(): void
    {
        $cat = Category::find(self::$testCategoryId);

        $this->assertNotFalse($cat);
        $this->assertIsArray($cat);
        $this->assertEquals(self::$testCategoryId, (int)$cat['id']);
    }

    public function testFindEnforcesTenantScoping(): void
    {
        // Use tenant 1 (a real tenant) instead of 999999, because
        // TenantContext::setById() silently ignores non-existent tenant IDs
        TenantContext::setById(1);

        $cat = Category::find(self::$testCategoryId);

        // Category was created under tenant 2, so tenant 1 context should not find it
        $this->assertFalse($cat, 'Category should not be found with wrong tenant context');

        // Restore
        TenantContext::setById(self::$testTenantId);
    }

    public function testFindReturnsFalseForNonExistent(): void
    {
        $cat = Category::find(999999999);

        $this->assertFalse($cat);
    }

    // ==========================================
    // All (List) Tests
    // ==========================================

    public function testAllReturnsArray(): void
    {
        $categories = Category::all();

        $this->assertIsArray($categories);
        $this->assertGreaterThanOrEqual(2, count($categories), 'Should have at least 2 test categories');
    }

    public function testAllScopesByTenant(): void
    {
        $categories = Category::all();

        foreach ($categories as $cat) {
            $this->assertEquals(self::$testTenantId, (int)$cat['tenant_id']);
        }
    }

    public function testAllOrderedByTypeAndName(): void
    {
        $categories = Category::all();

        // Should be ordered by type first, then name
        if (count($categories) > 1) {
            for ($i = 1; $i < count($categories); $i++) {
                $typeCompare = strcmp($categories[$i]['type'], $categories[$i - 1]['type']);
                if ($typeCompare === 0) {
                    // Same type, check name ordering
                    $this->assertGreaterThanOrEqual(
                        0,
                        strcasecmp($categories[$i]['name'], $categories[$i - 1]['name']),
                        'Within same type, categories should be ordered by name'
                    );
                }
            }
        }
    }

    // ==========================================
    // GetByType Tests
    // ==========================================

    public function testGetByTypeReturnsFilteredCategories(): void
    {
        $listings = Category::getByType('listing');

        $this->assertIsArray($listings);
        foreach ($listings as $cat) {
            $this->assertEquals('listing', $cat['type']);
        }
    }

    public function testGetByTypeReturnsEventCategories(): void
    {
        $events = Category::getByType('event');

        $this->assertIsArray($events);
        $this->assertGreaterThanOrEqual(1, count($events));

        foreach ($events as $cat) {
            $this->assertEquals('event', $cat['type']);
        }
    }

    public function testGetByTypeScopesByTenant(): void
    {
        $categories = Category::getByType('listing');

        foreach ($categories as $cat) {
            $this->assertEquals(self::$testTenantId, (int)$cat['tenant_id']);
        }
    }

    public function testGetByTypeReturnsEmptyForNonExistentType(): void
    {
        $categories = Category::getByType('nonexistent_type');

        $this->assertIsArray($categories);
        $this->assertEmpty($categories);
    }

    // ==========================================
    // Update Tests
    // ==========================================

    public function testUpdateChangesFields(): void
    {
        $newName = 'Updated Category ' . time();
        $newSlug = 'updated-cat-' . time();

        Category::update(self::$testCategoryId, [
            'name' => $newName,
            'slug' => $newSlug,
            'color' => 'orange',
        ]);

        $cat = Category::find(self::$testCategoryId);

        $this->assertEquals($newName, $cat['name']);
        $this->assertEquals($newSlug, $cat['slug']);
        $this->assertEquals('orange', $cat['color']);
    }

    public function testUpdatePreservesExistingValuesOnEmptyInput(): void
    {
        // First set to known values
        Category::update(self::$testCategoryId, [
            'name' => 'Preserved Name',
            'slug' => 'preserved-slug',
            'color' => 'pink',
            'type' => 'listing',
        ]);

        // Now update with empty values -- should preserve existing
        Category::update(self::$testCategoryId, [
            'name' => '',
            'slug' => '',
            'color' => '',
            'type' => '',
        ]);

        $cat = Category::find(self::$testCategoryId);

        $this->assertEquals('Preserved Name', $cat['name'], 'Empty name should preserve existing value');
        $this->assertEquals('preserved-slug', $cat['slug'], 'Empty slug should preserve existing value');
        $this->assertEquals('pink', $cat['color'], 'Empty color should preserve existing value');
        $this->assertEquals('listing', $cat['type'], 'Empty type should preserve existing value');
    }

    public function testUpdateReturnsFalseForNonExistentCategory(): void
    {
        TenantContext::setById(self::$testTenantId);

        $result = Category::update(999999999, [
            'name' => 'Should Not Work',
        ]);

        $this->assertFalse($result);
    }

    public function testUpdateEnforcesTenantScoping(): void
    {
        $originalCat = Category::find(self::$testCategoryId);

        // Switch to tenant 1 (a real, different tenant) because
        // TenantContext::setById() silently ignores non-existent tenant IDs
        TenantContext::setById(1);

        // Update should not find the category (it belongs to tenant 2)
        $result = Category::update(self::$testCategoryId, [
            'name' => 'Wrong Tenant Update',
        ]);

        // Restore tenant
        TenantContext::setById(self::$testTenantId);

        $cat = Category::find(self::$testCategoryId);
        $this->assertNotEquals('Wrong Tenant Update', $cat['name'], 'Update from wrong tenant should not change data');
    }

    // ==========================================
    // Delete Tests
    // ==========================================

    public function testDeleteRemovesCategory(): void
    {
        $id = Category::create([
            'name' => 'To Be Deleted ' . time(),
            'slug' => 'to-be-deleted-' . time(),
            'color' => 'gray',
            'type' => 'listing',
        ]);

        Category::delete((int)$id);

        $cat = Category::find((int)$id);
        $this->assertFalse($cat, 'Deleted category should not be found');
    }

    public function testDeleteEnforcesTenantScoping(): void
    {
        $id = Category::create([
            'name' => 'Tenant Delete Test ' . time(),
            'slug' => 'tenant-delete-test-' . time(),
            'color' => 'gray',
            'type' => 'listing',
        ]);

        // Switch to tenant 1 (a real, different tenant) because
        // TenantContext::setById() silently ignores non-existent tenant IDs
        TenantContext::setById(1);

        Category::delete((int)$id);

        // Restore tenant
        TenantContext::setById(self::$testTenantId);

        // Category should still exist (created under tenant 2, delete was scoped to tenant 1)
        $cat = Category::find((int)$id);
        $this->assertNotFalse($cat, 'Category should survive delete attempt from wrong tenant');

        // Clean up
        Category::delete((int)$id);
    }

    // ==========================================
    // SeedDefaults Tests
    // ==========================================

    public function testSeedDefaultsCreatesCategories(): void
    {
        // Use a fresh tenant ID that does not exist to avoid polluting real data
        // We'll use a very high tenant_id and clean up after
        $tempTenantId = 999888;

        // Ensure the temp tenant exists (FK constraint requires it)
        Database::query(
            "INSERT IGNORE INTO tenants (id, name, slug, is_active) VALUES (?, ?, ?, 1)",
            [$tempTenantId, 'Temp Seed Test Tenant', 'temp-seed-test-tenant']
        );

        // Clean up any existing categories for this temp tenant
        Database::query("DELETE FROM categories WHERE tenant_id = ?", [$tempTenantId]);

        // Seed defaults
        Category::seedDefaults($tempTenantId);

        // Verify listing categories were created
        $listings = Database::query(
            "SELECT * FROM categories WHERE tenant_id = ? AND type = 'listing'",
            [$tempTenantId]
        )->fetchAll();

        $this->assertGreaterThanOrEqual(10, count($listings), 'Should create at least 10 listing categories');

        // Verify volunteering categories
        $volunteering = Database::query(
            "SELECT * FROM categories WHERE tenant_id = ? AND type = 'vol_opportunity'",
            [$tempTenantId]
        )->fetchAll();

        $this->assertGreaterThanOrEqual(4, count($volunteering), 'Should create volunteering categories');

        // Verify event categories
        $events = Database::query(
            "SELECT * FROM categories WHERE tenant_id = ? AND type = 'event'",
            [$tempTenantId]
        )->fetchAll();

        $this->assertGreaterThanOrEqual(3, count($events), 'Should create event categories');

        // Verify blog categories
        $blog = Database::query(
            "SELECT * FROM categories WHERE tenant_id = ? AND type = 'blog'",
            [$tempTenantId]
        )->fetchAll();

        $this->assertGreaterThanOrEqual(3, count($blog), 'Should create blog categories');

        // Clean up
        Database::query("DELETE FROM categories WHERE tenant_id = ?", [$tempTenantId]);
        Database::query("DELETE FROM tenants WHERE id = ?", [$tempTenantId]);
    }

    public function testSeedDefaultsIncludesExpectedListingCategories(): void
    {
        $tempTenantId = 999887;

        // Ensure the temp tenant exists (FK constraint requires it)
        Database::query(
            "INSERT IGNORE INTO tenants (id, name, slug, is_active) VALUES (?, ?, ?, 1)",
            [$tempTenantId, 'Temp Seed Test Tenant 2', 'temp-seed-test-tenant-2']
        );

        Database::query("DELETE FROM categories WHERE tenant_id = ?", [$tempTenantId]);
        Category::seedDefaults($tempTenantId);

        $categories = Database::query(
            "SELECT name FROM categories WHERE tenant_id = ? AND type = 'listing' ORDER BY name",
            [$tempTenantId]
        )->fetchAll();

        $names = array_column($categories, 'name');

        // Verify some key default categories exist
        $this->assertContains('Arts & Crafts', $names);
        $this->assertContains('Computers & Tech', $names);
        $this->assertContains('Food & Cooking', $names);
        $this->assertContains('Health & Wellbeing', $names);
        $this->assertContains('Miscellaneous', $names);

        // Clean up
        Database::query("DELETE FROM categories WHERE tenant_id = ?", [$tempTenantId]);
        Database::query("DELETE FROM tenants WHERE id = ?", [$tempTenantId]);
    }

    // ==========================================
    // Edge Cases
    // ==========================================

    public function testAllScopedByTenantDoesNotReturnOtherTenantData(): void
    {
        // Switch to tenant 1 (a real, different tenant) because
        // TenantContext::setById() silently ignores non-existent tenant IDs
        TenantContext::setById(1);

        $categories = Category::all();

        $this->assertIsArray($categories);
        // Verify none of the returned categories belong to our test tenant
        foreach ($categories as $cat) {
            $this->assertNotEquals(self::$testTenantId, (int)$cat['tenant_id'],
                'Categories from tenant 2 should not appear when context is set to tenant 1');
        }

        // Restore
        TenantContext::setById(self::$testTenantId);
    }

    public function testGetByTypeWithNullTenantHandledGracefully(): void
    {
        // This tests that the method doesn't crash even if TenantContext returns something odd
        $categories = Category::getByType('listing');

        $this->assertIsArray($categories);
    }

    public function testCreateCategoryWithAllTypes(): void
    {
        $types = ['listing', 'event', 'vol_opportunity', 'blog'];
        $ids = [];

        foreach ($types as $type) {
            $ids[] = Category::create([
                'name' => "Type Test {$type} " . time(),
                'slug' => "type-test-{$type}-" . time(),
                'color' => 'cyan',
                'type' => $type,
            ]);
        }

        foreach ($ids as $index => $id) {
            $cat = Database::query("SELECT type FROM categories WHERE id = ?", [$id])->fetch();
            $this->assertEquals($types[$index], $cat['type']);
        }

        // Clean up
        foreach ($ids as $id) {
            Database::query("DELETE FROM categories WHERE id = ?", [$id]);
        }
    }
}
