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
use Nexus\Models\Attribute;

/**
 * Attribute Model Tests
 *
 * Tests attribute CRUD, category association, type filtering,
 * and tenant scoping.
 */
class AttributeTest extends DatabaseTestCase
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
            Database::query("DELETE FROM attributes WHERE tenant_id = ? AND name LIKE 'Test Attr%'", [2]);
        } catch (\Exception $e) {
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);
    }

    public function testCreateReturnsId(): void
    {
        $id = Attribute::create('Test Attr Create ' . time());
        $this->assertNotEmpty($id);
    }

    public function testCreateWithInputType(): void
    {
        $id = Attribute::create('Test Attr Select ' . time(), null, 'select');
        $attr = Attribute::find($id);
        $this->assertNotFalse($attr);
        $this->assertEquals('select', $attr['input_type']);
    }

    public function testFindReturnsAttribute(): void
    {
        $id = Attribute::create('Test Attr Find ' . time());
        $attr = Attribute::find($id);
        $this->assertNotFalse($attr);
        $this->assertStringContainsString('Test Attr Find', $attr['name']);
    }

    public function testFindReturnsFalseForNonExistent(): void
    {
        $attr = Attribute::find(999999999);
        $this->assertFalse($attr);
    }

    public function testAllReturnsArray(): void
    {
        $all = Attribute::all();
        $this->assertIsArray($all);
    }

    public function testUpdateChangesFields(): void
    {
        $id = Attribute::create('Test Attr Update ' . time());
        Attribute::update($id, [
            'name' => 'Test Attr Updated',
            'category_id' => null,
            'input_type' => 'text',
            'is_active' => 1,
        ]);

        $attr = Attribute::find($id);
        $this->assertEquals('Test Attr Updated', $attr['name']);
        $this->assertEquals('text', $attr['input_type']);
    }

    public function testDeleteRemovesAttribute(): void
    {
        $id = Attribute::create('Test Attr Delete ' . time());
        Attribute::delete($id);

        $attr = Attribute::find($id);
        $this->assertFalse($attr);
    }

    public function testGetForCategoryReturnsArray(): void
    {
        $result = Attribute::getForCategory(null);
        $this->assertIsArray($result);
    }

    public function testGetByTypeReturnsArray(): void
    {
        $result = Attribute::getByType('offer');
        $this->assertIsArray($result);
    }
}
