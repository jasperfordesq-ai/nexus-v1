<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Models;

use Tests\Laravel\TestCase;
use App\Core\Database;
use App\Core\TenantContext;
use App\Models\AiSettings;

/**
 * AiSettings Model Tests
 *
 * Tests setting get/set/delete, multi-set, tenant retrieval,
 * masking of sensitive values, and existence check.
 */
class AiSettingsTest extends \Tests\Laravel\TestCase
{
    protected static ?int $staticTenantId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$staticTenantId = 2;
        TenantContext::setById(self::$staticTenantId);
    }

    public static function tearDownAfterClass(): void
    {
        try {
            Database::query("DELETE FROM ai_settings WHERE tenant_id = ? AND setting_key LIKE 'test_%'", [2]);
        } catch (\Exception $e) {
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$staticTenantId);
    }

    // ==========================================
    // Set and Get Tests
    // ==========================================

    public function testSetAndGetReturnsValue(): void
    {
        AiSettings::set(self::$staticTenantId, 'test_setting_basic', 'hello world');

        $value = AiSettings::get(self::$staticTenantId, 'test_setting_basic');
        $this->assertEquals('hello world', $value);
    }

    public function testGetReturnsNullForNonExistent(): void
    {
        $value = AiSettings::get(self::$staticTenantId, 'test_nonexistent_key_xyz');
        $this->assertNull($value);
    }

    public function testSetOverwritesExistingValue(): void
    {
        AiSettings::set(self::$staticTenantId, 'test_overwrite', 'original');
        AiSettings::set(self::$staticTenantId, 'test_overwrite', 'updated');

        $value = AiSettings::get(self::$staticTenantId, 'test_overwrite');
        $this->assertEquals('updated', $value);
    }

    // ==========================================
    // Delete Tests
    // ==========================================

    public function testDeleteRemovesSetting(): void
    {
        AiSettings::set(self::$staticTenantId, 'test_delete_me', 'temporary');
        AiSettings::delete(self::$staticTenantId, 'test_delete_me');

        $value = AiSettings::get(self::$staticTenantId, 'test_delete_me');
        $this->assertNull($value);
    }

    // ==========================================
    // GetAllForTenant Tests
    // ==========================================

    public function testGetAllForTenantReturnsArray(): void
    {
        AiSettings::set(self::$staticTenantId, 'test_all_a', 'value_a');
        AiSettings::set(self::$staticTenantId, 'test_all_b', 'value_b');

        $all = AiSettings::getAllForTenant(self::$staticTenantId);
        $this->assertIsArray($all);
        $this->assertArrayHasKey('test_all_a', $all);
        $this->assertArrayHasKey('test_all_b', $all);
        $this->assertEquals('value_a', $all['test_all_a']);
    }

    // ==========================================
    // SetMultiple Tests
    // ==========================================

    public function testSetMultipleSetsAllValues(): void
    {
        $result = AiSettings::setMultiple(self::$staticTenantId, [
            'test_multi_1' => 'one',
            'test_multi_2' => 'two',
            'test_multi_3' => 'three',
        ]);

        $this->assertTrue($result);

        $this->assertEquals('one', AiSettings::get(self::$staticTenantId, 'test_multi_1'));
        $this->assertEquals('two', AiSettings::get(self::$staticTenantId, 'test_multi_2'));
        $this->assertEquals('three', AiSettings::get(self::$staticTenantId, 'test_multi_3'));
    }

    // ==========================================
    // Has Tests
    // ==========================================

    public function testHasReturnsTrueForExisting(): void
    {
        AiSettings::set(self::$staticTenantId, 'test_has_key', 'some value');
        $this->assertTrue(AiSettings::has(self::$staticTenantId, 'test_has_key'));
    }

    public function testHasReturnsFalseForNonExistent(): void
    {
        $this->assertFalse(AiSettings::has(self::$staticTenantId, 'test_has_nonexistent_xyz'));
    }

    public function testHasReturnsFalseForEmptyString(): void
    {
        AiSettings::set(self::$staticTenantId, 'test_has_empty', '');
        $this->assertFalse(AiSettings::has(self::$staticTenantId, 'test_has_empty'));
    }

    // ==========================================
    // GetMasked Tests
    // ==========================================

    public function testGetMaskedReturnsNullForNonExistent(): void
    {
        $masked = AiSettings::getMasked(self::$staticTenantId, 'test_nonexistent_key');
        $this->assertNull($masked);
    }

    public function testGetMaskedReturnsPlainForNonSensitive(): void
    {
        AiSettings::set(self::$staticTenantId, 'test_plain_setting', 'plain value');
        $masked = AiSettings::getMasked(self::$staticTenantId, 'test_plain_setting');
        $this->assertEquals('plain value', $masked);
    }
}
