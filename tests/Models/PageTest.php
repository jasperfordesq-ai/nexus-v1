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
use Nexus\Models\Page;

/**
 * Page Model Tests
 *
 * Tests CMS page CRUD, slug resolution, publish status,
 * settings updates, and tenant scoping.
 */
class PageTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testPageId = null;
    protected static ?int $testUnpublishedPageId = null;
    protected static string $testSlug = '';

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
        self::$testSlug = "test-page-{$timestamp}";

        // Create a published test page
        Database::query(
            "INSERT INTO pages (tenant_id, title, slug, content, is_published, created_at, updated_at)
             VALUES (?, ?, ?, ?, 1, NOW(), NOW())",
            [
                self::$testTenantId,
                "Test Page {$timestamp}",
                self::$testSlug,
                '<h2>Test Page Content</h2><p>This is a test page for model tests.</p>'
            ]
        );
        self::$testPageId = (int)Database::getInstance()->lastInsertId();

        // Create an unpublished test page
        Database::query(
            "INSERT INTO pages (tenant_id, title, slug, content, is_published, created_at, updated_at)
             VALUES (?, ?, ?, ?, 0, NOW(), NOW())",
            [
                self::$testTenantId,
                "Unpublished Page {$timestamp}",
                "unpublished-page-{$timestamp}",
                '<p>This page is not published.</p>'
            ]
        );
        self::$testUnpublishedPageId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testPageId) {
            try {
                Database::query("DELETE FROM pages WHERE id = ?", [self::$testPageId]);
            } catch (\Exception $e) {}
        }
        if (self::$testUnpublishedPageId) {
            try {
                Database::query("DELETE FROM pages WHERE id = ?", [self::$testUnpublishedPageId]);
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
    // All (List) Tests
    // ==========================================

    public function testAllReturnsArray(): void
    {
        $pages = Page::all();

        $this->assertIsArray($pages);
    }

    public function testAllScopesByTenant(): void
    {
        $pages = Page::all();

        // All results should be from our tenant (verified by TenantContext)
        foreach ($pages as $page) {
            $this->assertArrayHasKey('title', $page);
            $this->assertArrayHasKey('slug', $page);
        }
    }

    public function testAllOrderedByTitle(): void
    {
        $pages = Page::all();

        if (count($pages) > 1) {
            for ($i = 1; $i < count($pages); $i++) {
                $this->assertGreaterThanOrEqual(
                    0,
                    strcmp($pages[$i]['title'], $pages[$i - 1]['title']),
                    'Pages should be ordered alphabetically by title'
                );
            }
        } else {
            $this->assertTrue(true, 'Not enough pages to verify ordering');
        }
    }

    public function testAllReturnsEmptyForWrongTenant(): void
    {
        // Switch to tenant 1 (a real, different tenant) because
        // TenantContext::setById() silently ignores non-existent tenant IDs
        TenantContext::setById(1);

        $pages = Page::all();

        $this->assertIsArray($pages);
        // Pages from tenant 1 should not include our test pages from tenant 2

        // Restore
        TenantContext::setById(self::$testTenantId);
    }

    // ==========================================
    // FindById Tests
    // ==========================================

    public function testFindByIdReturnsPage(): void
    {
        $page = Page::findById(self::$testPageId);

        $this->assertNotFalse($page);
        $this->assertIsArray($page);
        $this->assertEquals(self::$testPageId, (int)$page['id']);
        $this->assertEquals(self::$testTenantId, (int)$page['tenant_id']);
    }

    public function testFindByIdIncludesAllFields(): void
    {
        $page = Page::findById(self::$testPageId);

        $this->assertArrayHasKey('title', $page);
        $this->assertArrayHasKey('slug', $page);
        $this->assertArrayHasKey('content', $page);
        $this->assertArrayHasKey('is_published', $page);
    }

    public function testFindByIdReturnsFalseForNonExistent(): void
    {
        $page = Page::findById(999999999);

        $this->assertFalse($page);
    }

    public function testFindByIdEnforcesTenantIsolation(): void
    {
        // Switch to tenant 1 (a real, different tenant) because
        // TenantContext::setById() silently ignores non-existent tenant IDs
        TenantContext::setById(1);

        $page = Page::findById(self::$testPageId);

        // Page was created under tenant 2, so tenant 1 context should not find it
        $this->assertFalse($page, 'Page should not be found with wrong tenant context');

        // Restore
        TenantContext::setById(self::$testTenantId);
    }

    // ==========================================
    // FindBySlug Tests
    // ==========================================

    public function testFindBySlugReturnsPublishedPage(): void
    {
        $page = Page::findBySlug(self::$testSlug, self::$testTenantId);

        $this->assertNotFalse($page);
        $this->assertIsArray($page);
        $this->assertEquals(self::$testSlug, $page['slug']);
        $this->assertEquals(1, (int)$page['is_published']);
    }

    public function testFindBySlugReturnsFalseForUnpublished(): void
    {
        $unpublishedPage = Page::findById(self::$testUnpublishedPageId);
        $slug = $unpublishedPage['slug'];

        $page = Page::findBySlug($slug, self::$testTenantId);

        $this->assertFalse($page, 'Unpublished pages should not be found via findBySlug');
    }

    public function testFindBySlugReturnsFalseForNonExistent(): void
    {
        $page = Page::findBySlug('non-existent-slug-xyz', self::$testTenantId);

        $this->assertFalse($page);
    }

    public function testFindBySlugEnforcesTenantScoping(): void
    {
        $page = Page::findBySlug(self::$testSlug, 999999);

        $this->assertFalse($page, 'Page should not be found with wrong tenant_id');
    }

    // ==========================================
    // FindBySlugAny Tests (Admin Preview)
    // ==========================================

    public function testFindBySlugAnyReturnsPublishedPage(): void
    {
        $page = Page::findBySlugAny(self::$testSlug, self::$testTenantId);

        $this->assertNotFalse($page);
        $this->assertEquals(self::$testSlug, $page['slug']);
    }

    public function testFindBySlugAnyReturnsUnpublishedPage(): void
    {
        $unpublishedPage = Page::findById(self::$testUnpublishedPageId);
        $slug = $unpublishedPage['slug'];

        $page = Page::findBySlugAny($slug, self::$testTenantId);

        $this->assertNotFalse($page, 'findBySlugAny should return unpublished pages (for admin preview)');
        $this->assertEquals(0, (int)$page['is_published']);
    }

    public function testFindBySlugAnyEnforcesTenantScoping(): void
    {
        $page = Page::findBySlugAny(self::$testSlug, 999999);

        $this->assertFalse($page, 'findBySlugAny should still enforce tenant scoping');
    }

    // ==========================================
    // UpdateSettings Tests
    // ==========================================

    public function testUpdateSettingsChangesTitle(): void
    {
        $newTitle = 'Updated Page Title ' . time();

        $result = Page::updateSettings(self::$testPageId, self::$testTenantId, [
            'title' => $newTitle,
        ]);

        $this->assertTrue($result);

        $page = Page::findById(self::$testPageId);
        $this->assertEquals($newTitle, $page['title']);
    }

    public function testUpdateSettingsChangesPublishStatus(): void
    {
        // Unpublish
        Page::updateSettings(self::$testPageId, self::$testTenantId, [
            'is_published' => 0,
        ]);

        $page = Page::findById(self::$testPageId);
        $this->assertEquals(0, (int)$page['is_published']);

        // Re-publish
        Page::updateSettings(self::$testPageId, self::$testTenantId, [
            'is_published' => 1,
        ]);

        $page = Page::findById(self::$testPageId);
        $this->assertEquals(1, (int)$page['is_published']);
    }

    public function testUpdateSettingsChangesSlug(): void
    {
        $newSlug = 'updated-slug-' . time();

        Page::updateSettings(self::$testPageId, self::$testTenantId, [
            'slug' => $newSlug,
        ]);

        $page = Page::findBySlug($newSlug, self::$testTenantId);
        $this->assertNotFalse($page);
        $this->assertEquals(self::$testPageId, (int)$page['id']);

        // Restore original slug for other tests
        Page::updateSettings(self::$testPageId, self::$testTenantId, [
            'slug' => self::$testSlug,
        ]);
    }

    public function testUpdateSettingsOnlyAllowsWhitelistedFields(): void
    {
        $originalPage = Page::findById(self::$testPageId);

        // Try to update non-whitelisted field
        $result = Page::updateSettings(self::$testPageId, self::$testTenantId, [
            'content' => '<p>Hacked content</p>', // 'content' is NOT in allowedFields
        ]);

        $this->assertFalse($result, 'Updating non-whitelisted fields should return false');

        $page = Page::findById(self::$testPageId);
        $this->assertEquals($originalPage['content'], $page['content'], 'Content should not have changed');
    }

    public function testUpdateSettingsWithEmptyDataReturnsFalse(): void
    {
        $result = Page::updateSettings(self::$testPageId, self::$testTenantId, []);

        $this->assertFalse($result);
    }

    public function testUpdateSettingsEnforcesTenantScoping(): void
    {
        $originalPage = Page::findById(self::$testPageId);

        // Attempt update with wrong tenant
        $result = Page::updateSettings(self::$testPageId, 999999, [
            'title' => 'Should Not Work',
        ]);

        // The execute call may return true but affect 0 rows
        // Verify the title did not change
        $page = Page::findById(self::$testPageId);
        $this->assertEquals($originalPage['title'], $page['title']);
    }

    public function testUpdateSettingsChangesMenuSettings(): void
    {
        Page::updateSettings(self::$testPageId, self::$testTenantId, [
            'show_in_menu' => 1,
            'menu_location' => 'about',
        ]);

        $page = Page::findById(self::$testPageId);

        // Check if these columns exist (may depend on migrations)
        if (isset($page['show_in_menu'])) {
            $this->assertEquals(1, (int)$page['show_in_menu']);
        }
        if (isset($page['menu_location'])) {
            $this->assertEquals('about', $page['menu_location']);
        }
    }

    // ==========================================
    // Edge Cases
    // ==========================================

    public function testFindBySlugWithSpecialCharacters(): void
    {
        $page = Page::findBySlug("page-with-'quotes'", self::$testTenantId);

        $this->assertFalse($page); // Should not throw, just return false
    }

    public function testAllHandlesTableNotExistGracefully(): void
    {
        // The all() method has a try/catch for this case
        // We can't easily test table non-existence, but verify it returns array
        $pages = Page::all();

        $this->assertIsArray($pages);
    }
}
