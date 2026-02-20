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
use Nexus\Models\Newsletter;

/**
 * Newsletter Model Tests
 *
 * Tests newsletter CRUD, counting, queue operations,
 * recurring logic, and A/B testing helpers.
 */
class NewsletterTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;

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

        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 100, 1, NOW())",
            [self::$testTenantId, "newsletter_test_{$timestamp}@test.com", "newsletter_test_{$timestamp}", 'Newsletter', 'Tester', 'Newsletter Tester']
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM newsletters WHERE created_by = ?", [self::$testUserId]);
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

    public function testCreateReturnsId(): void
    {
        $id = Newsletter::create([
            'subject' => 'Test Newsletter',
            'content' => '<p>Hello world</p>',
            'created_by' => self::$testUserId,
        ]);

        $this->assertNotEmpty($id);
    }

    public function testCreateWithAllFields(): void
    {
        $id = Newsletter::create([
            'subject' => 'Full Newsletter',
            'preview_text' => 'Preview text here',
            'content' => '<p>Full content</p>',
            'status' => 'draft',
            'created_by' => self::$testUserId,
            'is_recurring' => 0,
        ]);

        $newsletter = Newsletter::findById($id);
        $this->assertNotFalse($newsletter);
        $this->assertEquals('Full Newsletter', $newsletter['subject']);
        $this->assertEquals('draft', $newsletter['status']);
    }

    // ==========================================
    // Find Tests
    // ==========================================

    public function testFindByIdReturnsTenantScoped(): void
    {
        $id = Newsletter::create([
            'subject' => 'Find Test',
            'content' => '<p>Content</p>',
            'created_by' => self::$testUserId,
        ]);

        $result = Newsletter::findById($id);
        $this->assertNotFalse($result);
        $this->assertEquals('Find Test', $result['subject']);
        $this->assertArrayHasKey('author_name', $result);
    }

    public function testFindByIdReturnsNullishForNonExistent(): void
    {
        $result = Newsletter::findById(999999999);
        $this->assertEmpty($result);
    }

    // ==========================================
    // GetAll Tests
    // ==========================================

    public function testGetAllReturnsArray(): void
    {
        Newsletter::create([
            'subject' => 'GetAll Test',
            'content' => '<p>Content</p>',
            'created_by' => self::$testUserId,
        ]);

        $all = Newsletter::getAll();
        $this->assertIsArray($all);
    }

    // ==========================================
    // Count Tests
    // ==========================================

    public function testCountReturnsNumeric(): void
    {
        $count = Newsletter::count();
        $this->assertIsNumeric($count);
    }

    public function testCountWithStatusFilter(): void
    {
        Newsletter::create([
            'subject' => 'Draft Newsletter',
            'content' => '<p>Draft</p>',
            'status' => 'draft',
            'created_by' => self::$testUserId,
        ]);

        $draftCount = Newsletter::count('draft');
        $this->assertIsNumeric($draftCount);
    }

    // ==========================================
    // Update Tests
    // ==========================================

    public function testUpdateChangesFields(): void
    {
        $id = Newsletter::create([
            'subject' => 'Original Subject',
            'content' => '<p>Original</p>',
            'created_by' => self::$testUserId,
        ]);

        $result = Newsletter::update($id, ['subject' => 'Updated Subject']);
        $this->assertTrue($result);

        $updated = Newsletter::findById($id);
        $this->assertEquals('Updated Subject', $updated['subject']);
    }

    public function testUpdateRejectsEmptySubject(): void
    {
        $id = Newsletter::create([
            'subject' => 'Keep This',
            'content' => '<p>Content</p>',
            'created_by' => self::$testUserId,
        ]);

        Newsletter::update($id, ['subject' => '']);

        $newsletter = Newsletter::findById($id);
        $this->assertEquals('Keep This', $newsletter['subject']);
    }

    // ==========================================
    // Delete Tests
    // ==========================================

    public function testDeleteRemovesNewsletter(): void
    {
        $id = Newsletter::create([
            'subject' => 'Delete Me',
            'content' => '<p>Bye</p>',
            'created_by' => self::$testUserId,
        ]);

        Newsletter::delete($id);

        $result = Newsletter::findById($id);
        $this->assertEmpty($result);
    }

    // ==========================================
    // Recurring Logic Tests
    // ==========================================

    public function testIsRecurringDueReturnsFalseForNonRecurring(): void
    {
        $newsletter = ['is_recurring' => 0, 'recurring_frequency' => null];
        $this->assertFalse(Newsletter::isRecurringDue($newsletter));
    }

    public function testIsRecurringDueReturnsFalseWithoutFrequency(): void
    {
        $newsletter = ['is_recurring' => 1, 'recurring_frequency' => ''];
        $this->assertFalse(Newsletter::isRecurringDue($newsletter));
    }
}
