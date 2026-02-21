<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

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
            "DELETE FROM user_first_contacts WHERE tenant_id = ? AND (user1_id = ? OR user2_id = ?)",
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
        $result = BrokerMessageVisibilityService::shouldCopyMessage(
            self::$testSenderId,
            self::$testReceiverId
        );

        // Should return a reason string or null
        $this->assertTrue(
            $result === null || is_string($result),
            'shouldCopyMessage should return a string reason or null'
        );
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

        $this->assertNotNull($copyId, 'Copy ID should not be null');
        $this->assertIsInt($copyId, 'Copy ID should be an integer');
        $this->assertGreaterThan(0, $copyId, 'Copy ID should be positive');

        self::$testCopyId = $copyId;
        return $copyId;
    }

    /**
     * Test getUnreviewedMessages
     * @depends testCopyMessageForBroker
     */
    public function testGetUnreviewedMessages(int $copyId): int
    {
        $result = BrokerMessageVisibilityService::getMessages('unreviewed');

        $this->assertIsArray($result, 'Should return an array');
        $this->assertArrayHasKey('items', $result, 'Should have items key');
        // Our copy should be in the list
        $found = false;
        foreach ($result['items'] as $msg) {
            if (isset($msg['id']) && (int) $msg['id'] === $copyId) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Our copied message should appear in unreviewed list');

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
        $result = BrokerMessageVisibilityService::markAsReviewed($copyId, self::$testBrokerId);

        $this->assertTrue($result, 'markAsReviewed should return true');
        return $copyId;
    }

    /**
     * Test that reviewed messages are not in unreviewed list
     * @depends testMarkAsReviewed
     */
    public function testReviewedMessagesNotInUnreviewedList(int $copyId): void
    {
        $result = BrokerMessageVisibilityService::getMessages('unreviewed');

        $found = false;
        foreach ($result['items'] as $msg) {
            if (isset($msg['id']) && (int) $msg['id'] === $copyId) {
                $found = true;
                break;
            }
        }
        $this->assertFalse($found, 'Reviewed message should not appear in unreviewed list');
    }

    /**
     * Test flagMessage
     */
    public function testFlagMessage(): void
    {
        // Create another message and copy it for flagging
        Database::query(
            "INSERT INTO messages (tenant_id, sender_id, receiver_id, body, created_at)
             VALUES (?, ?, ?, 'Test flag message', NOW())",
            [self::$testTenantId, self::$testSenderId, self::$testReceiverId]
        );
        $msgId = Database::getInstance()->lastInsertId();

        $copyId = BrokerMessageVisibilityService::copyMessageForBroker($msgId, 'random_sample');
        $this->assertNotNull($copyId, 'Should create a copy for flagging');

        $result = BrokerMessageVisibilityService::flagMessage(
            $copyId,
            self::$testBrokerId,
            'Test flag reason',
            'concern'
        );

        $this->assertTrue($result, 'flagMessage should return true');

        // Clean up
        Database::query("DELETE FROM broker_message_copies WHERE id = ?", [$copyId]);
        Database::query("DELETE FROM messages WHERE id = ?", [$msgId]);
    }

    /**
     * Test getMessages with flagged filter
     */
    public function testGetMessagesWithFlaggedFilter(): void
    {
        $messages = BrokerMessageVisibilityService::getMessages('flagged');
        $this->assertIsArray($messages, 'Should return an array for flagged filter');
    }

    /**
     * Test isFirstContact
     */
    public function testIsFirstContact(): void
    {
        // Use real user IDs that haven't been paired (sender/broker pair)
        // Clean up first to ensure no prior record
        $ids = [min(self::$testSenderId, self::$testBrokerId), max(self::$testSenderId, self::$testBrokerId)];
        Database::query(
            "DELETE FROM user_first_contacts WHERE tenant_id = ? AND user1_id = ? AND user2_id = ?",
            [self::$testTenantId, $ids[0], $ids[1]]
        );

        $result = BrokerMessageVisibilityService::isFirstContact(
            self::$testSenderId,
            self::$testBrokerId
        );

        $this->assertTrue($result, 'Should be first contact for new pair');
    }

    /**
     * Test recordFirstContact and subsequent check
     */
    public function testRecordFirstContact(): void
    {
        // Use actual test user (FK constraint requires real users)
        $userA = self::$testSenderId;
        $userB = self::$testBrokerId;

        // Ensure no prior first contact record exists
        $ids = [min($userA, $userB), max($userA, $userB)];
        Database::query(
            "DELETE FROM user_first_contacts WHERE tenant_id = ? AND user1_id = ? AND user2_id = ?",
            [self::$testTenantId, $ids[0], $ids[1]]
        );

        // Verify it's first contact before recording
        $this->assertTrue(
            BrokerMessageVisibilityService::isFirstContact($userA, $userB),
            'Should be first contact before recording'
        );

        BrokerMessageVisibilityService::recordFirstContact(
            $userA,
            $userB,
            self::$testMessageId
        );

        $isFirst = BrokerMessageVisibilityService::isFirstContact($userA, $userB);

        $this->assertFalse($isFirst, 'Should no longer be first contact after recording');

        // Clean up
        Database::query(
            "DELETE FROM user_first_contacts WHERE tenant_id = ? AND user1_id = ? AND user2_id = ?",
            [self::$testTenantId, $ids[0], $ids[1]]
        );
    }

    /**
     * Test statistics
     */
    public function testGetStatistics(): void
    {
        $stats = BrokerMessageVisibilityService::getStatistics();

        $this->assertIsArray($stats, 'Should return array');
        $this->assertArrayHasKey('unreviewed_messages', $stats);
        $this->assertArrayHasKey('flagged_messages', $stats);
        $this->assertArrayHasKey('first_contacts_today', $stats);
    }

    /**
     * Test copy reasons
     */
    public function testAllCopyReasons(): void
    {
        // Verify constants are defined with valid DB enum values
        $this->assertEquals('first_contact', BrokerMessageVisibilityService::REASON_FIRST_CONTACT);
        $this->assertEquals('new_member', BrokerMessageVisibilityService::REASON_NEW_MEMBER);
        $this->assertEquals('high_risk_listing', BrokerMessageVisibilityService::REASON_HIGH_RISK_LISTING);
        $this->assertEquals('flagged_user', BrokerMessageVisibilityService::REASON_FLAGGED_USER);
        $this->assertEquals('random_sample', BrokerMessageVisibilityService::REASON_MONITORING);
    }

    /**
     * Test shouldCopyMessage when visibility disabled
     */
    public function testShouldNotCopyWhenDisabled(): void
    {
        // Temporarily disable broker visibility
        $config = BrokerControlConfigService::getConfig();
        $savedConfig = $config;
        $config['broker_visibility']['enabled'] = false;
        BrokerControlConfigService::updateConfig($config);

        $result = BrokerMessageVisibilityService::shouldCopyMessage(
            self::$testSenderId,
            self::$testReceiverId
        );

        $this->assertNull($result, 'Should return null when visibility is disabled');

        // Restore config
        BrokerControlConfigService::updateConfig($savedConfig);
    }
}
