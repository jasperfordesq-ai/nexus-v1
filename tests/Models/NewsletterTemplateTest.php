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
use Nexus\Models\NewsletterTemplate;

/**
 * NewsletterTemplate Model Tests
 *
 * Tests template CRUD, counting, and category-based retrieval.
 */
class NewsletterTemplateTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);

        $timestamp = time();
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 100, 1, NOW())",
            [self::$testTenantId, "tpl_test_{$timestamp}@test.com", "tpl_test_{$timestamp}", 'Template', 'Tester', 'Template Tester']
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        try {
            if (self::$testUserId) {
                Database::query("DELETE FROM newsletter_templates WHERE created_by = ?", [self::$testUserId]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
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

    public function testGetAllReturnsArray(): void
    {
        $all = NewsletterTemplate::getAll();
        $this->assertIsArray($all);
    }

    public function testCreateReturnsId(): void
    {
        $id = NewsletterTemplate::create([
            'name' => 'Test Template',
            'content' => '<p>Template content</p>',
            'created_by' => self::$testUserId,
        ]);

        $this->assertNotEmpty($id);
    }

    public function testFindByIdReturnsTemplate(): void
    {
        $id = NewsletterTemplate::create([
            'name' => 'Find Template',
            'content' => '<p>Content</p>',
            'created_by' => self::$testUserId,
        ]);

        $tpl = NewsletterTemplate::findById($id);
        $this->assertNotFalse($tpl);
        $this->assertEquals('Find Template', $tpl['name']);
    }

    public function testDeleteRemovesTemplate(): void
    {
        $id = NewsletterTemplate::create([
            'name' => 'Delete Template',
            'content' => '<p>Content</p>',
            'created_by' => self::$testUserId,
        ]);

        NewsletterTemplate::delete($id);

        $tpl = NewsletterTemplate::findById($id);
        $this->assertEmpty($tpl);
    }

    public function testCountReturnsNumeric(): void
    {
        $count = NewsletterTemplate::count();
        $this->assertIsNumeric($count);
    }

    public function testGetByCategoryReturnsArray(): void
    {
        $result = NewsletterTemplate::getByCategory('custom');
        $this->assertIsArray($result);
    }
}
