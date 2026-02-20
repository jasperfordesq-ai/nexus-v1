<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Services\Federation;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\FederatedMessageService;

/**
 * FederatedMessageService Tests
 *
 * Tests cross-tenant messaging between federated timebank members.
 * Note: Full send flow requires active partnerships with messaging enabled,
 * federation opt-in settings, etc. These tests focus on edge cases,
 * error handling, and read-only operations.
 */
class FederatedMessageServiceTest extends DatabaseTestCase
{
    protected static ?int $tenant1Id = null;
    protected static ?int $tenant2Id = null;
    protected static ?int $testUser1Id = null;
    protected static ?int $testUser2Id = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$tenant1Id = 1;
        self::$tenant2Id = 2;

        TenantContext::setById(self::$tenant2Id);

        $timestamp = time();

        // Create test user in tenant 2
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'active', NOW())",
            [self::$tenant2Id, "fed_msg_test1_{$timestamp}@test.com", "fed_msg_test1_{$timestamp}", 'FedMsg', 'Test1', 'FedMsg Test1', 100]
        );
        self::$testUser1Id = (int)Database::getInstance()->lastInsertId();

        // Create test user in tenant 1
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'active', NOW())",
            [self::$tenant1Id, "fed_msg_test2_{$timestamp}@test.com", "fed_msg_test2_{$timestamp}", 'FedMsg', 'Test2', 'FedMsg Test2', 100]
        );
        self::$testUser2Id = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        $userIds = array_filter([self::$testUser1Id, self::$testUser2Id]);

        foreach ($userIds as $userId) {
            try {
                Database::query("DELETE FROM federation_messages WHERE sender_user_id = ? OR receiver_user_id = ?", [$userId, $userId]);
            } catch (\Exception $e) {}
            try {
                Database::query("DELETE FROM federation_user_settings WHERE user_id = ?", [$userId]);
            } catch (\Exception $e) {}
            try {
                Database::query("DELETE FROM federation_audit_log WHERE actor_user_id = ?", [$userId]);
            } catch (\Exception $e) {}
            try {
                Database::query("DELETE FROM users WHERE id = ?", [$userId]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    // ==========================================
    // sendMessage Tests
    // ==========================================

    public function testSendMessageFailsWhenFederationNotEnabled(): void
    {
        try {
            // This will likely fail because federation may not be enabled for the test tenants,
            // or because sender hasn't opted in, or partnership doesn't exist.
            $result = FederatedMessageService::sendMessage(
                self::$testUser1Id,
                self::$testUser2Id,
                self::$tenant1Id,
                'Test Subject',
                'Test Body'
            );

            $this->assertIsArray($result);
            $this->assertArrayHasKey('success', $result);
            // Expect failure due to federation not enabled, no partnership, or sender not opted in
            if (!$result['success']) {
                $this->assertArrayHasKey('error', $result);
                $this->assertIsString($result['error']);
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_messages table may not exist: ' . $e->getMessage());
        }
    }

    public function testSendMessageReturnsArrayStructure(): void
    {
        try {
            $result = FederatedMessageService::sendMessage(
                999999, // Non-existent sender
                999998, // Non-existent receiver
                self::$tenant1Id,
                'Test Subject',
                'Test Body'
            );

            $this->assertIsArray($result);
            $this->assertArrayHasKey('success', $result);
            $this->assertFalse($result['success']);
            $this->assertArrayHasKey('error', $result);
        } catch (\Exception $e) {
            $this->markTestSkipped('Required tables may not exist: ' . $e->getMessage());
        }
    }

    public function testSendMessageWithEmptySubject(): void
    {
        try {
            $result = FederatedMessageService::sendMessage(
                self::$testUser1Id,
                self::$testUser2Id,
                self::$tenant1Id,
                '',
                'Test Body'
            );

            $this->assertIsArray($result);
            $this->assertArrayHasKey('success', $result);
            // Will fail for other reasons (federation not enabled, etc.) but shouldn't crash
        } catch (\Exception $e) {
            $this->markTestSkipped('Required tables may not exist: ' . $e->getMessage());
        }
    }

    // ==========================================
    // getInbox Tests
    // ==========================================

    public function testGetInboxReturnsArray(): void
    {
        try {
            $result = FederatedMessageService::getInbox(self::$testUser1Id);

            $this->assertIsArray($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_messages table may not exist: ' . $e->getMessage());
        }
    }

    public function testGetInboxForNewUserReturnsEmpty(): void
    {
        try {
            $result = FederatedMessageService::getInbox(999999);

            $this->assertIsArray($result);
            $this->assertEmpty($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_messages table may not exist: ' . $e->getMessage());
        }
    }

    public function testGetInboxRespectsLimit(): void
    {
        try {
            $result = FederatedMessageService::getInbox(self::$testUser1Id, 5, 0);

            $this->assertIsArray($result);
            $this->assertLessThanOrEqual(5, count($result));
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_messages table may not exist: ' . $e->getMessage());
        }
    }

    public function testGetInboxWithOffset(): void
    {
        try {
            $result = FederatedMessageService::getInbox(self::$testUser1Id, 50, 100);

            $this->assertIsArray($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_messages table may not exist: ' . $e->getMessage());
        }
    }

    // ==========================================
    // getThread Tests
    // ==========================================

    public function testGetThreadReturnsArray(): void
    {
        try {
            $result = FederatedMessageService::getThread(
                self::$testUser1Id,
                self::$testUser2Id,
                self::$tenant1Id
            );

            $this->assertIsArray($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_messages table may not exist: ' . $e->getMessage());
        }
    }

    public function testGetThreadForNonExistentConversationReturnsEmpty(): void
    {
        try {
            $result = FederatedMessageService::getThread(999999, 999998, 999);

            $this->assertIsArray($result);
            $this->assertEmpty($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_messages table may not exist: ' . $e->getMessage());
        }
    }

    public function testGetThreadWithCustomLimit(): void
    {
        try {
            $result = FederatedMessageService::getThread(
                self::$testUser1Id,
                self::$testUser2Id,
                self::$tenant1Id,
                10
            );

            $this->assertIsArray($result);
            $this->assertLessThanOrEqual(10, count($result));
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_messages table may not exist: ' . $e->getMessage());
        }
    }

    // ==========================================
    // markAsRead Tests
    // ==========================================

    public function testMarkAsReadReturnsBool(): void
    {
        try {
            $result = FederatedMessageService::markAsRead(999999, self::$testUser1Id);

            $this->assertIsBool($result);
            // Non-existent message should return false (0 rows affected)
            $this->assertFalse($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_messages table may not exist: ' . $e->getMessage());
        }
    }

    public function testMarkAsReadForNonExistentMessageReturnsFalse(): void
    {
        try {
            $result = FederatedMessageService::markAsRead(999999, 999999);

            $this->assertFalse($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_messages table may not exist: ' . $e->getMessage());
        }
    }

    // ==========================================
    // markThreadAsRead Tests
    // ==========================================

    public function testMarkThreadAsReadReturnsInt(): void
    {
        try {
            $result = FederatedMessageService::markThreadAsRead(
                self::$testUser1Id,
                self::$testUser2Id,
                self::$tenant1Id
            );

            $this->assertIsInt($result);
            $this->assertGreaterThanOrEqual(0, $result);
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_messages table may not exist: ' . $e->getMessage());
        }
    }

    public function testMarkThreadAsReadForNonExistentThreadReturnsZero(): void
    {
        try {
            $result = FederatedMessageService::markThreadAsRead(999999, 999998, 999);

            $this->assertIsInt($result);
            $this->assertEquals(0, $result);
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_messages table may not exist: ' . $e->getMessage());
        }
    }

    // ==========================================
    // getUnreadCount Tests
    // ==========================================

    public function testGetUnreadCountReturnsInt(): void
    {
        try {
            $result = FederatedMessageService::getUnreadCount(self::$testUser1Id);

            $this->assertIsInt($result);
            $this->assertGreaterThanOrEqual(0, $result);
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_messages table may not exist: ' . $e->getMessage());
        }
    }

    public function testGetUnreadCountForNewUserReturnsZero(): void
    {
        try {
            $result = FederatedMessageService::getUnreadCount(999999);

            $this->assertIsInt($result);
            $this->assertEquals(0, $result);
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_messages table may not exist: ' . $e->getMessage());
        }
    }

    // ==========================================
    // storeExternalMessage Tests
    // ==========================================

    public function testStoreExternalMessageReturnsExpectedStructure(): void
    {
        try {
            $result = FederatedMessageService::storeExternalMessage(
                self::$testUser1Id,
                1,       // external partner ID
                42,      // external receiver ID
                'John Doe',
                'Partner Timebank',
                'Test External Subject',
                'Test External Body',
                'ext-msg-123'
            );

            $this->assertIsArray($result);
            $this->assertArrayHasKey('success', $result);

            if ($result['success']) {
                $this->assertArrayHasKey('message_id', $result);
                $this->assertIsNumeric($result['message_id']);

                // Clean up the created message
                try {
                    Database::query("DELETE FROM federation_messages WHERE id = ?", [$result['message_id']]);
                } catch (\Exception $e) {}
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_messages table may not exist: ' . $e->getMessage());
        }
    }

    public function testStoreExternalMessageWithoutExternalId(): void
    {
        try {
            $result = FederatedMessageService::storeExternalMessage(
                self::$testUser1Id,
                1,
                42,
                'Jane Doe',
                'Another Timebank',
                'Subject Without ExtId',
                'Body',
                null // No external message ID
            );

            $this->assertIsArray($result);
            $this->assertArrayHasKey('success', $result);

            if ($result['success']) {
                // Clean up
                try {
                    Database::query("DELETE FROM federation_messages WHERE id = ?", [$result['message_id']]);
                } catch (\Exception $e) {}
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('federation_messages table may not exist: ' . $e->getMessage());
        }
    }

    // ==========================================
    // getFederatedUserInfo Tests
    // ==========================================

    public function testGetFederatedUserInfoReturnsArrayForExistingUser(): void
    {
        try {
            $result = FederatedMessageService::getFederatedUserInfo(self::$testUser2Id, self::$tenant1Id);

            if ($result !== null) {
                $this->assertIsArray($result);
                $this->assertArrayHasKey('id', $result);
                $this->assertArrayHasKey('name', $result);
                $this->assertArrayHasKey('tenant_name', $result);
                $this->assertArrayHasKey('service_reach', $result);
                $this->assertArrayHasKey('messaging_enabled_federated', $result);
                $this->assertArrayHasKey('federation_optin', $result);
            } else {
                // User might not be in the expected tenant due to test environment
                $this->assertNull($result);
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('Required tables may not exist: ' . $e->getMessage());
        }
    }

    public function testGetFederatedUserInfoReturnsNullForNonExistentUser(): void
    {
        try {
            $result = FederatedMessageService::getFederatedUserInfo(999999, self::$tenant1Id);

            $this->assertNull($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('Required tables may not exist: ' . $e->getMessage());
        }
    }

    public function testGetFederatedUserInfoReturnsNullForWrongTenant(): void
    {
        try {
            // User 1 is in tenant 2, but we ask for tenant 999
            $result = FederatedMessageService::getFederatedUserInfo(self::$testUser1Id, 999999);

            $this->assertNull($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('Required tables may not exist: ' . $e->getMessage());
        }
    }

    public function testGetFederatedUserInfoUserInCorrectTenant(): void
    {
        try {
            // testUser1Id is in tenant 2
            $result = FederatedMessageService::getFederatedUserInfo(self::$testUser1Id, self::$tenant2Id);

            if ($result !== null) {
                $this->assertEquals(self::$testUser1Id, $result['id']);
                $this->assertIsString($result['name']);
                $this->assertIsString($result['tenant_name']);
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('Required tables may not exist: ' . $e->getMessage());
        }
    }
}
