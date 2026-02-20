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
use Nexus\Models\AiConversation;

/**
 * AiConversation Model Tests
 *
 * Tests conversation CRUD, user ownership, title updates,
 * counting, and tenant scoping.
 */
class AiConversationTest extends DatabaseTestCase
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
            [self::$testTenantId, "ai_conv_test_{$timestamp}@test.com", "ai_conv_test_{$timestamp}", 'AiConv', 'Tester', 'AiConv Tester']
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        try {
            if (self::$testUserId) {
                Database::query("DELETE FROM ai_messages WHERE conversation_id IN (SELECT id FROM ai_conversations WHERE user_id = ?)", [self::$testUserId]);
                Database::query("DELETE FROM ai_conversations WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM activity_log WHERE user_id = ?", [self::$testUserId]);
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

    // ==========================================
    // Create Tests
    // ==========================================

    public function testCreateReturnsId(): void
    {
        $id = AiConversation::create(self::$testUserId);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testCreateWithCustomData(): void
    {
        $id = AiConversation::create(self::$testUserId, [
            'title' => 'Custom Chat Title',
            'provider' => 'openai',
            'model' => 'gpt-4',
            'context_type' => 'listing',
            'context_id' => 123,
        ]);

        $conv = AiConversation::findById($id);
        $this->assertNotNull($conv);
        $this->assertEquals('Custom Chat Title', $conv['title']);
        $this->assertEquals('openai', $conv['provider']);
        $this->assertEquals('gpt-4', $conv['model']);
        $this->assertEquals('listing', $conv['context_type']);
    }

    public function testCreateDefaultsToNewChat(): void
    {
        $id = AiConversation::create(self::$testUserId);

        $conv = AiConversation::findById($id);
        $this->assertEquals('New Chat', $conv['title']);
        $this->assertEquals('general', $conv['context_type']);
    }

    // ==========================================
    // FindById Tests
    // ==========================================

    public function testFindByIdReturnsConversation(): void
    {
        $id = AiConversation::create(self::$testUserId);

        $conv = AiConversation::findById($id);
        $this->assertIsArray($conv);
        $this->assertEquals($id, $conv['id']);
        $this->assertEquals(self::$testUserId, $conv['user_id']);
    }

    public function testFindByIdReturnsNullForNonExistent(): void
    {
        $conv = AiConversation::findById(999999999);
        $this->assertNull($conv);
    }

    // ==========================================
    // GetByUserId Tests
    // ==========================================

    public function testGetByUserIdReturnsArray(): void
    {
        AiConversation::create(self::$testUserId);

        $conversations = AiConversation::getByUserId(self::$testUserId);
        $this->assertIsArray($conversations);
        $this->assertNotEmpty($conversations);
    }

    public function testGetByUserIdIncludesMessageCount(): void
    {
        $conversations = AiConversation::getByUserId(self::$testUserId);
        if (!empty($conversations)) {
            $this->assertArrayHasKey('message_count', $conversations[0]);
            $this->assertArrayHasKey('first_message', $conversations[0]);
        }
    }

    public function testGetByUserIdReturnsEmptyForNonExistent(): void
    {
        $conversations = AiConversation::getByUserId(999999999);
        $this->assertIsArray($conversations);
        $this->assertEmpty($conversations);
    }

    // ==========================================
    // Update Tests
    // ==========================================

    public function testUpdateChangesTitle(): void
    {
        $id = AiConversation::create(self::$testUserId);

        AiConversation::update($id, ['title' => 'Updated Title']);

        $conv = AiConversation::findById($id);
        $this->assertEquals('Updated Title', $conv['title']);
    }

    public function testUpdateReturnsTrueWithNoFields(): void
    {
        $id = AiConversation::create(self::$testUserId);
        $result = AiConversation::update($id, []);
        $this->assertTrue($result);
    }

    // ==========================================
    // UpdateTitleFromContent Tests
    // ==========================================

    public function testUpdateTitleFromContentTruncates(): void
    {
        $id = AiConversation::create(self::$testUserId);

        $longContent = str_repeat('a', 100);
        AiConversation::updateTitleFromContent($id, $longContent);

        $conv = AiConversation::findById($id);
        $this->assertStringEndsWith('...', $conv['title']);
        $this->assertLessThanOrEqual(53, strlen($conv['title'])); // 50 chars + "..."
    }

    public function testUpdateTitleFromContentStripsHtml(): void
    {
        $id = AiConversation::create(self::$testUserId);

        AiConversation::updateTitleFromContent($id, '<p>Hello <strong>World</strong></p>');

        $conv = AiConversation::findById($id);
        $this->assertStringNotContainsString('<p>', $conv['title']);
        $this->assertStringContainsString('Hello', $conv['title']);
    }

    // ==========================================
    // BelongsToUser Tests
    // ==========================================

    public function testBelongsToUserReturnsTrueForOwner(): void
    {
        $id = AiConversation::create(self::$testUserId);
        $this->assertTrue(AiConversation::belongsToUser($id, self::$testUserId));
    }

    public function testBelongsToUserReturnsFalseForNonOwner(): void
    {
        $id = AiConversation::create(self::$testUserId);
        $this->assertFalse(AiConversation::belongsToUser($id, 999999999));
    }

    // ==========================================
    // CountByUserId Tests
    // ==========================================

    public function testCountByUserIdReturnsInt(): void
    {
        $count = AiConversation::countByUserId(self::$testUserId);
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    // ==========================================
    // Delete Tests
    // ==========================================

    public function testDeleteRemovesConversation(): void
    {
        $id = AiConversation::create(self::$testUserId);
        AiConversation::delete($id);

        $conv = AiConversation::findById($id);
        $this->assertNull($conv);
    }

    public function testDeleteAllForUserRemovesAll(): void
    {
        AiConversation::create(self::$testUserId, ['title' => 'Bulk Delete 1']);
        AiConversation::create(self::$testUserId, ['title' => 'Bulk Delete 2']);

        AiConversation::deleteAllForUser(self::$testUserId);

        $count = AiConversation::countByUserId(self::$testUserId);
        $this->assertEquals(0, $count);
    }

    // ==========================================
    // GetWithMessages Tests
    // ==========================================

    public function testGetWithMessagesReturnsNullForNonExistent(): void
    {
        $result = AiConversation::getWithMessages(999999999);
        $this->assertNull($result);
    }

    public function testGetWithMessagesIncludesMessagesArray(): void
    {
        $id = AiConversation::create(self::$testUserId);

        $result = AiConversation::getWithMessages($id);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('messages', $result);
        $this->assertIsArray($result['messages']);
    }
}
