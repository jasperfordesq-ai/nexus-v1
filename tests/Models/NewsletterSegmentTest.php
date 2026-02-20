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
use Nexus\Models\NewsletterSegment;

/**
 * NewsletterSegment Model Tests
 *
 * Tests segment CRUD, rule validation, and static helper methods.
 */
class NewsletterSegmentTest extends DatabaseTestCase
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
            Database::query("DELETE FROM newsletter_segments WHERE tenant_id = ? AND name LIKE 'Test Segment%'", [2]);
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
    // Rule Validation
    // ==========================================

    public function testValidateRulesAcceptsValidRules(): void
    {
        $rules = [
            'match' => 'all',
            'conditions' => [
                ['field' => 'role', 'operator' => 'equals', 'value' => 'user']
            ]
        ];

        $this->assertTrue(NewsletterSegment::validateRules($rules));
    }

    public function testValidateRulesThrowsForMissingMatch(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        NewsletterSegment::validateRules([
            'conditions' => [['field' => 'role', 'operator' => 'equals', 'value' => 'user']]
        ]);
    }

    public function testValidateRulesThrowsForEmptyConditions(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        NewsletterSegment::validateRules([
            'match' => 'all',
            'conditions' => []
        ]);
    }

    public function testValidateRulesThrowsForInvalidField(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        NewsletterSegment::validateRules([
            'match' => 'all',
            'conditions' => [
                ['field' => 'invalid_field_xyz', 'operator' => 'equals', 'value' => 'test']
            ]
        ]);
    }

    public function testValidateRulesThrowsForInvalidOperator(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        NewsletterSegment::validateRules([
            'match' => 'all',
            'conditions' => [
                ['field' => 'role', 'operator' => 'invalid_op', 'value' => 'test']
            ]
        ]);
    }

    // ==========================================
    // CRUD Tests
    // ==========================================

    public function testCreateReturnsId(): void
    {
        $id = NewsletterSegment::create([
            'name' => 'Test Segment Create',
            'rules' => ['match' => 'all', 'conditions' => [['field' => 'role', 'operator' => 'equals', 'value' => 'user']]]
        ]);

        $this->assertNotEmpty($id);
    }

    public function testFindByIdReturnsSegment(): void
    {
        $id = NewsletterSegment::create([
            'name' => 'Test Segment Find',
            'rules' => ['match' => 'all', 'conditions' => [['field' => 'role', 'operator' => 'equals', 'value' => 'user']]]
        ]);

        $segment = NewsletterSegment::findById($id);
        $this->assertNotFalse($segment);
        $this->assertEquals('Test Segment Find', $segment['name']);
        $this->assertIsArray($segment['rules']);
    }

    public function testGetAllReturnsArray(): void
    {
        $all = NewsletterSegment::getAll();
        $this->assertIsArray($all);
    }

    public function testDeleteRemovesSegment(): void
    {
        $id = NewsletterSegment::create([
            'name' => 'Test Segment Delete',
            'rules' => ['match' => 'all', 'conditions' => [['field' => 'role', 'operator' => 'equals', 'value' => 'user']]]
        ]);

        NewsletterSegment::delete($id);

        $segment = NewsletterSegment::findById($id);
        $this->assertEmpty($segment);
    }

    // ==========================================
    // Static Helpers
    // ==========================================

    public function testGetIrishCountiesReturnsArray(): void
    {
        $counties = NewsletterSegment::getIrishCounties();
        $this->assertIsArray($counties);
        $this->assertContains('Dublin', $counties);
        $this->assertContains('Cork', $counties);
    }

    public function testGetIrishTownsReturnsArray(): void
    {
        $towns = NewsletterSegment::getIrishTowns();
        $this->assertIsArray($towns);
        $this->assertContains('Dublin', $towns);
    }

    public function testGetAvailableFieldsReturnsArray(): void
    {
        $fields = NewsletterSegment::getAvailableFields();
        $this->assertIsArray($fields);
        $this->assertArrayHasKey('role', $fields);
        $this->assertArrayHasKey('created_at', $fields);
    }
}
