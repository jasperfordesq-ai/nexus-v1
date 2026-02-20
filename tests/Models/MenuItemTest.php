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
use Nexus\Models\MenuItem;

/**
 * MenuItem Model Tests
 *
 * Tests menu item CRUD, hierarchical retrieval, sort order,
 * counting, visibility rules, and URL resolution.
 */
class MenuItemTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testMenuId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);

        $timestamp = time();

        // Create a test menu
        self::$testMenuId = (int)Menu::create([
            'name' => 'Test Menu Items ' . $timestamp,
            'slug' => 'test-menu-items-' . $timestamp,
            'location' => 'header',
        ]);
    }

    public static function tearDownAfterClass(): void
    {
        try {
            if (self::$testMenuId) {
                Database::query("DELETE FROM menu_items WHERE menu_id = ?", [self::$testMenuId]);
                Database::query("DELETE FROM menus WHERE id = ?", [self::$testMenuId]);
            }
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
        $id = MenuItem::create([
            'menu_id' => self::$testMenuId,
            'label' => 'Test Item',
            'url' => '/test',
            'sort_order' => 10,
        ]);

        $this->assertNotEmpty($id);
        $this->assertGreaterThan(0, (int)$id);
    }

    public function testCreateWithAllFields(): void
    {
        $id = MenuItem::create([
            'menu_id' => self::$testMenuId,
            'label' => 'Full Item',
            'type' => 'external',
            'url' => 'https://example.com',
            'icon' => 'fa-link',
            'css_class' => 'custom-class',
            'target' => '_blank',
            'sort_order' => 20,
            'is_active' => 1,
        ]);

        $item = MenuItem::find($id);
        $this->assertNotFalse($item);
        $this->assertEquals('external', $item['type']);
        $this->assertEquals('_blank', $item['target']);
        $this->assertEquals('fa-link', $item['icon']);
    }

    // ==========================================
    // Find Tests
    // ==========================================

    public function testFindReturnsItem(): void
    {
        $id = MenuItem::create([
            'menu_id' => self::$testMenuId,
            'label' => 'Find Me Item',
            'url' => '/find-me',
        ]);

        $item = MenuItem::find($id);
        $this->assertNotFalse($item);
        $this->assertEquals('Find Me Item', $item['label']);
    }

    public function testFindReturnsFalseForNonExistent(): void
    {
        $item = MenuItem::find(999999999);
        $this->assertFalse($item);
    }

    // ==========================================
    // GetByMenu Tests
    // ==========================================

    public function testGetByMenuReturnsArray(): void
    {
        $items = MenuItem::getByMenu(self::$testMenuId);
        $this->assertIsArray($items);
    }

    public function testGetByMenuHierarchicalReturnsArray(): void
    {
        $items = MenuItem::getByMenu(self::$testMenuId, true);
        $this->assertIsArray($items);
    }

    public function testGetByMenuReturnsEmptyForNonExistent(): void
    {
        $items = MenuItem::getByMenu(999999999);
        $this->assertIsArray($items);
        $this->assertEmpty($items);
    }

    // ==========================================
    // Update Tests
    // ==========================================

    public function testUpdateChangesFields(): void
    {
        $id = MenuItem::create([
            'menu_id' => self::$testMenuId,
            'label' => 'Original Item',
            'url' => '/original',
        ]);

        MenuItem::update($id, [
            'label' => 'Updated Item',
            'url' => '/updated',
            'sort_order' => 99,
        ]);

        $item = MenuItem::find($id);
        $this->assertEquals('Updated Item', $item['label']);
        $this->assertEquals('/updated', $item['url']);
    }

    // ==========================================
    // Delete Tests
    // ==========================================

    public function testDeleteRemovesItem(): void
    {
        $id = MenuItem::create([
            'menu_id' => self::$testMenuId,
            'label' => 'Delete Me',
            'url' => '/delete',
        ]);

        MenuItem::delete($id);

        $item = MenuItem::find($id);
        $this->assertFalse($item);
    }

    // ==========================================
    // CountByMenu Tests
    // ==========================================

    public function testCountByMenuReturnsNumeric(): void
    {
        $count = MenuItem::countByMenu(self::$testMenuId);
        $this->assertIsNumeric($count);
    }

    // ==========================================
    // IsVisible Tests (Pure Function)
    // ==========================================

    public function testIsVisibleReturnsTrueForNoRules(): void
    {
        $item = ['visibility_rules' => null];
        $this->assertTrue(MenuItem::isVisible($item));
    }

    public function testIsVisibleReturnsTrueForEmptyRules(): void
    {
        $item = ['visibility_rules' => ''];
        $this->assertTrue(MenuItem::isVisible($item));
    }

    // ==========================================
    // FilterVisible Tests (Pure Function)
    // ==========================================

    public function testFilterVisibleReturnsAllWithNoRules(): void
    {
        $items = [
            ['label' => 'A', 'visibility_rules' => null],
            ['label' => 'B', 'visibility_rules' => null],
        ];

        $filtered = MenuItem::filterVisible($items);
        $this->assertCount(2, $filtered);
    }

    // ==========================================
    // ResolveUrl Tests (Pure Function)
    // ==========================================

    public function testResolveUrlReturnsUrlForLink(): void
    {
        $item = ['type' => 'link', 'url' => '/about'];
        $url = MenuItem::resolveUrl($item);
        $this->assertEquals('/about', $url);
    }

    public function testResolveUrlReturnsUrlForExternal(): void
    {
        $item = ['type' => 'external', 'url' => 'https://example.com'];
        $url = MenuItem::resolveUrl($item);
        $this->assertEquals('https://example.com', $url);
    }

    public function testResolveUrlReturnsHashForDivider(): void
    {
        $item = ['type' => 'divider', 'url' => null];
        $url = MenuItem::resolveUrl($item);
        $this->assertEquals('#', $url);
    }
}
