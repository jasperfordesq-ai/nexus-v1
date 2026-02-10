<?php

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\BrokerMessageVisibilityService;
use Nexus\Services\BrokerControlConfigService;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * BrokerMessageVisibilityServiceTest
 *
 * Tests for the broker message visibility service.
 * Covers message copying, first contact detection, review, and flagging.
 */
class BrokerMessageVisibilityServiceTest extends TestCase
{
    private static $testTenantId = 1;
    private static $testSenderId;
    private static $testReceiverId;
    private static $testBrokerId;
    private static $testMessageId;
    private static $testCopyId;
    private static $originalConfig;

    public static function setUpBeforeClass(): void
    {
        TenantContext::setById(self::$testTenantId);

        // Store original config
        self::$originalConfig = BrokerControlConfigService::getConfig();

        // Enable broker visibility for tests
        $config = self::$originalConfig;
        $config['broker_visibility']['enabled'] = true;
        $config['broker_visibility']['copy_first_contact'] = true;
        $config['messaging']['first_contact_monitoring'] = true;
        $config['messaging']['new_member_monitoring_days'] = 30;
        BrokerControlConfigService::updateConfig($config);

        $timestamp = time() . rand(1000, 9999);

        // Create test sender (new member)
        Database::query(
            "INSERT INTO users (tenant_id, email, name, first_name, last_name, role, is_approved, status, created_at)
             VALUES (?, ?, 'Test Sender', 'Test', 'Sender', 'member', 1, 'active', NOW())",
            [self::$testTenantId, 'msg_sender_' . $timestamp . '@test.com']
        );
        self::$testSenderId = Database::getInstance()->lastInsertId();

        // Create test receiver
        Database::query(
            "INSERT INTO users (tenant_id, email, name, first_name, last_name, role, is_approved, status, created_at)
             VALUES (?, ?, 'Test Receiver', 'Test', 'Receiver', 'member', 1, 'active', DATE_SUB(NOW(), INTERVAL 60 DAY))",
            [self::$testTenantId, 'msg_receiver_' . $timestamp . '@test.com']
        );
        self::$testReceiverId = Database::getInstance()->lastInsertId();

        // Create test broker
        Database::query(
            "INSERT INTO users (tenant_id, email, name, first_name, last_name, role, is_approved, status, created_at)
             VALUES (?, ?, 'Test MsgBroker', 'Test', 'MsgBroker', 'broker', 1, 'active', NOW())",
            [self::$testTenantId, 'msg_broker_' . $timestamp . '@test.com']
        );
        self::$testBrokerId = Database::getInstance()->lastInsertId();

        // Create a test message to copy
        Database::query(
            "INSERT INTO messages (tenant_id, sender_id, receiver_id, body, created_at)
             VALUES (?, ?, ?, 'Test message for broker visibility', NOW())",
            [self::$testTenantId, self::$testSenderId, self::$testReceiverId]
        );
        self::$testMessageId = Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        // Restore original config
        if (self::$originalConfig) {
            BrokerControlConfigService::updateConfig(self::$originalConfig);
        }

        // Clean up broker message copies
        Database::query(
            "DELETE FROM broker_message_copies WHERE tenant_id = ? AND (sender_id = ? OR receiver_id = ?)",
            [self::$testTenantId, self::$testSenderId, self::$testSenderId]
        );

        // Clean up first contacts
        Database::query(
            "DELETE FROM user_first_contacts WHERE tenant_id = ? AND (user_a = ? OR user_b = ?)",
            [self::$testTenantId, self::$testSenderId, self::$testSenderId]
        );

        // Clean up test message
        if (self::$testMessageId) {
            Database::query("DELETE FROM messages WHERE id = ?", [self::$testMessageId]);
        }

        // Clean up test users
        foreach ([self::$testSenderId, self::$testReceiverId, self::$testBrokerId] as $userId) {
            if ($userId) {
                Database::query("DELETE FROM users WHERE id = ?", [$userId]);
            }
        }
    }

    /**
     * Test shouldCopyMessage for first contact
     */
    public function testShouldCopyMessageForFirstContact(): void
    {
        $reason = BrokerMessageVisibilityService::shouldCopyMessage(
            self::$testSenderId,
            self::$testReceiverId
        );

        $this->assertNotNull($reason, 'Should return a reason for first contact');
        $this->assertContains($reason, ['first_contact', 'new_member'], 'Reason should be first_contact or new_member');
    }

