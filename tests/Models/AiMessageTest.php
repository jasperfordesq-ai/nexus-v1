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
use Nexus\Models\AiMessage;

/**
 * AiMessage Model Tests
 *
 * Tests message CRUD, role-specific creation helpers,
 * conversation retrieval, counting, token tracking,
 * and bulk deletion.
 */
class AiMessageTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testConversationId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);

        $timestamp = time();
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 100, 1, NOW())",
            [self::$testTenantId, "ai_msg_test_{$timestamp}@test.com", "ai_msg_test_{$timestamp}", 'AiMsg', 'Tester', 'AiMsg Tester']
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Create test conversation
        self::$testConversationId = AiConversation::create(self::$testUserId, ['title' => 'Message Test Conversation']);
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
        $id = AiMessage::create(self::$testConversationId, 'user', 'Hello, AI!');
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testCreateWithTokenData(): void
    {
        $id = AiMessage::create(self::$testConversationId, 'assistant', 'Hello!', [
            'tokens_used' => 150,
            'model' => 'gpt-4',
        ]);

        $msg = AiMessage::findById($id);
        $this->assertNotNull($msg);
        $this->assertEquals(150, (int)$msg['tokens_used']);
        $this->assertEquals('gpt-4', $msg['model']);
    }

    // ==========================================
    // Role-Specific Creation Helpers
    // ==========================================

    public function testCreateUserMessageSetsUserRole(): void
    {
        $id = AiMessage::createUserMessage(self::$testConversationId, 'User question');
        $msg = AiMessage::findById($id);
        $this->assertEquals('user', $msg['role']);
        $this->assertEquals('User question', $msg['content']);
    }

    public function testCreateAssistantMessageSetsAssistantRole(): void
    {
        $id = AiMessage::createAssistantMessage(self::$testConversationId, 'AI response', [
            'tokens_used' => 200,
        ]);
        $msg = AiMessage::findById($id);
        $this->assertEquals('assistant', $msg['role']);
        $this->assertEquals('AI response', $msg['content']);
        $this->assertEquals(200, (int)$msg['tokens_used']);
    }

    public function testCreateSystemMessageSetsSystemRole(): void
    {
        $id = AiMessage::createSystemMessage(self::$testConversationId, 'System prompt');
        $msg = AiMessage::findById($id);
        $this->assertEquals('system', $msg['role']);
    }

    // ==========================================
    // FindById Tests
    // ==========================================

    public function testFindByIdReturnsMessage(): void
    {
        $id = AiMessage::create(self::$testConversationId, 'user', 'Findable message');
        $msg = AiMessage::findById($id);
        $this->assertIsArray($msg);
        $this->assertEquals($id, $msg['id']);
    }

    public function testFindByIdReturnsNullForNonExistent(): void
    {
        $msg = AiMessage::findById(999999999);
        $this->assertNull($msg);
    }

    // ==========================================
    // GetByConversationId Tests
    // ==========================================

    public function testGetByConversationIdReturnsArray(): void
    {
        $messages = AiMessage::getByConversationId(self::$testConversationId);
        $this->assertIsArray($messages);
    }

    public function testGetByConversationIdOrdersByCreatedAtAsc(): void
    {
        // Create messages in order
        AiMessage::createUserMessage(self::$testConversationId, 'First message');
        AiMessage::createAssistantMessage(self::$testConversationId, 'Second message');

        $messages = AiMessage::getByConversationId(self::$testConversationId);
        if (count($messages) >= 2) {
            $last = end($messages);
            $first = reset($messages);
            $this->assertLessThanOrEqual($last['created_at'], $first['created_at']);
        }
    }

    // ==========================================
    // GetRecentForContext Tests
    // ==========================================

    public function testGetRecentForContextReturnsArray(): void
    {
        $messages = AiMessage::getRecentForContext(self::$testConversationId);
        $this->assertIsArray($messages);
    }

    public function testGetRecentForContextOnlyReturnsRoleAndContent(): void
    {
        AiMessage::createUserMessage(self::$testConversationId, 'Context message');

        $messages = AiMessage::getRecentForContext(self::$testConversationId);
        if (!empty($messages)) {
            $this->assertArrayHasKey('role', $messages[0]);
            $this->assertArrayHasKey('content', $messages[0]);
        }
    }

    // ==========================================
    // Update Tests
    // ==========================================

    public function testUpdateChangesContent(): void
    {
        $id = AiMessage::create(self::$testConversationId, 'user', 'Original content');

        AiMessage::update($id, ['content' => 'Updated content']);

        $msg = AiMessage::findById($id);
        $this->assertEquals('Updated content', $msg['content']);
    }

    public function testUpdateReturnsTrueWithNoFields(): void
    {
        $id = AiMessage::create(self::$testConversationId, 'user', 'No update');
        $result = AiMessage::update($id, []);
        $this->assertTrue($result);
    }

    // ==========================================
    // CountByConversationId Tests
    // ==========================================

    public function testCountByConversationIdReturnsInt(): void
    {
        $count = AiMessage::countByConversationId(self::$testConversationId);
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    // ==========================================
    // GetTotalTokens Tests
    // ==========================================

    public function testGetTotalTokensReturnsInt(): void
    {
        $tokens = AiMessage::getTotalTokens(self::$testConversationId);
        $this->assertIsInt($tokens);
    }

    // ==========================================
    // Delete Tests
    // ==========================================

    public function testDeleteRemovesMessage(): void
    {
        $id = AiMessage::create(self::$testConversationId, 'user', 'Delete me');
        AiMessage::delete($id);

        $msg = AiMessage::findById($id);
        $this->assertNull($msg);
    }

    public function testDeleteByConversationIdRemovesAll(): void
    {
        // Create a separate conversation for bulk delete test
        $convId = AiConversation::create(self::$testUserId, ['title' => 'Bulk Message Delete']);
        AiMessage::createUserMessage($convId, 'Msg 1');
        AiMessage::createAssistantMessage($convId, 'Msg 2');

        AiMessage::deleteByConversationId($convId);

        $count = AiMessage::countByConversationId($convId);
        $this->assertEquals(0, $count);
    }
}
