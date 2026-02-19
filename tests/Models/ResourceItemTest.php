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
use Nexus\Models\ResourceItem;

/**
 * ResourceItem Model Tests
 *
 * Tests resource CRUD operations, category association, download tracking,
 * and tenant scoping.
 */
class ResourceItemTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testCategoryId = null;
    protected static ?int $testResourceId = null;

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
                "resource_model_test_{$timestamp}@test.com",
                "resource_model_test_{$timestamp}",
                'Resource',
                'Tester',
                'Resource Tester',
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
                Database::query(
                    "INSERT INTO categories (tenant_id, name, slug, created_at) VALUES (?, ?, ?, NOW())",
                    [self::$testTenantId, 'Test Resource Category', 'test-resource-category-' . $timestamp]
                );
                self::$testCategoryId = (int)Database::getInstance()->lastInsertId();
            }
        } catch (\Exception $e) {
            self::$testCategoryId = null;
        }

        // Create test resource
        Database::query(
            "INSERT INTO resources (tenant_id, user_id, title, description, file_path, file_type, file_size, category_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                self::$testTenantId,
                self::$testUserId,
                "Test Resource {$timestamp}",
                'A test resource description',
                '/uploads/resources/test-file.pdf',
                'application/pdf',
                1024,
                self::$testCategoryId
            ]
        );
        self::$testResourceId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM resources WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {
            }
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

    public function testCreateResourceReturnsId(): void
    {
        $id = ResourceItem::create(
            self::$testTenantId,
            self::$testUserId,
            'New Resource',
            'Resource description',
            '/uploads/resources/new-file.pdf',
            'application/pdf',
            2048
        );

        $this->assertIsNumeric($id);
        $this->assertGreaterThan(0, (int)$id);
    }

    public function testCreateResourceWithCategory(): void
    {
        if (!self::$testCategoryId) {
            $this->markTestSkipped('No test category available');
        }

        $id = ResourceItem::create(
            self::$testTenantId,
            self::$testUserId,
            'Categorized Resource',
            'Has a category',
            '/uploads/resources/categorized.pdf',
            'application/pdf',
            512,
            self::$testCategoryId
        );

        $resource = ResourceItem::find($id);
        $this->assertNotFalse($resource);
        $this->assertEquals(self::$testCategoryId, $resource['category_id']);
    }

    public function testCreateResourceWithoutCategory(): void
    {
        $id = ResourceItem::create(
            self::$testTenantId,
            self::$testUserId,
            'Uncategorized Resource',
            'No category',
            '/uploads/resources/uncategorized.pdf',
            'application/pdf',
            256,
            null
        );

        $resource = ResourceItem::find($id);
        $this->assertNotFalse($resource);
        $this->assertNull($resource['category_id']);
    }

    // ==========================================
    // Find Tests
    // ==========================================

    public function testFindReturnsResource(): void
    {
        $resource = ResourceItem::find(self::$testResourceId);

        $this->assertNotFalse($resource);
        $this->assertIsArray($resource);
        $this->assertEquals(self::$testResourceId, $resource['id']);
        $this->assertEquals(self::$testTenantId, $resource['tenant_id']);
    }

    public function testFindReturnsFalseForNonExistent(): void
    {
        $resource = ResourceItem::find(999999999);

        $this->assertFalse($resource);
    }

    public function testFindIncludesAllFields(): void
    {
        $resource = ResourceItem::find(self::$testResourceId);

        $this->assertArrayHasKey('title', $resource);
        $this->assertArrayHasKey('description', $resource);
        $this->assertArrayHasKey('file_path', $resource);
        $this->assertArrayHasKey('file_type', $resource);
        $this->assertArrayHasKey('file_size', $resource);
        $this->assertArrayHasKey('tenant_id', $resource);
        $this->assertArrayHasKey('user_id', $resource);
    }

    // ==========================================
    // All (List) Tests — Tenant Scoping
    // ==========================================

    public function testAllReturnsArrayForTenant(): void
    {
        $resources = ResourceItem::all(self::$testTenantId);

        $this->assertIsArray($resources);
        $this->assertGreaterThanOrEqual(1, count($resources));
    }

    public function testAllIncludesUploaderName(): void
    {
        $resources = ResourceItem::all(self::$testTenantId);

        $this->assertNotEmpty($resources);
        $this->assertArrayHasKey('uploader_name', $resources[0]);
    }

    public function testAllFiltersByCategory(): void
    {
        if (!self::$testCategoryId) {
            $this->markTestSkipped('No test category available');
        }

        $resources = ResourceItem::all(self::$testTenantId, self::$testCategoryId);

        $this->assertIsArray($resources);
        foreach ($resources as $resource) {
            $this->assertEquals(self::$testCategoryId, $resource['category_id']);
        }
    }

    public function testAllIncludesCategoryInfo(): void
    {
        if (!self::$testCategoryId) {
            $this->markTestSkipped('No test category available');
        }

        $resources = ResourceItem::all(self::$testTenantId, self::$testCategoryId);

        $this->assertNotEmpty($resources);
        $this->assertArrayHasKey('category_name', $resources[0]);
        $this->assertArrayHasKey('category_color', $resources[0]);
    }

    public function testAllScopesByTenant(): void
    {
        $resources = ResourceItem::all(self::$testTenantId);

        foreach ($resources as $resource) {
            $this->assertEquals(self::$testTenantId, $resource['tenant_id']);
        }
    }

    public function testAllReturnsEmptyForNonExistentTenant(): void
    {
        $resources = ResourceItem::all(999999);

        $this->assertIsArray($resources);
        $this->assertEmpty($resources);
    }

    // ==========================================
    // Update Tests
    // ==========================================

    public function testUpdateChangesFields(): void
    {
        $id = ResourceItem::create(
            self::$testTenantId,
            self::$testUserId,
            'Original Title',
            'Original Description',
            '/uploads/resources/update-test.pdf',
            'application/pdf',
            1024
        );

        ResourceItem::update($id, 'Updated Title', 'Updated Description', self::$testCategoryId);

        $resource = ResourceItem::find($id);
        $this->assertEquals('Updated Title', $resource['title']);
        $this->assertEquals('Updated Description', $resource['description']);
    }

    public function testUpdateChangesCategory(): void
    {
        if (!self::$testCategoryId) {
            $this->markTestSkipped('No test category available');
        }

        $id = ResourceItem::create(
            self::$testTenantId,
            self::$testUserId,
            'Category Change Test',
            'Description',
            '/uploads/resources/cat-change.pdf',
            'application/pdf',
            512,
            null
        );

        ResourceItem::update($id, 'Category Change Test', 'Description', self::$testCategoryId);

        $resource = ResourceItem::find($id);
        $this->assertEquals(self::$testCategoryId, $resource['category_id']);
    }

    // ==========================================
    // Delete Tests
    // ==========================================

    public function testDeleteRemovesResource(): void
    {
        $id = ResourceItem::create(
            self::$testTenantId,
            self::$testUserId,
            'To Be Deleted',
            'Will be deleted',
            '/uploads/resources/nonexistent-delete-test.pdf',
            'application/pdf',
            128
        );

        $this->assertNotFalse(ResourceItem::find($id));

        ResourceItem::delete($id);

        $resource = ResourceItem::find($id);
        $this->assertFalse($resource);
    }

    public function testDeleteNonExistentResourceDoesNotThrow(): void
    {
        // Should not throw an exception
        ResourceItem::delete(999999999);
        $this->assertTrue(true);
    }

    // ==========================================
    // Increment Download Tests
    // ==========================================

    public function testIncrementDownloadIncreasesCount(): void
    {
        $id = ResourceItem::create(
            self::$testTenantId,
            self::$testUserId,
            'Download Test',
            'Testing download counter',
            '/uploads/resources/download-test.pdf',
            'application/pdf',
            1024
        );

        $before = ResourceItem::find($id);
        $initialDownloads = (int)($before['downloads'] ?? 0);

        ResourceItem::incrementDownload($id);

        $after = ResourceItem::find($id);
        $this->assertEquals($initialDownloads + 1, (int)$after['downloads']);
    }

    public function testIncrementDownloadMultipleTimes(): void
    {
        $id = ResourceItem::create(
            self::$testTenantId,
            self::$testUserId,
            'Multi Download Test',
            'Testing multiple increments',
            '/uploads/resources/multi-download.pdf',
            'application/pdf',
            1024
        );

        ResourceItem::incrementDownload($id);
        ResourceItem::incrementDownload($id);
        ResourceItem::incrementDownload($id);

        $resource = ResourceItem::find($id);
        $this->assertEquals(3, (int)$resource['downloads']);
    }

    // ==========================================
    // Edge Cases
    // ==========================================

    public function testCreateWithSpecialCharactersInTitle(): void
    {
        $id = ResourceItem::create(
            self::$testTenantId,
            self::$testUserId,
            'Resource with "quotes" & <tags> and émojis',
            'Description with special chars: <script>alert("xss")</script>',
            '/uploads/resources/special-chars.pdf',
            'application/pdf',
            256
        );

        $resource = ResourceItem::find($id);
        $this->assertNotFalse($resource);
        $this->assertStringContainsString('quotes', $resource['title']);
    }

    public function testCreateWithEmptyDescription(): void
    {
        $id = ResourceItem::create(
            self::$testTenantId,
            self::$testUserId,
            'No Description Resource',
            '',
            '/uploads/resources/no-desc.pdf',
            'application/pdf',
            64
        );

        $resource = ResourceItem::find($id);
        $this->assertNotFalse($resource);
        $this->assertEquals('', $resource['description']);
    }
}