    /**
     * Test copying message for broker
     */
    public function testCopyMessageForBroker(): int
    {
        $copyId = BrokerMessageVisibilityService::copyMessageForBroker(
            self::$testMessageId,
            'first_contact'
        );

        $this->assertNotNull($copyId, 'Should return a copy ID');
        $this->assertIsInt($copyId, 'Copy ID should be an integer');

        self::$testCopyId = $copyId;

        // Verify the copy was created
        $stmt = Database::query(
            "SELECT * FROM broker_message_copies WHERE id = ?",
            [$copyId]
        );
        $copy = $stmt->fetch();

        $this->assertNotFalse($copy, 'Copy should exist');
        $this->assertEquals(self::$testMessageId, $copy['original_message_id']);
        $this->assertEquals('first_contact', $copy['copy_reason']);
        $this->assertNull($copy['reviewed_at']);
        $this->assertFalse((bool)$copy['flagged']);

        return $copyId;
    }

    /**
     * Test getUnreviewedMessages
     * @depends testCopyMessageForBroker
     */
    public function testGetUnreviewedMessages(int $copyId): int
    {
        $messages = BrokerMessageVisibilityService::getUnreviewedMessages();

        $this->assertIsArray($messages, 'Should return an array');

        // Find our test copy
        $found = false;
        foreach ($messages as $msg) {
            if ($msg['id'] == $copyId) {
                $found = true;
                $this->assertNull($msg['reviewed_at']);
                $this->assertArrayHasKey('sender_name', $msg);
                $this->assertArrayHasKey('receiver_name', $msg);
                break;
            }
        }
        $this->assertTrue($found, 'Test copy should be in unreviewed list');

        return $copyId;
    }

    /**
     * Test countUnreviewed
     */
    public function testCountUnreviewed(): void
    {
        $count = BrokerMessageVisibilityService::countUnreviewed();

        $this->assertIsInt($count, 'Should return an integer');
        $this->assertGreaterThanOrEqual(0, $count, 'Count should be non-negative');
    }

    /**
     * Test markAsReviewed
     * @depends testGetUnreviewedMessages
     */
    public function testMarkAsReviewed(int $copyId): int
    {
        $result = BrokerMessageVisibilityService::markAsReviewed(
            $copyId,
            self::$testBrokerId
        );

        $this->assertTrue($result, 'Mark as reviewed should succeed');

        // Verify
        $stmt = Database::query(
            "SELECT * FROM broker_message_copies WHERE id = ?",
            [$copyId]
        );
        $copy = $stmt->fetch();

        $this->assertNotNull($copy['reviewed_at'], 'Reviewed timestamp should be set');
        $this->assertEquals(self::$testBrokerId, $copy['reviewed_by']);

        return $copyId;
    }

    /**
     * Test that reviewed messages are not in unreviewed list
     * @depends testMarkAsReviewed
     */
    public function testReviewedMessagesNotInUnreviewedList(int $copyId): void
    {
        $messages = BrokerMessageVisibilityService::getUnreviewedMessages();

        $found = false;
        foreach ($messages as $msg) {
            if ($msg['id'] == $copyId) {
                $found = true;
                break;
            }
        }
        $this->assertFalse($found, 'Reviewed message should not be in unreviewed list');
    }

    /**
     * Test flagMessage
     */
    public function testFlagMessage(): void
    {
        // Create a new copy to flag
        Database::query(
            "INSERT INTO messages (tenant_id, sender_id, receiver_id, body, created_at)
             VALUES (?, ?, ?, 'Suspicious message content', NOW())",
            [self::$testTenantId, self::$testSenderId, self::$testReceiverId]
        );
        $newMessageId = Database::getInstance()->lastInsertId();

        $copyId = BrokerMessageVisibilityService::copyMessageForBroker(
            $newMessageId,
            'monitoring'
        );

        $result = BrokerMessageVisibilityService::flagMessage(
            $copyId,
            self::$testBrokerId,
            'Concerning language detected'
        );

        $this->assertTrue($result, 'Flag should succeed');

        // Verify
        $stmt = Database::query(
            "SELECT * FROM broker_message_copies WHERE id = ?",
            [$copyId]
        );
        $copy = $stmt->fetch();

        $this->assertTrue((bool)$copy['flagged'], 'Message should be flagged');
        $this->assertNotNull($copy['reviewed_at'], 'Should be marked as reviewed');

        // Clean up
        Database::query("DELETE FROM broker_message_copies WHERE id = ?", [$copyId]);
        Database::query("DELETE FROM messages WHERE id = ?", [$newMessageId]);
    }

