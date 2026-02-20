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
use Nexus\Models\GroupType;

/**
 * GroupType Model Tests
 *
 * Tests group type CRUD, slug generation, active toggling,
 * statistics, hub type operations, and tenant scoping.
 */
class GroupTypeTest extends DatabaseTestCase
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
            Database::query("DELETE FROM group_types WHERE tenant_id = ? AND name LIKE 'Test Type%'", [2]);
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
        $id = GroupType::create([
            'name' => 'Test Type Create ' . time(),
            'description' => 'A test group type',
        ]);

        $this->assertNotEmpty($id);
        $this->assertGreaterThan(0, (int)$id);
    }

    public function testCreateGeneratesSlug(): void
    {
        $name = 'Test Type Slug ' . time();
        $id = GroupType::create([
            'name' => $name,
            'description' => 'Type with auto-generated slug',
        ]);

        $type = GroupType::findById($id);
        $this->assertNotFalse($type);
        $this->assertNotEmpty($type['slug']);
        $this->assertStringContainsString('test-type-slug', $type['slug']);
    }

    public function testCreateWithCustomSlug(): void
    {
        $slug = 'custom-slug-' . time();
        $id = GroupType::create([
            'name' => 'Test Type Custom Slug ' . time(),
            'slug' => $slug,
        ]);

        $type = GroupType::findById($id);
        $this->assertEquals($slug, $type['slug']);
    }

    public function testCreateWithDefaults(): void
    {
        $id = GroupType::create([
            'name' => 'Test Type Defaults ' . time(),
        ]);

        $type = GroupType::findById($id);
        $this->assertNotFalse($type);
        $this->assertEquals(1, (int)$type['is_active']);
        $this->assertEquals(0, (int)$type['is_hub']);
    }

    // ==========================================
    // FindById Tests
    // ==========================================

    public function testFindByIdReturnsType(): void
    {
        $id = GroupType::create([
            'name' => 'Test Type FindById ' . time(),
        ]);

        $type = GroupType::findById($id);
        $this->assertNotFalse($type);
        $this->assertEquals($id, $type['id']);
    }

    public function testFindByIdReturnsFalseForNonExistent(): void
    {
        $type = GroupType::findById(999999999);
        $this->assertFalse($type);
    }

    // ==========================================
    // FindBySlug Tests
    // ==========================================

    public function testFindBySlugReturnsType(): void
    {
        $slug = 'findbyslug-test-' . time();
        $id = GroupType::create([
            'name' => 'Test Type FindBySlug ' . time(),
            'slug' => $slug,
        ]);

        $type = GroupType::findBySlug($slug);
        $this->assertNotFalse($type);
        $this->assertEquals($id, $type['id']);
    }

    public function testFindBySlugReturnsFalseForNonExistent(): void
    {
        $type = GroupType::findBySlug('nonexistent-slug-xyz-' . time());
        $this->assertFalse($type);
    }

    // ==========================================
    // Update Tests
    // ==========================================

    public function testUpdateChangesFields(): void
    {
        $id = GroupType::create([
            'name' => 'Test Type Update ' . time(),
            'description' => 'Original description',
        ]);

        GroupType::update($id, [
            'name' => 'Updated Type Name',
            'description' => 'Updated description',
            'color' => '#ff0000',
        ]);

        $type = GroupType::findById($id);
        $this->assertEquals('Updated Type Name', $type['name']);
        $this->assertEquals('Updated description', $type['description']);
        $this->assertEquals('#ff0000', $type['color']);
    }

    public function testUpdateIgnoresDisallowedFields(): void
    {
        $id = GroupType::create([
            'name' => 'Test Type Disallowed ' . time(),
        ]);

        $result = GroupType::update($id, [
            'tenant_id' => 999,
            'id' => 999999,
        ]);

        // Should return false since no allowed fields were set
        $this->assertFalse($result);
    }

    // ==========================================
    // All Tests
    // ==========================================

    public function testAllReturnsArray(): void
    {
        $all = GroupType::all();
        $this->assertIsArray($all);
    }

    public function testAllIncludesGroupCount(): void
    {
        $all = GroupType::all();
        if (!empty($all)) {
            $this->assertArrayHasKey('group_count', $all[0]);
        }
    }

    public function testGetActiveReturnsOnlyActive(): void
    {
        $active = GroupType::getActive();
        $this->assertIsArray($active);
        foreach ($active as $type) {
            $this->assertEquals(1, (int)$type['is_active']);
        }
    }

    // ==========================================
    // Delete Tests
    // ==========================================

    public function testDeleteRemovesType(): void
    {
        $id = GroupType::create([
            'name' => 'Test Type Delete ' . time(),
        ]);

        GroupType::delete($id);

        $type = GroupType::findById($id);
        $this->assertFalse($type);
    }

    // ==========================================
    // Slug Uniqueness Tests
    // ==========================================

    public function testIsSlugUniqueReturnsTrueForNewSlug(): void
    {
        $result = GroupType::isSlugUnique('unique-slug-' . time());
        $this->assertTrue($result);
    }

    public function testIsSlugUniqueReturnsFalseForExisting(): void
    {
        $slug = 'duplicate-slug-' . time();
        GroupType::create([
            'name' => 'Test Type Dup Slug ' . time(),
            'slug' => $slug,
        ]);

        $result = GroupType::isSlugUnique($slug);
        $this->assertFalse($result);
    }

    public function testIsSlugUniqueWithExcludeId(): void
    {
        $slug = 'exclude-slug-' . time();
        $id = GroupType::create([
            'name' => 'Test Type Exclude ' . time(),
            'slug' => $slug,
        ]);

        // Should be unique when excluding the same type
        $result = GroupType::isSlugUnique($slug, $id);
        $this->assertTrue($result);
    }

    // ==========================================
    // GenerateSlug Tests
    // ==========================================

    public function testGenerateSlugCreatesUrlFriendlySlug(): void
    {
        $slug = GroupType::generateSlug('Test Type Name');
        $this->assertStringContainsString('test-type-name', $slug);
    }

    // ==========================================
    // ToggleActive Tests
    // ==========================================

    public function testToggleActiveFlipsStatus(): void
    {
        $id = GroupType::create([
            'name' => 'Test Type Toggle ' . time(),
            'is_active' => 1,
        ]);

        GroupType::toggleActive($id);
        $type = GroupType::findById($id);
        $this->assertEquals(0, (int)$type['is_active']);

        GroupType::toggleActive($id);
        $type = GroupType::findById($id);
        $this->assertEquals(1, (int)$type['is_active']);
    }

    // ==========================================
    // Stats Tests
    // ==========================================

    public function testGetStatsReturnsStructure(): void
    {
        $id = GroupType::create([
            'name' => 'Test Type Stats ' . time(),
        ]);

        $stats = GroupType::getStats($id);
        $this->assertNotFalse($stats);
        $this->assertArrayHasKey('total_groups', $stats);
        $this->assertArrayHasKey('total_members', $stats);
        $this->assertArrayHasKey('public_groups', $stats);
        $this->assertArrayHasKey('private_groups', $stats);
    }

    public function testGetOverviewStatsReturnsStructure(): void
    {
        $stats = GroupType::getOverviewStats();
        $this->assertNotFalse($stats);
        $this->assertArrayHasKey('total_types', $stats);
        $this->assertArrayHasKey('active_types', $stats);
        $this->assertArrayHasKey('categorized_groups', $stats);
        $this->assertArrayHasKey('uncategorized_groups', $stats);
    }

    // ==========================================
    // Hub Type Tests
    // ==========================================

    public function testGetRegularTypesReturnsArray(): void
    {
        $regular = GroupType::getRegularTypes();
        $this->assertIsArray($regular);
        foreach ($regular as $type) {
            $this->assertEquals(0, (int)$type['is_hub']);
        }
    }

    public function testIsHubTypeReturnsFalseForRegular(): void
    {
        $id = GroupType::create([
            'name' => 'Test Type Regular ' . time(),
            'is_hub' => 0,
        ]);

        $this->assertFalse(GroupType::isHubType($id));
    }

    public function testIsHubTypeReturnsFalseForNonExistent(): void
    {
        $this->assertFalse(GroupType::isHubType(999999999));
    }
}
