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
use Nexus\Models\Menu;

/**
 * Menu Model Tests
 *
 * Tests menu CRUD, slug lookup, location-based retrieval,
 * pagination, counting, active toggling, and tenant scoping.
 */
class MenuTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);
    }

    public static function tearDownAfterClass(): void
    {
        try {
            // Clean up test menus and their items
            $menus = Database::query("SELECT id FROM menus WHERE tenant_id = ? AND name LIKE 'Test Menu%'", [2])->fetchAll();
            foreach ($menus as $menu) {
                Database::query("DELETE FROM menu_items WHERE menu_id = ?", [$menu['id']]);
            }
            Database::query("DELETE FROM menus WHERE tenant_id = ? AND name LIKE 'Test Menu%'", [2]);
        } catch (\Exception $e) {
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

    public function testCreateReturnsId(): void
    {
        $id = Menu::create([
            'name' => 'Test Menu Create ' . time(),
            'slug' => 'test-menu-create-' . time(),
            'location' => 'header',
        ]);

        $this->assertNotEmpty($id);
        $this->assertGreaterThan(0, (int)$id);
    }

    // ==========================================
    // Find Tests
    // ==========================================

    public function testFindReturnsMenu(): void
    {
        $slug = 'test-menu-find-' . time();
        $id = Menu::create([
            'name' => 'Test Menu Find ' . time(),
            'slug' => $slug,
            'location' => 'footer',
        ]);

        $menu = Menu::find($id);
        $this->assertNotFalse($menu);
        $this->assertEquals($slug, $menu['slug']);
    }

    public function testFindReturnsFalseForNonExistent(): void
    {
        $menu = Menu::find(999999999);
        $this->assertFalse($menu);
    }

    // ==========================================
    // FindBySlug Tests
    // ==========================================

    public function testFindBySlugReturnsMenu(): void
    {
        $slug = 'test-menu-slug-' . time();
        Menu::create([
            'name' => 'Test Menu Slug ' . time(),
            'slug' => $slug,
            'location' => 'sidebar',
        ]);

        $menu = Menu::findBySlug($slug);
        $this->assertNotFalse($menu);
        $this->assertEquals($slug, $menu['slug']);
    }

    public function testFindBySlugReturnsFalseForNonExistent(): void
    {
        $menu = Menu::findBySlug('nonexistent-menu-slug-' . time());
        $this->assertFalse($menu);
    }

    // ==========================================
    // All Tests
    // ==========================================

    public function testAllReturnsArray(): void
    {
        $all = Menu::all();
        $this->assertIsArray($all);
    }

    // ==========================================
    // Paginate Tests
    // ==========================================

    public function testPaginateReturnsStructure(): void
    {
        $result = Menu::paginate();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertArrayHasKey('current_page', $result['pagination']);
        $this->assertArrayHasKey('total', $result['pagination']);
    }

    // ==========================================
    // GetByLocation Tests
    // ==========================================

    public function testGetByLocationReturnsArray(): void
    {
        $menus = Menu::getByLocation('header');
        $this->assertIsArray($menus);
    }

    // ==========================================
    // Count Tests
    // ==========================================

    public function testCountReturnsNumeric(): void
    {
        $count = Menu::count();
        $this->assertIsNumeric($count);
    }

    // ==========================================
    // Update Tests
    // ==========================================

    public function testUpdateChangesFields(): void
    {
        $id = Menu::create([
            'name' => 'Test Menu Update ' . time(),
            'slug' => 'test-menu-update-' . time(),
            'location' => 'header',
        ]);

        Menu::update($id, [
            'name' => 'Test Menu Updated',
            'slug' => 'test-menu-updated-' . time(),
            'location' => 'footer',
        ]);

        $menu = Menu::find($id);
        $this->assertEquals('Test Menu Updated', $menu['name']);
        $this->assertEquals('footer', $menu['location']);
    }

    // ==========================================
    // Delete Tests
    // ==========================================

    public function testDeleteRemovesMenu(): void
    {
        $id = Menu::create([
            'name' => 'Test Menu Delete ' . time(),
            'slug' => 'test-menu-delete-' . time(),
            'location' => 'header',
        ]);

        Menu::delete($id);

        $menu = Menu::find($id);
        $this->assertFalse($menu);
    }

    // ==========================================
    // ToggleActive Tests
    // ==========================================

    public function testToggleActiveFlipsStatus(): void
    {
        $id = Menu::create([
            'name' => 'Test Menu Toggle ' . time(),
            'slug' => 'test-menu-toggle-' . time(),
            'location' => 'header',
            'is_active' => 1,
        ]);

        Menu::toggleActive($id);
        $menu = Menu::find($id);
        $this->assertEquals(0, (int)$menu['is_active']);

        Menu::toggleActive($id);
        $menu = Menu::find($id);
        $this->assertEquals(1, (int)$menu['is_active']);
    }

    // ==========================================
    // GetWithItems Tests
    // ==========================================

    public function testGetWithItemsReturnsNullForNonExistent(): void
    {
        $result = Menu::getWithItems(999999999);
        $this->assertNull($result);
    }

    public function testGetWithItemsIncludesItemsArray(): void
    {
        $id = Menu::create([
            'name' => 'Test Menu WithItems ' . time(),
            'slug' => 'test-menu-withitems-' . time(),
            'location' => 'header',
        ]);

        $result = Menu::getWithItems($id);
        $this->assertNotNull($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertIsArray($result['items']);
    }
}