    /**
     * Test getMessages with flagged filter
     */
    public function testGetMessagesWithFlaggedFilter(): void
    {
        // Create and flag a message
        Database::query(
            "INSERT INTO messages (tenant_id, sender_id, receiver_id, body, created_at)
             VALUES (?, ?, ?, 'Message to flag', NOW())",
            [self::$testTenantId, self::$testSenderId, self::$testReceiverId]
        );
        $messageId = Database::getInstance()->lastInsertId();

        $copyId = BrokerMessageVisibilityService::copyMessageForBroker($messageId, 'monitoring');
        BrokerMessageVisibilityService::flagMessage($copyId, self::$testBrokerId, 'Test flag');

        $result = BrokerMessageVisibilityService::getMessages('flagged');

        $this->assertIsArray($result, 'Should return an array');
        $this->assertArrayHasKey('items', $result);

        $found = false;
        foreach ($result['items'] as $msg) {
            if ($msg['id'] == $copyId) {
                $found = true;
                $this->assertTrue((bool)$msg['flagged']);
                break;
            }
        }
        $this->assertTrue($found, 'Flagged message should be in list');

        // Clean up
        Database::query("DELETE FROM broker_message_copies WHERE id = ?", [$copyId]);
        Database::query("DELETE FROM messages WHERE id = ?", [$messageId]);
    }

    /**
     * Test isFirstContact
     */
    public function testIsFirstContact(): void
    {
        // Should be first contact for new pair
        $isFirst = BrokerMessageVisibilityService::isFirstContact(
            self::$testSenderId,
            999999999 // Non-existent user
        );
        $this->assertTrue($isFirst, 'Should be first contact for new pair');
    }

    /**
     * Test recordFirstContact and subsequent check
     */
    public function testRecordFirstContact(): void
    {
        // Create a fresh message for this test
        Database::query(
            "INSERT INTO messages (tenant_id, sender_id, receiver_id, body, created_at)
             VALUES (?, ?, ?, 'First contact test', NOW())",
            [self::$testTenantId, self::$testSenderId, self::$testReceiverId]
        );
        $messageId = Database::getInstance()->lastInsertId();

        // Record first contact with message ID
        BrokerMessageVisibilityService::recordFirstContact(
            self::$testSenderId,
            self::$testReceiverId,
            $messageId
        );

        $isFirst = BrokerMessageVisibilityService::isFirstContact(
            self::$testSenderId,
            self::$testReceiverId
        );
        $this->assertFalse($isFirst, 'Should not be first contact after recording');

        // Clean up
        Database::query("DELETE FROM messages WHERE id = ?", [$messageId]);
    }

    /**
     * Test statistics
     */
    public function testGetStatistics(): void
    {
        $stats = BrokerMessageVisibilityService::getStatistics();

        $this->assertIsArray($stats, 'Should return an array');
        $this->assertArrayHasKey('unreviewed_messages', $stats);
        $this->assertArrayHasKey('flagged_messages', $stats);
    }

    /**
     * Test copy reasons
     */
    public function testAllCopyReasons(): void
    {
        $reasons = ['first_contact', 'high_risk_listing', 'new_member', 'flagged_user', 'monitoring'];

        foreach ($reasons as $reason) {
            Database::query(
                "INSERT INTO messages (tenant_id, sender_id, receiver_id, body, created_at)
                 VALUES (?, ?, ?, ?, NOW())",
                [self::$testTenantId, self::$testSenderId, self::$testReceiverId, "Test $reason"]
            );
            $messageId = Database::getInstance()->lastInsertId();

            $copyId = BrokerMessageVisibilityService::copyMessageForBroker(
                $messageId,
                $reason
            );

            $this->assertNotNull($copyId, "Should create copy for reason: $reason");

            $stmt = Database::query(
                "SELECT copy_reason FROM broker_message_copies WHERE id = ?",
                [$copyId]
            );
            $copy = $stmt->fetch();
            $this->assertEquals($reason, $copy['copy_reason']);

            // Clean up
            Database::query("DELETE FROM broker_message_copies WHERE id = ?", [$copyId]);
            Database::query("DELETE FROM messages WHERE id = ?", [$messageId]);
        }
    }

    /**
     * Test shouldCopyMessage when visibility disabled
     */
    public function testShouldNotCopyWhenDisabled(): void
    {
        // Disable broker visibility
        $config = BrokerControlConfigService::getConfig();
        $config['broker_visibility']['enabled'] = false;
        BrokerControlConfigService::updateConfig($config);

        $reason = BrokerMessageVisibilityService::shouldCopyMessage(
            self::$testSenderId,
            999999999
        );

        $this->assertNull($reason, 'Should not copy when visibility disabled');

        // Re-enable for other tests
        $config['broker_visibility']['enabled'] = true;
        BrokerControlConfigService::updateConfig($config);
    }
}
